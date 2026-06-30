<?php

namespace App\Livewire\Governance;

use Livewire\Component;
use App\Models\InventoryAdjustment;
use App\Models\InventoryAdjustmentItem;
use App\Models\User;
use Carbon\Carbon;

class InventoryAdjustmentsPage extends Component
{
    // Filters
    public $startDate = '';
    public $endDate = '';
    public $operatorId = '';
    public $statusFilter = 'ALL';

    // Expanded Headers State
    public $expandedHeaders = [];

    // Rejection Flow State
    public $rejectReasons = [];
    public $confirmingRejectId = null;

    // Date presets
    public function mount()
    {
        $this->startDate = Carbon::today()->startOfMonth()->format('Y-m-d');
        $this->endDate = Carbon::today()->format('Y-m-d');
    }

    public function toggleExpand($headerId)
    {
        if (in_array($headerId, $this->expandedHeaders)) {
            $this->expandedHeaders = array_diff($this->expandedHeaders, [$headerId]);
        } else {
            $this->expandedHeaders[] = $headerId;
        }
    }

    public function startReject($itemId)
    {
        $this->confirmingRejectId = $itemId;
        $this->rejectReasons[$itemId] = '';
    }

    public function cancelReject()
    {
        $this->confirmingRejectId = null;
    }

    public function confirmReject($itemId)
    {
        $reason = $this->rejectReasons[$itemId] ?? '';
        $this->rejectItem($itemId, $reason);
        $this->confirmingRejectId = null;
    }

    public function approveItem($itemId)
    {
        if (auth()->user()->role !== 'manager') {
            session()->flash('error', 'Unauthorized. Only Manager PPIC can authorize adjustments.');
            return;
        }

        try {
            \Illuminate\Support\Facades\DB::transaction(function () use ($itemId) {
                // Lock the specific row for update (Safe Concurrency & Idempotency)
                $item = \App\Models\InventoryAdjustmentItem::where('id', $itemId)
                    ->lockForUpdate()
                    ->first();

                if (!$item) {
                    throw new \Exception("Adjustment item not found.");
                }

                // Strictly verify the item status is WAITING before performing any mutations
                if ($item->status !== 'WAITING') {
                    // Item has already been processed (approved or rejected) in another thread/request.
                    // Abort silently and safely to guarantee idempotency.
                    return;
                }

                $bin = $item->bin;
                $header = $item->header;

                // Execute the workflow-agnostic InventoryService movement
                $inventoryService = app(\App\Services\Inventory\InventoryService::class);
                
                $movement = $inventoryService->moveStock(
                    $bin,
                    $item->variance,
                    'ADJUSTMENT',
                    'Approved Adjustment: ' . $header->adjustment_no . ' (Reason: ' . $item->reason_code . ')',
                    (string)auth()->id()
                );

                // Update the snapshot reference or track the movement_id if needed, and update status
                $item->update([
                    'status' => 'APPROVED',
                    'approved_by' => auth()->id(),
                    'approved_at' => now(),
                ]);

                // Update item variant last_opname_at
                if ($item->itemVariant) {
                    $item->itemVariant->update([
                        'last_opname_at' => now()
                    ]);
                }

                // Synchronize parent header status
                \App\Models\InventoryAdjustment::synchronizeStatus($header->id);
            });

            session()->flash('message', 'Item approved and stock adjusted successfully.');
        } catch (\Exception $e) {
            session()->flash('error', 'Approval failed: ' . $e->getMessage());
        }
    }

