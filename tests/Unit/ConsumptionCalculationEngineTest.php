<?php

namespace Tests\Unit;

use App\Services\Intelligence\ConsumptionCalculationService;
use PHPUnit\Framework\TestCase;

class ConsumptionCalculationEngineTest extends TestCase
{
    protected ConsumptionCalculationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ConsumptionCalculationService();
    }

    /**
     * Case 1: Sporadic single spike (0,0,0,0,0,0,0,24)
     * Expected: Low confidence (<= 0.20), classified as SPORADIC_SPIKE, low Trend Index (< 15).
     */
    public function test_case_1_sporadic_spike_penalized()
    {
        $quantities = [0, 0, 0, 0, 0, 0, 0, 24];
        $result = $this->service->calculateSkuMetrics($quantities);

        $this->assertEquals(ConsumptionCalculationService::CLASS_SPORADIC_SPIKE, $result['classification']);
        $this->assertEquals(1, $result['active_weeks_count']);
        $this->assertLessThanOrEqual(0.20, $result['confidence_score']);
        $this->assertLessThan(15.0, $result['trend_index'], "Sporadic spike should not obtain high Trend Index");
    }

    /**
     * Case 2: Steady baseline then spike (100,100,100,100,100,100,100,200)
     * Expected: Positive trend, 100% active weeks, strong index (> 65), meeting eligible.
     */
    public function test_case_2_steady_baseline_then_spike()
    {
        $quantities = [100, 100, 100, 100, 100, 100, 100, 200];
        $result = $this->service->calculateSkuMetrics($quantities);

        $this->assertEquals(8, $result['active_weeks_count']);
        $this->assertGreaterThan(0.9, $result['confidence_score']);
        $this->assertGreaterThan(65.0, $result['trend_index']);
        $this->assertTrue($result['meeting_eligible']);
        $this->assertEquals(ConsumptionCalculationService::CLASS_STRONG_RISING, $result['classification']);
    }

    /**
     * Case 3: Ideal steady progressive growth (100,110,120,130,140,150,160,170)
     * Expected: STRONG_RISING, Top Trend Index (> 70), meeting eligible.
     */
    public function test_case_3_ideal_progressive_growth()
    {
        $quantities = [100, 110, 120, 130, 140, 150, 160, 170];
        $result = $this->service->calculateSkuMetrics($quantities);

        $this->assertEquals(ConsumptionCalculationService::CLASS_STRONG_RISING, $result['classification']);
        $this->assertEquals(8, $result['active_weeks_count']);
        $this->assertGreaterThan(70.0, $result['trend_index']);
        $this->assertGreaterThan(0.8, $result['stability_score']);
        $this->assertTrue($result['meeting_eligible']);
    }

    /**
     * Case 4: Highly volatile zig-zag (100,50,200,40,180,30,190,20)
     * Expected: Volatility penalty, trend index < 50, not strong rising.
     */
    public function test_case_4_high_volatility_penalized()
    {
        $quantities = [100, 50, 200, 40, 180, 30, 190, 20];
        $result = $this->service->calculateSkuMetrics($quantities);

        $this->assertLessThan(0.7, $result['stability_score']);
        $this->assertLessThan(50.0, $result['trend_index']);
        $this->assertNotEquals(ConsumptionCalculationService::CLASS_STRONG_RISING, $result['classification']);
    }

    /**
     * Case 5: Low-volume steady climber (1,1,2,1,2,2,3,3)
     * Expected: Positive trend detected (index > 60), BUT meeting_eligible = false, classified as WATCH (NOT Strong Rising).
     */
    public function test_case_5_low_volume_growth_detected_but_gated_to_watch()
    {
        $quantities = [1, 1, 2, 1, 2, 2, 3, 3];
        $result = $this->service->calculateSkuMetrics($quantities);

        $this->assertEquals(8, $result['active_weeks_count']);
        $this->assertGreaterThan(0.0, $result['normalized_slope_pct']);
        $this->assertGreaterThan(60.0, $result['trend_index']);
        $this->assertFalse($result['meeting_eligible'], "15 units total should not pass Meeting Evidence Gate");
        $this->assertEquals(ConsumptionCalculationService::CLASS_WATCH, $result['classification'], "Low volume climber must be classified as WATCH, not Strong Rising");
    }

    /**
     * Case 6: Intermittent demand (0,0,10,0,0,20,0,15)
     * Expected: Intermittent demand, moderate/low confidence (< 0.40), trend index < 35.
     */
    public function test_case_6_intermittent_demand_handling()
    {
        $quantities = [0, 0, 10, 0, 0, 20, 0, 15];
        $result = $this->service->calculateSkuMetrics($quantities);

        $this->assertEquals(3, $result['active_weeks_count']);
        $this->assertLessThan(0.40, $result['confidence_score']);
        $this->assertLessThan(35.0, $result['trend_index']);
    }

    /**
     * Dormant SKU (0,0,0,0,0,0,0,0)
     */
    public function test_dormant_sku_returns_zero_index()
    {
        $quantities = [0, 0, 0, 0, 0, 0, 0, 0];
        $result = $this->service->calculateSkuMetrics($quantities);

        $this->assertEquals(ConsumptionCalculationService::CLASS_DORMANT, $result['classification']);
        $this->assertEquals(0.0, $result['trend_index']);
        $this->assertEquals(0, $result['active_weeks_count']);
        $this->assertFalse($result['meeting_eligible']);
    }
}
