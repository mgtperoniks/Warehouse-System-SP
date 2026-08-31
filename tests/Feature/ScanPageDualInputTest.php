<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseFamilyAssignment;
use App\Models\Department;
use App\Models\Item;
use App\Models\ItemVariant;
use App\Models\ItemBarcode;
use App\Models\Bin;
use App\Models\StockTransaction;
use App\Models\StockMovement;
use App\Livewire\Scan\ScanPage;
use Livewire\Livewire;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class ScanPageDualInputTest extends TestCase
{
    use DatabaseTransactions;

    protected User $user;
    protected Warehouse $sparepartWh;
    protected Warehouse $consumableWh;
    protected Department $department;
    protected User $picUser;

    protected ItemVariant $sparepartVariant;
    protected ItemVariant $consumableVariantA;
    protected ItemVariant $consumableVariantB;

    protected Bin $sparepartBin;
    protected Bin $consumableBinA;
    protected Bin $consumableBinB;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Create Operator User
        $this->user = User::create([
            'name' => 'Scan Operator',
            'email' => 'scan_op_' . uniqid() . '@example.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
        ]);

        // 2. Create Warehouses
        $this->sparepartWh = Warehouse::firstOrCreate(
            ['code' => 'SPAREPART'],
            ['name' => 'Sparepart Warehouse', 'status' => 'ACTIVE']
        );

        $this->consumableWh = Warehouse::firstOrCreate(
            ['code' => 'CONSUMABLE'],
            ['name' => 'Consumable Warehouse', 'status' => 'ACTIVE']
        );

        // Map operator to both warehouses
        $this->user->warehouses()->syncWithoutDetaching([
            $this->sparepartWh->id,
            $this->consumableWh->id,
        ]);

        // 3. Configure Family Assignments for Domain Isolation
        WarehouseFamilyAssignment::whereIn('warehouse_id', [
            $this->sparepartWh->id,
            $this->consumableWh->id
        ])->delete();

        WarehouseFamilyAssignment::create(['warehouse_id' => $this->sparepartWh->id, 'family_code' => '5']);
        WarehouseFamilyAssignment::create(['warehouse_id' => $this->sparepartWh->id, 'family_code' => '6']);
        WarehouseFamilyAssignment::create(['warehouse_id' => $this->sparepartWh->id, 'family_code' => '7']);
        WarehouseFamilyAssignment::create(['warehouse_id' => $this->consumableWh->id, 'family_code' => '2']);

        // 4. Create Department & PIC
        $this->department = Department::firstOrCreate(
            ['code' => 'PROD_MNT'],
            ['name' => 'Production Maintenance', 'is_active' => true]
        );

        $this->picUser = User::create([
            'name' => 'PIC Tech Officer',
            'email' => 'pic_tech_' . uniqid() . '@example.com',
            'password' => bcrypt('password'),
            'role' => 'operator',
            'department_id' => $this->department->id,
            'is_active' => true,
        ]);

        // 5. Create Location
        $location = \App\Models\Location::firstOrCreate(
            ['code' => 'LOC-A1'],
            ['description' => 'Main Aisle 1']
        );

        // 6. Create Items & Variants
        $uid = uniqid();

        // Sparepart Item (Family 5)
        $itemSpa = Item::create(['name' => 'Bearing SKF 6204 ' . $uid]);
        $this->sparepartVariant = ItemVariant::create([
            'item_id' => $itemSpa->id,
            'erp_code' => '5.01.SKF.' . $uid,
            'sku' => 'SKU-SPA-' . $uid,
            'unit' => 'PCS',
            'price' => 50000,
        ]);
        ItemBarcode::create([
            'item_variant_id' => $this->sparepartVariant->id,
            'barcode' => 'BAR-SPA-' . $uid,
            'is_primary' => true,
        ]);
        $this->sparepartBin = Bin::create([
            'code' => 'BIN-SPA-' . $uid,
            'location_id' => $location->id,
            'item_variant_id' => $this->sparepartVariant->id,
            'warehouse_id' => $this->sparepartWh->id,
            'current_qty' => 100,
            'capacity' => 200,
        ]);

        // Consumable Item A (Family 2: AMS90)
        $itemConA = Item::create(['name' => 'AMS90 ' . $uid]);
        $this->consumableVariantA = ItemVariant::create([
            'item_id' => $itemConA->id,
            'erp_code' => '2.01.AMS90.' . $uid,
            'sku' => 'SKU-AMS90-' . $uid,
            'unit' => 'KG',
            'price' => 75000,
        ]);
        $this->consumableBinA = Bin::create([
            'code' => 'BIN-CON-A-' . $uid,
            'location_id' => $location->id,
            'item_variant_id' => $this->consumableVariantA->id,
            'warehouse_id' => $this->consumableWh->id,
            'current_qty' => 50,
            'capacity' => 100,
        ]);

        // Consumable Item B (Family 2: Nickel)
        $itemConB = Item::create(['name' => 'NICKEL ' . $uid]);
        $this->consumableVariantB = ItemVariant::create([
            'item_id' => $itemConB->id,
            'erp_code' => '2.02.NICK.' . $uid,
            'sku' => 'SKU-NICK-' . $uid,
            'unit' => 'KG',
            'price' => 120000,
        ]);
        $this->consumableBinB = Bin::create([
            'code' => 'BIN-CON-B-' . $uid,
            'location_id' => $location->id,
            'item_variant_id' => $this->consumableVariantB->id,
            'warehouse_id' => $this->consumableWh->id,
            'current_qty' => 30,
            'capacity' => 100,
        ]);
    }

    public function test_sparepart_warehouse_resolves_to_barcode_mode()
    {
        $this->actingAs($this->user);
        session([
            'active_warehouse_id' => $this->sparepartWh->id,
            'active_warehouse_code' => $this->sparepartWh->code,
        ]);

        Livewire::test(ScanPage::class)
            ->assertSet('inputMode', 'barcode')
            ->assertSee('READY TO SCAN PHYSICAL BARCODE')
            ->assertDontSee('Consumable Item Search');
    }

    public function test_consumable_warehouse_resolves_to_search_mode()
    {
        $this->actingAs($this->user);
        session([
            'active_warehouse_id' => $this->consumableWh->id,
            'active_warehouse_code' => $this->consumableWh->code,
        ]);

        Livewire::test(ScanPage::class)
            ->assertSet('inputMode', 'search')
            ->assertSee('Consumable Item Search')
            ->assertDontSee('READY TO SCAN PHYSICAL BARCODE');
    }

    public function test_consumable_search_returns_matching_variants_and_isolates_domains()
    {
        $this->actingAs($this->user);
        session([
            'active_warehouse_id' => $this->consumableWh->id,
            'active_warehouse_code' => $this->consumableWh->code,
        ]);

        // Search for "AMS"
        $test = Livewire::test(ScanPage::class)
            ->set('searchQuery', 'AMS')
            ->assertSet('searchQuery', 'AMS');

        $results = $test->get('searchResults');
        $this->assertNotEmpty($results);
        $resultIds = collect($results)->pluck('id')->toArray();

        // Must contain Consumable Item A (AMS90)
        $this->assertContains($this->consumableVariantA->id, $resultIds);

        // Must NOT contain Sparepart Item
        $this->assertNotContains($this->sparepartVariant->id, $resultIds);

        // Searching for Sparepart item name while in Consumable warehouse MUST yield 0 results
        $testSpa = Livewire::test(ScanPage::class)
            ->set('searchQuery', 'Bearing SKF')
            ->assertSet('searchResults', []);
    }

    public function test_selecting_search_result_adds_item_to_cart_with_default_qty_one()
    {
        $this->actingAs($this->user);
        session([
            'active_warehouse_id' => $this->consumableWh->id,
            'active_warehouse_code' => $this->consumableWh->code,
            'scan_cart' => [],
        ]);

        $test = Livewire::test(ScanPage::class)
            ->set('searchQuery', 'AMS')
            ->call('selectSearchResult', $this->consumableVariantA->id)
            ->assertSet('searchQuery', '')
            ->assertSet('searchResults', []);

        $cart = $test->get('cart');
        $this->assertCount(1, $cart);
        $this->assertEquals($this->consumableVariantA->id, $cart[0]['item_variant_id']);
        $this->assertEquals(1, $cart[0]['qty']);
        $this->assertEquals($this->consumableVariantA->erp_code, $cart[0]['erp_code']);
        $this->assertEquals($this->consumableVariantA->unit, $cart[0]['unit']);
        $this->assertEquals($this->consumableVariantA->price, $cart[0]['price']);
    }

    public function test_duplicate_search_selection_merges_qty_in_cart()
    {
        $this->actingAs($this->user);
        session([
            'active_warehouse_id' => $this->consumableWh->id,
            'active_warehouse_code' => $this->consumableWh->code,
            'scan_cart' => [],
        ]);

        $test = Livewire::test(ScanPage::class)
            ->call('selectSearchResult', $this->consumableVariantA->id, 5)
            ->call('selectSearchResult', $this->consumableVariantA->id, 3);

        $cart = $test->get('cart');
        $this->assertCount(1, $cart);
        $this->assertEquals(8, $cart[0]['qty']);
    }

    public function test_multiple_consumable_items_can_be_added_and_quantities_adjusted()
    {
        $this->actingAs($this->user);
        session([
            'active_warehouse_id' => $this->consumableWh->id,
            'active_warehouse_code' => $this->consumableWh->code,
            'scan_cart' => [],
        ]);

        $test = Livewire::test(ScanPage::class)
            ->call('selectSearchResult', $this->consumableVariantA->id, 10)
            ->call('selectSearchResult', $this->consumableVariantB->id, 1);

        $cart = $test->get('cart');
        $this->assertCount(2, $cart);
        $this->assertEquals(10, $cart[0]['qty']);
        $this->assertEquals(1, $cart[1]['qty']);

        // Update quantity of second item to 4
        $test->call('updateCartQty', 1, 4);
        $updatedCart = $test->get('cart');
        $this->assertEquals(4, $updatedCart[1]['qty']);

        // Attempting to set negative/zero quantity clamps to 1
        $test->call('updateCartQty', 1, 0);
        $clampedCart = $test->get('cart');
        $this->assertEquals(1, $clampedCart[1]['qty']);
    }

    public function test_existing_barcode_workflow_in_sparepart_warehouse_remains_fully_operational()
    {
        $this->actingAs($this->user);
        session([
            'active_warehouse_id' => $this->sparepartWh->id,
            'active_warehouse_code' => $this->sparepartWh->code,
            'scan_cart' => [],
        ]);

        $barcode = $this->sparepartVariant->primaryBarcode->barcode;

        $test = Livewire::test(ScanPage::class)
            ->call('submitScan', $barcode, 3);

        $cart = $test->get('cart');
        $this->assertCount(1, $cart);
        $this->assertEquals($this->sparepartVariant->id, $cart[0]['item_variant_id']);
        $this->assertEquals(3, $cart[0]['qty']);
        $this->assertEquals($barcode, $cart[0]['barcode']);
    }

    public function test_consumable_search_stock_out_submission_executes_successfully()
    {
        $this->actingAs($this->user);
        session([
            'active_warehouse_id' => $this->consumableWh->id,
            'active_warehouse_code' => $this->consumableWh->code,
            'scan_cart' => [],
        ]);

        $initialStockA = $this->consumableBinA->current_qty; // 50
        $initialStockB = $this->consumableBinB->current_qty; // 30

        $test = Livewire::test(ScanPage::class)
            ->set('deptId', $this->department->id)
            ->set('picId', $this->picUser->id)
            ->set('reference', 'REQ-CONSUMABLE-001')
            ->call('selectSearchResult', $this->consumableVariantA->id, 10)
            ->call('selectSearchResult', $this->consumableVariantB->id, 2);

        $test->call('submit');

        // Check StockTransaction created
        $trx = StockTransaction::where('reference', 'REQ-CONSUMABLE-001')->first();
        $this->assertNotNull($trx);
        $this->assertEquals('OUT', $trx->type);
        $this->assertEquals('CONFIRMED', $trx->status);
        $this->assertEquals($this->consumableWh->id, $trx->warehouse_id);
        $this->assertEquals($this->department->id, $trx->department_id);
        $this->assertEquals($this->picUser->id, $trx->user_id);

        // Check Inventory deducted accurately
        $this->consumableBinA->refresh();
        $this->consumableBinB->refresh();
        $this->assertEquals($initialStockA - 10, $this->consumableBinA->current_qty);
        $this->assertEquals($initialStockB - 2, $this->consumableBinB->current_qty);

        // Check StockMovements
        $movements = StockMovement::where('reference', $trx->code)->get();
        $this->assertCount(2, $movements);
        $this->assertEquals(12, $movements->sum('qty'));

        // Cart is cleared in session
        $this->assertEmpty(session()->get('scan_cart', []));
    }

    public function test_consumable_search_by_erp_code_and_sku_works()
    {
        $this->actingAs($this->user);
        session([
            'active_warehouse_id' => $this->consumableWh->id,
            'active_warehouse_code' => $this->consumableWh->code,
        ]);

        // Search by partial ERP Code
        $testErp = Livewire::test(ScanPage::class)
            ->set('searchQuery', $this->consumableVariantA->erp_code);

        $results = $testErp->get('searchResults');
        $this->assertNotEmpty($results);
        $this->assertEquals($this->consumableVariantA->id, $results[0]['id']);

        // Search by partial SKU
        $testSku = Livewire::test(ScanPage::class)
            ->set('searchQuery', $this->consumableVariantB->sku);

        $resultsB = $testSku->get('searchResults');
        $this->assertNotEmpty($resultsB);
        $this->assertEquals($this->consumableVariantB->id, $resultsB[0]['id']);
    }

    public function test_search_mode_rejects_exceeding_available_stock()
    {
        $this->actingAs($this->user);
        session([
            'active_warehouse_id' => $this->consumableWh->id,
            'active_warehouse_code' => $this->consumableWh->code,
            'scan_cart' => [],
        ]);

        // Available stock is 50, request 60
        $test = Livewire::test(ScanPage::class)
            ->call('selectSearchResult', $this->consumableVariantA->id, 60);

        $cart = $test->get('cart');
        $this->assertEmpty($cart);
        $this->assertEquals('error', $test->get('messageType'));
        $this->assertStringContainsString('exceeds available stock', $test->get('message'));
    }

    public function test_mobile_scroll_safe_area_classes_are_rendered()
    {
        $this->actingAs($this->user);
        session([
            'active_warehouse_id' => $this->consumableWh->id,
            'active_warehouse_code' => $this->consumableWh->code,
        ]);

        Livewire::test(ScanPage::class)
            ->assertSee('pb-32 lg:pb-md', false)
            ->assertSee('lg:max-h-[calc(100vh-76px)]', false);
    }
}
