<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Warehouse;
use App\Models\Item;
use App\Models\ItemVariant;
use App\Models\Supplier;
use App\Models\Location;
use App\Models\Bin;
use App\Models\OutstandingPurchaseOrder;
use App\Models\OutstandingPurchaseOrderItem;
use App\Models\ReceivingSession;
use App\Models\ReceivingSessionItem;
use App\Models\StockMovement;
use App\Models\StockTransaction;
use App\Services\OutstandingPurchase\ImportPipelineService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class OutstandingPurchaseReconciliationPhase1Test extends TestCase
{
    use DatabaseTransactions;

    protected User $user;
    protected Warehouse $warehouse;
    protected Warehouse $otherWarehouse;
    protected ItemVariant $variant1;
    protected ItemVariant $variant2;
    protected Supplier $supplier;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Create User
        $this->user = User::create([
            'name' => 'WMS Operator',
            'email' => 'operator_' . uniqid() . '@example.com',
            'password' => bcrypt('password'),
            'role' => 'operator',
        ]);

        // 2. Fetch or Create Warehouses
        $this->warehouse = Warehouse::firstOrCreate(
            ['code' => 'SPAREPART'],
            ['name' => 'Sparepart Warehouse', 'status' => 'ACTIVE']
        );

        $this->otherWarehouse = Warehouse::firstOrCreate(
            ['code' => 'RAW_MATERIAL'],
            ['name' => 'Raw Material Warehouse', 'status' => 'ACTIVE']
        );

        $this->user->warehouses()->syncWithoutDetaching([$this->warehouse->id, $this->otherWarehouse->id]);
        session(['active_warehouse_id' => $this->warehouse->id]);

        // 3. Create Supplier
        $this->supplier = Supplier::create([
            'name' => 'PT. METAL PRIMA INDONESIA',
        ]);

        // 4. Create master items and variants
        $item1 = Item::create(['name' => 'AMS90 Special Alloy']);
        $this->variant1 = ItemVariant::create([
            'item_id' => $item1->id,
            'erp_code' => '5.01.AMS90',
            'sku' => 'SKU-AMS90',
            'unit' => 'KG',
        ]);

        $item2 = Item::create(['name' => 'Pure Nickel Plate']);
        $this->variant2 = ItemVariant::create([
            'item_id' => $item2->id,
            'erp_code' => '5.01.NICKEL',
            'sku' => 'SKU-NICKEL',
            'unit' => 'KG',
        ]);
    }

    /**
     * TEST 1: Import new PO line -> 1 PO line created.
     */
    public function test_1_import_new_po_line_creates_records()
    {
        $rows = [
            [
                'po_number' => 'PO-TEST-001',
                'supplier_name' => 'PT. METAL PRIMA INDONESIA',
                'po_date' => '2026-08-01',
                'erp_code' => '5.01.AMS90',
                'item_name' => 'AMS90 Special Alloy',
                'department_name' => 'Maintenance',
                'ordered_qty' => 100.0,
                'unit' => 'KG',
            ]
        ];

        $pipeline = new ImportPipelineService();
        $results = $pipeline->process($rows, 'ERP_IMPORT');

        $this->assertEquals(1, $results['success']);
        $this->assertEquals(1, $results['summary']['new_lines']);
        $this->assertEquals(0, $results['summary']['updated_lines']);

        $this->assertDatabaseHas('outstanding_purchase_orders', [
            'warehouse_id' => $this->warehouse->id,
            'po_number' => 'PO-TEST-001',
            'status' => OutstandingPurchaseOrder::STATUS_PENDING,
        ]);

        $po = OutstandingPurchaseOrder::where('po_number', 'PO-TEST-001')->first();
        $this->assertEquals(1, $po->items()->count());

        $item = $po->items()->first();
        $this->assertEquals('5.01.AMS90', $item->erp_code);
        $this->assertEquals(100.0, (float)$item->ordered_qty);
        $this->assertEquals(0.0, (float)$item->received_qty);
        $this->assertEquals(100.0, (float)$item->pending_qty);
        $this->assertEquals('Maintenance', $item->department_name);
    }

    /**
     * TEST 2: Import exact same Excel twice -> Still 1 PO line (Idempotent).
     */
    public function test_2_import_exact_same_excel_twice_is_idempotent()
    {
        $rows = [
            [
                'po_number' => 'PO-TEST-002',
                'supplier_name' => 'PT. METAL PRIMA INDONESIA',
                'po_date' => '2026-08-01',
                'erp_code' => '5.01.AMS90',
                'item_name' => 'AMS90 Special Alloy',
                'ordered_qty' => 100.0,
                'unit' => 'KG',
            ]
        ];

        $pipeline = new ImportPipelineService();
        
        // Pass 1
        $pipeline->process($rows, 'ERP_IMPORT');
        $this->assertEquals(1, OutstandingPurchaseOrderItem::where('erp_code', '5.01.AMS90')->count());

        // Pass 2
        $results2 = $pipeline->process($rows, 'ERP_IMPORT');
        $this->assertEquals(1, $results2['success']);
        $this->assertEquals(0, $results2['summary']['new_lines']);
        $this->assertEquals(1, OutstandingPurchaseOrderItem::where('erp_code', '5.01.AMS90')->count());
    }

    /**
     * TEST 3: Import same PO line when WMS status PENDING -> ERP snapshot updated, No duplicate.
     */
    public function test_3_import_same_po_line_when_pending_updates_erp_snapshot_without_duplicate()
    {
        $pipeline = new ImportPipelineService();

        // Initial setup with 100 KG
        $pipeline->process([[
            'po_number' => 'PO-TEST-003',
            'supplier_name' => 'PT. METAL PRIMA INDONESIA',
            'po_date' => '2026-08-01',
            'erp_code' => '5.01.AMS90',
            'item_name' => 'AMS90 Special Alloy',
            'ordered_qty' => 100.0,
            'unit' => 'KG',
        ]], 'ERP_IMPORT');

        // Second import with modified ordered qty 120 KG and remarks
        $pipeline->process([[
            'po_number' => 'PO-TEST-003',
            'supplier_name' => 'PT. METAL PRIMA INDONESIA',
            'po_date' => '2026-08-01',
            'erp_code' => '5.01.AMS90',
            'item_name' => 'AMS90 Special Alloy',
            'ordered_qty' => 120.0,
            'unit' => 'KG',
            'remarks' => 'ERP Revision 2',
        ]], 'ERP_IMPORT');

        $this->assertEquals(1, OutstandingPurchaseOrderItem::where('erp_code', '5.01.AMS90')->count());
        $item = OutstandingPurchaseOrderItem::where('erp_code', '5.01.AMS90')->first();
        $this->assertEquals(120.0, (float)$item->ordered_qty);
        $this->assertEquals(120.0, (float)$item->erp_ordered_qty);
        $this->assertEquals('ERP Revision 2', $item->remarks);
    }

    /**
     * TEST 4: Import same PO line when WMS status PARTIAL -> WMS receiving state preserved.
     */
    public function test_4_import_same_po_line_when_partial_preserves_wms_state()
    {
        $pipeline = new ImportPipelineService();

        // 1. Initial import 100 KG
        $pipeline->process([[
            'po_number' => 'PO-TEST-004',
            'supplier_name' => 'PT. METAL PRIMA INDONESIA',
            'po_date' => '2026-08-01',
            'erp_code' => '5.01.AMS90',
            'item_name' => 'AMS90 Special Alloy',
            'ordered_qty' => 100.0,
            'unit' => 'KG',
        ]], 'ERP_IMPORT');

        $poItem = OutstandingPurchaseOrderItem::where('erp_code', '5.01.AMS90')->first();

        // 2. Simulate WMS partial receiving of 40 KG
        $poItem->received_qty = 40.0;
        $poItem->save(); // PO status becomes PARTIAL

        $po = $poItem->outstandingPurchaseOrder;
        $this->assertEquals(OutstandingPurchaseOrder::STATUS_PARTIAL, $po->status);
        $this->assertEquals(60.0, (float)$poItem->pending_qty);

        // 3. Stale ERP import arrives saying ordered 100, received 0, outstanding 100
        $pipeline->process([[
            'po_number' => 'PO-TEST-004',
            'supplier_name' => 'PT. METAL PRIMA INDONESIA',
            'po_date' => '2026-08-01',
            'erp_code' => '5.01.AMS90',
            'item_name' => 'AMS90 Special Alloy',
            'ordered_qty' => 100.0,
            'erp_received_qty' => 0.0,
            'erp_outstanding_qty' => 100.0,
            'unit' => 'KG',
        ]], 'ERP_IMPORT');

        // Assert WMS state is strictly preserved
        $poItem->refresh();
        $this->assertEquals(40.0, (float)$poItem->received_qty);
        $this->assertEquals(60.0, (float)$poItem->pending_qty);
        $this->assertEquals('Partial', $poItem->status);

        $po->refresh();
        $this->assertEquals(OutstandingPurchaseOrder::STATUS_PARTIAL, $po->status);
        $this->assertEquals('ERP_BEHIND', $poItem->erp_sync_status);
        $this->assertEquals('ERP_BEHIND', $po->erp_sync_status);
    }

    /**
     * TEST 5: Import same PO line when WMS status COMPLETED -> COMPLETED remains COMPLETED. No duplicate receiving, No inventory mutation.
     */
    public function test_5_import_same_po_line_when_completed_remains_completed()
    {
        $pipeline = new ImportPipelineService();

        // 1. Initial import 100 KG
        $pipeline->process([[
            'po_number' => 'PO-TEST-005',
            'supplier_name' => 'PT. METAL PRIMA INDONESIA',
            'po_date' => '2026-08-01',
            'erp_code' => '5.01.AMS90',
            'item_name' => 'AMS90 Special Alloy',
            'ordered_qty' => 100.0,
            'unit' => 'KG',
        ]], 'ERP_IMPORT');

        $poItem = OutstandingPurchaseOrderItem::where('erp_code', '5.01.AMS90')->first();

        // 2. Simulate WMS complete receiving of 100 KG
        $poItem->received_qty = 100.0;
        $poItem->save(); // PO status becomes CLOSED

        $po = $poItem->outstandingPurchaseOrder;
        $this->assertEquals(OutstandingPurchaseOrder::STATUS_CLOSED, $po->status);
        $this->assertEquals(0.0, (float)$poItem->pending_qty);

        // Count existing stock movements and receiving sessions
        $initialMovements = StockMovement::count();
        $initialSessions = ReceivingSession::count();

        // 3. Stale ERP re-upload says ordered 100, received 0, outstanding 100
        $results = $pipeline->process([[
            'po_number' => 'PO-TEST-005',
            'supplier_name' => 'PT. METAL PRIMA INDONESIA',
            'po_date' => '2026-08-01',
            'erp_code' => '5.01.AMS90',
            'item_name' => 'AMS90 Special Alloy',
            'ordered_qty' => 100.0,
            'erp_received_qty' => 0.0,
            'erp_outstanding_qty' => 100.0,
            'unit' => 'KG',
        ]], 'ERP_IMPORT');

        $this->assertEquals(1, $results['summary']['wms_completed']);
        $this->assertEquals(1, $results['summary']['erp_behind']);

        // Assert COMPLETED state is unchanged
        $poItem->refresh();
        $this->assertEquals(100.0, (float)$poItem->received_qty);
        $this->assertEquals(0.0, (float)$poItem->pending_qty);
        $this->assertEquals('Closed', $poItem->status);

        $po->refresh();
        $this->assertEquals(OutstandingPurchaseOrder::STATUS_CLOSED, $po->status);

        // Zero mutations to receiving sessions or stock movements
        $this->assertEquals($initialMovements, StockMovement::count());
        $this->assertEquals($initialSessions, ReceivingSession::count());
    }

    /**
     * TEST 6: ERP says received 0 while WMS received 100 -> WMS COMPLETED, ERP OUT OF SYNC (ERP_BEHIND).
     */
    public function test_6_erp_unreceived_and_wms_completed_flags_erp_behind()
    {
        $pipeline = new ImportPipelineService();

        $pipeline->process([[
            'po_number' => 'PO-TEST-006',
            'supplier_name' => 'PT. METAL PRIMA INDONESIA',
            'po_date' => '2026-08-01',
            'erp_code' => '5.01.AMS90',
            'item_name' => 'AMS90 Special Alloy',
            'ordered_qty' => 100.0,
            'unit' => 'KG',
        ]], 'ERP_IMPORT');

        $poItem = OutstandingPurchaseOrderItem::where('erp_code', '5.01.AMS90')->first();
        $poItem->received_qty = 100.0;
        $poItem->save();

        $results = $pipeline->process([[
            'po_number' => 'PO-TEST-006',
            'supplier_name' => 'PT. METAL PRIMA INDONESIA',
            'po_date' => '2026-08-01',
            'erp_code' => '5.01.AMS90',
            'item_name' => 'AMS90 Special Alloy',
            'ordered_qty' => 100.0,
            'erp_received_qty' => 0.0,
            'erp_outstanding_qty' => 100.0,
            'unit' => 'KG',
        ]], 'ERP_IMPORT');

        $this->assertEquals(1, $results['summary']['erp_behind']);
        $this->assertNotEmpty($results['summary']['erp_behind_lines']);
        $this->assertEquals('PO-TEST-006', $results['summary']['erp_behind_lines'][0]['po_number']);
        $this->assertEquals(100.0, $results['summary']['erp_behind_lines'][0]['wms_received']);
        $this->assertEquals(0.0, $results['summary']['erp_behind_lines'][0]['erp_received']);

        $poItem->refresh();
        $this->assertTrue($poItem->isErpBehind());
    }

    /**
     * TEST 7: Partial WMS receiving 40/100 followed by stale ERP import -> WMS remains 40/100.
     */
    public function test_7_partial_wms_receiving_followed_by_stale_erp_import()
    {
        $pipeline = new ImportPipelineService();

        $pipeline->process([[
            'po_number' => 'PO-TEST-007',
            'supplier_name' => 'PT. METAL PRIMA INDONESIA',
            'po_date' => '2026-08-01',
            'erp_code' => '5.01.AMS90',
            'item_name' => 'AMS90 Special Alloy',
            'ordered_qty' => 100.0,
            'unit' => 'KG',
        ]], 'ERP_IMPORT');

        $poItem = OutstandingPurchaseOrderItem::where('erp_code', '5.01.AMS90')->first();
        $poItem->received_qty = 40.0;
        $poItem->save();

        // Stale ERP says ordered 100, received 0
        $pipeline->process([[
            'po_number' => 'PO-TEST-007',
            'supplier_name' => 'PT. METAL PRIMA INDONESIA',
            'po_date' => '2026-08-01',
            'erp_code' => '5.01.AMS90',
            'item_name' => 'AMS90 Special Alloy',
            'ordered_qty' => 100.0,
            'erp_received_qty' => 0.0,
            'unit' => 'KG',
        ]], 'ERP_IMPORT');

        $poItem->refresh();
        $this->assertEquals(40.0, (float)$poItem->received_qty);
        $this->assertEquals(60.0, (float)$poItem->pending_qty);

        // Later second receiving receives 60 KG
        $poItem->received_qty = 100.0;
        $poItem->save();

        $poItem->refresh();
        $this->assertEquals(100.0, (float)$poItem->received_qty);
        $this->assertEquals(0.0, (float)$poItem->pending_qty);
        $this->assertEquals('Closed', $poItem->status);
    }

    /**
     * TEST 8: Same PO with one new item -> Existing line updated, New line inserted, No duplicate existing line.
     */
    public function test_8_same_po_with_one_new_item_inserts_new_line_without_duplicate()
    {
        $pipeline = new ImportPipelineService();

        // 1. Initial import with AMS90
        $pipeline->process([[
            'po_number' => 'PO-TEST-008',
            'supplier_name' => 'PT. METAL PRIMA INDONESIA',
            'po_date' => '2026-08-01',
            'erp_code' => '5.01.AMS90',
            'item_name' => 'AMS90 Special Alloy',
            'ordered_qty' => 50.0,
            'unit' => 'KG',
        ]], 'ERP_IMPORT');

        $po = OutstandingPurchaseOrder::where('po_number', 'PO-TEST-008')->first();
        $this->assertEquals(1, $po->items()->count());

        // 2. Subsequent import has AMS90 + NICKEL
        $results = $pipeline->process([
            [
                'po_number' => 'PO-TEST-008',
                'supplier_name' => 'PT. METAL PRIMA INDONESIA',
                'po_date' => '2026-08-01',
                'erp_code' => '5.01.AMS90',
                'item_name' => 'AMS90 Special Alloy',
                'ordered_qty' => 50.0,
                'unit' => 'KG',
            ],
            [
                'po_number' => 'PO-TEST-008',
                'supplier_name' => 'PT. METAL PRIMA INDONESIA',
                'po_date' => '2026-08-01',
                'erp_code' => '5.01.NICKEL',
                'item_name' => 'Pure Nickel Plate',
                'ordered_qty' => 25.0,
                'unit' => 'KG',
            ]
        ], 'ERP_IMPORT');

        $this->assertEquals(1, $results['summary']['new_lines']);
        $this->assertEquals(2, $po->items()->count());
        $this->assertEquals(1, OutstandingPurchaseOrderItem::where('outstanding_purchase_order_id', $po->id)->where('erp_code', '5.01.AMS90')->count());
        $this->assertEquals(1, OutstandingPurchaseOrderItem::where('outstanding_purchase_order_id', $po->id)->where('erp_code', '5.01.NICKEL')->count());
    }

    /**
     * TEST 9: Decimal quantity 0.8 KG is preserved accurately without integer truncation.
     */
    public function test_9_decimal_quantity_is_preserved_accurately()
    {
        $pipeline = new ImportPipelineService();

        $pipeline->process([[
            'po_number' => 'PO-TEST-009',
            'supplier_name' => 'PT. METAL PRIMA INDONESIA',
            'po_date' => '2026-08-01',
            'erp_code' => '5.01.NICKEL',
            'item_name' => 'Pure Nickel Plate',
            'ordered_qty' => 0.8,
            'unit' => 'KG',
        ]], 'ERP_IMPORT');

        $poItem = OutstandingPurchaseOrderItem::where('erp_code', '5.01.NICKEL')->first();
        $this->assertEquals(0.8, (float)$poItem->ordered_qty);
        $this->assertEquals(0.8, (float)$poItem->pending_qty);

        // Simulate partial receipt of 0.3 KG
        $poItem->received_qty = 0.3;
        $poItem->save();

        $poItem->refresh();
        $this->assertEquals(0.3, (float)$poItem->received_qty);
        $this->assertEquals(0.5, (float)$poItem->pending_qty);
    }

    /**
     * TEST 10: Import must not create: StockTransaction, ReceivingSession, ReceivingSessionItem, StockMovement.
     */
    public function test_10_import_never_creates_receiving_or_stock_transactions()
    {
        $txCountBefore = StockTransaction::count();
        $sessionCountBefore = ReceivingSession::count();
        $sessionItemCountBefore = ReceivingSessionItem::count();
        $movementCountBefore = StockMovement::count();

        $pipeline = new ImportPipelineService();
        $pipeline->process([
            [
                'po_number' => 'PO-TEST-010',
                'supplier_name' => 'PT. METAL PRIMA INDONESIA',
                'po_date' => '2026-08-01',
                'erp_code' => '5.01.AMS90',
                'item_name' => 'AMS90 Special Alloy',
                'ordered_qty' => 100.0,
                'unit' => 'KG',
            ],
            [
                'po_number' => 'PO-TEST-010',
                'supplier_name' => 'PT. METAL PRIMA INDONESIA',
                'po_date' => '2026-08-01',
                'erp_code' => '5.01.NICKEL',
                'item_name' => 'Pure Nickel Plate',
                'ordered_qty' => 50.0,
                'unit' => 'KG',
            ]
        ], 'ERP_IMPORT');

        $this->assertEquals($txCountBefore, StockTransaction::count());
        $this->assertEquals($sessionCountBefore, ReceivingSession::count());
        $this->assertEquals($sessionItemCountBefore, ReceivingSessionItem::count());
        $this->assertEquals($movementCountBefore, StockMovement::count());
    }

    /**
     * TEST 11: Existing completed receiving session data remains completely unchanged after import.
     */
    public function test_11_existing_receiving_session_data_remains_unchanged_after_import()
    {
        $pipeline = new ImportPipelineService();

        // 1. Ingest PO
        $pipeline->process([[
            'po_number' => 'PO-TEST-011',
            'supplier_name' => 'PT. METAL PRIMA INDONESIA',
            'po_date' => '2026-08-01',
            'erp_code' => '5.01.AMS90',
            'item_name' => 'AMS90 Special Alloy',
            'ordered_qty' => 100.0,
            'unit' => 'KG',
        ]], 'ERP_IMPORT');

        $po = OutstandingPurchaseOrder::where('po_number', 'PO-TEST-011')->first();
        $poItem = $po->items()->first();

        // 2. Create completed receiving session
        $session = ReceivingSession::create([
            'warehouse_id' => $this->warehouse->id,
            'outstanding_purchase_order_id' => $po->id,
            'status' => ReceivingSession::STATUS_COMPLETED,
            'created_by' => $this->user->id,
            'started_at' => now()->subDay(),
            'completed_at' => now()->subDay(),
            'remarks' => 'Completed Receiving Inspection',
        ]);

        $sessionItem = ReceivingSessionItem::create([
            'receiving_session_id' => $session->id,
            'outstanding_purchase_order_item_id' => $poItem->id,
            'item_variant_id' => $this->variant1->id,
            'expected_qty' => 100.0,
            'received_qty' => 100.0,
            'qty_datang' => 100.0,
            'verification_status' => ReceivingSessionItem::STATUS_VERIFIED,
            'check_result' => 'OK',
        ]);

        $poItem->received_qty = 100.0;
        $poItem->save();

        // 3. Stale ERP upload
        $pipeline->process([[
            'po_number' => 'PO-TEST-011',
            'supplier_name' => 'PT. METAL PRIMA INDONESIA',
            'po_date' => '2026-08-01',
            'erp_code' => '5.01.AMS90',
            'item_name' => 'AMS90 Special Alloy',
            'ordered_qty' => 100.0,
            'erp_received_qty' => 0.0,
            'unit' => 'KG',
        ]], 'ERP_IMPORT');

        // Assert receiving session and session item remained pristine
        $session->refresh();
        $this->assertEquals(ReceivingSession::STATUS_COMPLETED, $session->status);
        $this->assertEquals('Completed Receiving Inspection', $session->remarks);

        $sessionItem->refresh();
        $this->assertEquals(100.0, (float)$sessionItem->received_qty);
        $this->assertEquals('OK', $sessionItem->check_result);
    }

    /**
     * TEST 12: Warehouse/domain isolation remains intact.
     */
    public function test_12_warehouse_domain_isolation_is_strictly_enforced()
    {
        $pipeline = new ImportPipelineService();

        // 1. Import in Warehouse A (SPAREPART)
        session(['active_warehouse_id' => $this->warehouse->id]);
        $pipeline->process([[
            'po_number' => 'PO-SHARED-001',
            'supplier_name' => 'PT. METAL PRIMA INDONESIA',
            'po_date' => '2026-08-01',
            'erp_code' => '5.01.AMS90',
            'item_name' => 'AMS90 Special Alloy',
            'ordered_qty' => 50.0,
            'unit' => 'KG',
        ]], 'ERP_IMPORT');

        // 2. Import in Warehouse B (RAW_MATERIAL)
        session(['active_warehouse_id' => $this->otherWarehouse->id]);
        $pipeline->process([[
            'po_number' => 'PO-SHARED-001',
            'supplier_name' => 'PT. METAL PRIMA INDONESIA',
            'po_date' => '2026-08-01',
            'erp_code' => '5.01.AMS90',
            'item_name' => 'AMS90 Special Alloy',
            'ordered_qty' => 75.0,
            'unit' => 'KG',
        ]], 'ERP_IMPORT');

        // Verify isolation
        $poA = OutstandingPurchaseOrder::where('warehouse_id', $this->warehouse->id)->where('po_number', 'PO-SHARED-001')->first();
        $poB = OutstandingPurchaseOrder::where('warehouse_id', $this->otherWarehouse->id)->where('po_number', 'PO-SHARED-001')->first();

        $this->assertNotNull($poA);
        $this->assertNotNull($poB);
        $this->assertNotEquals($poA->id, $poB->id);
        $this->assertEquals(50.0, (float)$poA->items()->first()->ordered_qty);
        $this->assertEquals(75.0, (float)$poB->items()->first()->ordered_qty);
    }
}
