<?php

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';

use App\Models\Item;
use App\Models\ItemVariant;
use App\Models\StockInReceipt;
use App\Models\StockInItem;
use App\Models\StockMovement;
use App\Services\Inventory\InventoryService;
use Illuminate\Support\Facades\DB;

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// 1. Setup Mock User Session & Data
$user = \App\Models\User::first();
if (!$user) {
    echo "Creating a test user...\n";
    $user = \App\Models\User::create([
        'name' => 'Test Operator',
        'email' => 'operator@test.com',
        'password' => bcrypt('password'),
    ]);
}
auth()->login($user);
session()->put('active_warehouse_id', 1);

// 2. Fetch or Create test variant
echo "Setting up test item variant...\n";
$item = Item::firstOrCreate(['name' => 'Verification Null Bin Item']);
$variant = ItemVariant::firstOrCreate([
    'item_id' => $item->id,
    'erp_code' => 'ERP-NULL-BIN-TEST',
    'sku' => 'SKU-NULL-BIN-TEST',
]);

// Ensure any old receipt/movement for this test is deleted
StockMovement::where('item_variant_id', $variant->id)->delete();

DB::beginTransaction();
try {
    // 3. Simulate StockInReceipt creation
    echo "Creating active receiving session...\n";
    $receipt = StockInReceipt::create([
        'receipt_code' => 'IN-TEST-' . time(),
        'user_id' => $user->id,
        'status' => 'ACTIVE',
        'last_activity_at' => now(),
        'warehouse_id' => 1,
        'operator_id' => $user->id,
        'terminal_id' => 'SPAREPART-DESK-A',
        'terminal_session_id' => 'test-session-id',
    ]);

    // 4. Simulate addToCart with null bin and null supplier
    echo "Adding StockInItem Draft with null bin and null supplier...\n";
    $stockInItem = StockInItem::create([
        'stock_in_receipt_id' => $receipt->id,
        'item_variant_id' => $variant->id,
        'qty' => 5,
        'bin_id' => null,
        'supplier_id' => null,
    ]);

    echo "Draft StockInItem created. ID: {$stockInItem->id}, Bin ID: " . var_export($stockInItem->bin_id, true) . ", Supplier ID: " . var_export($stockInItem->supplier_id, true) . "\n";

    // 5. Simulate submission and committing to inventory
    echo "Submitting / committing stock...\n";
    $cart = [
        [
            'id' => $stockInItem->id,
            'item_variant_id' => $stockInItem->item_variant_id,
            'qty' => $stockInItem->qty,
            'bin_id' => $stockInItem->bin_id,
            'supplier_id' => $stockInItem->supplier_id,
        ]
    ];

    $inventoryService = new InventoryService();
    foreach ($cart as $item) {
        if ($item['bin_id']) {
            $bin = Bin::findOrFail($item['bin_id']);
            $inventoryService->moveStock(
                $bin,
                $item['qty'],
                'IN',
                'Manual Stock IN',
                auth()->id(),
                $item['supplier_id']
            );
        } else {
            // Null bin: record movement directly with null bin_id
            StockMovement::create([
                'item_variant_id'       => $item['item_variant_id'],
                'bin_id'                => null,
                'supplier_id'           => $item['supplier_id'],
                'type'                  => 'IN',
                'qty'                   => $item['qty'],
                'reference'             => 'Manual Stock IN',
                'created_by'            => auth()->id(),
                'warehouse_id'          => 1,
                'operator_id'           => auth()->id(),
                'terminal_id'           => 'SPAREPART-DESK-A',
                'terminal_session_id'   => 'test-session-id',
            ]);
        }
    }

    $receipt->update([
        'status' => 'COMMITTED',
        'last_activity_at' => now(),
    ]);

    echo "Stock successfully committed!\n";

    // 6. Verify database records
    $movement = StockMovement::where('item_variant_id', $variant->id)->first();
    if ($movement && $movement->bin_id === null) {
        echo "SUCCESS: StockMovement created with null bin_id!\n";
    } else {
        echo "FAILURE: StockMovement not found or bin_id not null!\n";
    }

    DB::commit();
    echo "Transaction successfully committed!\n";
} catch (\Exception $e) {
    DB::rollBack();
    echo "ERROR during test: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}
