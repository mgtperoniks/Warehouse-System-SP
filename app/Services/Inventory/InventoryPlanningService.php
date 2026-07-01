<?php

namespace App\Services\Inventory;

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class InventoryPlanningService
{
    /**
     * Calculate weekly average consumption from OUT movements in the last 90 days.
     * Grouped by week.
     */
    public function calculateWeeklyAverage(int $variantId): float
    {
        $startDate = Carbon::now()->subDays(90)->startOfDay();
        
        $movements = DB::table('stock_movements')
            ->where('item_variant_id', $variantId)
            ->where('type', 'OUT')
            ->where('created_at', '>=', $startDate)
            ->get();

        // Group into calendar weeks (format: 'o-W')
        $weeklyQuantities = [];
        $current = clone $startDate;
        while ($current <= Carbon::now()) {
            $weekKey = $current->format('o-W');
            $weeklyQuantities[$weekKey] = 0;
            $current->addWeek();
        }
        // Ensure the current week key is initialized
        $weeklyQuantities[Carbon::now()->format('o-W')] = 0;

        foreach ($movements as $m) {
            $weekKey = Carbon::parse($m->created_at)->format('o-W');
            if (isset($weeklyQuantities[$weekKey])) {
                $weeklyQuantities[$weekKey] += (float)$m->qty;
            } else {
                $weeklyQuantities[$weekKey] = (float)$m->qty;
            }
        }

        $totalWeeks = count($weeklyQuantities);
        return $totalWeeks > 0 ? array_sum($weeklyQuantities) / $totalWeeks : 0.0;
    }

    /**
     * Calculate monthly average consumption from OUT movements in the last 180 days.
     * Grouped by month.
     */
    public function calculateMonthlyAverage(int $variantId): float
    {
        $startDate = Carbon::now()->subDays(180)->startOfDay();

        $movements = DB::table('stock_movements')
            ->where('item_variant_id', $variantId)
            ->where('type', 'OUT')
            ->where('created_at', '>=', $startDate)
            ->get();

        // Group into calendar months (format: 'Y-m')
        $monthlyQuantities = [];
        $current = clone $startDate;
        while ($current <= Carbon::now()) {
            $monthKey = $current->format('Y-m');
            $monthlyQuantities[$monthKey] = 0;
            $current->addMonth();
        }
        // Ensure current month key is initialized
        $monthlyQuantities[Carbon::now()->format('Y-m')] = 0;

        foreach ($movements as $m) {
            $monthKey = Carbon::parse($m->created_at)->format('Y-m');
            if (isset($monthlyQuantities[$monthKey])) {
                $monthlyQuantities[$monthKey] += (float)$m->qty;
            } else {
                $monthlyQuantities[$monthKey] = (float)$m->qty;
            }
        }

        $totalMonths = count($monthlyQuantities);
        return $totalMonths > 0 ? array_sum($monthlyQuantities) / $totalMonths : 0.0;
    }

    /**
     * Calculate average consumption over the last six months (total OUT / 6).
     */
    public function calculateSixMonthAverage(int $variantId): float
    {
        $startDate = Carbon::now()->subDays(180)->startOfDay();

        $totalOut = DB::table('stock_movements')
            ->where('item_variant_id', $variantId)
            ->where('type', 'OUT')
            ->where('created_at', '>=', $startDate)
            ->sum('qty');

        return (float)($totalOut / 6.0);
    }

    /**
     * Calculate Days Left = Current Stock / Average Weekly * 7
     */
    public function calculateDaysLeft(int $currentStock, float $averageWeekly): ?float
    {
        if ($averageWeekly <= 0.0) {
            return null;
        }

        return ($currentStock / $averageWeekly) * 7.0;
    }

    /**
     * Determine health status: Healthy, Warning, Critical
     */
    public function calculateHealthStatus(?float $daysLeft, int $leadTimeDays): string
    {
        if ($daysLeft === null) {
            return 'Healthy';
        }

        if ($daysLeft <= $leadTimeDays) {
            return 'Critical';
        }

        if ($daysLeft <= $leadTimeDays * 2) {
            return 'Warning';
        }

        return 'Healthy';
    }
}
