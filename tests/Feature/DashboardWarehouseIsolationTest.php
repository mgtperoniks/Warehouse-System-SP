<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseFamilyAssignment;
use App\Models\Item;
use App\Models\ItemVariant;
use App\Models\Bin;
use App\Models\Location;
use App\Models\StockTransaction;
use App\Models\StockMovement;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;

class DashboardWarehouseIsolationTest extends TestCase
{
    use DatabaseTransactions;

    protected User $user;
    protected Warehouse $sparepartWh;
    protected Warehouse $rawMaterialWh;

    protected ItemVariant $sparepartItem;
    protected ItemVariant $rawMaterialItem;

    protected Bin $sparepartBin;
    protected Bin $rawMaterialBin;

    protected StockTransaction $sparepartTx;
    protected StockTransaction $rawMaterialTx;

    protected StockMovement $sparepartMovement;
    protected StockMovement $rawMaterialMovement;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Create User
        $this->user = User::create([
            'name' => 'Dashboard Test Admin',
            'email' => 'dash_admin_' . uniqid() . '@example.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
        ]);

        // 2. Create Warehouses
        $this->sparepartWh = Warehouse::firstOrCreate(
            ['code' => 'SPAREPART'],
            ['name' => 'Spareparts Warehouse', 'status' => 'ACTIVE']
        );

        $this->rawMaterialWh = Warehouse::firstOrCreate(
            ['code' => 'RAW_MATERIAL'],
            ['name' => 'Raw Materials Warehouse', 'status' => 'ACTIVE']
        );

        $this->user->warehouses()->syncWithoutDetaching([
            $this->sparepartWh->id,
            $this->rawMaterialWh->id,
        ]);

        // 3. Setup Family Assignments
        WarehouseFamilyAssignment::whereIn('warehouse_id', [
            $this->sparepartWh->id,
            $this->rawMaterialWh->id,
        ])->delete();

        WarehouseFamilyAssignment::create(['warehouse_id' => $this->sparepartWh->id, 'family_code' => '5']);
        WarehouseFamilyAssignment::create(['warehouse_id' => $this->rawMaterialWh->id, 'family_code' => '1']);

        // 4. Create Items & Variants
        $itemSp = Item::create(['name' => 'Sparepart Item']);
        $itemRaw = Item::create(['name' => 'Raw Material Item']);

        $this->sparepartItem = ItemVariant::create([
            'item_id' => $itemSp->id,
            'erp_code' => '5.01.001',
            'sku' => 'SKU-SP-5',
            'unit' => 'PCS',
            'brand' => 'SP-Brand',
        ]);

        $this->rawMaterialItem = ItemVariant::create([
            'item_id' => $itemRaw->id,
            'erp_code' => '1.01.001',
            'sku' => 'SKU-RAW-1',
            'unit' => 'PCS',
            'brand' => 'RM-Brand',
        ]);

        // 5. Create Locations & Bins
        $locSp = Location::create(['code' => 'LOC-SP-01', 'name' => 'Loc SP 01']);
        $locRaw = Location::create(['code' => 'LOC-RM-01', 'name' => 'Loc RM 01']);

        // Sparepart Bin (Low stock, qty 5 < min 10)
        $this->sparepartBin = Bin::create([
            'location_id' => $locSp->id,
            'item_variant_id' => $this->sparepartItem->id,
            'code' => 'BIN-SP-LOW',
            'current_qty' => 5,
            'min_qty' => 10,
            'warehouse_id' => $this->sparepartWh->id,
        ]);

        Bin::create([
            'location_id' => $locSp->id,
            'item_variant_id' => $this->sparepartItem->id,
            'code' => 'BIN-SP-HIGH',
            'current_qty' => 999999,
            'min_qty' => 10,
            'warehouse_id' => $this->sparepartWh->id,
        ]);

        // Raw Material Bin (Out of stock, qty 0 < min 20)
        $this->rawMaterialBin = Bin::create([
            'location_id' => $locRaw->id,
            'item_variant_id' => $this->rawMaterialItem->id,
            'code' => 'BIN-RM-OUT',
            'current_qty' => 0,
            'min_qty' => 20,
            'warehouse_id' => $this->rawMaterialWh->id,
        ]);

