<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Item;
use App\Models\ItemVariant;
use App\Services\Inventory\InventoryPlanningService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class InventoryDecisionEngineTest extends TestCase
{
    use DatabaseTransactions;

    protected InventoryPlanningService $service;
    protected ItemVariant $variant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new InventoryPlanningService();

        $item = Item::create(['name' => 'Decision Engine Test Item']);
        $this->variant = ItemVariant::create([
            'item_id' => $item->id,
            'sku' => 'SKU-DECISION-123',
            'erp_code' => 'ERP-DECISION-123',
            'unit' => 'PCS',
            'procurement_type' => 'LOCAL',
            'inventory_class' => 'CONSUMABLE',
            'lead_time_days' => 10,
        ]);
    }

    public function test_weekly_average_calculation()
    {
        Carbon::setTestNow('2026-07-01 12:00:00');

        // Create OUT movements in the last 28 days
        DB::table('stock_movements')->insert([
            ['item_variant_id' => $this->variant->id, 'type' => 'OUT', 'qty' => 10.0, 'created_at' => '2026-06-25 10:00:00', 'warehouse_id' => 1, 'operator_id' => 1, 'terminal_id' => 'T', 'terminal_session_id' => 'S'],
            ['item_variant_id' => $this->variant->id, 'type' => 'OUT', 'qty' => 30.0, 'created_at' => '2026-06-15 10:00:00', 'warehouse_id' => 1, 'operator_id' => 1, 'terminal_id' => 'T', 'terminal_session_id' => 'S'],
            // Outside 28 days but inside 90 days
            ['item_variant_id' => $this->variant->id, 'type' => 'OUT', 'qty' => 50.0, 'created_at' => '2026-05-15 10:00:00', 'warehouse_id' => 1, 'operator_id' => 1, 'terminal_id' => 'T', 'terminal_session_id' => 'S'],
        ]);

        // Weekly Avg (28-day window) = (10 + 30) / 4 = 10.0
        $weeklyAvg = $this->service->calculateWeeklyAverage($this->variant->id);
        $this->assertEquals(10.0, $weeklyAvg);

        // Eager calculation with pre-computed value:
        $weeklyAvgPrecomputed = $this->service->calculateWeeklyAverage($this->variant->id, 40.0);
        $this->assertEquals(10.0, $weeklyAvgPrecomputed);

        Carbon::setTestNow();
    }

    public function test_monthly_average_calculation()
    {
        Carbon::setTestNow('2026-07-01 12:00:00');

        // Create OUT movements in the last 90 days
        DB::table('stock_movements')->insert([
            ['item_variant_id' => $this->variant->id, 'type' => 'OUT', 'qty' => 20.0, 'created_at' => '2026-06-25 10:00:00', 'warehouse_id' => 1, 'operator_id' => 1, 'terminal_id' => 'T', 'terminal_session_id' => 'S'],
            ['item_variant_id' => $this->variant->id, 'type' => 'OUT', 'qty' => 40.0, 'created_at' => '2026-05-15 10:00:00', 'warehouse_id' => 1, 'operator_id' => 1, 'terminal_id' => 'T', 'terminal_session_id' => 'S'],
            // Outside 90 days but inside 180 days
            ['item_variant_id' => $this->variant->id, 'type' => 'OUT', 'qty' => 90.0, 'created_at' => '2026-02-15 10:00:00', 'warehouse_id' => 1, 'operator_id' => 1, 'terminal_id' => 'T', 'terminal_session_id' => 'S'],
        ]);

        // Monthly Avg (90-day window) = (20 + 40) / 3 = 20.0
        $monthlyAvg = $this->service->calculateMonthlyAverage($this->variant->id);
        $this->assertEquals(20.0, $monthlyAvg);

        // Pre-computed:
        $monthlyAvgPrecomputed = $this->service->calculateMonthlyAverage($this->variant->id, 60.0);
        $this->assertEquals(20.0, $monthlyAvgPrecomputed);

        Carbon::setTestNow();
    }

    public function test_six_month_average_calculation()
    {
        Carbon::setTestNow('2026-07-01 12:00:00');

        DB::table('stock_movements')->insert([
            ['item_variant_id' => $this->variant->id, 'type' => 'OUT', 'qty' => 120.0, 'created_at' => '2026-02-15 10:00:00', 'warehouse_id' => 1, 'operator_id' => 1, 'terminal_id' => 'T', 'terminal_session_id' => 'S'],
        ]);

        // Six Month Avg (180-day window) = 120 / 6 = 20.0
        $sixMonthAvg = $this->service->calculateSixMonthAverage($this->variant->id);
        $this->assertEquals(20.0, $sixMonthAvg);

        // Pre-computed:
        $sixMonthAvgPrecomputed = $this->service->calculateSixMonthAverage($this->variant->id, 120.0);
        $this->assertEquals(20.0, $sixMonthAvgPrecomputed);

        Carbon::setTestNow();
    }

    public function test_days_left_and_projected_empty_date()
    {
        Carbon::setTestNow('2026-07-01 12:00:00');

        // Current Stock = 40. Weekly Average = 28.0 (consumption = 4 per day)
        // Days Left = 40 / (28.0 / 7) = 40 / 4 = 10.0 days.
        $daysLeft = $this->service->calculateDaysLeft(40, 28.0);
        $this->assertEquals(10.0, $daysLeft);

        // Projected Empty Date = 2026-07-01 + 10 days = 2026-07-11
        $projectedDate = $this->service->calculateProjectedEmptyDate($daysLeft);
        $this->assertEquals('11 Jul 2026', $projectedDate);

        // Zero Consumption
        $daysLeftZero = $this->service->calculateDaysLeft(40, 0.0);
        $this->assertNull($daysLeftZero);
        $this->assertNull($this->service->calculateProjectedEmptyDate($daysLeftZero));

        Carbon::setTestNow();
    }

    public function test_trend_calculation()
    {
        // Weekly >= Monthly * 1.20 -> Increasing
        $this->assertEquals('Increasing', $this->service->calculateTrend(12.0, 10.0));
        
        // Weekly <= Monthly * 0.80 -> Decreasing
        $this->assertEquals('Decreasing', $this->service->calculateTrend(8.0, 10.0));

        // Otherwise -> Stable
        $this->assertEquals('Stable', $this->service->calculateTrend(10.0, 10.0));
        $this->assertEquals('Stable', $this->service->calculateTrend(11.0, 10.0));
        $this->assertEquals('Stable', $this->service->calculateTrend(9.0, 10.0));
    }

    public function test_health_status_priorities_and_boundaries()
    {
        // Lead Time = 10 days. Safety Buffer = 14 days.
        
        // 1. Critical (Days Left <= Lead Time)
        $this->assertEquals('CRITICAL', $this->service->calculateHealthStatus(10.0, 10));
        $this->assertEquals('CRITICAL', $this->service->calculateHealthStatus(5.0, 10));
        $this->assertEquals(1, $this->service->calculatePlanningPriority(10.0, 10));

        // 2. Reorder Now (Days Left <= Lead Time + 14)
        $this->assertEquals('REORDER NOW', $this->service->calculateHealthStatus(24.0, 10));
        $this->assertEquals('REORDER NOW', $this->service->calculateHealthStatus(11.0, 10));
        $this->assertEquals(2, $this->service->calculatePlanningPriority(24.0, 10));

        // 3. Watchlist (Days Left <= Lead Time * 2)
        // With Lead Time = 20, Lead Time + 14 = 34, Lead Time * 2 = 40.
        // Days Left = 38 => Watchlist.
        $this->assertEquals('WATCHLIST', $this->service->calculateHealthStatus(38.0, 20));
        $this->assertEquals(3, $this->service->calculatePlanningPriority(38.0, 20));

        // 4. Healthy (Otherwise)
        $this->assertEquals('HEALTHY', $this->service->calculateHealthStatus(45.0, 20));
        $this->assertEquals(4, $this->service->calculatePlanningPriority(45.0, 20));

        // 5. No Consumption (Days Left is null)
        $this->assertEquals('NO CONSUMPTION', $this->service->calculateHealthStatus(null, 10));
        $this->assertEquals(5, $this->service->calculatePlanningPriority(null, 10));
    }

    public function test_no_n_plus_one_queries_on_planning_page()
    {
        $location = \App\Models\Location::firstOrCreate(['code' => 'LOC-N1'], ['description' => 'Loc N1']);
        $warehouse = \App\Models\Warehouse::firstOrCreate(['code' => 'WH-N1'], ['name' => 'WH N1', 'status' => 'ACTIVE']);

        for ($i = 0; $i < 5; $i++) {
            $item = Item::create(['name' => "Item N1 $i"]);
            $variant = ItemVariant::create([
                'item_id' => $item->id,
                'sku' => "SKU-N1-$i",
                'erp_code' => "ERP-N1-$i",
                'unit' => 'PCS',
                'lead_time_days' => 10,
            ]);
            DB::table('bins')->insert([
                'item_variant_id' => $variant->id,
                'code' => "BIN-N1-$i",
                'warehouse_id' => $warehouse->id,
                'location_id' => $location->id,
                'current_qty' => 10,
            ]);
            DB::table('stock_movements')->insert([
                'item_variant_id' => $variant->id,
                'type' => 'OUT',
                'qty' => 10,
                'warehouse_id' => $warehouse->id,
                'operator_id' => 1,
                'terminal_id' => 'TEST',
                'terminal_session_id' => 'TEST',
                'created_at' => now(),
            ]);
        }

        DB::flushQueryLog();
        DB::enableQueryLog();

        \Livewire\Livewire::test(\App\Livewire\Items\InventoryPlanningPage::class);

        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        // The query count should be low (e.g. pagination query + variants load + subqueries + barcodes eagerly loaded).
        // It must NOT grow linearly with the number of variants.
        $this->assertLessThan(15, $queryCount, "Query count is too high, N+1 queries might be present.");
    }
}
