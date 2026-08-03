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
    public $statusFilter = '';
    public $sortField = 'erp_code';
    public $sortDir = 'asc';
    public $sortDirection = 'asc';
    public $perPage = 25;

    protected $queryString = [
        'search' => ['except' => ''],
        'procurementFilter' => ['except' => ''],
        'classFilter' => ['except' => ''],
        'statusFilter' => ['except' => ''],
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

    public function updatingStatusFilter()
    {
        $this->resetPage();
    }

    public function toggleStatusFilter($status)
    {
        if ($this->statusFilter === $status) {
            $this->statusFilter = '';
        } else {
            $this->statusFilter = $status;
        }
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

    public function render(\App\Services\Inventory\InventoryPlanningService $planningService)
    {
        $date28 = now()->subDays(28)->startOfDay();
        $date90 = now()->subDays(90)->startOfDay();
        $date180 = now()->subDays(180)->startOfDay();

        // 1. Build Base Query for Aggregate Dashboard Counts (without status filter)
        $baseQuery = ItemVariant::forActiveWarehouse()
            ->select(
                'item_variants.id',
                'item_variants.lead_time_days',
                DB::raw('COALESCE(stock_data.total_stock, 0) as total_stock'),
                DB::raw('COALESCE(movement_data.total_out_28, 0) as total_out_28')
            )
            ->join('items', 'items.id', '=', 'item_variants.item_id');

        // Subqueries used for both base query and main query
        $stockSubquery = DB::table('bins')
            ->select('item_variant_id', DB::raw('SUM(current_qty) as total_stock'))
            ->groupBy('item_variant_id');

        $movementSubqueryCounts = DB::table('stock_movements')
            ->select('item_variant_id')
            ->selectRaw('SUM(qty) as total_out_28')
            ->where('type', 'OUT')
            ->where('created_at', '>=', $date28)
            ->groupBy('item_variant_id');

        $baseQuery->leftJoinSub($stockSubquery, 'stock_data', 'item_variants.id', '=', 'stock_data.item_variant_id');
        $baseQuery->leftJoinSub($movementSubqueryCounts, 'movement_data', 'item_variants.id', '=', 'movement_data.item_variant_id');

        // Apply filters (search, procurement, class) to the base query
        if (!empty($this->search)) {
            $search = $this->search;
            $baseQuery->where(function ($q) use ($search) {
                $q->where('item_variants.erp_code', 'like', $search . '%')
                  ->orWhere('items.name', 'like', '%' . $search . '%')
                  ->orWhereHas('barcodes', function ($sq) use ($search) {
                      $sq->where('barcode', $search);
                  });
            });
        }

        if (!empty($this->procurementFilter)) {
            $baseQuery->where('item_variants.procurement_type', $this->procurementFilter);
        }

        if (!empty($this->classFilter)) {
            $baseQuery->where('item_variants.inventory_class', $this->classFilter);
        }

        // Fetch all matching items in current context (extremely fast since only key columns are selected)
        $allMatching = $baseQuery->get();

        // Calculate aggregate counts and determine matching IDs for status filter
        $dashboardCounts = [
            'TOTAL' => 0,
            'CRITICAL' => 0,
            'REORDER' => 0,
            'WATCHLIST' => 0,
            'HEALTHY' => 0,
            'OVERSTOCK' => 0,
            'UNKNOWN' => 0,
        ];

        $matchedIds = [];

        foreach ($allMatching as $v) {
            $stock = (int)$v->total_stock;
            $weeklyAvg = (float)$v->total_out_28 / 4.0;
            
            $daysLeft = null;
            if ($weeklyAvg > 0.0) {
                $daysLeft = $stock / ($weeklyAvg / 7.0);
            }

            $leadTime = (int)($v->lead_time_days ?? 30);
            
            // Replicate thresholds exactly as in calculatePlanningPriority()
            if ($daysLeft === null) {
                $status = 'UNKNOWN';
            } elseif ($daysLeft <= $leadTime) {
                $status = 'CRITICAL';
            } elseif ($daysLeft <= $leadTime + 14) {
                $status = 'REORDER';
            } elseif ($daysLeft <= $leadTime * 2) {
                $status = 'WATCHLIST';
            } else {
                $status = 'HEALTHY';
            }

            $dashboardCounts['TOTAL']++;
            $dashboardCounts[$status]++;

            // Collect IDs matching current statusFilter
            if (!empty($this->statusFilter)) {
                if ($this->statusFilter === $status) {
                    $matchedIds[] = $v->id;
                }
            }
        }

        // 2. Build Paginated Query for Table
        $query = ItemVariant::forActiveWarehouse()
            ->select(
                'item_variants.*',
                DB::raw('COALESCE(stock_data.total_stock, 0) as total_stock'),
                DB::raw('COALESCE(movement_data.total_out_28, 0) as total_out_28'),
                DB::raw('COALESCE(movement_data.total_out_90, 0) as total_out_90'),
                DB::raw('COALESCE(movement_data.total_out_180, 0) as total_out_180')
            )
            ->join('items', 'items.id', '=', 'item_variants.item_id')
            ->with(['item', 'barcodes']);

        $query->leftJoinSub($stockSubquery, 'stock_data', 'item_variants.id', '=', 'stock_data.item_variant_id');

        $movementSubqueryTable = DB::table('stock_movements')
            ->select('item_variant_id')
            ->selectRaw('SUM(CASE WHEN created_at >= ? THEN qty ELSE 0 END) as total_out_28', [$date28])
            ->selectRaw('SUM(CASE WHEN created_at >= ? THEN qty ELSE 0 END) as total_out_90', [$date90])
            ->selectRaw('SUM(CASE WHEN created_at >= ? THEN qty ELSE 0 END) as total_out_180', [$date180])
            ->where('type', 'OUT')
            ->where('created_at', '>=', $date180)
            ->groupBy('item_variant_id');

        $query->leftJoinSub($movementSubqueryTable, 'movement_data', 'item_variants.id', '=', 'movement_data.item_variant_id');

        // Apply filters (search, procurement, class) to the main query
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

        // Apply Status Filter (using pre-matched IDs from the base query)
        if (!empty($this->statusFilter)) {
            $query->whereIn('item_variants.id', $matchedIds);
        }

        $dir = $this->sortDir === 'desc' ? 'desc' : 'asc';

        if ($this->sortField === 'stock') {
            $query->orderBy('total_stock', $dir);
        } elseif ($this->sortField === 'weekly_avg') {
            $query->orderBy('total_out_28', $dir);
        } elseif ($this->sortField === 'days_left') {
            $query->orderByRaw('CASE WHEN COALESCE(movement_data.total_out_28, 0) = 0 THEN 1 ELSE 0 END ASC');
            $query->orderByRaw('(COALESCE(stock_data.total_stock, 0) / NULLIF(movement_data.total_out_28, 0)) ' . $dir);
        } elseif ($this->sortField === 'status') {
            $query->orderByRaw('CASE WHEN COALESCE(movement_data.total_out_28, 0) = 0 THEN 1 ELSE 0 END ASC');
            $query->orderByRaw('(COALESCE(stock_data.total_stock, 0) / NULLIF(movement_data.total_out_28 * item_variants.lead_time_days, 0)) ' . $dir);
        } elseif ($this->sortField === 'procurement_type') {
            $query->orderBy('item_variants.procurement_type', $dir);
        } elseif ($this->sortField === 'inventory_class') {
            $query->orderBy('item_variants.inventory_class', $dir);
        } elseif ($this->sortField === 'lead_time_days') {
            $query->orderBy('item_variants.lead_time_days', $dir);
        } elseif ($this->sortField === 'name') {
            $query->orderBy('items.name', $dir);
        } else {
            $query->orderByRaw('COALESCE(item_variants.erp_code, "") ' . ($dir === 'desc' ? 'DESC' : 'ASC'));
        }

        $variants = $query->paginate((int)$this->perPage);

        foreach ($variants as $variant) {
            $variant->total_stock = (int)($variant->total_stock ?? 0);
            $variant->weekly_avg = $planningService->calculateWeeklyAverage($variant->id, (float)($variant->total_out_28 ?? 0));
            $variant->monthly_avg = $planningService->calculateMonthlyAverage($variant->id, (float)($variant->total_out_90 ?? 0));
            $variant->six_month_avg = $planningService->calculateSixMonthAverage($variant->id, (float)($variant->total_out_180 ?? 0));
            $variant->days_left = $planningService->calculateDaysLeft($variant->total_stock, $variant->weekly_avg);
            $variant->health_status = $planningService->calculateHealthStatus($variant->days_left, $variant->lead_time_days);
            $variant->trend = $planningService->calculateTrend($variant->weekly_avg, $variant->monthly_avg);
        }

        return view('livewire.items.inventory-planning-page', [
            'variants' => $variants,
            'dashboardCounts' => $dashboardCounts,
        ]);
    }
}
