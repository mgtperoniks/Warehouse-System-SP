<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\Location;
use App\Models\Bin;
use App\Models\Item;
use App\Models\ItemVariant;
use App\Models\StockInReceipt;
use App\Models\StockMovement;
use App\Models\StockTransaction;
use App\Models\Department;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use App\Livewire\Stock\StockInPage;
use App\Livewire\Scan\ScanPage;

class InventoryEngineHardeningTest extends TestCase
{
    use DatabaseTransactions;

    protected User $operator;
    protected Warehouse $warehouse;
    protected Bin $bin1;
    protected Bin $bin2;
    protected ItemVariant $variant1;
    protected ItemVariant $variant2;
    protected Department $department;

    protected function setUp(): void
    {
        parent::setUp();

        // Safe database deletion of WMS data to ensure isolation from pre-existing local data
        DB::table('stock_movements')->delete();
        DB::table('stock_transaction_items')->delete();
        DB::table('stock_transactions')->delete();
        DB::table('stock_in_items')->delete();
        DB::table('stock_in_receipts')->delete();
        DB::table('bins')->delete();

        $this->operator = User::create([
            'role' => 'admin',
            'name' => 'Operator ' . uniqid(),
            'email' => 'operator_' . uniqid() . '@peroniks.com',
            'password' => bcrypt('password'),
        ]);

        $this->warehouse = Warehouse::firstOrCreate(
            ['code' => 'SPAREPART'],
            ['name' => 'Sparepart Warehouse', 'status' => 'ACTIVE']
        );

        $item = Item::firstOrCreate(['name' => 'Hardened Item']);

        $this->variant1 = ItemVariant::firstOrCreate(
            ['sku' => 'SKU-001'],
            [
                'item_id' => $item->id,
                'name' => 'V1',
                'erp_code' => 'ERP-001',
                'unit' => 'PCS'
            ]
        );

        $this->variant2 = ItemVariant::firstOrCreate(
            ['sku' => 'SKU-002'],
            [
                'item_id' => $item->id,
                'name' => 'V2',
                'erp_code' => 'ERP-002',
                'unit' => 'PCS'
            ]
        );

        $location = Location::firstOrCreate(
            ['code' => 'LOC-A'],
            ['description' => 'Location A']
        );

        $this->bin1 = Bin::firstOrCreate(
            ['warehouse_id' => $this->warehouse->id, 'code' => 'BIN-001'],
            [
                'location_id' => $location->id,
                'item_variant_id' => $this->variant1->id,
                'current_qty' => 10,
                'min_qty' => 1,
            ]
        );

        $this->bin2 = Bin::firstOrCreate(
            ['warehouse_id' => $this->warehouse->id, 'code' => 'BIN-002'],
            [
                'location_id' => $location->id,
                'item_variant_id' => $this->variant2->id,
                'current_qty' => 10,
                'min_qty' => 1,
            ]
        );

        // Ensure current quantities are exactly 10
        $this->bin1->update(['current_qty' => 10]);
        $this->bin2->update(['current_qty' => 10]);

        // Create matching movements to prevent ledger drift in the audit health check
        StockMovement::create([
            'item_variant_id' => $this->variant1->id,
            'bin_id' => $this->bin1->id,
            'qty' => 10,
            'type' => 'IN',
            'warehouse_id' => $this->warehouse->id,
            'operator_id' => $this->operator->id,
            'terminal_id' => 'SPAREPART-DESK-A',
            'terminal_session_id' => 'test-session',
        ]);

        StockMovement::create([
            'item_variant_id' => $this->variant2->id,
            'bin_id' => $this->bin2->id,
            'qty' => 10,
            'type' => 'IN',
            'warehouse_id' => $this->warehouse->id,
            'operator_id' => $this->operator->id,
            'terminal_id' => 'SPAREPART-DESK-A',
            'terminal_session_id' => 'test-session',
        ]);

        $this->department = Department::firstOrCreate(
            ['code' => 'ENG'],
            ['name' => 'Engineering', 'is_active' => true]
        );

        $this->actingAs($this->operator);
        session([
            'active_warehouse_id' => $this->warehouse->id,
            'wms_terminal_id' => 'SPAREPART-DESK-A'
        ]);
    }

