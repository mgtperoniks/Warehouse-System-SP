<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\Department;
use App\Models\Item;
use App\Models\ItemVariant;
use App\Models\StockTransaction;
use App\Models\StockTransactionItem;
use App\Models\StockMovement;
use App\Livewire\Reports\StockOutReport;
use Livewire\Livewire;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class StockOutThermalReportTest extends TestCase
{
    use DatabaseTransactions;

    protected User $user;
    protected Warehouse $warehouse;
    protected Warehouse $otherWarehouse;
    protected Department $department;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'email' => 'admin_thermal_' . uniqid() . '@test.com',
            'name' => 'ADMIN THERMAL',
            'role' => 'SUPERADMIN',
        ]);

        $this->warehouse = Warehouse::firstOrCreate(
            ['code' => 'SPAREPART'],
            ['name' => 'Sparepart Warehouse']
        );

        $this->otherWarehouse = Warehouse::firstOrCreate(
            ['code' => 'RAW_MATERIAL'],
            ['name' => 'Raw Materials Warehouse']
        );

        $this->department = Department::firstOrCreate(
            ['code' => 'ALU'],
            ['name' => 'ALUMINIUM']
        );

        session(['active_warehouse_id' => $this->warehouse->id]);
        session(['active_warehouse_code' => $this->warehouse->code]);
    }

    public function test_stock_out_report_renders_department_batch_thermal_button_when_report_generated(): void
    {
        $this->actingAs($this->user);

        $dept = Department::create(['code' => 'D' . rand(100, 999), 'name' => 'DEPT_' . uniqid()]);
        $itemA = Item::firstOrCreate(['name' => 'BEARING SKF 6204 ZZ']);
        $variantA = ItemVariant::firstOrCreate(
            ['erp_code' => '5.01.SKF.6204'],
            [
                'item_id' => $itemA->id,
                'sku' => 'SKU-SKF-6204',
                'unit' => 'PCS'
            ]
        );

        $txCode = 'OUT-THERMAL-' . uniqid();
        $tx = StockTransaction::create([
            'code' => $txCode,
            'type' => 'OUT',
            'status' => 'CONFIRMED',
            'warehouse_id' => $this->warehouse->id,
            'department_id' => $dept->id,
            'user_id' => $this->user->id,
            'operator_id' => $this->user->id,
            'created_at' => '2026-08-31 01:34:00', // UTC 01:34 -> WIB 08:34
        ]);

        StockTransactionItem::create([
            'stock_transaction_id' => $tx->id,
            'item_variant_id' => $variantA->id,
            'item_name_snapshot' => 'BEARING SKF 6204 ZZ - 5.01.SKF.6204',
            'erp_code_snapshot' => '5.01.SKF.6204',
            'qty' => 2,
            'unit_snapshot' => 'PCS',
            'erp_transfer_status' => StockTransactionItem::ERP_NOT_STARTED,
        ]);

        $component = Livewire::test(StockOutReport::class)
            ->set('startDate', '2026-08-31')
            ->set('endDate', '2026-08-31')
            ->set('departmentId', $dept->id)
            ->call('generateReport');

        $html = $component->html();

        $component->assertStatus(200)
            ->assertSee('PRINT ALL THERMAL (1 RECEIPT)')
            ->assertSee($txCode);

        // Individual row PRINT button is REMOVED
        $this->assertEquals(0, substr_count($html, '@click="printSingleTx'), "Expected NO individual row print buttons in table");
    }

    /**
     * Multi-Item Transaction Test (1 StockTransaction with 3 items)
     * Verifies:
     * - Department button rendered with singular label (1 RECEIPT)
     * - No individual row print buttons in table
     * - Transaction snapshot contains all 3 items with their ERP codes and quantities
     * - Table clearly labels multi-item count
     * - Absolute Read-Only Guarantee: Zero DB mutations
     */
    public function test_multi_item_transaction_renders_department_batch_action_and_encapsulates_all_items(): void
    {
        $this->actingAs($this->user);

        $dept = Department::create(['code' => 'D' . rand(100, 999), 'name' => 'DEPT_' . uniqid()]);
        $item1 = Item::firstOrCreate(['name' => 'BEARING EZO 696 ZZ']);
        $var1 = ItemVariant::firstOrCreate(['erp_code' => '5.01.EZO.696'], ['item_id' => $item1->id, 'sku' => 'SKU-EZO-696', 'unit' => 'PCS']);

        $item2 = Item::firstOrCreate(['name' => 'BEARING FYH UC 207']);
        $var2 = ItemVariant::firstOrCreate(['erp_code' => '5.01.FYH.UC.207'], ['item_id' => $item2->id, 'sku' => 'SKU-FYH-207', 'unit' => 'PCS']);

        $item3 = Item::firstOrCreate(['name' => 'BEARING MRK 51203']);
        $var3 = ItemVariant::firstOrCreate(['erp_code' => '5.01.MRK.51203'], ['item_id' => $item3->id, 'sku' => 'SKU-MRK-51203', 'unit' => 'PCS']);

        $tx = StockTransaction::create([
            'code' => 'OUT-TEST-MULTI-001',
            'type' => 'OUT',
            'status' => 'CONFIRMED',
            'warehouse_id' => $this->warehouse->id,
            'department_id' => $dept->id,
            'user_id' => $this->user->id,
            'operator_id' => $this->user->id,
            'created_at' => '2026-08-31 01:34:00',
        ]);

        StockTransactionItem::create([
            'stock_transaction_id' => $tx->id,
            'item_variant_id' => $var1->id,
            'item_name_snapshot' => 'BEARING EZO 696 ZZ',
            'erp_code_snapshot' => '5.01.EZO.696',
            'qty' => 3,
            'unit_snapshot' => 'PCS',
            'erp_transfer_status' => StockTransactionItem::ERP_NOT_STARTED,
        ]);

        StockTransactionItem::create([
            'stock_transaction_id' => $tx->id,
            'item_variant_id' => $var2->id,
            'item_name_snapshot' => 'BEARING FYH UC 207',
            'erp_code_snapshot' => '5.01.FYH.UC.207',
            'qty' => 1,
            'unit_snapshot' => 'PCS',
            'erp_transfer_status' => StockTransactionItem::ERP_NOT_STARTED,
        ]);

        StockTransactionItem::create([
            'stock_transaction_id' => $tx->id,
            'item_variant_id' => $var3->id,
            'item_name_snapshot' => 'BEARING MRK 51203',
            'erp_code_snapshot' => '5.01.MRK.51203',
            'qty' => 1,
            'unit_snapshot' => 'PCS',
            'erp_transfer_status' => StockTransactionItem::ERP_NOT_STARTED,
        ]);

        $initialTxCount = StockTransaction::count();
        $initialItemCount = StockTransactionItem::count();
        $initialMovementCount = StockMovement::count();

        $component = Livewire::test(StockOutReport::class)
            ->set('startDate', '2026-08-31')
            ->set('endDate', '2026-08-31')
            ->set('departmentId', $dept->id)
            ->call('generateReport');

        $html = $component->html();

        // 1. Transaction Code is displayed with item summary badge
        $component->assertSee('OUT-TEST-MULTI-001')
            ->assertSee('3 ITEMS (5 PCS)')
            ->assertSee('BEARING EZO 696 ZZ')
            ->assertSee('BEARING FYH UC 207')
            ->assertSee('BEARING MRK 51203');

        // 2. Department header has singular button
        $component->assertSee('PRINT ALL THERMAL (1 RECEIPT)');

        // 3. No individual row PRINT button in table
        $this->assertEquals(0, substr_count($html, '@click="printSingleTx'));

        // 4. Client-side snapshot dictionary verification contains all 3 items
        $this->assertStringContainsString("\"{$tx->id}\":", $html);
        $this->assertStringContainsString('5.01.EZO.696', $html);
        $this->assertStringContainsString('5.01.FYH.UC.207', $html);
        $this->assertStringContainsString('5.01.MRK.51203', $html);

        // 5. Absolute Read-Only Guarantee: Zero DB mutations
        $this->assertEquals($initialTxCount, StockTransaction::count());
        $this->assertEquals($initialItemCount, StockTransactionItem::count());
        $this->assertEquals($initialMovementCount, StockMovement::count());
    }

    /**
     * Department Batch with Multiple Transactions Test
     * Verifies:
     * - Department containing 3 transactions renders label "PRINT ALL THERMAL (3 RECEIPTS)"
     * - data-tx-ids attribute on department print button contains all 3 transaction IDs
     * - No individual row print buttons rendered in table
     */
    public function test_department_batch_with_multiple_transactions_renders_exact_plural_count_and_department_ids(): void
    {
        $this->actingAs($this->user);

        $dept = Department::create(['code' => 'D' . rand(100, 999), 'name' => 'DEPT_' . uniqid()]);

        // Create 3 distinct transactions in same department
        $txA = StockTransaction::create([
            'code' => 'OUT-BATCH-0001', 'type' => 'OUT', 'status' => 'CONFIRMED',
            'warehouse_id' => $this->warehouse->id, 'department_id' => $dept->id,
            'user_id' => $this->user->id, 'operator_id' => $this->user->id,
            'created_at' => '2026-08-31 01:00:00',
        ]);
        $txB = StockTransaction::create([
            'code' => 'OUT-BATCH-0002', 'type' => 'OUT', 'status' => 'CONFIRMED',
            'warehouse_id' => $this->warehouse->id, 'department_id' => $dept->id,
            'user_id' => $this->user->id, 'operator_id' => $this->user->id,
            'created_at' => '2026-08-31 02:00:00',
        ]);
        $txC = StockTransaction::create([
            'code' => 'OUT-BATCH-0003', 'type' => 'OUT', 'status' => 'CONFIRMED',
            'warehouse_id' => $this->warehouse->id, 'department_id' => $dept->id,
            'user_id' => $this->user->id, 'operator_id' => $this->user->id,
            'created_at' => '2026-08-31 03:00:00',
        ]);

        $itemA = Item::firstOrCreate(['name' => 'GENERIC ITEM']);
        $varA = ItemVariant::firstOrCreate(['erp_code' => '5.01.GEN'], ['item_id' => $itemA->id, 'sku' => 'SKU-GEN', 'unit' => 'PCS']);

        // Tx A: 3 items
        StockTransactionItem::create(['stock_transaction_id' => $txA->id, 'item_variant_id' => $varA->id, 'item_name_snapshot' => 'Item A1', 'erp_code_snapshot' => '5.01.A1', 'qty' => 1, 'unit_snapshot' => 'PCS', 'erp_transfer_status' => StockTransactionItem::ERP_NOT_STARTED]);
        StockTransactionItem::create(['stock_transaction_id' => $txA->id, 'item_variant_id' => $varA->id, 'item_name_snapshot' => 'Item A2', 'erp_code_snapshot' => '5.01.A2', 'qty' => 2, 'unit_snapshot' => 'PCS', 'erp_transfer_status' => StockTransactionItem::ERP_NOT_STARTED]);
        StockTransactionItem::create(['stock_transaction_id' => $txA->id, 'item_variant_id' => $varA->id, 'item_name_snapshot' => 'Item A3', 'erp_code_snapshot' => '5.01.A3', 'qty' => 3, 'unit_snapshot' => 'PCS', 'erp_transfer_status' => StockTransactionItem::ERP_NOT_STARTED]);

        // Tx B: 2 items
        StockTransactionItem::create(['stock_transaction_id' => $txB->id, 'item_variant_id' => $varA->id, 'item_name_snapshot' => 'Item B1', 'erp_code_snapshot' => '5.01.B1', 'qty' => 1, 'unit_snapshot' => 'PCS', 'erp_transfer_status' => StockTransactionItem::ERP_NOT_STARTED]);
        StockTransactionItem::create(['stock_transaction_id' => $txB->id, 'item_variant_id' => $varA->id, 'item_name_snapshot' => 'Item B2', 'erp_code_snapshot' => '5.01.B2', 'qty' => 1, 'unit_snapshot' => 'PCS', 'erp_transfer_status' => StockTransactionItem::ERP_NOT_STARTED]);

        // Tx C: 1 item
        StockTransactionItem::create(['stock_transaction_id' => $txC->id, 'item_variant_id' => $varA->id, 'item_name_snapshot' => 'Item C1', 'erp_code_snapshot' => '5.01.C1', 'qty' => 5, 'unit_snapshot' => 'PCS', 'erp_transfer_status' => StockTransactionItem::ERP_NOT_STARTED]);

        $component = Livewire::test(StockOutReport::class)
            ->set('startDate', '2026-08-31')
            ->set('endDate', '2026-08-31')
            ->set('departmentId', $dept->id)
            ->call('generateReport');

        $html = $component->html();

        // Plural label
        $component->assertSee('PRINT ALL THERMAL (3 RECEIPTS)');

        // Department button contains all 3 transaction IDs
        $this->assertStringContainsString("printDepartmentBatch([", $html);
        $this->assertStringContainsString((string)$txA->id, $html);
        $this->assertStringContainsString((string)$txB->id, $html);
        $this->assertStringContainsString((string)$txC->id, $html);

        // No individual row PRINT buttons
        $this->assertEquals(0, substr_count($html, '@click="printSingleTx'));
    }

    /**
     * Department Isolation Test
     * Verifies that transactions are strictly partitioned by department
     */
    public function test_department_batch_isolates_transactions_by_department(): void
    {
        $this->actingAs($this->user);

        $dept1 = Department::create(['code' => 'D' . rand(100, 999), 'name' => 'DEPT1_' . uniqid()]);
        $dept2 = Department::create(['code' => 'D' . rand(100, 999), 'name' => 'DEPT2_' . uniqid()]);

        $txDept1 = StockTransaction::create([
            'code' => 'OUT-ALU-001', 'type' => 'OUT', 'status' => 'CONFIRMED',
            'warehouse_id' => $this->warehouse->id, 'department_id' => $dept1->id,
            'user_id' => $this->user->id, 'operator_id' => $this->user->id,
        ]);

        $txDept2 = StockTransaction::create([
            'code' => 'OUT-LIL-001', 'type' => 'OUT', 'status' => 'CONFIRMED',
            'warehouse_id' => $this->warehouse->id, 'department_id' => $dept2->id,
            'user_id' => $this->user->id, 'operator_id' => $this->user->id,
        ]);

        $itemA = Item::firstOrCreate(['name' => 'GENERIC ITEM']);
        $varA = ItemVariant::firstOrCreate(['erp_code' => '5.01.GEN'], ['item_id' => $itemA->id, 'sku' => 'SKU-GEN', 'unit' => 'PCS']);

        StockTransactionItem::create(['stock_transaction_id' => $txDept1->id, 'item_variant_id' => $varA->id, 'item_name_snapshot' => 'Item ALU', 'erp_code_snapshot' => '5.01.ALU', 'qty' => 1, 'unit_snapshot' => 'PCS', 'erp_transfer_status' => StockTransactionItem::ERP_NOT_STARTED]);
        StockTransactionItem::create(['stock_transaction_id' => $txDept2->id, 'item_variant_id' => $varA->id, 'item_name_snapshot' => 'Item LIL', 'erp_code_snapshot' => '5.01.LIL', 'qty' => 1, 'unit_snapshot' => 'PCS', 'erp_transfer_status' => StockTransactionItem::ERP_NOT_STARTED]);

        $component = Livewire::test(StockOutReport::class)
            ->set('startDate', '2026-08-31')
            ->set('endDate', '2026-08-31')
            ->call('generateReport');

        $html = $component->html();

        // Department 1 has button with only txDept1
        $this->assertStringContainsString("printDepartmentBatch([{$txDept1->id}]", $html);

        // Department 2 has button with only txDept2
        $this->assertStringContainsString("printDepartmentBatch([{$txDept2->id}]", $html);
    }

    public function test_warehouse_isolation_prevents_unauthorized_warehouse_transactions(): void
    {
        $this->actingAs($this->user);

        $itemOther = Item::create(['name' => 'SECRET ITEM']);
        $variantOther = ItemVariant::create([
            'item_id' => $itemOther->id,
            'erp_code' => '9.99.999',
            'sku' => 'SKU-SECRET',
            'unit' => 'PCS'
        ]);

        // Transaction in OTHER warehouse
        $txOther = StockTransaction::create([
            'code' => 'OUT-OTHER-9999',
            'type' => 'OUT',
            'status' => 'CONFIRMED',
            'warehouse_id' => $this->otherWarehouse->id,
            'department_id' => $this->department->id,
            'user_id' => $this->user->id,
            'operator_id' => $this->user->id,
            'created_at' => '2026-08-31 01:34:00',
        ]);

        StockTransactionItem::create([
            'stock_transaction_id' => $txOther->id,
            'item_variant_id' => $variantOther->id,
            'item_name_snapshot' => 'SECRET ITEM',
            'erp_code_snapshot' => '9.99.999',
            'qty' => 10,
            'unit_snapshot' => 'PCS',
            'erp_transfer_status' => StockTransactionItem::ERP_NOT_STARTED,
        ]);

        $component = Livewire::test(StockOutReport::class)
            ->set('startDate', '2026-08-31')
            ->set('endDate', '2026-08-31')
            ->call('generateReport');

        $component->assertStatus(200)
            ->assertDontSee('OUT-OTHER-9999')
            ->assertDontSee('SECRET ITEM');
    }
}
