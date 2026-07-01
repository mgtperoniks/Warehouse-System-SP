<?php

namespace App\Services\Inventory;

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class InventoryPlanningService
{
    /**
     * Calculate weekly average consumption from OUT movements in the last 28 days.
     * Divided by 4.
     */
    public function calculateWeeklyAverage(int $variantId, ?float $totalOut28 = null): float
    {
        if ($totalOut28 === null) {
            $startDate = Carbon::now()->subDays(28)->startOfDay();
            $totalOut28 = (float) DB::table('stock_movements')
                ->where('item_variant_id', $variantId)
                ->where('type', 'OUT')
                ->where('created_at', '>=', $startDate)
                ->sum('qty');
        }

        return $totalOut28 / 4.0;
    }

    /**
     * Calculate monthly average consumption from OUT movements in the last 90 days.
     * Divided by 3.
     */
    public function calculateMonthlyAverage(int $variantId, ?float $totalOut90 = null): float
    {
        if ($totalOut90 === null) {
            $startDate = Carbon::now()->subDays(90)->startOfDay();
            $totalOut90 = (float) DB::table('stock_movements')
                ->where('item_variant_id', $variantId)
                ->where('type', 'OUT')
                ->where('created_at', '>=', $startDate)
                ->sum('qty');
        }

        return $totalOut90 / 3.0;
    }

    /**
     * Calculate six month average consumption from OUT movements in the last 180 days.
     * Divided by 6.
     */
    public function calculateSixMonthAverage(int $variantId, ?float $totalOut180 = null): float
    {
        if ($totalOut180 === null) {
            $startDate = Carbon::now()->subDays(180)->startOfDay();
            $totalOut180 = (float) DB::table('stock_movements')
                ->where('item_variant_id', $variantId)
                ->where('type', 'OUT')
                ->where('created_at', '>=', $startDate)
                ->sum('qty');
        }

        return $totalOut180 / 6.0;
    }

    /**
     * Calculate Days Left = Current Stock / (Average Weekly / 7.0)
     */
    public function calculateDaysLeft(int $currentStock, float $averageWeekly): ?float
    {
        if ($averageWeekly <= 0.0) {
            return null;
        }

        return $currentStock / ($averageWeekly / 7.0);
    }

    /**
     * Determine numeric planning priority:
     * CRITICAL = 1
     * REORDER NOW = 2
     * WATCHLIST = 3
     * HEALTHY = 4
     * NO CONSUMPTION = 5
     */
    public function calculatePlanningPriority(?float $daysLeft, int $leadTimeDays): int
    {
        if ($daysLeft === null) {
            return 5;
        }

        if ($daysLeft <= $leadTimeDays) {
            return 1;
        }

        if ($daysLeft <= $leadTimeDays + 14) {
            return 2;
        }

        if ($daysLeft <= $leadTimeDays * 2) {
            return 3;
        }

        return 4;
    }

    /**
     * Determine planning status label
     */
    public function calculateHealthStatus(?float $daysLeft, int $leadTimeDays): string
    {
        $priority = $this->calculatePlanningPriority($daysLeft, $leadTimeDays);

        return match ($priority) {
            1 => 'CRITICAL',
            2 => 'REORDER NOW',
            3 => 'WATCHLIST',
            4 => 'HEALTHY',
            5 => 'NO CONSUMPTION',
        };
    }

    /**
     * Determine consumption trend compared against monthly average
     */
    public function calculateTrend(float $weeklyAvg, float $monthlyAvg): string
    {
        if ($weeklyAvg >= $monthlyAvg * 1.20) {
            return 'Increasing';
        }

        if ($weeklyAvg <= $monthlyAvg * 0.80) {
            return 'Decreasing';
        }

        return 'Stable';
    }

    /**
     * Calculate Projected Empty Date = Today + Days Left
     */
    public function calculateProjectedEmptyDate(?float $daysLeft): ?string
    {
        if ($daysLeft === null) {
            return null;
        }

        return Carbon::now()->addDays((int) round($daysLeft))->format('d M Y');
    }
}

