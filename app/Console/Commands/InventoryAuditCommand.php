<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('inventory:audit')]
#[Description('Audit the health and integrity of WMS inventory records, checking ledger drift and constraints.')]
class InventoryAuditCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info("=== Starting WMS Inventory Engine Hardening Audit ===");

        $hasError = false;
        $hasWarning = false;

        // 1. Negative Bins
        $negativeBins = DB::table('bins')->where('current_qty', '<', 0)->get();
        if ($negativeBins->isNotEmpty()) {
            $hasError = true;
            $this->error("FAIL: Found " . $negativeBins->count() . " bins with negative quantities.");
            foreach ($negativeBins as $b) {
                $this->line("  - Bin ID: {$b->id}, Code: {$b->code}, Qty: {$b->current_qty}");
            }
        } else {
            $this->info("PASS: No negative bins found.");
        }

        // 2. Ledger Drift (Current Qty vs Ledger Sum of Movements)
        $driftingBins = DB::table('bins')
            ->leftJoin('stock_movements', 'bins.id', '=', 'stock_movements.bin_id')
            ->select('bins.id', 'bins.code', 'bins.current_qty', DB::raw('SUM(stock_movements.qty) as ledger_qty'))
            ->groupBy('bins.id', 'bins.code', 'bins.current_qty')
            ->havingRaw('bins.current_qty != COALESCE(SUM(stock_movements.qty), 0)')
            ->get();

        if ($driftingBins->isNotEmpty()) {
            $hasError = true;
            $this->error("FAIL: Found " . $driftingBins->count() . " bins with ledger quantity drift.");
            foreach ($driftingBins as $b) {
                $this->line("  - Bin ID: {$b->id}, Code: {$b->code}, Bin Qty: {$b->current_qty}, Ledger Qty: " . ($b->ledger_qty ?? 0));
            }
        } else {
            $this->info("PASS: No ledger quantity drift detected.");
        }

        // 3. Orphan Stock Movements (where bin_id is not null but bin does not exist)
        $orphanedMovements = DB::table('stock_movements')
            ->leftJoin('bins', 'stock_movements.bin_id', '=', 'bins.id')
            ->whereNull('bins.id')
            ->whereNotNull('stock_movements.bin_id')
            ->get();

        if ($orphanedMovements->isNotEmpty()) {
            $hasError = true;
            $this->error("FAIL: Found " . $orphanedMovements->count() . " orphaned StockMovement records.");
            foreach ($orphanedMovements as $m) {
                $this->line("  - Movement ID: {$m->id}, Bin ID Reference: {$m->bin_id}");
            }
        } else {
            $this->info("PASS: No orphaned StockMovements found.");
        }

        // 4. Broken FK References (bins referencing non-existent item variants)
        $brokenVariants = DB::table('bins')
            ->leftJoin('item_variants', 'bins.item_variant_id', '=', 'item_variants.id')
            ->whereNull('item_variants.id')
            ->get();

        if ($brokenVariants->isNotEmpty()) {
            $hasError = true;
            $this->error("FAIL: Found " . $brokenVariants->count() . " bins referencing non-existent item variants.");
            foreach ($brokenVariants as $b) {
                $this->line("  - Bin ID: {$b->id}, Code: {$b->code}, Item Variant ID: {$b->item_variant_id}");
            }
        } else {
            $this->info("PASS: No broken item variant references found.");
        }

        // 5. Adjustments waiting too long (status WAITING_APPROVAL created > 7 days ago)
        $longWaitingAdjustments = DB::table('inventory_adjustments')
            ->where('status', 'WAITING_APPROVAL')
            ->where('date', '<', now()->subDays(7)->format('Y-m-d'))
            ->get();

        if ($longWaitingAdjustments->isNotEmpty()) {
            $hasWarning = true;
            $this->warn("WARNING: Found " . $longWaitingAdjustments->count() . " adjustments waiting approval for > 7 days.");
            foreach ($longWaitingAdjustments as $adj) {
                $this->line("  - Adj No: {$adj->adjustment_no}, Date: {$adj->date}");
            }
        } else {
            $this->info("PASS: No adjustments waiting approval for > 7 days.");
        }

        // 6. Duplicate Active Headers (multiple WAITING_APPROVAL headers per warehouse, operator, date)
        $duplicateHeaders = DB::table('inventory_adjustments')
            ->where('status', 'WAITING_APPROVAL')
            ->select('warehouse_id', 'operator_id', 'date', DB::raw('COUNT(*) as count'))
            ->groupBy('warehouse_id', 'operator_id', 'date')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        if ($duplicateHeaders->isNotEmpty()) {
            $hasError = true;
            $this->error("FAIL: Found duplicate active WAITING_APPROVAL headers for the same warehouse, operator, and date.");
            foreach ($duplicateHeaders as $dh) {
                $this->line("  - Warehouse ID: {$dh->warehouse_id}, Operator ID: {$dh->operator_id}, Date: {$dh->date}, Count: {$dh->count}");
            }
        } else {
            $this->info("PASS: No duplicate active adjustment headers detected.");
        }

        // 7. Warehouse Scope Violations (movement warehouse != bin warehouse)
        $scopeViolations = DB::table('stock_movements')
            ->join('bins', 'stock_movements.bin_id', '=', 'bins.id')
            ->whereRaw('stock_movements.warehouse_id != bins.warehouse_id')
            ->select('stock_movements.id as sm_id', 'stock_movements.warehouse_id as sm_wh', 'bins.id as bin_id', 'bins.warehouse_id as bin_wh')
            ->get();

        if ($scopeViolations->isNotEmpty()) {
            $hasError = true;
            $this->error("FAIL: Found " . $scopeViolations->count() . " warehouse scope violations (movement warehouse != bin warehouse).");
            foreach ($scopeViolations as $sv) {
                $this->line("  - Movement ID: {$sv->sm_id} (Wh: {$sv->sm_wh}) -> Bin ID: {$sv->bin_id} (Wh: {$sv->bin_wh})");
            }
        } else {
            $this->info("PASS: No warehouse scope violations detected.");
        }

        $this->info("==================================================");
        if ($hasError) {
            $this->error("AUDIT RESULT: ERROR");
            return self::FAILURE;
        } elseif ($hasWarning) {
            $this->warn("AUDIT RESULT: WARNING");
            return self::SUCCESS;
        }

        $this->info("AUDIT RESULT: PASS");
        return self::SUCCESS;
    }
}