    public function rejectItem($itemId, $rejectReason)
    {
        if (auth()->user()->role !== 'manager') {
            session()->flash('error', 'Unauthorized. Only Manager PPIC can authorize adjustments.');
            return;
        }

        if (empty(trim($rejectReason))) {
            session()->flash('error', 'Rejection reason is required.');
            return;
        }

        try {
            \Illuminate\Support\Facades\DB::transaction(function () use ($itemId, $rejectReason) {
                // Lock the specific row for update (Safe Concurrency & Idempotency)
                $item = \App\Models\InventoryAdjustmentItem::where('id', $itemId)
                    ->lockForUpdate()
                    ->first();

                if (!$item) {
                    throw new \Exception("Adjustment item not found.");
                }

                // Strictly verify status is WAITING (already processed items cannot be modified)
                if ($item->status !== 'WAITING') {
                    return;
                }

                // Mark status as REJECTED and log details
                $item->update([
                    'status' => 'REJECTED',
                    'rejected_by' => auth()->id(),
                    'rejected_at' => now(),
                    'reject_reason' => trim($rejectReason),
                ]);

                // Synchronize parent header status
                \App\Models\InventoryAdjustment::synchronizeStatus($item->inventory_adjustment_id);
            });

            session()->flash('message', 'Adjustment item rejected successfully.');
        } catch (\Exception $e) {
            session()->flash('error', 'Rejection failed: ' . $e->getMessage());
        }
    }

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
                $this->startDate = Carbon::today()->startOfWeek()->format('Y-m-d');
                $this->endDate = Carbon::today()->endOfWeek()->format('Y-m-d');
                break;
            case 'this_month':
                $this->startDate = Carbon::today()->startOfMonth()->format('Y-m-d');
                $this->endDate = Carbon::today()->endOfMonth()->format('Y-m-d');
                break;
        }
    }

    public function render()
    {
        $activeWarehouseId = session()->get('active_warehouse_id');

        // 1. Fetch Operators for filter selection
        $operators = User::whereIn('role', ['admin', 'operator', 'auditor'])
            ->orderBy('name')
            ->get();

        // 2. Base Query for Headers (Scoped to Active Warehouse)
        $headersQuery = InventoryAdjustment::forActiveWarehouse()
            ->with([
                'warehouse',
                'operator',
                'items' => function ($q) {
                    $q->orderBy('created_at', 'desc');
                }
            ])
            ->withCount([
                'items as waiting_count' => function ($q) { $q->where('status', 'WAITING'); },
                'items as approved_count' => function ($q) { $q->where('status', 'APPROVED'); },
                'items as rejected_count' => function ($q) { $q->where('status', 'REJECTED'); }
            ]);

        // 3. Role-Based Visibility
        if (auth()->user()->role !== 'manager') {
            $headersQuery->where('operator_id', auth()->id());
        } else {
            if (!empty($this->operatorId)) {
                $headersQuery->where('operator_id', $this->operatorId);
            }
        }

        // 4. Date Filters
        if ($this->startDate) {
            $headersQuery->where('date', '>=', $this->startDate);
        }
        if ($this->endDate) {
            $headersQuery->where('date', '<=', $this->endDate);
        }

        // 5. Status Filtering (Filter headers that have items matching the selected status)
        if ($this->statusFilter && $this->statusFilter !== 'ALL') {
            $headersQuery->whereHas('items', function ($q) {
                $q->where('status', $this->statusFilter);
            });
        }

        // Get Headers
        $headers = $headersQuery->orderBy('date', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();

        // 6. Base Query for KPI Counters (Matches same isolation and scope rules)
        $kpiItemQuery = InventoryAdjustmentItem::whereHas('header', function ($q) use ($activeWarehouseId) {
            $q->where('warehouse_id', $activeWarehouseId);
            if (auth()->user()->role !== 'manager') {
                $q->where('operator_id', auth()->id());
            }
        });

        $kpis = [
            'waiting' => (clone $kpiItemQuery)->where('status', 'WAITING')->count(),
            'approved_today' => (clone $kpiItemQuery)->where('status', 'APPROVED')->whereDate('approved_at', Carbon::today())->count(),
            'rejected_today' => (clone $kpiItemQuery)->where('status', 'REJECTED')->whereDate('rejected_at', Carbon::today())->count(),
            'avg_time' => '--' // Placeholder for future phase
        ];

        return view('livewire.governance.inventory-adjustments-page', [
            'headers' => $headers,
            'operators' => $operators,
            'kpis' => $kpis
        ])->layout('layouts.app');
    }
}
