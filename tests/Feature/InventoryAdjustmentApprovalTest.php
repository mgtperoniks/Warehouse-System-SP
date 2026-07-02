<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\Location;
use App\Models\Bin;
use App\Models\Item;
use App\Models\ItemVariant;
use App\Models\InventoryAdjustment;
use App\Models\InventoryAdjustmentItem;
use App\Models\StockMovement;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use App\Livewire\Governance\InventoryAdjustmentsPage;

class InventoryAdjustmentApprovalTest extends TestCase
{
    use DatabaseTransactions;

    protected User $manager;
    protected User $adminUser;
    protected Warehouse $warehouse;
    protected Bin $bin;
    protected ItemVariant $variant;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Create Roles with unique emails
        $this->manager = User::create([
            'role' => 'manager',
            'name' => 'Manager PPIC ' . uniqid(),
            'email' => 'manager_' . uniqid() . '@peroniks.com',
            'password' => bcrypt('password'),
        ]);

        $this->adminUser = User::create([
            'role' => 'admin',
            'name' => 'Admin Sparepart ' . uniqid(),
            'email' => 'admin_' . uniqid() . '@peroniks.com',
            'password' => bcrypt('password'),
        ]);

        // 2. Create Warehouse and active session using firstOrCreate
        $this->warehouse = Warehouse::firstOrCreate(
            ['code' => 'SPAREPART'],
            ['name' => 'Sparepart Warehouse', 'status' => 'ACTIVE']
        );
        session(['active_warehouse_id' => $this->warehouse->id]);

        \App\Models\WarehouseFamilyAssignment::firstOrCreate([
            'warehouse_id' => $this->warehouse->id,
            'family_code' => 'ERP'
        ]);
        \App\Models\WarehouseFamilyAssignment::firstOrCreate([
            'warehouse_id' => $this->warehouse->id,
            'family_code' => 'ERP001'
        ]);

        // 3. Create Item & Variant using firstOrCreate
        $item = Item::firstOrCreate(
            ['name' => 'Test Item']
        );
        
        $this->variant = ItemVariant::firstOrCreate(
            ['sku' => 'VAR001'],
            [
                'item_id' => $item->id,
                'name' => 'Default Variant',
                'erp_code' => 'ERP001',
                'unit' => 'PCS',
            ]
        );

        // 4. Create Location
        $location = Location::firstOrCreate(
            ['code' => 'LOC-A'],
            ['description' => 'Location A']
        );

        // 5. Create Bin using firstOrCreate
        $this->bin = Bin::firstOrCreate(
            ['warehouse_id' => $this->warehouse->id, 'code' => 'BIN-A1'],
            [
                'location_id' => $location->id,
                'item_variant_id' => $this->variant->id,
                'current_qty' => 10,
                'min_qty' => 1,
                'max_qty' => 100,
            ]
        );
        
