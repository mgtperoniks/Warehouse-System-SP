<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Set the currently logged in user for the application and auto-populate active warehouse session.
     */
    public function actingAs(\Illuminate\Contracts\Auth\Authenticatable $user, $driver = null)
    {
        $res = parent::actingAs($user, $driver);

        if (method_exists($user, 'warehouses')) {
            $warehouse = $user->warehouses()->first();
            if ($warehouse) {
                session([
                    'active_warehouse_id' => $warehouse->id,
                    'active_warehouse_code' => $warehouse->code,
                    'active_warehouse_name' => $warehouse->name,
                ]);
            }
        }

        return $res;
    }
}