    public function test_it_sorts_bin_allocations_to_prevent_deadlocks_in_scan_page_submit()
    {
        // Setup cart with multiple items in the scan session
        $cart = [
            [
                'item_variant_id' => $this->variant2->id,
                'qty' => 3,
                'name' => 'V2',
                'barcode' => 'SKU-002',
                'price' => 100,
                'unit' => 'PCS',
                'erp_code' => 'ERP-002'
            ],
            [
                'item_variant_id' => $this->variant1->id,
                'qty' => 2,
                'name' => 'V1',
                'barcode' => 'SKU-001',
                'price' => 100,
                'unit' => 'PCS',
                'erp_code' => 'ERP-001'
            ]
        ];
        session()->put('scan_cart', $cart);

        // The checkout is processed via Livewire
        Livewire::test(ScanPage::class)
            ->set('cart', $cart)
            ->set('deptId', $this->department->id)
            ->set('picId', $this->operator->id)
            ->call('submit')
            ->assertHasNoErrors();

        // Verify quantities were correctly updated
        $this->assertEquals(8, $this->bin1->fresh()->current_qty);
        $this->assertEquals(7, $this->bin2->fresh()->current_qty);

        // Find the created transaction
        $trx = StockTransaction::first();
        $this->assertNotNull($trx);

        // Verify that movements exist
        $movements = StockMovement::where('reference', $trx->code)->orderBy('id')->get();
        $this->assertCount(2, $movements);

        // Check if movement bin_id list is sorted by bin_id ASC in the audit log
        $this->assertTrue($this->bin1->id < $this->bin2->id, "Bin 1 must have smaller ID for order assertion.");
        $this->assertEquals($this->bin1->id, $movements[0]->bin_id);
        $this->assertEquals($this->bin2->id, $movements[1]->bin_id);
    }

    public function test_it_prevents_double_submission_via_pessimistic_lock_in_stock_in_page()
    {
        $cart = [
            'some-key' => [
                'id' => 999,
                'item_variant_id' => $this->variant1->id,
                'name' => 'V1',
                'erp_code' => 'ERP-001',
                'qty' => 5,
                'bin_id' => $this->bin1->id,
                'bin_name' => 'BIN-001',
                'supplier_id' => null,
                'supplier_name' => 'N/A',
            ]
        ];

        // 1. Initialize component
        $component = Livewire::test(StockInPage::class)
            ->set('cart', $cart);

        // Get the active receipt that was automatically created/assigned during mount
        $receipt = $component->get('activeReceipt');
        $this->assertNotNull($receipt);
        $this->assertEquals('ACTIVE', $receipt->status);

        // 2. Change the status of the receipt to COMMITTED in the database
        // simulating a concurrent request that has already committed this receipt.
        DB::table('stock_in_receipts')
            ->where('id', $receipt->id)
            ->update(['status' => 'COMMITTED']);

        // 3. Try submitting the cart
        $component->call('submit');

        // 4. Verify quantity remains 10 (no stock movements processed)
        $this->assertEquals(10, $this->bin1->fresh()->current_qty);
    }

    public function test_it_prevents_concurrent_checkout_in_scan_page_when_lock_is_active()
    {
        $cart = [
            [
                'item_variant_id' => $this->variant1->id,
                'qty' => 2,
                'name' => 'V1',
                'barcode' => 'SKU-001',
                'price' => 100,
                'unit' => 'PCS',
                'erp_code' => 'ERP-001'
            ]
        ];
        session()->put('scan_cart', $cart);

        // Pre-acquire the lock simulating a concurrent request in progress
        $lockKey = 'stock-out-lock-' . $this->operator->id;
        $lock = Cache::lock($lockKey, 10);
        $lock->get();

        Livewire::test(ScanPage::class)
            ->set('cart', $cart)
            ->set('deptId', $this->department->id)
            ->set('picId', $this->operator->id)
            ->call('submit')
            ->assertSee('Your checkout request is already being processed');

        // Verify stock was NOT deducted
        $this->assertEquals(10, $this->bin1->fresh()->current_qty);

        // Release lock
        $lock->release();
    }

    public function test_it_runs_inventory_audit_command_successfully()
    {
        // 1. Run audit when system is in perfect state
        $exitCode = Artisan::call('inventory:audit');
        $this->assertEquals(0, $exitCode);
        $output = Artisan::output();
        $this->assertStringContainsString('PASS', $output);
        $this->assertStringContainsString('AUDIT RESULT: PASS', $output);

        // 2. Introduce negative bin quantity and assert FAIL
        $this->bin1->update(['current_qty' => -5]);
        $exitCode = Artisan::call('inventory:audit');
        $this->assertEquals(1, $exitCode);
        $output = Artisan::output();
        $this->assertStringContainsString('FAIL: Found 1 bins with negative quantities', $output);
        $this->assertStringContainsString('AUDIT RESULT: ERROR', $output);

        // Reset bin quantity to clean state
        $this->bin1->update(['current_qty' => 10]);

        // 3. Introduce ledger drift
        // We update the bin's current qty without creating a movement, causing a drift of 5 units
        $this->bin1->update(['current_qty' => 15]);
        $exitCode = Artisan::call('inventory:audit');
        $this->assertEquals(1, $exitCode);
        $output = Artisan::output();
        $this->assertStringContainsString('FAIL: Found 1 bins with ledger quantity drift', $output);
    }
}
