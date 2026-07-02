<?php

namespace App\Http\Controllers;

use App\Models\Warehouse;
use App\Models\StockInReceipt;
use Illuminate\Http\Request;

class WarehouseSwitchController extends Controller
{
    public function switchWarehouse(Request $request, $id)
    {
        $user = auth()->user();
        if (!$user) {
            abort(403, 'Unauthenticated.');
        }

        $mappedWarehouses = $user->warehouses;
        $count = $mappedWarehouses->count();

        if ($count === 0) {
            abort(403, 'User has no mapped warehouses.');
        }

        if ($count === 1) {
            $onlyWarehouse = $mappedWarehouses->first();
            if ($id != $onlyWarehouse->id) {
                abort(403, 'Unauthorized warehouse switch.');
            }
            return redirect()->back()->with('success', "Switched active warehouse to {$onlyWarehouse->name}.");
        }

        $hasAccess = $user->warehouses()->whereKey($id)->exists();
        if (!$hasAccess) {
            abort(403, 'Unauthorized warehouse switch.');
        }

        $warehouse = Warehouse::findOrFail($id);

        // 1. Clear Active Stock Out Session Carts
        session()->forget('scan_cart');

        // 2. Abandon Active Inbound Draft Receipts to avoid contamination
        \App\Models\StockInReceipt::where('user_id', $user->id)
            ->where('status', 'ACTIVE')
            ->update(['status' => 'ABANDONED']);

        // 3. Save Active Tenant Context inside Session State
        session()->put('active_warehouse_id', $warehouse->id);
        session()->put('active_warehouse_code', $warehouse->code);
        session()->put('active_warehouse_name', $warehouse->name);

        return redirect()->back()->with('success', "Switched active warehouse to {$warehouse->name}.");
    }
}
