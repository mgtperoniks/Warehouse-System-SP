<?php

namespace App\Livewire\Reports;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\StockInReceipt;
use App\Models\StockInItem;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class StockInReport extends Component
{
    use WithPagination;

    public bool $reportGenerated = false;

    // Filters
    public $startDate;
    public $endDate;
    public $operatorId;
    public $receiptCode;
    public $erpTransferStatus = StockInItem::ERP_NOT_STARTED; // Default to show pending daily entries

    // Document suggestions and user inputs (indexed by receipt id)
    public $suggestedBpbRefs = [];

    // Transfer status updates (item-level)
    public $selectedItemIds = [];

    protected $queryString = [
        'startDate' => ['except' => ''],
        'endDate' => ['except' => ''],
        'operatorId' => ['except' => ''],
        'receiptCode' => ['except' => ''],
        'erpTransferStatus' => ['except' => ''],
    ];

    public function mount()
    {
        // By default, set the date range to the current month to prevent massive unbounded query loads
        $this->startDate = Carbon::now()->startOfMonth()->format('Y-m-d');
        $this->endDate = Carbon::today()->format('Y-m-d');
    }

    public function updatedStartDate() 
    { 
        $this->reportGenerated = false;
        $this->resetPage(); 
    }
    
    public function updatedEndDate() 
    { 
        $this->reportGenerated = false;
        $this->resetPage(); 
    }

    public function updatedOperatorId() { $this->resetPage(); }
    public function updatedReceiptCode() { $this->resetPage(); }
    public function updatedErpTransferStatus() { $this->resetPage(); }

    public function generateReport()
    {
        if (empty($this->startDate) || empty($this->endDate)) {
            session()->flash('warning', 'Please select both Start Date and End Date.');
            return;
        }

        $start = Carbon::parse($this->startDate);
        $end = Carbon::parse($this->endDate);

        if ($start->gt($end)) {
            session()->flash('warning', 'Start Date cannot be after End Date.');
            return;
        }

        if ($start->diffInDays($end) > 45) {
            session()->flash('warning', 'The selected date range exceeds the maximum limit of 45 days.');
            return;
        }

        $this->reportGenerated = true;
    }

    /**
     * Apply quick preset date boundaries.
     */
    public function setDatePreset($preset)
    {
        switch ($preset) {
            case 'today':
                $this->startDate = Carbon::today()->format('Y-m-d');
                $this->endDate = Carbon::today()->format('Y-m-d');
                break;
            case 'yesterday':
                $this->startDate = Carbon::yesterday()->format('Y-m-d');
                $this->endDate = Carbon::yesterday()->format('Y-m-d');
                break;
            case 'this_week':
                $this->startDate = Carbon::now()->startOfWeek()->format('Y-m-d');
                $this->endDate = Carbon::now()->endOfWeek()->format('Y-m-d');
                break;
            case 'this_month':
                $this->startDate = Carbon::now()->startOfMonth()->format('Y-m-d');
                $this->endDate = Carbon::now()->endOfMonth()->format('Y-m-d');
                break;
        }
        $this->reportGenerated = false;
        $this->resetPage();
    }

    /**
     * Set transfer status for selected items.
     */
    public function updateStatusBulk($status)
    {
        if (empty($this->selectedItemIds)) {
            session()->flash('warning', 'Please select at least one item first.');
            return;
        }

        DB::transaction(function () use ($status) {
            $userId = auth()->id();
            $now = Carbon::now();

            $updateData = ['erp_transfer_status' => $status];
            if ($status === StockInItem::ERP_COMPLETED) {
                $updateData['transferred_by'] = $userId;
                $updateData['transferred_at'] = $now;
            } else {
                $updateData['transferred_by'] = null;
                $updateData['transferred_at'] = null;
            }

            // Get unique receipt IDs before updating items
            $affectedReceiptIds = StockInItem::whereIn('id', $this->selectedItemIds)
                ->pluck('stock_in_receipt_id')
                ->unique();

            // Update items
            StockInItem::whereIn('id', $this->selectedItemIds)->update($updateData);

            // Sync parent receipts
            foreach ($affectedReceiptIds as $receiptId) {
                $this->syncParentReceipt($receiptId, $userId, $now);
            }
        });

        $this->selectedItemIds = [];
        session()->flash('message', 'Transfer status updated successfully.');
    }

    /**
     * Mark remaining items of a receipt session batch as completed.
     */
    public function completeReceiptBatch($receiptId)
    {
        $customRef = $this->suggestedBpbRefs[$receiptId] ?? null;
        $receipt = StockInReceipt::findOrFail($receiptId);

        DB::transaction(function () use ($receipt, $customRef) {
            $userId = auth()->id();
            $now = Carbon::now();

            // Update remaining items that are NOT_STARTED
            $receipt->items()->where('stock_in_items.erp_transfer_status', StockInItem::ERP_NOT_STARTED)->update([
                'erp_transfer_status' => StockInItem::ERP_COMPLETED,
                'transferred_by' => $userId,
                'transferred_at' => $now,
            ]);

            // Sync parent receipt to COMPLETED
            $receipt->update([
                'erp_transfer_status' => StockInItem::ERP_COMPLETED,
                'purchase_order_ref' => $customRef ?: $receipt->purchase_order_ref,
                'transferred_by' => $userId,
                'transferred_at' => $now,
            ]);
        });

        session()->flash('message', "Closed BPB Receipt session batch successfully under Reference: " . ($customRef ?: 'WMS Reference'));
    }

    /**
     * Synchronize parent receipt aggregate status.
     */
    private function syncParentReceipt($receiptId, $userId, $now)
    {
        $receipt = StockInReceipt::find($receiptId);
        if (!$receipt) return;

        // Check if any item in this receipt is still NOT_STARTED
        $hasPending = $receipt->items()->where('stock_in_items.erp_transfer_status', StockInItem::ERP_NOT_STARTED)->exists();

        if ($hasPending) {
            $receipt->update([
                'erp_transfer_status' => StockInItem::ERP_NOT_STARTED,
            ]);
        } else {
            $receipt->update([
                'erp_transfer_status' => StockInItem::ERP_COMPLETED,
                'transferred_by' => $userId,
                'transferred_at' => $now,
            ]);
        }
    }

    public function render()
    {
        $receipts = collect();
        $globalTotal = 0;
        $globalRemaining = 0;

        if ($this->reportGenerated) {
            $query = StockInReceipt::where('stock_in_receipts.status', 'COMMITTED')
                ->forActiveWarehouse();

            if ($this->startDate) {
                $query->whereDate('stock_in_receipts.created_at', '>=', $this->startDate);
            }
            if ($this->endDate) {
                $query->whereDate('stock_in_receipts.created_at', '<=', $this->endDate);
            }
            if ($this->operatorId) {
                $query->where('stock_in_receipts.operator_id', $this->operatorId);
            }
            if ($this->receiptCode) {
                $query->where('stock_in_receipts.receipt_code', 'like', '%' . $this->receiptCode . '%');
            }

            // High-performance SQL aggregation counters for Global KPI
            $sumQuery = clone $query;
            $stats = $sumQuery->join('stock_in_items', 'stock_in_receipts.id', '=', 'stock_in_items.stock_in_receipt_id')
                ->selectRaw('
                    COUNT(stock_in_items.id) as total_items,
                    SUM(CASE WHEN stock_in_items.erp_transfer_status = ? THEN 1 ELSE 0 END) as remaining_items
                ', [StockInItem::ERP_NOT_STARTED])
                ->first();

            $globalTotal = $stats->total_items ?? 0;
            $globalRemaining = $stats->remaining_items ?? 0;

            if ($this->erpTransferStatus) {
                $query->whereHas('items', function ($q) {
                    $q->where('stock_in_items.erp_transfer_status', $this->erpTransferStatus);
                });
            }

            // Eager load items and properties matching the active status filter
            $receipts = $query->with([
                'operator',
                'supplier',
                'items' => function ($q) {
                    $q->with(['variant.item', 'bin']);
                    if ($this->erpTransferStatus) {
                        $q->where('stock_in_items.erp_transfer_status', $this->erpTransferStatus);
                    }
                }
            ])
            ->withCount([
                'items as total_items_count',
                'items as remaining_items_count' => function ($q) {
                    $q->where('stock_in_items.erp_transfer_status', StockInItem::ERP_NOT_STARTED);
                }
            ])
            ->orderBy('stock_in_receipts.created_at', 'desc')
            ->take(1000)
            ->get();

            // Initialize suggestible BPB references
            $dateStr = Carbon::now()->format('Ymd');
            $whCode = session('active_warehouse_code', 'SPAREPART');
            $whSuffix = $whCode === 'SPAREPART' ? 'SP' : ($whCode === 'RAW_MATERIAL' ? 'RM' : ($whCode === 'CONSUMABLE' ? 'CS' : 'WH'));

            foreach ($receipts as $index => $receipt) {
                if (!isset($this->suggestedBpbRefs[$receipt->id])) {
                    $seq = str_pad($index + 1, 3, '0', STR_PAD_LEFT);
                    $this->suggestedBpbRefs[$receipt->id] = "BPB-{$whSuffix}-{$dateStr}-{$seq}";
                }
            }
        }

        $operators = User::orderBy('name')->get();

        return view('livewire.reports.stock-in-report', [
            'receipts' => $receipts,
            'operators' => $operators,
            'globalTotal' => $globalTotal,
            'globalRemaining' => $globalRemaining,
        ])->layout('layouts.app');
    }
}
