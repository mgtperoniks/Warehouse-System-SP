<?php

namespace App\Livewire\Reports;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\StockInReceipt;
use App\Models\User;
use Carbon\Carbon;

class StockInReport extends Component
{
    use WithPagination;

    public bool $reportGenerated = false;

    // Filters
    public $startDate;
    public $endDate;
    public $operatorId;
    public $receiptCode;
    public $erpTransferStatus = 'NOT_STARTED'; // Default to show pending daily entries

    // Document suggestions (indexed by receipt id)
    public $suggestedBpbRefs = [];

    // Transfer status updates
    public $selectedReceiptIds = [];

    // UI state
    public $isCompactMode = false;
    public $isErpTransferView = false;

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
     * Set transfer status for selected receipts.
     */
    public function updateStatusBulk($status)
    {
        if (empty($this->selectedReceiptIds)) {
            session()->flash('warning', 'Please select at least one receipt first.');
            return;
        }

        $updateData = ['erp_transfer_status' => $status];
        if ($status === 'COMPLETED') {
            $updateData['transferred_by'] = auth()->id();
            $updateData['transferred_at'] = Carbon::now();
        } else {
            $updateData['transferred_by'] = null;
            $updateData['transferred_at'] = null;
        }

        StockInReceipt::whereIn('id', $this->selectedReceiptIds)->update($updateData);

        $this->selectedReceiptIds = [];
        session()->flash('message', 'Transfer status updated successfully.');
    }

    /**
     * Mark an entire receipt session batch as completed.
     */
    public function completeReceiptBatch($receiptId)
    {
        $receipt = StockInReceipt::findOrFail($receiptId);
        $customRef = $this->suggestedBpbRefs[$receiptId] ?? null;

        $receipt->update([
            'erp_transfer_status' => 'COMPLETED',
            'purchase_order_ref' => $customRef ?: $receipt->purchase_order_ref,
            'transferred_by' => auth()->id(),
            'transferred_at' => Carbon::now(),
        ]);

        session()->flash('message', "Closed BPB Receipt session batch successfully under Reference: " . ($customRef ?: 'WMS Reference'));
    }

    /**
     * Toggle compact screen mode.
     */
    public function toggleCompactMode()
    {
        $this->isCompactMode = !$this->isCompactMode;
    }

    /**
     * Toggle ERP transfer view workspace.
     */
    public function toggleErpTransferView()
    {
        $this->isErpTransferView = !$this->isErpTransferView;
    }

    public function render()
    {
        $receipts = collect();

        if ($this->reportGenerated) {
            $query = StockInReceipt::with(['operator', 'items.variant.item', 'items.bin', 'supplier'])
                ->where('status', 'COMMITTED')
                ->forActiveWarehouse();

            if ($this->startDate) {
                $query->whereDate('created_at', '>=', $this->startDate);
            }
            if ($this->endDate) {
                $query->whereDate('created_at', '<=', $this->endDate);
            }
            if ($this->operatorId) {
                $query->where('operator_id', $this->operatorId);
            }
            if ($this->receiptCode) {
                $query->where('receipt_code', 'like', '%' . $this->receiptCode . '%');
            }
            if ($this->erpTransferStatus) {
                $query->where('erp_transfer_status', $this->erpTransferStatus);
            }

            $receipts = $query->orderBy('created_at', 'desc')->take(1000)->get();

            // Initialize suggestible BPB references (e.g. BPB-SP-20260518-003)
            $dateStr = Carbon::now()->format('Ymd');
            $whCode = session('active_warehouse_code', 'SPAREPART');
            $whSuffix = $whCode === 'SPAREPART' ? 'SP' : ($whCode === 'RAW_MATERIAL' ? 'RM' : ($whCode === 'CONSUMABLE' ? 'CS' : 'WH'));

            foreach ($receipts as $index => $receipt) {
                if (!isset($this->suggestedBpbRefs[$receipt->id])) {
                    // Generate sequential code BPB-SP-YYYYMMDD-003
                    $seq = str_pad($index + 1, 3, '0', STR_PAD_LEFT);
                    $this->suggestedBpbRefs[$receipt->id] = "BPB-{$whSuffix}-{$dateStr}-{$seq}";
                }
            }
        }

        $operators = User::orderBy('name')->get();

        return view('livewire.reports.stock-in-report', [
            'receipts' => $receipts,
            'operators' => $operators,
        ])->layout('layouts.app');
    }
}
