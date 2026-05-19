<?php

namespace App\Livewire\Reports;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\StockTransaction;
use App\Models\Department;
use App\Models\User;
use Carbon\Carbon;

class StockOutReport extends Component
{
    use WithPagination;

    // Filters
    public $startDate;
    public $endDate;
    public $departmentId;
    public $picId;
    public $code;
    public $erpTransferStatus = 'NOT_STARTED'; // Default to show pending daily entries

    // Document suggestions and user inputs (indexed by department id)
    public $suggestedBkbRefs = [];

    // Transfer status updates
    public $selectedTxIds = [];

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

    public function updatedStartDate() { $this->resetPage(); }
    public function updatedEndDate() { $this->resetPage(); }
    public function updatedDepartmentId() { $this->resetPage(); }
    public function updatedPicId() { $this->resetPage(); }
    public function updatedCode() { $this->resetPage(); }
    public function updatedErpTransferStatus() { $this->resetPage(); }

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
        $this->resetPage();
    }

    /**
     * Set transfer status for selected transactions.
     */
    public function updateStatusBulk($status)
    {
        if (empty($this->selectedTxIds)) {
            session()->flash('warning', 'Please select at least one transaction first.');
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

        StockTransaction::whereIn('id', $this->selectedTxIds)->update($updateData);

        $this->selectedTxIds = [];
        session()->flash('message', 'Transfer status updated successfully.');
    }

    /**
     * Mark an entire department batch as transferred.
     */
    public function completeDepartmentBatch($deptId)
    {
        $customRef = $this->suggestedBkbRefs[$deptId] ?? null;

        // Find all filtered transactions in this department that are not already completed
        $query = StockTransaction::where('department_id', $deptId)
            ->where('type', 'OUT')
            ->where('status', 'CONFIRMED')
            ->forActiveWarehouse();

        if ($this->startDate) {
            $query->whereDate('created_at', '>=', $this->startDate);
        }
        if ($this->endDate) {
            $query->whereDate('created_at', '<=', $this->endDate);
        }
        if ($this->picId) {
            $query->where('user_id', $this->picId);
        }
        if ($this->code) {
            $query->where('code', 'like', '%' . $this->code . '%');
        }
        if ($this->erpTransferStatus) {
            $query->where('erp_transfer_status', $this->erpTransferStatus);
        }

        $txs = $query->get();

        if ($txs->isEmpty()) {
            session()->flash('warning', 'No transactions found in this department to close.');
            return;
        }

        foreach ($txs as $tx) {
            $tx->update([
                'erp_transfer_status' => 'COMPLETED',
                'reference' => $customRef ?: $tx->reference,
                'transferred_by' => auth()->id(),
                'transferred_at' => Carbon::now(),
            ]);
        }

        session()->flash('message', "Closed BKB Department batch successfully under Reference: " . ($customRef ?: 'WMS Reference'));
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
        // Build base transaction query
        $query = StockTransaction::with(['department', 'user', 'items.variant.item'])
            ->where('type', 'OUT')
            ->where('status', 'CONFIRMED')
            ->forActiveWarehouse();

        if ($this->startDate) {
            $query->whereDate('created_at', '>=', $this->startDate);
        }
        if ($this->endDate) {
            $query->whereDate('created_at', '<=', $this->endDate);
        }
        if ($this->departmentId) {
            $query->where('department_id', $this->departmentId);
        }
        if ($this->picId) {
            $query->where('user_id', $this->picId);
        }
        if ($this->code) {
            $query->where('code', 'like', '%' . $this->code . '%');
        }
        if ($this->erpTransferStatus) {
            $query->where('erp_transfer_status', $this->erpTransferStatus);
        }

        $transactions = $query->orderBy('created_at', 'desc')->take(1000)->get();

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

        // Get dropdown lists for filter form
        $departments = Department::orderBy('name')->get();
        $users = User::orderBy('name')->get();

        return view('livewire.reports.stock-out-report', [
            'groupedTransactions' => $groupedTransactions,
            'departments' => $departments,
            'users' => $users,
        ])->layout('layouts.app');
    }
}
