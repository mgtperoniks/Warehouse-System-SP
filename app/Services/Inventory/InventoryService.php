<?php

namespace App\Services\Inventory;

use App\Models\Bin;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;

class InventoryService
{
    /**
     * Handle stock movement (IN/OUT/ADJUSTMENT)
     */
    public function moveStock(Bin $bin, int $qty, string $type, ?string $reference = null, ?string $userId = null)
    {
        return DB::transaction(function () use ($bin, $qty, $type, $reference, $userId) {
            // Lock the specific bin row for update to prevent race conditions (Safe Stock Update)
            $lockedBin = Bin::where('id', $bin->id)->lockForUpdate()->first();
            
            if (!$lockedBin) {
                throw new \Exception("Bin ID {$bin->id} not found during transaction processing.");
            }

            // Create movement record
            $movement = StockMovement::create([
                'item_variant_id' => $lockedBin->item_variant_id,
                'bin_id'          => $lockedBin->id,
                'type'            => $type,
                'qty'             => $qty,
                'reference'       => $reference,
                'created_by'      => $userId,
            ]);

            // Update bin quantity
            if ($type === 'IN') {
                $lockedBin->current_qty += $qty;
            } elseif ($type === 'OUT') {
                if ($lockedBin->current_qty < $qty) {
                    throw new \Exception("Insufficient stock in bin {$lockedBin->code}. Current qty: {$lockedBin->current_qty}, Requested: {$qty}");
                }
                $lockedBin->current_qty -= $qty;
            } elseif ($type === 'ADJUSTMENT') {
                // For adjustments, qty can be negative or positive
                $lockedBin->current_qty += $qty;
            }

            $lockedBin->save();

            return $movement;
        });
    }

    /**
     * Helper for Stock IN
     */
    public function stockIn(Bin $bin, int $qty, ?string $reference = null, ?string $userId = null)
    {
        if ($qty <= 0) {
            throw new \Exception("Quantity must be greater than zero for Stock IN.");
        }
        return $this->moveStock($bin, $qty, 'IN', $reference, $userId);
    }

    /**
     * Helper for Stock OUT
     */
    public function stockOut(Bin $bin, int $qty, ?string $reference = null, ?string $userId = null)
    {
        if ($qty <= 0) {
            throw new \Exception("Quantity must be greater than zero for Stock OUT.");
        }
        return $this->moveStock($bin, $qty, 'OUT', $reference, $userId);
    }
}
