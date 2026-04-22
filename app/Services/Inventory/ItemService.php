<?php

namespace App\Services\Inventory;

use App\Models\ItemVariant;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class ItemService
{
    /**
     * Get paginated items with aggregated stock and movement data.
     */
    public function getPaginatedItems(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        $query = ItemVariant::query()
            ->select(
                'item_variants.*',
                'stock_data.total_stock',
                'stock_data.total_min_stock',
                'movement_data.last_movement_at'
            )
            ->join('items', 'items.id', '=', 'item_variants.item_id')
            ->with(['item', 'barcodes']);

        // 1. Subquery for Aggregated Stock
        $stockSubquery = DB::table('bins')
            ->select('item_variant_id', 
                DB::raw('SUM(current_qty) as total_stock'),
                DB::raw('SUM(min_qty) as total_min_stock')
            )
            ->groupBy('item_variant_id');

        $query->leftJoinSub($stockSubquery, 'stock_data', function ($join) {
            $join->on('item_variants.id', '=', 'stock_data.item_variant_id');
        });

        // 2. Subquery for Last Movement
        $movementSubquery = DB::table('stock_movements')
            ->select('item_variant_id', DB::raw('MAX(created_at) as last_movement_at'))
            ->groupBy('item_variant_id');

        $query->leftJoinSub($movementSubquery, 'movement_data', function ($join) {
            $join->on('item_variants.id', '=', 'movement_data.item_variant_id');
        });

        // 3. Apply Filters
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                // ERP Code (Exact or Prefix)
                $q->where('item_variants.erp_code', 'like', $search . '%')
                  // Item Name (Contain)
                  ->orWhere('items.name', 'like', '%' . $search . '%')
                  // Barcode (Exact match via subquery to keep it fast)
                  ->orWhereHas('barcodes', function ($sq) use ($search) {
                      $sq->where('barcode', $search);
                  });
            });
        }

        if (!empty($filters['brand'])) {
            $query->where('item_variants.brand', $filters['brand']);
        }

        if (!empty($filters['status'])) {
            switch ($filters['status']) {
                case 'OUT_OF_STOCK':
                    $query->where(function($q) {
                        $q->where('stock_data.total_stock', '<=', 0)
                          ->orWhereNull('stock_data.total_stock');
                    });
                    break;
                case 'LOW_STOCK':
                    $query->whereColumn('stock_data.total_stock', '<=', 'stock_data.total_min_stock')
                          ->where('stock_data.total_stock', '>', 0);
                    break;
                case 'IN_STOCK':
                    $query->whereColumn('stock_data.total_stock', '>', 'stock_data.total_min_stock');
                    break;
            }
        }

        // 4. Sorting
        $sortField = $filters['sort_field'] ?? 'items.name';
        $sortDir = $filters['sort_dir'] ?? 'asc';

        // Map UI sort fields to DB columns
        $sortMap = [
            'erp_code' => 'item_variants.erp_code',
            'name'     => 'items.name',
            'stock'    => 'stock_data.total_stock',
            'movement' => 'movement_data.last_movement_at',
        ];

        $dbSortField = $sortMap[$sortField] ?? 'items.name';
        
        $query->orderBy($dbSortField, $sortDir);

        // Return paginated results
        return $query->paginate($perPage);
    }

    /**
     * Get distinct brands for the searchable filter.
     */
    public function getBrands(): array
    {
        return ItemVariant::select('brand')
            ->distinct()
            ->whereNotNull('brand')
            ->orderBy('brand')
            ->pluck('brand')
            ->toArray();
    }
}
