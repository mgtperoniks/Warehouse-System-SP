<?php

namespace App\Http\Controllers;

use App\Models\Bin;
use App\Models\ItemVariant;
use App\Models\StockMovement;
use App\Models\StockTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class DashboardController extends Controller
{
    public function index()
    {
        // Initialize Default Warehouse operational context
        if (!session()->has('active_warehouse_id') && auth()->check()) {
            $defaultWarehouse = auth()->user()->warehouses()->first();
            if ($defaultWarehouse) {
                session()->put('active_warehouse_id', $defaultWarehouse->id);
                session()->put('active_warehouse_code', $defaultWarehouse->code);
                session()->put('active_warehouse_name', $defaultWarehouse->name);
            }
        }

        // ─── KPI Cards ────────────────────────────────────────────────────
        $totalItems      = ItemVariant::count();
        $todayTx         = StockTransaction::forActiveWarehouse()->whereDate('created_at', today())->count();
        $lowStockCount   = Bin::forActiveWarehouse()
                              ->where('min_qty', '>', 0)
                              ->whereColumn('current_qty', '<=', 'min_qty')
                              ->where('current_qty', '>', 0)
                              ->count();
        $outOfStockCount = Bin::forActiveWarehouse()->where('current_qty', '<=', 0)->count();

        // ─── Trend Charts (7d & 30d) ──────────────────────────────────────
        $maxDays = 30;
        $startDate = Carbon::today()->subDays($maxDays - 1);

        $movements = StockMovement::forActiveWarehouse()
            ->where('created_at', '>=', $startDate)
            ->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('SUM(CASE WHEN type = "IN" THEN qty ELSE 0 END) as total_in'),
                DB::raw('SUM(CASE WHEN type = "OUT" THEN qty ELSE 0 END) as total_out')
            )
            ->groupBy('date')
            ->get()
            ->keyBy('date');

        // Prepare 7-day data
        $chartLabels = [];
        $chartStockIn = [];
        $chartStockOut = [];
        for ($i = 6; $i >= 0; $i--) {
            $dateStr = Carbon::today()->subDays($i)->format('Y-m-d');
            $label = Carbon::today()->subDays($i)->format('d M');
            $data = $movements->get($dateStr);
            
            $chartLabels[] = $label;
            $chartStockIn[] = $data ? (int)$data->total_in : 0;
            $chartStockOut[] = $data ? (int)$data->total_out : 0;
        }

        // Prepare 30-day data
        $chartLabels30 = [];
        $chartIn30 = [];
        $chartOut30 = [];
        for ($i = 29; $i >= 0; $i--) {
            $dateStr = Carbon::today()->subDays($i)->format('Y-m-d');
            $label = Carbon::today()->subDays($i)->format('d M');
            $data = $movements->get($dateStr);
            
            $chartLabels30[] = $label;
            $chartIn30[] = $data ? (int)$data->total_in : 0;
            $chartOut30[] = $data ? (int)$data->total_out : 0;
        }

        // ─── Recent Transactions ──────────────────────────────────────────
        $recentTransactions = StockTransaction::forActiveWarehouse()
                                ->with(['department', 'user'])
                                ->latest()
                                ->limit(10)
                                ->get();

        // ─── Critical Stock Alerts ────────────────────────────────────────
        $criticalAlerts = Bin::forActiveWarehouse()
                            ->with(['itemVariant.item'])
                            ->where('current_qty', '<=', 0)
                            ->limit(5)
                            ->get();

        $lowStockAlerts = Bin::forActiveWarehouse()
                            ->with(['itemVariant.item'])
                            ->where('min_qty', '>', 0)
                            ->whereColumn('current_qty', '<=', 'min_qty')
                            ->where('current_qty', '>', 0)
                            ->orderBy('current_qty', 'asc')
                            ->limit(5)
                            ->get();

        // ─── Category Distribution Chart (Donut) ─────────────────────────
        $categoryData = Bin::forActiveWarehouse()
                        ->select(
                            DB::raw('SUM(current_qty) as total_qty'),
                            'item_variants.brand'
                        )
                        ->join('item_variants', 'bins.item_variant_id', '=', 'item_variants.id')
                        ->groupBy('item_variants.brand')
                        ->orderByDesc('total_qty')
                        ->limit(6)
                        ->get();

        $donutLabels = $categoryData->pluck('brand')->map(fn ($b) => $b ?: 'Unbranded')->values()->toArray();
        $donutData   = $categoryData->pluck('total_qty')->values()->toArray();

        // ─── Today's Stock Movement Summary ──────────────────────────────
        $todayStockIn  = StockMovement::forActiveWarehouse()->whereDate('created_at', today())->where('type', 'IN')->sum('qty');
        $todayStockOut = StockMovement::forActiveWarehouse()->whereDate('created_at', today())->where('type', 'OUT')->sum('qty');

        return view('dashboard.index', compact(
            'totalItems',
            'todayTx',
            'lowStockCount',
            'outOfStockCount',
            'chartLabels',
            'chartStockIn',
            'chartStockOut',
            'chartLabels30',
            'chartIn30',
            'chartOut30',
            'recentTransactions',
            'criticalAlerts',
            'lowStockAlerts',
            'donutLabels',
            'donutData',
            'todayStockIn',
            'todayStockOut',
        ));
    }
}
