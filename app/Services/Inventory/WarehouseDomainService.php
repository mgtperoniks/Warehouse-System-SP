<?php

namespace App\Services\Inventory;

use App\Models\Warehouse;

class WarehouseDomainService
{
    /**
     * Get allowed family codes for the active warehouse context.
     *
     * @return array
     */
    public function getAllowedFamilies(): array
    {
        $activeWarehouseId = session('active_warehouse_id');
        if (!$activeWarehouseId) {
            return [];
        }

        $warehouse = Warehouse::find($activeWarehouseId);
        if (!$warehouse) {
            return [];
        }

        return $warehouse->allowedFamilyCodes();
    }

    /**
     * Extract the ERP family code from an ERP code.
     * The ERP family is always derived from the first segment before the first dot.
     *
     * @param string|null $erpCode
     * @return string
     */
    public function extractFamily(?string $erpCode): string
    {
        if (empty($erpCode)) {
            return '';
        }

        $parts = explode('.', $erpCode);
        return trim($parts[0]);
    }

    /**
     * Check if a given family code belongs to the active warehouse domain.
     *
     * @param string $familyCode
     * @return bool
     */
    public function belongsToActiveWarehouse(string $familyCode): bool
    {
        if (empty($familyCode)) {
            return false;
        }

        return in_array($familyCode, $this->getAllowedFamilies(), true);
    }

    /**
     * Apply domain family filtering on a query.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function applyDomainFilter($query)
    {
        $allowedFamilies = $this->getAllowedFamilies();

        if (empty($allowedFamilies)) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function ($q) use ($allowedFamilies) {
            $table = $q->getModel()->getTable();
            foreach ($allowedFamilies as $family) {
                $q->orWhere("{$table}.erp_code", $family)
                  ->orWhere("{$table}.erp_code", 'like', $family . '.%');
            }
        });
    }
}

