<?php

namespace App\Console\Commands;

use App\Services\Intelligence\ConsumptionCalculationService;
use Illuminate\Console\Command;

class CalculateConsumptionIntelligenceCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'intelligence:calculate-consumption {--warehouse= : Target warehouse ID (optional)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Compute and store the latest 8-week consumption trend intelligence analytical snapshots';

    /**
     * Execute the console command.
     */
    public function handle(ConsumptionCalculationService $service)
    {
        $this->info('Starting Consumption Intelligence Analytical Engine calculation...');

        $warehouseId = $this->option('warehouse') ? (int) $this->option('warehouse') : null;

        $t0 = microtime(true);
        $result = $service->calculateAndPersistSnapshots($warehouseId);
        $t1 = microtime(true);

        if (($result['status'] ?? '') !== 'success') {
            $this->error($result['message'] ?? 'Calculation failed.');
            return 1;
        }

        $duration = round(($t1 - $t0) * 1000, 2);

        $this->info("Successfully calculated and stored snapshots in {$duration} ms.");
        $this->table(
            ['Metric', 'Value'],
            [
                ['Period Start', $result['period_start']],
                ['Period End', $result['period_end']],
                ['Valid Operational Weeks', count($result['valid_weeks'])],
                ['Total Master SKUs', $result['stats']['total_variants']],
                ['Active SKUs in Period', $result['stats']['active_variants']],
                ['Strong Rising SKUs', $result['stats']['strong_rising']],
                ['Rising SKUs', $result['stats']['rising']],
                ['Watchlist SKUs', $result['stats']['watch']],
                ['Volatile SKUs', $result['stats']['volatile']],
                ['Sporadic Single-Spike SKUs', $result['stats']['sporadic']],
                ['Dormant SKUs', $result['stats']['dormant']],
            ]
        );

        return 0;
    }
}
