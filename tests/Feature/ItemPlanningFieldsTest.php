<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Item;
use App\Models\ItemVariant;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use App\Livewire\Items\ItemForm;
use App\Livewire\Items\InventoryPlanningPage;

class ItemPlanningFieldsTest extends TestCase
{
    use DatabaseTransactions;

    protected User $adminUser;
    protected Warehouse $warehouse;
    protected Item $item;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminUser = User::create([
            'role' => 'admin',
            'name' => 'Admin Test User',
            'email' => 'admin_test_' . uniqid() . '@peroniks.com',
            'password' => bcrypt('password'),
        ]);

        $this->warehouse = Warehouse::firstOrCreate(
            ['code' => 'SPAREPART'],
            ['name' => 'Sparepart Warehouse', 'status' => 'ACTIVE']
        );
        session(['active_warehouse_id' => $this->warehouse->id]);

        $this->item = Item::create(['name' => 'Planning Test Item']);
    }

    public function test_can_create_item_with_planning_fields()
    {
        $this->actingAs($this->adminUser);

        Livewire::test(ItemForm::class, ['mode' => 'create'])
            ->set('name', 'Planning New Item')
            ->set('sku', 'SKU-PLAN-123')
            ->set('erp_code', '5.PLAN-123')
            ->set('unit', 'PCS')
            ->set('procurement_type', 'IMPORT')
            ->set('inventory_class', 'SPAREPART')
            ->set('lead_time_days', 45)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('item_variants', [
            'sku' => 'SKU-PLAN-123',
            'erp_code' => '5.PLAN-123',
            'procurement_type' => 'IMPORT',
            'inventory_class' => 'SPAREPART',
            'lead_time_days' => 45,
        ]);
    }

    public function test_can_edit_existing_item_planning_fields()
    {
        $variant = ItemVariant::create([
            'item_id' => $this->item->id,
            'sku' => 'SKU-EDIT-789',
            'erp_code' => '5.EDIT-789',
            'unit' => 'PCS',
            'procurement_type' => 'LOCAL',
            'inventory_class' => 'CONSUMABLE',
            'lead_time_days' => 30,
        ]);

        $this->actingAs($this->adminUser);

        Livewire::test(ItemForm::class, ['mode' => 'edit', 'variant' => $variant])
            ->assertSet('procurement_type', 'LOCAL')
            ->assertSet('inventory_class', 'CONSUMABLE')
            ->assertSet('lead_time_days', 30)
            ->set('procurement_type', 'IMPORT')
            ->set('inventory_class', 'SPAREPART')
            ->set('lead_time_days', 90)
            ->call('save')
            ->assertHasNoErrors();

        $variant->refresh();
        $this->assertEquals('IMPORT', $variant->procurement_type);
        $this->assertEquals('SPAREPART', $variant->inventory_class);
        $this->assertEquals(90, $variant->lead_time_days);
    }

    public function test_validation_fails_for_invalid_planning_fields()
    {
        $this->actingAs($this->adminUser);

        Livewire::test(ItemForm::class, ['mode' => 'create'])
            ->set('procurement_type', 'INVALID_TYPE')
            ->set('inventory_class', 'INVALID_CLASS')
            ->set('lead_time_days', -5)
            ->call('save')
            ->assertHasErrors([
                'procurement_type' => 'in',
                'inventory_class' => 'in',
                'lead_time_days' => 'min',
            ]);
    }

    public function test_planning_dashboard_lists_items_and_supports_inline_edits()
    {
        $variant = ItemVariant::create([
            'item_id' => $this->item->id,
            'sku' => 'SKU-INLINE-456',
            'erp_code' => '5.INLINE-456',
            'unit' => 'PCS',
            'procurement_type' => 'LOCAL',
            'inventory_class' => 'CONSUMABLE',
            'lead_time_days' => 30,
        ]);

        $this->actingAs($this->adminUser);

        Livewire::test(InventoryPlanningPage::class)
            ->set('search', '5.INLINE-456')
            ->assertSee('5.INLINE-456')
            ->call('updatePlanning', $variant->id, 'procurement_type', 'IMPORT')
            ->call('updatePlanning', $variant->id, 'inventory_class', 'SPAREPART')
            ->call('updatePlanning', $variant->id, 'lead_time_days', 60)
            ->assertHasNoErrors();

        $variant->refresh();
        $this->assertEquals('IMPORT', $variant->procurement_type);
        $this->assertEquals('SPAREPART', $variant->inventory_class);
        $this->assertEquals(60, $variant->lead_time_days);
    }

    public function test_bulk_import_saves_planning_fields()
    {
        $this->actingAs($this->adminUser);

        // Name(0), ERP(1), SKU(2), Unit(3), Brand(4), Supplier(5), Bin(6), Stock(7), Price(8), Desc(9), Barcode(10), Procurement(11), Class(12), Lead Time(13)
        $data = [
            [
                'Import Item 1',
                '5.IMP-001',
                'SKU-IMP-001',
                'PCS',
                'Brand A',
                'Supplier A',
                'BIN-IMP-1',
                '50',
                '100000',
                'Description A',
                '1234567890',
                'IMPORT',
                'SPAREPART',
                '45'
            ]
        ];

        Livewire::test(\App\Livewire\Items\BulkImport::class)
            ->call('saveItems', $data)
            ->assertHasNoErrors();

        $variant = ItemVariant::where('erp_code', '5.IMP-001')->firstOrFail();
        $this->assertEquals('IMPORT', $variant->procurement_type);
        $this->assertEquals('SPAREPART', $variant->inventory_class);
        $this->assertEquals(45, $variant->lead_time_days);
    }
}
