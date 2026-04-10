<?php

namespace App\Services\Dashboard;

use App\Models\Bin;

class DashboardService
{
    /**
     * Get low stock items based on min_qty
     */
    public function getLowStockBins()
    {
        return Bin::with(['itemVariant.item', 'location'])
            ->whereColumn('current_qty', '<=', 'min_qty')
            ->where('current_qty', '>', 0)
            ->get();
    }

    /**
     * Get out of stock items
     */
    public function getOutOfStockBins()
    {
        return Bin::with(['itemVariant.item', 'location'])
            ->where('current_qty', '<=', 0) // Should logically be 0, but catching negatives as well
            ->get();
    }

    /**
     * Fast moving items (could be based on highest number of OUT movements in last X days)
     */
    public function getFastMovingItems(int $days = 30)
    {
        // This is a placeholder for complex logic.
        // E.g., query StockMovement type = OUT grouped by item_variant_id ordered by SUM(qty)
        return [];
    }
}
