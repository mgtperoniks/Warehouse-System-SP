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
        // ─── KPI Cards ────────────────────────────────────────────────────
        $totalItems      = ItemVariant::count();
        $todayTx         = StockTransaction::whereDate('created_at', today())->count();
        $lowStockCount   = Bin::where('min_qty', '>', 0)
                              ->whereColumn('current_qty', '<=', 'min_qty')
                              ->where('current_qty', '>', 0)
                              ->count();
        $outOfStockCount = Bin::where('current_qty', '<=', 0)->count();

        // ─── 7-Day Trend Chart ────────────────────────────────────────────
        $chartDays     = 7;
        $chartLabels   = [];
        $chartStockIn  = [];
        $chartStockOut = [];

        for ($i = $chartDays - 1; $i >= 0; $i--) {
            $date            = Carbon::today()->subDays($i);
            $chartLabels[]   = $date->format('d M');

            $chartStockIn[]  = StockMovement::whereDate('created_at', $date)
                                            ->where('type', 'IN')
                                            ->sum('qty');

            $chartStockOut[] = StockMovement::whereDate('created_at', $date)
                                            ->where('type', 'OUT')
                                            ->sum('qty');
        }

        // ─── Monthly Trend (30d) ──────────────────────────────────────────
        $chartDays30     = 30;
        $chartLabels30   = [];
        $chartIn30       = [];
        $chartOut30      = [];

        for ($i = $chartDays30 - 1; $i >= 0; $i--) {
            $date             = Carbon::today()->subDays($i);
            $chartLabels30[]  = $date->format('d M');
            $chartIn30[]      = StockMovement::whereDate('created_at', $date)->where('type', 'IN')->sum('qty');
            $chartOut30[]     = StockMovement::whereDate('created_at', $date)->where('type', 'OUT')->sum('qty');
        }

        // ─── Recent Transactions ──────────────────────────────────────────
        $recentTransactions = StockTransaction::with(['department', 'user'])
                                ->latest()
                                ->limit(10)
                                ->get();

        // ─── Critical Stock Alerts ────────────────────────────────────────
        $criticalAlerts = Bin::with(['itemVariant.item'])
                            ->where('current_qty', '<=', 0)
                            ->limit(5)
                            ->get();

        $lowStockAlerts = Bin::with(['itemVariant.item'])
                            ->where('min_qty', '>', 0)
                            ->whereColumn('current_qty', '<=', 'min_qty')
                            ->where('current_qty', '>', 0)
                            ->orderBy('current_qty', 'asc')
                            ->limit(5)
                            ->get();

        // ─── Category Distribution Chart (Donut) ─────────────────────────
        $categoryData = Bin::select(
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
        $todayStockIn  = StockMovement::whereDate('created_at', today())->where('type', 'IN')->sum('qty');
        $todayStockOut = StockMovement::whereDate('created_at', today())->where('type', 'OUT')->sum('qty');

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
