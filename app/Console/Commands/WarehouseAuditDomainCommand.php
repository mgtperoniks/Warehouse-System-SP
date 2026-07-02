<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Warehouse;
use App\Models\ItemVariant;
use App\Services\Inventory\WarehouseDomainService;
use App\Models\Scopes\ActiveWarehouseDomainScope;

class WarehouseAuditDomainCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'warehouse:audit-domain';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Read-only deployment verification of warehouse family mapping and matching items.';

    /**
     * Execute the console command.
     */
    public function handle(WarehouseDomainService $domainService)
    {
        $warehouses = Warehouse::all();

        if ($warehouses->isEmpty()) {
            $this->warn('No warehouses found in the database.');
            return 0;
        }

        foreach ($warehouses as $warehouse) {
            $this->info("Warehouse: {$warehouse->name} ({$warehouse->code})");
            $this->line(str_repeat('-', 40));

            $allowedFamilies = $warehouse->allowedFamilyCodes();
            $this->line("Allowed Families: " . (empty($allowedFamilies) ? 'None' : implode(', ', $allowedFamilies)));

            // Fetch variants that have at least one bin in this warehouse
            $variants = ItemVariant::withoutGlobalScope(ActiveWarehouseDomainScope::class)
                ->whereHas('bins', function ($q) use ($warehouse) {
                    $q->where('warehouse_id', $warehouse->id);
                })
                ->get();

            $expectedCounts = [];
            $unexpectedCounts = [];

            foreach ($variants as $variant) {
                $family = $domainService->extractFamily($variant->erp_code);
                
                if (in_array($family, $allowedFamilies, true)) {
                    if (!isset($expectedCounts[$family])) {
                        $expectedCounts[$family] = 0;
                    }
                    $expectedCounts[$family]++;
                } else {
                    if (!isset($unexpectedCounts[$family])) {
                        $unexpectedCounts[$family] = 0;
                    }
                    $unexpectedCounts[$family]++;
                }
            }

            $this->line("\nItems");
            if (empty($expectedCounts)) {
                $this->line("  None");
            } else {
                foreach ($expectedCounts as $fam => $count) {
                    $this->line("  {$fam}.xxx : {$count} items");
                }
            }

            $hasUnexpected = !empty($unexpectedCounts);
            if ($hasUnexpected) {
                $this->warn("\nUnexpected Families");
                foreach ($unexpectedCounts as $fam => $count) {
                    $this->warn("  {$fam}.xxx : {$count} items");
                }
            }

            $this->line("");
            if ($hasUnexpected) {
                $this->error("STATUS : WARNING");
            } else {
                $this->info("STATUS : OK");
            }
            $this->line("\n");
        }

        return 0;
    }
}
