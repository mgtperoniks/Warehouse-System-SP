<?php

namespace App\Livewire\Items;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\ItemVariant;
use Illuminate\Support\Facades\DB;

class InventoryPlanningPage extends Component
{
    use WithPagination;

    public $search = '';
    public $procurementFilter = '';
    public $classFilter = '';
    public $sortField = 'erp_code';
    public $sortDir = 'asc';
    public $sortDirection = 'asc';
    public $perPage = 25;

    protected $queryString = [
        'search' => ['except' => ''],
        'procurementFilter' => ['except' => ''],
        'classFilter' => ['except' => ''],
        'sortField' => ['except' => 'erp_code'],
        'sortDir' => ['except' => 'asc'],
        'sortDirection' => ['except' => 'asc'],
    ];

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function updatingProcurementFilter()
    {
        $this->resetPage();
    }

    public function updatingClassFilter()
    {
        $this->resetPage();
    }

    public function sortBy($field)
    {
        if ($this->sortField === $field) {
            $this->sortDir = $this->sortDir === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDir = 'asc';
        }
        $this->sortDirection = $this->sortDir;
    }

    public function updatePlanning($variantId, $field, $value)
    {
        $variant = ItemVariant::findOrFail($variantId);

        $rules = [
            'procurement_type' => 'required|in:LOCAL,IMPORT',
            'inventory_class' => 'required|in:CONSUMABLE,SPAREPART',
            'lead_time_days' => 'required|integer|min:1|max:365',
        ];

        if (isset($rules[$field])) {
            $validated = validator([$field => $value], [$field => $rules[$field]])->validate();
            $variant->update([$field => $validated[$field]]);
            
            $this->dispatch('notyf', type: 'success', message: 'Planning field updated successfully.');
        }
    }

    public function render(\App\Services\Inventory\InventoryPlanningService $planningService)
    {
        $startDate = now()->subDays(90)->startOfDay();

        // Calculate weeks to keep DB logic identical to Service
        $weeklyQuantities = [];
        $current = clone $startDate;
        while ($current <= now()) {
            $weeklyQuantities[$current->format('o-W')] = 0;
            $current->addWeek();
        }
        $weeklyQuantities[now()->format('o-W')] = 0;
        $numWeeks = (float)count($weeklyQuantities);

        $query = ItemVariant::query()
            ->select(
                'item_variants.*',
                DB::raw('COALESCE(stock_data.total_stock, 0) as total_stock'),
                DB::raw("COALESCE(weekly_avg_data.total_out_90, 0) / {$numWeeks} as weekly_avg"),
                DB::raw("CASE WHEN COALESCE(weekly_avg_data.total_out_90, 0) = 0 THEN NULL ELSE (COALESCE(stock_data.total_stock, 0) / (weekly_avg_data.total_out_90 / {$numWeeks})) * 7 END as days_left")
            )
            ->join('items', 'items.id', '=', 'item_variants.item_id')
            ->with(['item']);

        // Subquery for Aggregated Stock
        $stockSubquery = DB::table('bins')
            ->select('item_variant_id', 
                DB::raw('SUM(current_qty) as total_stock')
            )
            ->groupBy('item_variant_id');

        $query->leftJoinSub($stockSubquery, 'stock_data', function ($join) {
            $join->on('item_variants.id', '=', 'stock_data.item_variant_id');
        });

        // Subquery for OUT movements in the last 90 days
        $weeklyAvgSubquery = DB::table('stock_movements')
            ->select('item_variant_id', DB::raw('SUM(qty) as total_out_90'))
            ->where('type', 'OUT')
            ->where('created_at', '>=', $startDate)
            ->groupBy('item_variant_id');

        $query->leftJoinSub($weeklyAvgSubquery, 'weekly_avg_data', function ($join) {
            $join->on('item_variants.id', '=', 'weekly_avg_data.item_variant_id');
        });

        if (!empty($this->search)) {
            $search = $this->search;
            $query->where(function ($q) use ($search) {
                $q->where('item_variants.erp_code', 'like', $search . '%')
                  ->orWhere('items.name', 'like', '%' . $search . '%')
                  ->orWhereHas('barcodes', function ($sq) use ($search) {
                      $sq->where('barcode', $search);
                  });
            });
        }

        if (!empty($this->procurementFilter)) {
            $query->where('item_variants.procurement_type', $this->procurementFilter);
        }

        if (!empty($this->classFilter)) {
            $query->where('item_variants.inventory_class', $this->classFilter);
        }

        $dir = $this->sortDir === 'desc' ? 'desc' : 'asc';

        if ($this->sortField === 'stock') {
            $query->orderBy('total_stock', $dir);
        } elseif ($this->sortField === 'weekly_avg') {
            $query->orderBy('weekly_avg', $dir);
        } elseif ($this->sortField === 'days_left') {
            // "No Usage" always appears last
            $query->orderByRaw('CASE WHEN COALESCE(weekly_avg_data.total_out_90, 0) = 0 THEN 1 ELSE 0 END ASC');
            $query->orderBy('days_left', $dir);
        } elseif ($this->sortField === 'status') {
            // Status custom priority: Critical (1), Warning (2), Healthy (3)
            $daysLeftExpr = "CASE WHEN COALESCE(weekly_avg_data.total_out_90, 0) = 0 THEN NULL ELSE (COALESCE(stock_data.total_stock, 0) / (weekly_avg_data.total_out_90 / {$numWeeks})) * 7 END";
            if ($dir === 'asc') {
                $statusPrioritySql = "CASE
                    WHEN {$daysLeftExpr} <= item_variants.lead_time_days THEN 1
                    WHEN {$daysLeftExpr} <= (item_variants.lead_time_days * 2) THEN 2
                    ELSE 3
                END";
            } else {
                $statusPrioritySql = "CASE
                    WHEN {$daysLeftExpr} IS NULL OR {$daysLeftExpr} > (item_variants.lead_time_days * 2) THEN 1
                    WHEN {$daysLeftExpr} > item_variants.lead_time_days AND {$daysLeftExpr} <= (item_variants.lead_time_days * 2) THEN 2
                    ELSE 3
                END";
            }
            $query->orderByRaw("{$statusPrioritySql} ASC");
        } elseif ($this->sortField === 'procurement_type') {
            $query->orderBy('item_variants.procurement_type', $dir);
        } elseif ($this->sortField === 'inventory_class') {
            $query->orderBy('item_variants.inventory_class', $dir);
        } elseif ($this->sortField === 'lead_time_days') {
            $query->orderBy('item_variants.lead_time_days', $dir);
        } elseif ($this->sortField === 'name') {
            $query->orderBy('items.name', $dir);
        } else {
            // default erp_code
            $query->orderByRaw('COALESCE(item_variants.erp_code, "") ' . ($dir === 'desc' ? 'DESC' : 'ASC'));
        }

        $variants = $query->paginate((int)$this->perPage);

        foreach ($variants as $variant) {
            $variant->total_stock = (int)($variant->total_stock ?? 0);
            $variant->weekly_avg = $planningService->calculateWeeklyAverage($variant->id);
            $variant->days_left = $planningService->calculateDaysLeft($variant->total_stock, $variant->weekly_avg);
            $variant->health_status = $planningService->calculateHealthStatus($variant->days_left, $variant->lead_time_days);
        }

        return view('livewire.items.inventory-planning-page', [
            'variants' => $variants,
        ]);
    }
}
