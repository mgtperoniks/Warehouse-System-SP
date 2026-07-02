<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseFamilyAssignment;
use App\Models\Item;
use App\Models\ItemVariant;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use App\Livewire\Items\ItemList;
use App\Livewire\Items\InventoryPlanningPage;
use App\Livewire\Items\BulkImport;
use App\Services\Inventory\ItemService;

class WarehouseDomainIsolationTest extends TestCase
{
    use DatabaseTransactions;

    protected User $user;
    protected Warehouse $sparepartWh;
    protected Warehouse $rawMaterialWh;
    protected Warehouse $consumableWh;

    protected ItemVariant $sparepartItem;
    protected ItemVariant $rawMaterialItem;
    protected ItemVariant $consumableItem;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Create User
        $this->user = User::create([
            'name' => 'Isolation Admin',
            'email' => 'isolation_' . uniqid() . '@example.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
        ]);

        // 2. Fetch or Create Warehouses
        $this->sparepartWh = Warehouse::firstOrCreate(
            ['code' => 'SPAREPART'],
            ['name' => 'Sparepart Warehouse', 'status' => 'ACTIVE']
        );

        $this->rawMaterialWh = Warehouse::firstOrCreate(
            ['code' => 'RAW_MATERIAL'],
            ['name' => 'Raw Material Warehouse', 'status' => 'ACTIVE']
        );

        $this->consumableWh = Warehouse::firstOrCreate(
            ['code' => 'CONSUMABLE'],
            ['name' => 'Consumable Warehouse', 'status' => 'ACTIVE']
        );

        // Map user to all warehouses
        $this->user->warehouses()->syncWithoutDetaching([
            $this->sparepartWh->id,
            $this->rawMaterialWh->id,
            $this->consumableWh->id
        ]);

        // 3. Clear existing mappings and seed cleanly
        WarehouseFamilyAssignment::whereIn('warehouse_id', [
            $this->sparepartWh->id,
            $this->rawMaterialWh->id,
            $this->consumableWh->id
        ])->delete();

        WarehouseFamilyAssignment::create(['warehouse_id' => $this->sparepartWh->id, 'family_code' => '5']);
        WarehouseFamilyAssignment::create(['warehouse_id' => $this->sparepartWh->id, 'family_code' => '6']);
        WarehouseFamilyAssignment::create(['warehouse_id' => $this->sparepartWh->id, 'family_code' => '7']);
        WarehouseFamilyAssignment::create(['warehouse_id' => $this->rawMaterialWh->id, 'family_code' => '1']);
        WarehouseFamilyAssignment::create(['warehouse_id' => $this->consumableWh->id, 'family_code' => '2']);

        // 4. Create Parent Items
        $item1 = Item::create(['name' => 'Raw Item']);
        $item2 = Item::create(['name' => 'Consumable Item']);
        $item3 = Item::create(['name' => 'Sparepart Item']);

        // 5. Create Variants
        $this->rawMaterialItem = ItemVariant::create([
            'item_id' => $item1->id,
            'erp_code' => '1.01.001',
            'sku' => 'SKU-RAW-1',
            'unit' => 'PCS',
            'brand' => 'Brand A',
        ]);

        $this->consumableItem = ItemVariant::create([
            'item_id' => $item2->id,
            'erp_code' => '2.02.002',
            'sku' => 'SKU-CON-2',
            'unit' => 'PCS',
            'brand' => 'Brand B',
        ]);

