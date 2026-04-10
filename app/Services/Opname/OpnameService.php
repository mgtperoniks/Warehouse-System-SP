<?php

namespace App\Services\Opname;

use App\Models\Bin;
use App\Models\StockOpname;
use App\Models\StockOpnameItem;
use Illuminate\Support\Facades\DB;

class OpnameService
{
    /**
     * Create a new stock opname
     */
    public function createOpname(string $scopeType, int $scopeId, ?string $userId = null)
    {
        return DB::transaction(function () use ($scopeType, $scopeId, $userId) {
            $code = 'OPN-' . date('Ymd-His') . '-' . strtoupper(uniqid());

            $opname = StockOpname::create([
                'code'       => $code,
                'scope_type' => $scopeType,
                'scope_id'   => $scopeId,
                'status'     => 'DRAFT',
                'created_by' => $userId,
            ]);

            // If scope is LOCATION, find all bins in that location
            // If scope is ITEM, find all bins containing that item variant
            $bins = [];
            if ($scopeType === 'LOCATION') {
                $bins = Bin::where('location_id', $scopeId)->get();
            } elseif ($scopeType === 'ITEM') {
                $bins = Bin::where('item_variant_id', $scopeId)->get();
            }

            foreach ($bins as $bin) {
                StockOpnameItem::create([
                    'stock_opname_id' => $opname->id,
                    'bin_id'          => $bin->id,
                    'system_qty'      => $bin->current_qty,
                    'actual_qty'      => null, // To be filled during opname
                    'difference'      => null,
                ]);
            }

            return $opname;
        });
    }

    /**
     * Input actual quantity for a bin during opname
     */
    public function inputActualQuantity(int $opnameItemId, int $actualQty)
    {
        $opnameItem = StockOpnameItem::findOrFail($opnameItemId);
        
        $opnameItem->actual_qty = $actualQty;
        $opnameItem->difference = $actualQty - $opnameItem->system_qty;
        $opnameItem->save();

        return $opnameItem;
    }

    /**
     * Complete the opname (finalize status)
     */
    public function completeOpname(StockOpname $opname)
    {
        // Could trigger adjustment here based on business rules
        $opname->update(['status' => 'COMPLETED']);
        return $opname;
    }
}