        Bin::create([
            'location_id' => $locRaw->id,
            'item_variant_id' => $this->rawMaterialItem->id,
            'code' => 'BIN-RM-HIGH',
            'current_qty' => 999999,
            'min_qty' => 20,
            'warehouse_id' => $this->rawMaterialWh->id,
        ]);

        // 6. Create Stock Transactions (with today's date)
        $this->sparepartTx = StockTransaction::create([
            'code' => 'TX-SP-001',
            'type' => 'IN',
            'status' => 'CONFIRMED',
            'warehouse_id' => $this->sparepartWh->id,
        ]);

        $this->rawMaterialTx = StockTransaction::create([
            'code' => 'TX-RM-001',
            'type' => 'OUT',
            'status' => 'CONFIRMED',
            'warehouse_id' => $this->rawMaterialWh->id,
        ]);

        // 7. Create Stock Movements (with today's date)
        $this->sparepartMovement = StockMovement::create([
            'item_variant_id' => $this->sparepartItem->id,
            'warehouse_id' => $this->sparepartWh->id,
            'type' => 'IN',
            'qty' => 10,
            'balance_after' => 10,
            'created_at' => Carbon::now(),
        ]);

        $this->rawMaterialMovement = StockMovement::create([
            'item_variant_id' => $this->rawMaterialItem->id,
            'warehouse_id' => $this->rawMaterialWh->id,
            'type' => 'OUT',
            'qty' => 5,
            'balance_after' => 0,
            'created_at' => Carbon::now(),
        ]);
    }

    private function setSessionActiveWarehouse(Warehouse $warehouse)
    {
        session()->put('active_warehouse_id', $warehouse->id);
        session()->put('active_warehouse_code', $warehouse->code);
        session()->put('active_warehouse_name', $warehouse->name);
    }

    /**
     * Test all dashboard widgets in Spareparts Warehouse operational context
     */
    public function test_dashboard_widgets_reflect_spareparts_warehouse_context(): void
    {
        $this->actingAs($this->user);
        $this->setSessionActiveWarehouse($this->sparepartWh);

        $response = $this->get(route('dashboard'));
        $response->assertStatus(200);

        // Calculate expected values based on current DB state
        $expectedItems = ItemVariant::withoutGlobalScopes()
            ->where('erp_code', 'like', '5.%')
            ->count();

        $expectedTx = StockTransaction::where('warehouse_id', $this->sparepartWh->id)
            ->whereDate('created_at', today())
            ->count();

        $expectedLowStock = Bin::where('warehouse_id', $this->sparepartWh->id)
            ->where('min_qty', '>', 0)
            ->whereColumn('current_qty', '<=', 'min_qty')
            ->where('current_qty', '>', 0)
            ->count();

        $expectedOutOfStock = Bin::where('warehouse_id', $this->sparepartWh->id)
            ->where('current_qty', '<=', 0)
            ->count();

        // 1. KPI Cards
        $response->assertViewHas('totalItems', $expectedItems);
        $response->assertViewHas('todayTx', $expectedTx);
        $response->assertViewHas('lowStockCount', $expectedLowStock);
        $response->assertViewHas('outOfStockCount', $expectedOutOfStock);

        // 2. Recent Transactions
        $recentTransactions = $response->viewData('recentTransactions');
        $this->assertTrue($recentTransactions->every(fn($tx) => $tx->warehouse_id === $this->sparepartWh->id));

        // 3. Stock Alerts
        $criticalAlerts = $response->viewData('criticalAlerts');
        $this->assertTrue($criticalAlerts->every(fn($bin) => $bin->warehouse_id === $this->sparepartWh->id && $bin->current_qty <= 0));

        $lowStockAlerts = $response->viewData('lowStockAlerts');
        $this->assertTrue($lowStockAlerts->every(fn($bin) => $bin->warehouse_id === $this->sparepartWh->id && $bin->current_qty > 0 && $bin->current_qty <= $bin->min_qty));

        // 4. Brand Distribution
        $donutLabels = $response->viewData('donutLabels');
        $this->assertContains('SP-Brand', $donutLabels);
        $this->assertNotContains('RM-Brand', $donutLabels);

        // 5. Stock Movement Chart & Summary
        $expectedStockIn = StockMovement::where('warehouse_id', $this->sparepartWh->id)
            ->whereDate('created_at', today())
            ->where('type', 'IN')
            ->sum('qty');
        $expectedStockOut = StockMovement::where('warehouse_id', $this->sparepartWh->id)
            ->whereDate('created_at', today())
            ->where('type', 'OUT')
            ->sum('qty');

        $response->assertViewHas('todayStockIn', $expectedStockIn);
        $response->assertViewHas('todayStockOut', $expectedStockOut);
        
        $chartStockIn = $response->viewData('chartStockIn');
        $chartStockOut = $response->viewData('chartStockOut');
        $this->assertEquals($expectedStockIn, end($chartStockIn));
        $this->assertEquals($expectedStockOut, end($chartStockOut));

        // 6. Header Label
        $response->assertSee('Spareparts Warehouse');
    }

    /**
     * Test all dashboard widgets in Raw Materials Warehouse operational context
     */
    public function test_dashboard_widgets_reflect_raw_materials_warehouse_context(): void
    {
        $this->actingAs($this->user);
        $this->setSessionActiveWarehouse($this->rawMaterialWh);

        $response = $this->get(route('dashboard'));
        $response->assertStatus(200);

        // Calculate expected values based on current DB state
        $expectedItems = ItemVariant::withoutGlobalScopes()
            ->where('erp_code', 'like', '1.%')
            ->count();

        $expectedTx = StockTransaction::where('warehouse_id', $this->rawMaterialWh->id)
            ->whereDate('created_at', today())
            ->count();

        $expectedLowStock = Bin::where('warehouse_id', $this->rawMaterialWh->id)
            ->where('min_qty', '>', 0)
            ->whereColumn('current_qty', '<=', 'min_qty')
            ->where('current_qty', '>', 0)
            ->count();

        $expectedOutOfStock = Bin::where('warehouse_id', $this->rawMaterialWh->id)
            ->where('current_qty', '<=', 0)
            ->count();

        // 1. KPI Cards
        $response->assertViewHas('totalItems', $expectedItems);
        $response->assertViewHas('todayTx', $expectedTx);
        $response->assertViewHas('lowStockCount', $expectedLowStock);
        $response->assertViewHas('outOfStockCount', $expectedOutOfStock);

        // 2. Recent Transactions
        $recentTransactions = $response->viewData('recentTransactions');
        $this->assertTrue($recentTransactions->every(fn($tx) => $tx->warehouse_id === $this->rawMaterialWh->id));

        // 3. Stock Alerts
        $criticalAlerts = $response->viewData('criticalAlerts');
        $this->assertTrue($criticalAlerts->every(fn($bin) => $bin->warehouse_id === $this->rawMaterialWh->id && $bin->current_qty <= 0));

        $lowStockAlerts = $response->viewData('lowStockAlerts');
        $this->assertTrue($lowStockAlerts->every(fn($bin) => $bin->warehouse_id === $this->rawMaterialWh->id && $bin->current_qty > 0 && $bin->current_qty <= $bin->min_qty));

        // 4. Brand Distribution
        $donutLabels = $response->viewData('donutLabels');
        $this->assertContains('RM-Brand', $donutLabels);
        $this->assertNotContains('SP-Brand', $donutLabels);

        // 5. Stock Movement Chart & Summary
        $expectedStockIn = StockMovement::where('warehouse_id', $this->rawMaterialWh->id)
            ->whereDate('created_at', today())
            ->where('type', 'IN')
            ->sum('qty');
        $expectedStockOut = StockMovement::where('warehouse_id', $this->rawMaterialWh->id)
            ->whereDate('created_at', today())
            ->where('type', 'OUT')
            ->sum('qty');

        $response->assertViewHas('todayStockIn', $expectedStockIn);
        $response->assertViewHas('todayStockOut', $expectedStockOut);

        $chartStockIn = $response->viewData('chartStockIn');
        $chartStockOut = $response->viewData('chartStockOut');
        $this->assertEquals($expectedStockIn, end($chartStockIn));
        $this->assertEquals($expectedStockOut, end($chartStockOut));

        // 6. Header Label
        $response->assertSee('Raw Materials Warehouse');
    }
}