        $this->sparepartItem = ItemVariant::create([
            'item_id' => $item3->id,
            'erp_code' => '5.05.005',
            'sku' => 'SKU-SPA-5',
            'unit' => 'PCS',
            'brand' => 'Brand C',
        ]);
    }

    public function test_sparepart_warehouse_only_lists_family_5_6_7()
    {
        $this->actingAs($this->user);
        session([
            'active_warehouse_id' => $this->sparepartWh->id,
            'active_warehouse_name' => $this->sparepartWh->name,
        ]);

        $items = ItemVariant::forActiveWarehouse()->get();

        $this->assertTrue($items->contains('id', $this->sparepartItem->id));
        $this->assertFalse($items->contains('id', $this->rawMaterialItem->id));
        $this->assertFalse($items->contains('id', $this->consumableItem->id));
    }

    public function test_raw_material_warehouse_only_lists_family_1()
    {
        $this->actingAs($this->user);
        session([
            'active_warehouse_id' => $this->rawMaterialWh->id,
            'active_warehouse_name' => $this->rawMaterialWh->name,
        ]);

        $items = ItemVariant::forActiveWarehouse()->get();

        $this->assertFalse($items->contains('id', $this->sparepartItem->id));
        $this->assertTrue($items->contains('id', $this->rawMaterialItem->id));
        $this->assertFalse($items->contains('id', $this->consumableItem->id));
    }

    public function test_consumable_warehouse_only_lists_family_2()
    {
        $this->actingAs($this->user);
        session([
            'active_warehouse_id' => $this->consumableWh->id,
            'active_warehouse_name' => $this->consumableWh->name,
        ]);

        $items = ItemVariant::forActiveWarehouse()->get();

        $this->assertFalse($items->contains('id', $this->sparepartItem->id));
        $this->assertFalse($items->contains('id', $this->rawMaterialItem->id));
        $this->assertTrue($items->contains('id', $this->consumableItem->id));
    }

    public function test_item_detail_returns_404_outside_domain()
    {
        $this->actingAs($this->user);

        // Active warehouse set to SPAREPART
        session([
            'active_warehouse_id' => $this->sparepartWh->id,
            'active_warehouse_name' => $this->sparepartWh->name,
        ]);

        // Allowed variant 5.05.005 should return 200
        $response = $this->get(route('items.show', $this->sparepartItem->id));
        $response->assertStatus(200);

        // Forbidden variant 1.01.001 should return 404
        $response = $this->get(route('items.show', $this->rawMaterialItem->id));
        $response->assertStatus(404);
    }

    public function test_bulk_import_rejects_invalid_erp_families()
    {
        $this->actingAs($this->user);
        session([
            'active_warehouse_id' => $this->consumableWh->id,
            'active_warehouse_name' => $this->consumableWh->name,
        ]);

        // We try to import:
        // Row 0: ERP 2.88.999 (Allowed in Consumable)
        // Row 1: ERP 5.11.111 (Forbidden in Consumable, family 5)
        $data = [
            [
                'Test Item Allowed',
                '2.88.999',
                'SKU-TEST-ALLOWED',
                'PCS',
                'Brand X',
                '', '', '0', '1000', '', '', 'LOCAL', 'CONSUMABLE', '30'
            ],
            [
                'Test Item Forbidden',
                '5.11.111',
                'SKU-TEST-FORBIDDEN',
                'PCS',
                'Brand Y',
                '', '', '0', '2000', '', '', 'LOCAL', 'CONSUMABLE', '30'
            ]
        ];

        Livewire::test(BulkImport::class)
            ->call('saveItems', $data)
            ->assertDispatched('importCompleted', function ($name, $params) {
                $results = $params[0] ?? [];
                return ($results['success'] ?? 0) === 1 
                    && ($results['rejected'] ?? 0) === 1 
                    && str_contains($results['details'][0]['reason'] ?? '', 'ERP Family 5')
                    && str_contains($results['details'][0]['reason'] ?? '', 'not permitted');
            });

        // Verify only the allowed one was created
        $this->assertTrue(ItemVariant::where('erp_code', '2.88.999')->exists());
        $this->assertFalse(ItemVariant::where('erp_code', '5.11.111')->exists());
    }

    public function test_item_catalog_component_respects_domain()
    {
        $this->actingAs($this->user);
        session([
            'active_warehouse_id' => $this->sparepartWh->id,
            'active_warehouse_name' => $this->sparepartWh->name,
        ]);

        Livewire::test(ItemList::class)
            ->set('search', '5.05.005')
            ->assertSee('5.05.005')
            ->set('search', '1.01.001')
            ->assertDontSee('1.01.001')
            ->set('search', '2.02.002')
            ->assertDontSee('2.02.002');
    }

    public function test_inventory_planning_component_respects_domain()
    {
        $this->actingAs($this->user);
        session([
            'active_warehouse_id' => $this->sparepartWh->id,
            'active_warehouse_name' => $this->sparepartWh->name,
        ]);

        Livewire::test(InventoryPlanningPage::class)
            ->set('search', '5.05.005')
            ->assertSee('5.05.005')
            ->set('search', '1.01.001')
            ->assertDontSee('1.01.001')
            ->set('search', '2.02.002')
            ->assertDontSee('2.02.002');
    }

    public function test_catalog_search_sort_and_pagination_respects_domain()
    {
        $this->actingAs($this->user);
        session([
            'active_warehouse_id' => $this->sparepartWh->id,
            'active_warehouse_name' => $this->sparepartWh->name,
        ]);

        // Search for allowed sparepart ERP code -> matches
        Livewire::test(ItemList::class)
            ->set('search', '5.05.005')
            ->assertSee('5.05.005');

        // Search for forbidden ERP code (which will match raw item if unrestricted, but here should return nothing)
        Livewire::test(ItemList::class)
            ->set('search', '1.01.001')
            ->assertDontSee('1.01.001')
            ->assertDontSee('5.05.005');

        // Verify sorting on name is operational
        Livewire::test(ItemList::class)
            ->set('search', '5.05.005')
            ->call('sortBy', 'name')
            ->assertSee('5.05.005');
    }
}
