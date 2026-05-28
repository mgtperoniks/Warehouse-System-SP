<?php

namespace App\Services\Reports;

use App\Models\StockMovement;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

class MovementLedgerService
{
    /**
     * Compute starting balance before a given start date.
     */
    public function getStartingBalance(int $variantId, string $startDate): int
    {
        return (int) StockMovement::forActiveWarehouse()
            ->where('item_variant_id', $variantId)
            ->where('created_at', '<', $startDate . ' 00:00:00')
            ->sum('qty');
    }

    /**
     * Build base ledger query.
     */
    public function getLedgerQuery(int $variantId, string $startDate, string $endDate, string $movementType = 'ALL'): Builder
    {
        $query = StockMovement::forActiveWarehouse()
            ->with(['bin', 'operator', 'supplier', 'transaction.department', 'transaction.user', 'receipt.user', 'receipt.supplier'])
            ->where('item_variant_id', $variantId)
            ->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59']);

        if ($movementType === 'IN') {
            $query->where('type', 'IN');
        } elseif ($movementType === 'OUT') {
            $query->where('type', 'OUT');
        } elseif ($movementType === 'ADJUSTMENT') {
            $query->whereIn('type', ['ADJUSTMENT', 'REVERSAL']);
        }

        return $query->orderBy('id', 'asc');
    }

    /**
     * Get paginated ledger movements with calculated running balances.
     */
    public function getPaginatedLedger(int $variantId, string $startDate, string $endDate, string $movementType = 'ALL', int $perPage = 100): LengthAwarePaginator
    {
        $query = $this->getLedgerQuery($variantId, $startDate, $endDate, $movementType);
        
        $paginator = $query->paginate($perPage);

        if ($paginator->isEmpty()) {
            return $paginator;
        }

        // Get the first record ID of the current page to dynamically calculate offset starting balance
        $firstRecordId = $paginator->first()->id;

        // Dynamic chronological running balance offset lookup
        $startingBalance = (int) StockMovement::forActiveWarehouse()
            ->where('item_variant_id', $variantId)
            ->where('id', '<', $firstRecordId)
            ->sum('qty');

        $runningBalance = $startingBalance;
        foreach ($paginator->items() as $item) {
            $runningBalance += $item->qty;
            $item->running_balance = $runningBalance;
        }

        return $paginator;
    }
}
