<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class WmsWarehouseContextMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if (auth()->check()) {
            $user = auth()->user();
            $activeWarehouseId = session('active_warehouse_id');

            $isValid = false;
            if ($activeWarehouseId) {
                $isValid = $user->warehouses()->where('warehouses.id', $activeWarehouseId)->exists();
            }

            if (!$isValid) {
                $firstWarehouse = $user->warehouses()->first();
                if (!$firstWarehouse) {
                    abort(403, 'User has no mapped warehouses.');
                }

                session()->put('active_warehouse_id', $firstWarehouse->id);
                session()->put('active_warehouse_code', $firstWarehouse->code);
                session()->put('active_warehouse_name', $firstWarehouse->name);
            }
        }

        return $next($request);
    }
}
