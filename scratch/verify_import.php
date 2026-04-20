<?php

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';

use App\Jobs\ImportItemsJob;
use App\Models\ItemVariant;
use App\Models\Bin;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Run the job
echo "Running ImportItemsJob...\n";
(new ImportItemsJob('imports/test.csv', 1))->handle();

// Verify results
$variant = ItemVariant::where('erp_code', 'ERP-TEST-001')->first();
if ($variant) {
    echo "Variant created: " . $variant->erp_code . "\n";
    $bin = Bin::where('item_variant_id', $variant->id)->where('code', 'BIN-TEST-01')->first();
    if ($bin) {
        echo "Bin created: " . $bin->code . ", Qty: " . $bin->current_qty . "\n";
        $movement = StockMovement::where('bin_id', $bin->id)->first();
        if ($movement) {
            echo "Movement created: Type: " . $movement->type . ", Qty: " . $movement->qty . ", Ref: " . $movement->reference . "\n";
        } else {
            echo "ERROR: Movement not found!\n";
        }
    } else {
        echo "ERROR: Bin not found!\n";
    }
} else {
    echo "ERROR: Variant not found!\n";
}

// Cleanup
if ($variant) {
    echo "Cleaning up...\n";
    DB::transaction(function() use ($variant) {
        StockMovement::where('item_variant_id', $variant->id)->delete();
        Bin::where('item_variant_id', $variant->id)->delete();
        $variant->barcodes()->delete();
        $variant->suppliers()->detach();
        $itemId = $variant->item_id;
        $variant->delete();
        \App\Models\Item::find($itemId)?->delete();
    });
}
