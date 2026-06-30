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
    public function moveStock(Bin $bin, int $qty, string $type, ?string $reference = null, ?string $userId = null, ?int $supplierId = null)
    {
        return DB::transaction(function () use ($bin, $qty, $type, $reference, $userId, $supplierId) {
            // Lock the specific bin row for update to prevent race conditions (Safe Stock Update)
            $lockedBin = Bin::where('id', $bin->id)->lockForUpdate()->first();
            
            if (!$lockedBin) {
                throw new \Exception("Bin ID {$bin->id} not found during transaction processing.");
            }

            $activeWarehouseId = session()->get('active_warehouse_id');
            $terminalId = session()->get('wms_terminal_id') ?: 'SPAREPART-DESK-A';
            $sessionToken = session()->getId();

            // Create movement record
            $movement = StockMovement::create([
                'item_variant_id'       => $lockedBin->item_variant_id,
                'bin_id'                => $lockedBin->id,
                'supplier_id'           => $supplierId,
                'type'                  => $type,
                'qty'                   => $qty,
                'reference'             => $reference,
                'created_by'            => $userId,
                'warehouse_id'          => $activeWarehouseId ?: $lockedBin->warehouse_id,
                'operator_id'           => $userId ?: auth()->id(),
                'terminal_id'           => $terminalId,
                'terminal_session_id'   => $sessionToken,
            ]);

            // Update bin quantity
            if ($type === 'IN') {
                $lockedBin->current_qty += $qty;
            } elseif ($type === 'OUT') {
                if ($lockedBin->current_qty < $qty) {
                    throw new \Exception("Insufficient stock in bin {$lockedBin->code}. Current qty: {$lockedBin->current_qty}, Requested: {$qty}");
                }
                $lockedBin->current_qty -= $qty;
            } elseif ($type === 'ADJUSTMENT' || $type === 'REVERSAL') {
                // For adjustments or reversals, qty can be negative or positive
                $lockedBin->current_qty += $qty;
            }

            $lockedBin->save();

            return $movement;
        });
    }

    /**
     * Handle stock movement without a physical bin (e.g. inbound routing to unassigned locations)
     */
    public function moveStockWithoutBin(int $itemVariantId, int $qty, string $type, ?string $reference = null, ?string $userId = null, ?int $supplierId = null)
    {
        return DB::transaction(function () use ($itemVariantId, $qty, $type, $reference, $userId, $supplierId) {
            $activeWarehouseId = session()->get('active_warehouse_id');
            $terminalId = session()->get('wms_terminal_id') ?: 'SPAREPART-DESK-A';
            $sessionToken = session()->getId();

            $movement = StockMovement::create([
                'item_variant_id'       => $itemVariantId,
                'bin_id'                => null,
                'supplier_id'           => $supplierId,
                'type'                  => $type,
                'qty'                   => $qty,
                'reference'             => $reference,
                'created_by'            => $userId,
                'warehouse_id'          => $activeWarehouseId ?: 1, // Fallback default
                'operator_id'           => $userId ?: auth()->id(),
                'terminal_id'           => $terminalId,
                'terminal_session_id'   => $sessionToken,
            ]);

            return $movement;
        });
    }

    /**
     * Execute compensating reversal transaction (One-Click Reversal Workflow)
     */
    public function executeReversal(int $movementId, ?string $reason = 'Operator Compensating Reversal')
    {
        return DB::transaction(function () use ($movementId, $reason) {
            $original = StockMovement::findOrFail($movementId);

            // Prevent double-reversals
            $alreadyReversed = StockMovement::where('linked_transaction_id', $original->id)->exists();
            if ($alreadyReversed) {
                throw new \Exception("Operational Error: This transaction has already been reversed.");
            }

            // Enforce bin availability
            $bin = Bin::where('id', $original->bin_id)->lockForUpdate()->first();
            if (!$bin) {
                throw new \Exception("Target bin not found for transaction compensation.");
            }

            // Calculate compensating quantity
            $reversalQty = 0;
            if ($original->type === 'OUT' || ($original->type === 'REVERSAL' && $original->qty < 0)) {
                // Original deducted stock, so reversal adds it back
                $reversalQty = abs($original->qty);
                $bin->current_qty += $reversalQty;
            } else {
                // Original added stock, so reversal deducts it
                $reversalQty = -abs($original->qty);
                if ($bin->current_qty < abs($reversalQty)) {
                    throw new \Exception("Audit Failure: Cannot reverse because target bin has insufficient stock.");
                }
                $bin->current_qty -= abs($reversalQty);
            }

            // Create compensating movement entry
            $reversalMovement = StockMovement::create([
                'item_variant_id'       => $original->item_variant_id,
                'bin_id'                => $original->bin_id,
                'supplier_id'           => $original->supplier_id,
                'type'                  => 'REVERSAL',
                'qty'                   => $reversalQty,
                'reference'             => "Reversal of #{$original->id}: " . $reason,
                'created_by'            => auth()->id(),
                'warehouse_id'          => $original->warehouse_id,
                'operator_id'           => auth()->id(),
                'terminal_id'           => session()->get('wms_terminal_id') ?: 'SPAREPART-DESK-A',
                'terminal_session_id'   => session()->getId(),
                'linked_transaction_id' => $original->id,
            ]);

            $bin->save();

            return $reversalMovement;
        });
    }

    /**
     * Helper for Stock IN
     */
    public function stockIn(Bin $bin, int $qty, ?string $reference = null, ?string $userId = null, ?int $supplierId = null)
    {
        if ($qty <= 0) {
            throw new \Exception("Quantity must be greater than zero for Stock IN.");
        }
        return $this->moveStock($bin, $qty, 'IN', $reference, $userId, $supplierId);
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
