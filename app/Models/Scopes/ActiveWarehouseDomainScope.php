<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use App\Services\Inventory\WarehouseDomainService;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Schema;

class ActiveWarehouseDomainScope implements Scope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     */
    public function apply(Builder $builder, Model $model): void
    {
        // Skip filtering under CLI/Artisan/Seeding environments if context isn't fully available
        if (App::runningInConsole()) {
            if (!auth()->check() || !session()->has('active_warehouse_id')) {
                return;
            }
        }

        // Avoid breaking migrations or seeders before mappings table exists
        if (!Schema::hasTable('warehouse_family_assignments') || !Schema::hasTable('warehouses')) {
            return;
        }

        $authId = auth()->id();
        $activeWarehouseId = session('active_warehouse_id');

        if (!$authId || !$activeWarehouseId) {
            return;
        }

        app(WarehouseDomainService::class)->applyDomainFilter($builder);
    }
}
