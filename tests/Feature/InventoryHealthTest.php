<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Item;
use App\Models\ItemVariant;
use App\Services\Inventory\InventoryPlanningService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class InventoryHealthTest extends TestCase
{
    use DatabaseTransactions;

    protected ItemVariant $variant;
    protected InventoryPlanningService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $item = Item::create(['name' => 'Health Test Item']);
        $this->variant = ItemVariant::create([
            'item_id' => $item->id,
            'sku' => 'SKU-HEALTH-123',
            'erp_code' => 'ERP-HEALTH-123',
            'unit' => 'PCS',
            'procurement_type' => 'LOCAL',
            'inventory_class' => 'CONSUMABLE',
            'lead_time_days' => 10,
        ]);

        $this->service = new InventoryPlanningService();
    }

    public function test_inventory_health_calculations_with_movements()
    {
        // Use a fixed date to prevent calendar drifts in tests
        Carbon::setTestNow('2026-07-01 12:00:00');

        // Create movements
        // 1. OUT on 2026-06-25 (Thursday) -> qty = 14
        DB::table('stock_movements')->insert([
            'item_variant_id' => $this->variant->id,
            'type' => 'OUT',
            'qty' => 14,
            'reference' => 'Test Out 1',
            'created_at' => '2026-06-25 10:00:00',
            'updated_at' => '2026-06-25 10:00:00',
            'warehouse_id' => 1,
            'operator_id' => 1,
            'terminal_id' => 'TEST',
            'terminal_session_id' => 'TEST',
        ]);

        // 2. OUT on 2026-06-18 (Thursday) -> qty = 21
        DB::table('stock_movements')->insert([
            'item_variant_id' => $this->variant->id,
            'type' => 'OUT',
            'qty' => 21,
            'reference' => 'Test Out 2',
            'created_at' => '2026-06-18 10:00:00',
            'updated_at' => '2026-06-18 10:00:00',
            'warehouse_id' => 1,
            'operator_id' => 1,
            'terminal_id' => 'TEST',
            'terminal_session_id' => 'TEST',
        ]);

        // 3. OUT on 2026-05-15 -> qty = 70
        DB::table('stock_movements')->insert([
            'item_variant_id' => $this->variant->id,
            'type' => 'OUT',
            'qty' => 70,
            'reference' => 'Test Out 3',
            'created_at' => '2026-05-15 10:00:00',
            'updated_at' => '2026-05-15 10:00:00',
            'warehouse_id' => 1,
            'operator_id' => 1,
            'terminal_id' => 'TEST',
            'terminal_session_id' => 'TEST',
        ]);

        // 4. OUT on 2026-02-10 -> qty = 120 (This is > 90 days ago, but < 180 days ago)
        DB::table('stock_movements')->insert([
            'item_variant_id' => $this->variant->id,
            'type' => 'OUT',
            'qty' => 120,
            'reference' => 'Test Out 4',
            'created_at' => '2026-02-10 10:00:00',
            'updated_at' => '2026-02-10 10:00:00',
            'warehouse_id' => 1,
            'operator_id' => 1,
            'terminal_id' => 'TEST',
            'terminal_session_id' => 'TEST',
        ]);

        // Let's assert the calculations
        // 90 days sub is 2026-04-02. Weeks: 2026-14 to 2026-27 (14 weeks).
        // Total OUT in last 90 days = 14 + 21 + 70 = 105.
        // Weekly average = 105 / 14 = 7.5.
        $weeklyAverage = $this->service->calculateWeeklyAverage($this->variant->id);
        $this->assertEquals(7.5, $weeklyAverage);

        // 180 days sub is 2026-01-02. Months: Jan, Feb, Mar, Apr, May, Jun, Jul (7 months).
        // Total OUT in last 180 days = 14 + 21 + 70 + 120 = 225.
        // Monthly average = 225 / 7 = 32.14...
        $monthlyAverage = $this->service->calculateMonthlyAverage($this->variant->id);
        $this->assertEqualsWithDelta(32.14, $monthlyAverage, 0.01);

        // 6 Month average = 225 / 6 = 37.5.
        $sixMonthAverage = $this->service->calculateSixMonthAverage($this->variant->id);
        $this->assertEquals(37.5, $sixMonthAverage);

        // Days Left = Stock / Weekly Average * 7.
        // Let's test with stock = 30. Days Left = 30 / 7.5 * 7 = 28.
        $daysLeft = $this->service->calculateDaysLeft(30, $weeklyAverage);
        $this->assertEquals(28.0, $daysLeft);

        // Statuses:
        // Lead Time = 10.
        // Days Left = 28.
        // 28 > 10 * 2 (20) -> Healthy.
        $this->assertEquals('Healthy', $this->service->calculateHealthStatus($daysLeft, 10));

        // Days Left = 15.
        // 10 < 15 <= 20 -> Warning.
        $this->assertEquals('Warning', $this->service->calculateHealthStatus(15.0, 10));

        // Days Left = 9.
        // 9 <= 10 -> Critical.
        $this->assertEquals('Critical', $this->service->calculateHealthStatus(9.0, 10));

        Carbon::setTestNow();
    }

    public function test_inventory_health_calculations_zero_usage()
    {
        Carbon::setTestNow('2026-07-01 12:00:00');

        $weeklyAverage = $this->service->calculateWeeklyAverage($this->variant->id);
        $this->assertEquals(0.0, $weeklyAverage);

        $daysLeft = $this->service->calculateDaysLeft(50, $weeklyAverage);
        $this->assertNull($daysLeft);

        $status = $this->service->calculateHealthStatus($daysLeft, 10);
        $this->assertEquals('Healthy', $status);

        Carbon::setTestNow();
    }

    public function test_planning_dashboard_sorting_behavior()
    {
        Carbon::setTestNow('2026-07-01 12:00:00');

        $location = \App\Models\Location::firstOrCreate(
            ['code' => 'LOC-HEALTH'],
            ['description' => 'Location Health']
        );
        $warehouse = \App\Models\Warehouse::firstOrCreate(
            ['code' => 'WH-HEALTH'],
            ['name' => 'Warehouse Health', 'status' => 'ACTIVE']
        );

        // Variant A: stock = 10, lead time = 5, weekly average = 2 (Days left = 10/2*7 = 35 days -> Status: Healthy)
        $itemA = Item::create(['name' => 'Item A']);
        $varA = ItemVariant::create([
            'item_id' => $itemA->id,
            'sku' => 'SKU-A',
            'erp_code' => 'ERP-A',
            'unit' => 'PCS',
            'procurement_type' => 'LOCAL',
            'inventory_class' => 'CONSUMABLE',
            'lead_time_days' => 5,
        ]);
        DB::table('bins')->insert([
            'item_variant_id' => $varA->id,
            'code' => 'BIN-A',
            'warehouse_id' => $warehouse->id,
            'location_id' => $location->id,
            'current_qty' => 10,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('stock_movements')->insert([
            'item_variant_id' => $varA->id,
            'type' => 'OUT',
            'qty' => 28,
            'reference' => 'Out A',
            'created_at' => '2026-06-25 10:00:00',
            'updated_at' => '2026-06-25 10:00:00',
            'warehouse_id' => $warehouse->id,
            'operator_id' => 1,
            'terminal_id' => 'TEST',
            'terminal_session_id' => 'TEST',
        ]);

        // Variant B: stock = 5, lead time = 10, weekly average = 5 (Days left = 5/5*7 = 7 days -> Status: Critical)
        $itemB = Item::create(['name' => 'Item B']);
        $varB = ItemVariant::create([
            'item_id' => $itemB->id,
            'sku' => 'SKU-B',
            'erp_code' => 'ERP-B',
            'unit' => 'PCS',
            'procurement_type' => 'IMPORT',
            'inventory_class' => 'SPAREPART',
            'lead_time_days' => 10,
        ]);
        DB::table('bins')->insert([
            'item_variant_id' => $varB->id,
            'code' => 'BIN-B',
            'warehouse_id' => $warehouse->id,
            'location_id' => $location->id,
            'current_qty' => 5,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('stock_movements')->insert([
            'item_variant_id' => $varB->id,
            'type' => 'OUT',
            'qty' => 70,
            'reference' => 'Out B',
            'created_at' => '2026-06-25 10:00:00',
            'updated_at' => '2026-06-25 10:00:00',
            'warehouse_id' => $warehouse->id,
            'operator_id' => 1,
            'terminal_id' => 'TEST',
            'terminal_session_id' => 'TEST',
        ]);

        // Variant C: stock = 20, lead time = 8, weekly average = 10 (Days left = 20/10*7 = 14 days -> Status: Warning)
        $itemC = Item::create(['name' => 'Item C']);
        $varC = ItemVariant::create([
            'item_id' => $itemC->id,
            'sku' => 'SKU-C',
            'erp_code' => 'ERP-C',
            'unit' => 'PCS',
            'procurement_type' => 'LOCAL',
            'inventory_class' => 'CONSUMABLE',
            'lead_time_days' => 8,
        ]);
        DB::table('bins')->insert([
            'item_variant_id' => $varC->id,
            'code' => 'BIN-C',
            'warehouse_id' => $warehouse->id,
            'location_id' => $location->id,
            'current_qty' => 20,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('stock_movements')->insert([
            'item_variant_id' => $varC->id,
            'type' => 'OUT',
            'qty' => 140,
            'reference' => 'Out C',
            'created_at' => '2026-06-25 10:00:00',
            'updated_at' => '2026-06-25 10:00:00',
            'warehouse_id' => $warehouse->id,
            'operator_id' => 1,
            'terminal_id' => 'TEST',
            'terminal_session_id' => 'TEST',
        ]);

        // Variant D: stock = 30, lead time = 12, no usage (weekly average = 0 -> Days Left = null -> Status: Healthy)
        $itemD = Item::create(['name' => 'Item D']);
        $varD = ItemVariant::create([
            'item_id' => $itemD->id,
            'sku' => 'SKU-D',
            'erp_code' => 'ERP-D',
            'unit' => 'PCS',
            'procurement_type' => 'IMPORT',
            'inventory_class' => 'SPAREPART',
            'lead_time_days' => 12,
        ]);
        DB::table('bins')->insert([
            'item_variant_id' => $varD->id,
            'code' => 'BIN-D',
            'warehouse_id' => $warehouse->id,
            'location_id' => $location->id,
            'current_qty' => 30,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 1. Stock sorting ASC (Expected order: $this->variant (0) -> B (5) -> A (10) -> C (20) -> D (30))
        \Livewire\Livewire::test(\App\Livewire\Items\InventoryPlanningPage::class)
            ->set('search', 'ERP-')
            ->set('sortField', 'stock')
            ->set('sortDir', 'asc')
            ->assertSeeInOrder(['ERP-HEALTH-123', 'ERP-B', 'ERP-A', 'ERP-C', 'ERP-D']);

        // Stock sorting DESC (Expected order: D (30) -> C (20) -> A (10) -> B (5) -> $this->variant (0))
        \Livewire\Livewire::test(\App\Livewire\Items\InventoryPlanningPage::class)
            ->set('search', 'ERP-')
            ->set('sortField', 'stock')
            ->set('sortDir', 'desc')
            ->assertSeeInOrder(['ERP-D', 'ERP-C', 'ERP-A', 'ERP-B', 'ERP-HEALTH-123']);

        // 2. Avg Weekly sorting ASC (Expected order: $this->variant (0) / D (0) -> A (2) -> B (5) -> C (10))
        \Livewire\Livewire::test(\App\Livewire\Items\InventoryPlanningPage::class)
            ->set('search', 'ERP-')
            ->set('sortField', 'weekly_avg')
            ->set('sortDir', 'asc')
            ->assertSeeInOrder(['ERP-A', 'ERP-B', 'ERP-C']);

        // Avg Weekly sorting DESC (Expected order: C (10) -> B (5) -> A (2) -> D (0) / $this->variant (0))
        \Livewire\Livewire::test(\App\Livewire\Items\InventoryPlanningPage::class)
            ->set('search', 'ERP-')
            ->set('sortField', 'weekly_avg')
            ->set('sortDir', 'desc')
            ->assertSeeInOrder(['ERP-C', 'ERP-B', 'ERP-A']);

        // 3. Days Left sorting: "No Usage" (D and $this->variant) must always appear LAST in both directions!
        // Days Left ASC: B (7) -> C (14) -> A (35) -> D & $this->variant (No Usage last!)
        \Livewire\Livewire::test(\App\Livewire\Items\InventoryPlanningPage::class)
            ->set('search', 'ERP-')
            ->set('sortField', 'days_left')
            ->set('sortDir', 'asc')
            ->assertSeeInOrder(['ERP-B', 'ERP-C', 'ERP-A'])
            ->assertSeeInOrder(['ERP-A', 'ERP-D']);

        // Days Left DESC: A (35) -> C (14) -> B (7) -> D & $this->variant (No Usage last!)
        \Livewire\Livewire::test(\App\Livewire\Items\InventoryPlanningPage::class)
            ->set('search', 'ERP-')
            ->set('sortField', 'days_left')
            ->set('sortDir', 'desc')
            ->assertSeeInOrder(['ERP-A', 'ERP-C', 'ERP-B'])
            ->assertSeeInOrder(['ERP-B', 'ERP-D']);

        // 4. Lead Time sorting ASC: A (5) -> C (8) -> B (10) / $this->variant (10) -> D (12)
        \Livewire\Livewire::test(\App\Livewire\Items\InventoryPlanningPage::class)
            ->set('search', 'ERP-')
            ->set('sortField', 'lead_time_days')
            ->set('sortDir', 'asc')
            ->assertSeeInOrder(['ERP-A', 'ERP-C', 'ERP-B', 'ERP-D']);

        // Lead Time sorting DESC: D (12) -> B (10) / $this->variant (10) -> C (8) -> A (5)
        \Livewire\Livewire::test(\App\Livewire\Items\InventoryPlanningPage::class)
            ->set('search', 'ERP-')
            ->set('sortField', 'lead_time_days')
            ->set('sortDir', 'desc')
            ->assertSeeInOrder(['ERP-D', 'ERP-B', 'ERP-C', 'ERP-A']);

        // 5. Status custom sorting:
        // Status ASC: Critical first -> Warning second -> Healthy last. (B -> C -> A/D/$this->variant)
        \Livewire\Livewire::test(\App\Livewire\Items\InventoryPlanningPage::class)
            ->set('search', 'ERP-')
            ->set('sortField', 'status')
            ->set('sortDir', 'asc')
            ->assertSeeInOrder(['ERP-B', 'ERP-C', 'ERP-A']);

        // Status DESC: Healthy first -> Warning second -> Critical last. (A/D/$this->variant -> C -> B)
        \Livewire\Livewire::test(\App\Livewire\Items\InventoryPlanningPage::class)
            ->set('search', 'ERP-')
            ->set('sortField', 'status')
            ->set('sortDir', 'desc')
            ->assertSeeInOrder(['ERP-A', 'ERP-C', 'ERP-B']);

        Carbon::setTestNow();
    }
}
