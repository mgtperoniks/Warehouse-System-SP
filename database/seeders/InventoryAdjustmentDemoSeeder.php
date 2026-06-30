<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\Bin;
use App\Models\ItemVariant;
use App\Models\InventoryAdjustment;
use App\Models\InventoryAdjustmentItem;
use App\Models\UserWarehouseAccess;

class InventoryAdjustmentDemoSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Ensure core roles
        $admin = User::firstOrCreate(
            ['email' => 'adminsp@peroniks.com'],
            [
                'name' => 'Admin Sparepart',
                'password' => bcrypt('321password'),
                'role' => 'admin',
            ]
        );

        $manager = User::firstOrCreate(
            ['email' => 'managerppic@peroniks.com'],
            [
                'name' => 'Manager PPIC',
                'password' => bcrypt('password123'),
                'role' => 'manager',
            ]
        );

        // 2. Ensure Spareparts warehouse
        $warehouse = Warehouse::firstOrCreate(
            ['code' => 'SPAREPART'],
            ['name' => 'Spareparts Warehouse', 'status' => 'ACTIVE']
        );

        // 3. Grant access to manager & admin
        foreach ([$admin, $manager] as $user) {
            UserWarehouseAccess::firstOrCreate([
                'user_id' => $user->id,
                'warehouse_id' => $warehouse->id,
            ], [
                'can_stock_in' => true,
                'can_stock_out' => true,
                'can_opname' => true,
                'can_adjust' => true,
                'can_print' => true,
                'can_view_reports' => true,
            ]);
        }

        // 4. Retrieve a valid bin & item variant
        $bin = Bin::where('warehouse_id', $warehouse->id)->first();
        if (!$bin) {
            return;
        }

        $variant = $bin->itemVariant;
        if (!$variant) {
            return;
        }

        $item = $variant->item;
        $itemName = $item ? $item->name : 'Sparepart Component';

        // 5. Create a WAITING_APPROVAL adjustment header
        $header = InventoryAdjustment::firstOrCreate(
            ['adjustment_no' => 'IA-SP-20260630-001'],
            [
                'warehouse_id' => $warehouse->id,
                'operator_id' => $admin->id,
                'date' => '2026-06-30',
                'status' => 'WAITING_APPROVAL',
            ]
        );

        // 6. Create WAITING adjustment item
        InventoryAdjustmentItem::firstOrCreate(
            [
                'inventory_adjustment_id' => $header->id,
                'bin_id' => $bin->id,
                'item_variant_id' => $variant->id,
            ],
            [
                'system_qty' => $bin->current_qty,
                'physical_qty' => $bin->current_qty + 5,
                'variance' => 5,
                'reason_code' => 'FOUND_ITEM',
                'notes' => 'Found 5 extra units behind the main rack during weekly opname.',
                'status' => 'WAITING',
                'item_name_snapshot' => $itemName,
                'erp_code_snapshot' => $variant->erp_code ?: 'ERP-UNKNOWN',
                'bin_code_snapshot' => $bin->code,
                'unit_snapshot' => $variant->unit ?: 'PCS',
                'warehouse_name_snapshot' => $warehouse->name,
                'operator_name_snapshot' => $admin->name,
            ]
        );

        // Update header count cache
        InventoryAdjustment::synchronizeStatus($header->id);
    }
}