        // Ensure bin quantity starts at 10 for the test context
        $this->bin->update(['current_qty' => 10]);
    }

    protected function createAdjustmentItem($headerId, $binId, $variantId, $variance, $reason, $notes, $status)
    {
        return InventoryAdjustmentItem::create([
            'inventory_adjustment_id' => $headerId,
            'bin_id' => $binId,
            'item_variant_id' => $variantId,
            'system_qty' => 10,
            'physical_qty' => 10 + $variance,
            'variance' => $variance,
            'reason_code' => $reason,
            'notes' => $notes,
            'status' => $status,
            'item_name_snapshot' => 'Test Item',
            'erp_code_snapshot' => 'ERP001',
            'bin_code_snapshot' => 'BIN-A1',
            'unit_snapshot' => 'PCS',
            'warehouse_name_snapshot' => 'Sparepart Warehouse',
            'operator_name_snapshot' => 'Admin Operator',
        ]);
    }

    public function test_manager_can_approve_variance_and_mutate_stock()
    {
        // 1. Create a waiting adjustment header & item
        $header = InventoryAdjustment::create([
            'adjustment_no' => 'IA-SP-20260630-001-' . uniqid(),
            'warehouse_id' => $this->warehouse->id,
            'operator_id' => $this->adminUser->id,
            'date' => '2026-06-30',
            'status' => 'WAITING_APPROVAL',
        ]);

        $item = $this->createAdjustmentItem(
            $header->id,
            $this->bin->id,
            $this->variant->id,
            5,
            'FOUND_ITEM',
            'Found under bin',
            'WAITING'
        );

        // 2. Act as Manager & Approve
        $this->actingAs($this->manager);

        Livewire::test(InventoryAdjustmentsPage::class)
            ->call('approveItem', $item->id)
            ->assertHasNoErrors();

        // 3. Assert status updates
        $item->refresh();
        $header->refresh();
        $this->bin->refresh();

        $this->assertEquals('APPROVED', $item->status);
        $this->assertEquals($this->manager->id, $item->approved_by);
        $this->assertNotNull($item->approved_at);
        $this->assertEquals('COMPLETED', $header->status);

        // 4. Assert stock mutation: 10 + 5 = 15
        $this->assertEquals(15, $this->bin->current_qty);

        // 5. Assert stock movement was logged
        $this->assertDatabaseHas('stock_movements', [
            'item_variant_id' => $this->variant->id,
            'bin_id' => $this->bin->id,
            'qty' => 5,
            'type' => 'ADJUSTMENT',
            'operator_id' => $this->manager->id,
        ]);
        
        // 6. Assert variant last_opname_at updated
        $this->variant->refresh();
        $this->assertNotNull($this->variant->last_opname_at);
    }

    public function test_manager_can_reject_variance_with_reason()
    {
        $header = InventoryAdjustment::create([
            'adjustment_no' => 'IA-SP-20260630-002-' . uniqid(),
            'warehouse_id' => $this->warehouse->id,
            'operator_id' => $this->adminUser->id,
            'date' => '2026-06-30',
            'status' => 'WAITING_APPROVAL',
        ]);

        $item = $this->createAdjustmentItem(
            $header->id,
            $this->bin->id,
            $this->variant->id,
            -2,
            'DAMAGED_ITEM',
            'Damaged during forklift scan',
            'WAITING'
        );

        $this->actingAs($this->manager);

        Livewire::test(InventoryAdjustmentsPage::class)
            ->call('rejectItem', $item->id, 'Rejected due to validation mismatch')
            ->assertHasNoErrors();

        $item->refresh();
        $header->refresh();
        $this->bin->refresh();

        // 1. Assert status and details
        $this->assertEquals('REJECTED', $item->status);
        $this->assertEquals($this->manager->id, $item->rejected_by);
        $this->assertNotNull($item->rejected_at);
        $this->assertEquals('Rejected due to validation mismatch', $item->reject_reason);
        $this->assertEquals('COMPLETED', $header->status);

        // 2. Assert stock is NOT changed (remains 10)
        $this->assertEquals(10, $this->bin->current_qty);

        // 3. Assert no stock movement logged
        $this->assertDatabaseMissing('stock_movements', [
            'item_variant_id' => $this->variant->id,
            'qty' => -2,
        ]);
    }

    public function test_non_manager_cannot_approve_or_reject()
    {
        $header = InventoryAdjustment::create([
            'adjustment_no' => 'IA-SP-20260630-003-' . uniqid(),
            'warehouse_id' => $this->warehouse->id,
            'operator_id' => $this->adminUser->id,
            'date' => '2026-06-30',
            'status' => 'WAITING_APPROVAL',
        ]);

        $item = $this->createAdjustmentItem(
            $header->id,
            $this->bin->id,
            $this->variant->id,
            -5,
            'WRONG_BIN',
            '',
            'WAITING'
        );

        // Act as Admin (non-manager)
        $this->actingAs($this->adminUser);

        Livewire::test(InventoryAdjustmentsPage::class)
            ->call('approveItem', $item->id)
            ->call('rejectItem', $item->id, 'Try reject');

        $item->refresh();
        $this->assertEquals('WAITING', $item->status); // Remains WAITING
        $this->assertEquals(10, $this->bin->current_qty); // No stock change
    }

    public function test_approval_is_idempotent()
    {
        $header = InventoryAdjustment::create([
            'adjustment_no' => 'IA-SP-20260630-004-' . uniqid(),
            'warehouse_id' => $this->warehouse->id,
            'operator_id' => $this->adminUser->id,
            'date' => '2026-06-30',
            'status' => 'WAITING_APPROVAL',
        ]);

        $item = $this->createAdjustmentItem(
            $header->id,
            $this->bin->id,
            $this->variant->id,
            2,
            'FOUND_ITEM',
            '',
            'WAITING'
        );

        $this->actingAs($this->manager);

        // Perform double call simulating double click
        $lw = Livewire::test(InventoryAdjustmentsPage::class);
        $lw->call('approveItem', $item->id);
        $lw->call('approveItem', $item->id); // Repeat call

        $item->refresh();
        $this->bin->refresh();

        // 1. Bin quantity must have increased by 2 exactly once (10 + 2 = 12)
        $this->assertEquals(12, $this->bin->current_qty);

        // 2. Only one stock movement was created
        $this->assertEquals(1, StockMovement::where('item_variant_id', $this->variant->id)->count());
    }
}
