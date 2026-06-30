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
        $this->startDate = Carbon::today()->format('Y-m-d');
        $this->endDate = Carbon::today()->format('Y-m-d');
        
        if (auth()->user()->role !== 'manager') {
            $this->operatorId = (string)auth()->id();
        } else {
            $this->operatorId = '';
        }
    }

    /**
     * Format Carbon timestamp into short natural age layout.
     */
    public function formatAge($createdAt)
    {
        $dt = Carbon::parse($createdAt);
        $now = Carbon::now();
        $diffSeconds = (int) $dt->diffInSeconds($now);
        
        if ($diffSeconds < 60) {
            return $diffSeconds . ' sec';
        }
        
        $diffMinutes = (int) $dt->diffInMinutes($now);
        if ($diffMinutes < 60) {
            return $diffMinutes . ' min';
        }
        
        $diffHours = (int) $dt->diffInHours($now);
        if ($diffHours < 24) {
            return $diffHours . ' hr';
        }
        
        if ($dt->isYesterday()) {
            return 'Yesterday';
        }
        
        $diffDays = (int) $dt->diffInDays($now);
        return $diffDays . ' days';
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

            session()->flash('message', 'Inventory adjustment berhasil disetujui. Stok fisik telah diperbarui.');
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

            session()->flash('message', 'Inventory adjustment berhasil ditolak.');
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

    /**
     * Generate BASO PDF for a completed Inventory Adjustment.
     */
    public function generateBaso($headerId)
    {
        if (auth()->user()->role !== 'manager') {
            session()->flash('error', 'Unauthorized. Only Manager PPIC can generate BASO.');
            return;
        }

        try {
            $adjustment = InventoryAdjustment::findOrFail($headerId);

            if ($adjustment->status !== 'COMPLETED') {
                session()->flash('error', 'Cannot generate BASO. The Inventory Adjustment session is not yet completed.');
                return;
            }

            $baso = \Illuminate\Support\Facades\DB::transaction(function () use ($adjustment) {
                $existing = \App\Models\BasoDocument::where('inventory_adjustment_id', $adjustment->id)->first();
                if ($existing) {
                    return $existing;
                }

                $basoNumber = \App\Models\BasoDocument::generateNumber($adjustment->warehouse_id, $adjustment->date);

                // Prepare storage directory
                $dir = 'baso';
                if (!\Illuminate\Support\Facades\Storage::disk('public')->exists($dir)) {
                    \Illuminate\Support\Facades\Storage::disk('public')->makeDirectory($dir);
                }

                $filename = "{$basoNumber}.pdf";
                $relativePdfPath = "baso/{$filename}";

                return \App\Models\BasoDocument::create([
                    'inventory_adjustment_id' => $adjustment->id,
                    'baso_number' => $basoNumber,
                    'generated_by' => auth()->id(),
                    'generated_at' => now(),
                    'pdf_path' => $relativePdfPath,
                ]);
            });

            // Compile the PDF using DomPDF
            if (!\Illuminate\Support\Facades\Storage::disk('public')->exists($baso->pdf_path)) {
                $items = $adjustment->items()->with(['bin', 'itemVariant.item'])->get();
                $totalItems = $items->count();
                $approvedCount = $items->where('status', 'APPROVED')->count();
                $rejectedCount = $items->where('status', 'REJECTED')->count();
                
                $posVariance = $items->where('variance', '>', 0)->sum('variance');
                $negVariance = $items->where('variance', '<', 0)->sum('variance');
                $netVariance = $items->sum('variance');

                $warehouseName = $adjustment->warehouse->name ?? 'N/A';
                $operatorName = $adjustment->operator->name ?? 'N/A';
                $managerName = $baso->generator->name ?? 'N/A';
                $businessDate = $adjustment->date;

                $reasonsMap = [
                    'COUNTING_ERROR' => 'Salah Hitung',
                    'WRONG_SCAN' => 'Salah Scan Barcode',
                    'WRONG_BIN' => 'Salah Rak / Salah Penempatan',
                    'WRONG_PICK' => 'Salah Ambil Barang',
                    'FOUND_ITEM' => 'Barang Ditemukan',
                    'RETURN_FOUND' => 'Barang Retur Ditemukan',
                    'LEFTOVER_FOUND' => 'Sisa Produksi Ditemukan',
                    'MISSING_ITEM' => 'Barang Tidak Ditemukan',
                    'DAMAGED_ITEM' => 'Barang Rusak',
                    'EXPIRED_ITEM' => 'Barang Kadaluarsa / Tidak Layak Pakai',
                    'MOVED_WITHOUT_SCAN' => 'Dipindahkan Tanpa Scan',
                    'SYSTEM_GLITCH' => 'Glitch Sistem / Selisih Sinkronisasi',
                    'LAINNYA' => 'Lainnya (Butuh Catatan)',
                ];

                $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('reports.baso-pdf', [
                    'baso' => $baso,
                    'adjustment' => $adjustment,
                    'items' => $items,
                    'totalItems' => $totalItems,
                    'approvedCount' => $approvedCount,
                    'rejectedCount' => $rejectedCount,
                    'posVariance' => $posVariance,
                    'negVariance' => $negVariance,
                    'netVariance' => $netVariance,
                    'warehouseName' => $warehouseName,
                    'operatorName' => $operatorName,
                    'managerName' => $managerName,
                    'businessDate' => $businessDate,
                    'reasonsMap' => $reasonsMap,
                ])->setPaper('a4', 'portrait');

                \Illuminate\Support\Facades\Storage::disk('public')->put($baso->pdf_path, $pdf->output());
            }

            session()->flash('message', 'BASO PDF berhasil dibuat.');
        } catch (\Exception $e) {
            session()->flash('error', 'Failed to generate BASO: ' . $e->getMessage());
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

        // 5. Status Filtering
        if ($this->statusFilter && $this->statusFilter !== 'ALL') {
            $headersQuery->whereHas('items', function ($q) {
                $q->where('status', $this->statusFilter);
            });
        }

        // Get Headers
        $headers = $headersQuery->orderBy('date', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();

        // Map BASO documents for the headers in this request
        $basoMap = \App\Models\BasoDocument::whereIn('inventory_adjustment_id', $headers->pluck('id'))
            ->get()
            ->keyBy('inventory_adjustment_id');

        // 6. Base Query for KPI Counters
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
            'avg_time' => '--'
        ];

        return view('livewire.governance.inventory-adjustments-page', [
            'headers' => $headers,
            'operators' => $operators,
            'kpis' => $kpis,
            'basoMap' => $basoMap,
        ])->layout('layouts.app');
    }
}

