<?php

namespace App\Livewire\Reports;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\StockTransaction;
use App\Models\StockTransactionItem;
use App\Models\Department;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class StockOutReport extends Component
{
    use WithPagination;

    public bool $reportGenerated = false;

    // Filters
    public $startDate;
    public $endDate;
    public $departmentId;
    public $picId;
    public $code;
    public $erpTransferStatus = StockTransactionItem::ERP_NOT_STARTED; // Default to show pending daily entries

    // Document suggestions and user inputs (indexed by department id)
    public $suggestedBkbRefs = [];

    // Transfer status updates (item-level)
    public $selectedItemIds = [];

    // UI state
    public $isCompactMode = false;
    public $isErpTransferView = false;

    protected $queryString = [
        'startDate' => ['except' => ''],
        'endDate' => ['except' => ''],
        'departmentId' => ['except' => ''],
        'picId' => ['except' => ''],
        'code' => ['except' => ''],
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

    public function updatedDepartmentId() { $this->resetPage(); }
    public function updatedPicId() { $this->resetPage(); }
    public function updatedCode() { $this->resetPage(); }
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
     * Apply date preset quick filters.
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
            if ($status === StockTransactionItem::ERP_COMPLETED) {
                $updateData['transferred_by'] = $userId;
                $updateData['transferred_at'] = $now;
            } else {
                $updateData['transferred_by'] = null;
                $updateData['transferred_at'] = null;
            }

            // Get unique transaction IDs before updating
            $affectedTxIds = StockTransactionItem::whereIn('id', $this->selectedItemIds)
                ->pluck('stock_transaction_id')
                ->unique();

            // Update items
            StockTransactionItem::whereIn('id', $this->selectedItemIds)->update($updateData);

            // Sync parent transactions
            foreach ($affectedTxIds as $txId) {
                $this->syncParentTransaction($txId, $userId, $now);
            }
        });

        $this->selectedItemIds = [];
        session()->flash('message', 'Transfer status updated successfully.');
    }

    /**
     * Mark remaining items of a department batch as transferred.
     */
    public function completeDepartmentBatch($deptId)
    {
        $customRef = $this->suggestedBkbRefs[$deptId] ?? null;

        // Find all filtered transactions in this department that are not already completed
        $query = StockTransaction::where('stock_transactions.department_id', $deptId)
            ->where('stock_transactions.type', 'OUT')
            ->where('stock_transactions.status', 'CONFIRMED')
            ->forActiveWarehouse();

        if ($this->startDate) {
            $query->whereDate('stock_transactions.created_at', '>=', $this->startDate);
        }
        if ($this->endDate) {
            $query->whereDate('stock_transactions.created_at', '<=', $this->endDate);
        }
        if ($this->picId) {
            $query->where('stock_transactions.user_id', $this->picId);
        }
        if ($this->code) {
            $query->where('stock_transactions.code', 'like', '%' . $this->code . '%');
        }
        if ($this->erpTransferStatus) {
            $query->whereHas('items', function ($q) {
                $q->where('stock_transaction_items.erp_transfer_status', $this->erpTransferStatus);
            });
        }

        $txs = $query->get();

        if ($txs->isEmpty()) {
            session()->flash('warning', 'No transactions found in this department to close.');
            return;
        }

        DB::transaction(function () use ($txs, $customRef) {
            $userId = auth()->id();
            $now = Carbon::now();

            foreach ($txs as $tx) {
                // Only update remaining items that are NOT_STARTED
                $tx->items()->where('stock_transaction_items.erp_transfer_status', StockTransactionItem::ERP_NOT_STARTED)->update([
                    'erp_transfer_status' => StockTransactionItem::ERP_COMPLETED,
                    'transferred_by' => $userId,
                    'transferred_at' => $now,
                ]);

                // Sync parent transaction to completed
                $tx->update([
                    'erp_transfer_status' => StockTransactionItem::ERP_COMPLETED,
                    'reference' => $customRef ?: $tx->reference,
                    'transferred_by' => $userId,
                    'transferred_at' => $now,
                ]);
            }
        });

        session()->flash('message', "Closed BKB Department batch successfully under Reference: " . ($customRef ?: 'WMS Reference'));
    }

    /**
     * Synchronize parent transaction aggregate status.
     */
    private function syncParentTransaction($txId, $userId, $now)
    {
        $tx = StockTransaction::find($txId);
        if (!$tx) return;

        // Check if any item in this transaction is still NOT_STARTED
        $hasPending = $tx->items()->where('stock_transaction_items.erp_transfer_status', StockTransactionItem::ERP_NOT_STARTED)->exists();

        if ($hasPending) {
            $tx->update([
                'erp_transfer_status' => StockTransactionItem::ERP_NOT_STARTED,
            ]);
        } else {
            $tx->update([
                'erp_transfer_status' => StockTransactionItem::ERP_COMPLETED,
                'transferred_by' => $userId,
                'transferred_at' => $now,
            ]);
        }
    }

    /**
     * Toggle compact dual monitor view mode.
     */
    public function toggleCompactMode()
    {
        $this->isCompactMode = !$this->isCompactMode;
    }

    /**
     * Toggle ERP transfer mode workspace.
     */
    public function toggleErpTransferView()
    {
        $this->isErpTransferView = !$this->isErpTransferView;
    }

    public function render()
    {
        $groupedTransactions = collect();
        $globalTotal = 0;
        $globalRemaining = 0;

        if ($this->reportGenerated) {
            // Build base transaction query
            $query = StockTransaction::where('stock_transactions.type', 'OUT')
                ->where('stock_transactions.status', 'CONFIRMED')
                ->forActiveWarehouse();

            if ($this->startDate) {
                $query->whereDate('stock_transactions.created_at', '>=', $this->startDate);
            }
            if ($this->endDate) {
                $query->whereDate('stock_transactions.created_at', '<=', $this->endDate);
            }
            if ($this->departmentId) {
                $query->where('stock_transactions.department_id', $this->departmentId);
            }
            if ($this->picId) {
                $query->where('stock_transactions.user_id', $this->picId);
            }
            if ($this->code) {
                $query->where('stock_transactions.code', 'like', '%' . $this->code . '%');
            }
            // High-performance SQL aggregation counters
            $sumQuery = clone $query;
            $stats = $sumQuery->join('stock_transaction_items', 'stock_transactions.id', '=', 'stock_transaction_items.stock_transaction_id')
                ->selectRaw('
                    COUNT(stock_transaction_items.id) as total_items,
                    SUM(CASE WHEN stock_transaction_items.erp_transfer_status = ? THEN 1 ELSE 0 END) as remaining_items
                ', [StockTransactionItem::ERP_NOT_STARTED])
                ->first();

            $globalTotal = $stats->total_items ?? 0;
            $globalRemaining = $stats->remaining_items ?? 0;

            if ($this->erpTransferStatus) {
                $query->whereHas('items', function ($q) {
                    $q->where('stock_transaction_items.erp_transfer_status', $this->erpTransferStatus);
                });
            }

            // Apply eager loading and retrieve the results for the page
            $transactions = $query->with([
                'department',
                'user',
                'items' => function ($q) {
                    $q->with(['variant.item']);
                    if ($this->erpTransferStatus) {
                        $q->where('stock_transaction_items.erp_transfer_status', $this->erpTransferStatus);
                    }
                }
            ])
            ->withCount([
                'items as total_items_count',
                'items as remaining_items_count' => function ($q) {
                    $q->where('stock_transaction_items.erp_transfer_status', StockTransactionItem::ERP_NOT_STARTED);
                }
            ])
            ->orderBy('stock_transactions.created_at', 'desc')
            ->take(1000)
            ->get();

            // Group by Department for daily BKB entry
            $groupedTransactions = $transactions->groupBy(function($tx) {
                return $tx->department_id ? $tx->department->name : 'UNMAPPED / GENERAL';
            });

            // Initialize suggestible BKB references for each department
            $dateStr = Carbon::now()->format('Ymd');
            $whCode = session('active_warehouse_code', 'SP');
            $whSuffix = $whCode === 'SPAREPART' ? 'SP' : ($whCode === 'RAW_MATERIAL' ? 'RM' : ($whCode === 'CONSUMABLE' ? 'CS' : 'WH'));

            foreach ($groupedTransactions as $deptName => $txs) {
                $firstTx = $txs->first();
                if ($firstTx && $firstTx->department_id) {
                    $deptId = $firstTx->department_id;
                    if (!isset($this->suggestedBkbRefs[$deptId])) {
                        $deptCode = strtoupper($firstTx->department->code ?? 'GEN');
                        // Suggest BK-BBT-20260518-001 (based on first item's ID or dynamic index)
                        $this->suggestedBkbRefs[$deptId] = "BK-{$deptCode}-{$dateStr}-001";
                    }
                }
            }
        }

        // Get dropdown lists for filter form
        $departments = Department::orderBy('name')->get();
        $users = User::orderBy('name')->get();

        return view('livewire.reports.stock-out-report', [
            'groupedTransactions' => $groupedTransactions,
            'departments' => $departments,
            'users' => $users,
            'globalTotal' => $globalTotal,
            'globalRemaining' => $globalRemaining,
        ])->layout('layouts.app');
    }
}
