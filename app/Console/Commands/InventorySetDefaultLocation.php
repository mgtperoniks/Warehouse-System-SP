<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use App\Models\ItemVariant;
use App\Models\Bin;
use App\Models\Location;
use Illuminate\Support\Facades\DB;

#[Signature('inventory:set-default-location')]
#[Description('Populate a temporary default warehouse location (A-1) for existing inventory records without a valid location.')]
class InventorySetDefaultLocation extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info("Scanning inventory records...");

        $totalScanned = ItemVariant::count();
        
        // Count variants without bins
        $variantsWithoutBinsCount = ItemVariant::whereDoesntHave('bins')->count();

        // Count bins with invalid codes
        $invalidBinsCount = Bin::where(function($q) {
            $q->whereNull('code')
              ->orWhere('code', '')
              ->orWhereRaw("LOWER(TRIM(code)) = 'not stored'");
        })->count();

        $totalEligible = $variantsWithoutBinsCount + $invalidBinsCount;

        $this->line("Total records scanned: {$totalScanned}");
        $this->line("Total records eligible for update: {$totalEligible}");

        if ($totalEligible === 0) {
            $this->info("No records need updating.");
            $this->line("Records updated: 0");
            $this->line("Records skipped: {$totalScanned}");
            $this->info("Execution completed");
            return self::SUCCESS;
        }

        $this->info("Processing updates...");

        $updatedCount = 0;
        $skippedCount = $totalScanned - $totalEligible;

        DB::transaction(function () use (&$updatedCount) {
            // Get or create the default location
            $location = Location::firstOrCreate(
                ['code' => 'MAIN'],
                ['description' => 'Main Warehouse']
            );

            // 1. Process variants without bins
            ItemVariant::whereDoesntHave('bins')->chunkById(500, function ($variants) use ($location, &$updatedCount) {
                foreach ($variants as $variant) {
                    Bin::create([
                        'location_id' => $location->id,
                        'item_variant_id' => $variant->id,
                        'code' => 'A-1',
                        'current_qty' => 0,
                        'warehouse_id' => 1,
                    ]);
                    $updatedCount++;
                }
            });

            // 2. Process bins with null, empty, or 'NOT STORED' code
            Bin::where(function($q) {
                $q->whereNull('code')
                  ->orWhere('code', '')
                  ->orWhereRaw("LOWER(TRIM(code)) = 'not stored'");
            })->chunkById(500, function ($bins) use (&$updatedCount) {
                foreach ($bins as $bin) {
                    $bin->update([
                        'code' => 'A-1'
                    ]);
                    $updatedCount++;
                }
            });
        });

        $this->line("Records updated: {$updatedCount}");
        $this->line("Records skipped: {$skippedCount}");
        $this->info("Execution completed");

        return self::SUCCESS;
    }
}
