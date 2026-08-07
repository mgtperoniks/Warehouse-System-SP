<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Warehouse;
use App\Models\Item;
use App\Models\ItemVariant;
use App\Models\OutstandingPurchaseOrder;
use App\Models\OutstandingPurchaseOrderItem;
use App\Services\OutstandingPurchase\ImportPipelineService;
use App\Livewire\OutstandingPurchase\OutstandingPurchaseIndexPage;
use App\Livewire\OutstandingPurchase\OutstandingPurchaseShowPage;
use App\Livewire\OutstandingPurchase\OutstandingPurchaseImportPage;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

class OutstandingPurchaseTest extends TestCase
{
    use DatabaseTransactions;

    protected User $user;
    protected Warehouse $warehouse;
    protected ItemVariant $variant1;
    protected ItemVariant $variant2;

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

        // 2. Fetch or Create Warehouse
        $this->warehouse = Warehouse::firstOrCreate(
            ['code' => 'SPAREPART'],
            ['name' => 'Sparepart Warehouse', 'status' => 'ACTIVE']
        );

        $this->user->warehouses()->syncWithoutDetaching([$this->warehouse->id]);

        // Put active warehouse in session
        session(['active_warehouse_id' => $this->warehouse->id]);

        // 3. Create items/variants for import catalog mapping
        $item = Item::create(['name' => 'Test Sparepart Item']);
        $this->variant1 = ItemVariant::create([
            'item_id' => $item->id,
            'erp_code' => '5.01.abc.001',
            'sku' => 'SKU-ABC-001',
            'unit' => 'PCS',
        ]);

        $this->variant2 = ItemVariant::create([
            'item_id' => $item->id,
            'erp_code' => '5.01.abc.002',
            'sku' => 'SKU-ABC-002',
            'unit' => 'PCS',
        ]);
    }

    /**
     * Test basic import pipeline creates new PO and items.
     */
    public function test_import_new_po_creates_records_successfully()
    {
        $rows = [
            [
                'po_number' => 'PO-2026-0001',
                'supplier_name' => 'PT Supplier Utama',
                'supplier_code' => 'SU001',
                'po_date' => '2026-08-01',
                'expected_date' => '2026-08-15',
                'erp_code' => '5.01.abc.001',
                'item_name' => 'Test Sparepart Item A',
                'ordered_qty' => 100,
                'unit' => 'PCS',
                'line_number' => 1,
                'remarks' => 'First line remarks',
            ],
            [
                'po_number' => 'PO-2026-0001',
                'supplier_name' => 'PT Supplier Utama',
                'supplier_code' => 'SU001',
                'po_date' => '2026-08-01',
                'expected_date' => '2026-08-15',
                'erp_code' => '5.01.abc.002',
                'item_name' => 'Test Sparepart Item B',
                'ordered_qty' => 200,
                'unit' => 'PCS',
                'line_number' => 2,
                'remarks' => 'Second line remarks',
            ]
        ];

        $pipeline = new ImportPipelineService();
        $results = $pipeline->process($rows, 'ERP_IMPORT');

        $this->assertEquals(2, $results['success']);
        $this->assertEquals(0, $results['failed']);

        // Assert PO Header exists
        $this->assertDatabaseHas('outstanding_purchase_orders', [
            'warehouse_id' => $this->warehouse->id,
            'po_number' => 'PO-2026-0001',
            'supplier_name_snapshot' => 'PT Supplier Utama',
            'supplier_code_snapshot' => 'SU001',
            'status' => OutstandingPurchaseOrder::STATUS_PENDING,
            'is_archived' => false,
        ]);

        $po = OutstandingPurchaseOrder::where('po_number', 'PO-2026-0001')->first();

        // Assert PO Items exist
        $this->assertDatabaseHas('outstanding_purchase_order_items', [
            'outstanding_purchase_order_id' => $po->id,
            'item_variant_id' => $this->variant1->id,
            'erp_code' => '5.01.abc.001',
            'item_name_snapshot' => 'Test Sparepart Item A',
            'ordered_qty' => 100,
            'received_qty' => 0,
        ]);

        $this->assertDatabaseHas('outstanding_purchase_order_items', [
            'outstanding_purchase_order_id' => $po->id,
            'item_variant_id' => $this->variant2->id,
            'erp_code' => '5.01.abc.002',
            'item_name_snapshot' => 'Test Sparepart Item B',
            'ordered_qty' => 200,
            'received_qty' => 0,
        ]);
    }

    /**
     * Test duplicate imports are updated and not duplicated.
     */
    public function test_duplicate_import_updates_existing_records_without_duplication()
    {
        $rows1 = [
            [
                'po_number' => 'PO-2026-0002',
                'supplier_name' => 'PT Supplier Utama',
                'supplier_code' => 'SU001',
                'po_date' => '2026-08-01',
                'expected_date' => '2026-08-15',
                'erp_code' => '5.01.abc.001',
                'item_name' => 'Test Sparepart Item A',
                'ordered_qty' => 100,
                'unit' => 'PCS',
                'line_number' => 1,
                'remarks' => 'First run remarks',
            ]
        ];

        $pipeline = new ImportPipelineService();
        $pipeline->process($rows1, 'ERP_IMPORT');

        // Verify initial setup
        $this->assertEquals(1, OutstandingPurchaseOrder::where('po_number', 'PO-2026-0002')->count());
        $this->assertEquals(1, OutstandingPurchaseOrderItem::where('erp_code', '5.01.abc.001')->count());

        // Sync again with modifications
        $rows2 = [
            [
                'po_number' => 'PO-2026-0002',
                'supplier_name' => 'PT Supplier Utama Updated', // New Supplier Snapshot
                'supplier_code' => 'SU001-A',
                'po_date' => '2026-08-01',
                'expected_date' => '2026-08-20', // New expected date
                'erp_code' => '5.01.abc.001',
                'item_name' => 'Test Sparepart Item A',
                'ordered_qty' => 150, // Updated ordered qty
                'unit' => 'PCS',
                'line_number' => 1,
                'remarks' => 'Second run remarks updated',
            ]
        ];

        $pipeline->process($rows2, 'ERP_IMPORT');

        // Assert no duplicates created
        $this->assertEquals(1, OutstandingPurchaseOrder::where('po_number', 'PO-2026-0002')->count());
        $this->assertEquals(1, OutstandingPurchaseOrderItem::where('erp_code', '5.01.abc.001')->count());

        // Verify updates applied
        $po = OutstandingPurchaseOrder::where('po_number', 'PO-2026-0002')->first();
        $this->assertEquals('PT Supplier Utama Updated', $po->supplier_name_snapshot);
        $this->assertEquals('SU001-A', $po->supplier_code_snapshot);
        $this->assertEquals('2026-08-20', $po->expected_date->format('Y-m-d'));

        $item = $po->items()->first();
        $this->assertEquals(150, $item->ordered_qty);
        $this->assertEquals('Second run remarks updated', $item->remarks);
    }

    /**
     * Test missing POs in import are archived.
     */
    public function test_missing_po_in_subsequent_imports_is_archived()
    {
        // 1. Import PO-A
        $rows1 = [
            [
                'po_number' => 'PO-A',
                'supplier_name' => 'PT Supplier A',
                'supplier_code' => 'SU00A',
                'po_date' => '2026-08-01',
                'erp_code' => '5.01.abc.001',
                'item_name' => 'Test Item',
                'ordered_qty' => 10,
            ]
        ];

        $pipeline = new ImportPipelineService();
        $pipeline->process($rows1, 'ERP_IMPORT');

        $poA = OutstandingPurchaseOrder::where('po_number', 'PO-A')->first();
        $this->assertFalse($poA->is_archived);

        // 2. Import PO-B (PO-A is missing from this new import batch)
        $rows2 = [
            [
                'po_number' => 'PO-B',
                'supplier_name' => 'PT Supplier B',
                'supplier_code' => 'SU00B',
                'po_date' => '2026-08-02',
                'erp_code' => '5.01.abc.001',
                'item_name' => 'Test Item',
                'ordered_qty' => 20,
            ]
        ];

        $pipeline->process($rows2, 'ERP_IMPORT');

        // PO-A should now be archived
        $poA->refresh();
        $this->assertTrue($poA->is_archived);

        // PO-B should be active
        $poB = OutstandingPurchaseOrder::where('po_number', 'PO-B')->first();
        $this->assertFalse($poB->is_archived);
    }

    /**
     * Test pending quantity accessor calculations.
     */
    public function test_pending_quantity_accessor_calculation()
    {
        $po = OutstandingPurchaseOrder::create([
            'warehouse_id' => $this->warehouse->id,
            'supplier_name_snapshot' => 'Supplier',
            'po_number' => 'PO-TEST',
            'po_date' => '2026-08-01',
            'status' => OutstandingPurchaseOrder::STATUS_PENDING,
        ]);

        $item = OutstandingPurchaseOrderItem::create([
            'outstanding_purchase_order_id' => $po->id,
            'erp_code' => '5.01.abc.001',
            'item_name_snapshot' => 'Item',
            'ordered_qty' => 10,
            'received_qty' => 4,
            'unit' => 'PCS',
        ]);

        // Pending Qty = 10 - 4 = 6
        $this->assertEquals(6, $item->pending_qty);

        // Update received_qty to exceed ordered_qty
        $item->update(['received_qty' => 12]);
        // Pending Qty = 10 - 12 = -2 -> capped to 0
        $this->assertEquals(0, $item->pending_qty);
    }

    /**
     * Test status calculation logic.
     */
    public function test_po_status_calculation_logic()
    {
        $po = OutstandingPurchaseOrder::create([
            'warehouse_id' => $this->warehouse->id,
            'supplier_name_snapshot' => 'Supplier',
            'po_number' => 'PO-STATUS-TEST',
            'po_date' => '2026-08-01',
            'status' => OutstandingPurchaseOrder::STATUS_PENDING,
        ]);

        $item1 = OutstandingPurchaseOrderItem::create([
            'outstanding_purchase_order_id' => $po->id,
            'erp_code' => '5.01.abc.001',
            'item_name_snapshot' => 'Item A',
            'ordered_qty' => 10,
            'received_qty' => 0,
        ]);

        $item2 = OutstandingPurchaseOrderItem::create([
            'outstanding_purchase_order_id' => $po->id,
            'erp_code' => '5.01.abc.002',
            'item_name_snapshot' => 'Item B',
            'ordered_qty' => 20,
            'received_qty' => 0,
        ]);

        // All items received_qty is 0 -> Status is Pending
        $po->refresh();
        $this->assertEquals(OutstandingPurchaseOrder::STATUS_PENDING, $po->status);

        // Partially receive item 1
        $item1->update(['received_qty' => 5]);
        $po->refresh();
        $this->assertEquals(OutstandingPurchaseOrder::STATUS_PARTIAL, $po->status);

        // Fully receive item 1
        $item1->update(['received_qty' => 10]);
        $po->refresh();
        $this->assertEquals(OutstandingPurchaseOrder::STATUS_PARTIAL, $po->status); // Item 2 still pending

        // Fully receive item 2
        $item2->update(['received_qty' => 20]);
        $po->refresh();
        $this->assertEquals(OutstandingPurchaseOrder::STATUS_CLOSED, $po->status); // All items closed
    }

    /**
     * Test Livewire Index Page sorting, search, filtering.
     */
    public function test_index_page_list_search_filter_sort()
    {
        // Create multiple POs
        $po1 = OutstandingPurchaseOrder::create([
            'warehouse_id' => $this->warehouse->id,
            'supplier_name_snapshot' => 'Indo Supplier A',
            'po_number' => 'PO-AAA',
            'po_date' => '2026-08-01',
            'status' => OutstandingPurchaseOrder::STATUS_PENDING,
            'is_archived' => false,
        ]);

        $po2 = OutstandingPurchaseOrder::create([
            'warehouse_id' => $this->warehouse->id,
            'supplier_name_snapshot' => 'Indo Supplier B',
            'po_number' => 'PO-BBB',
            'po_date' => '2026-08-05', // Newer date
            'status' => OutstandingPurchaseOrder::STATUS_CLOSED,
            'is_archived' => false,
        ]);

        $po3 = OutstandingPurchaseOrder::create([
            'warehouse_id' => $this->warehouse->id,
            'supplier_name_snapshot' => 'Other Supplier C',
            'po_number' => 'PO-CCC',
            'po_date' => '2026-08-03',
            'status' => OutstandingPurchaseOrder::STATUS_PENDING,
            'is_archived' => true, // Archived
        ]);

        $this->actingAs($this->user);

        // 1. Test Default Render (Active non-archived sorted by Pending first, then date DESC, then PO number: po1 first, then po2)
        Livewire::test(OutstandingPurchaseIndexPage::class)
            ->assertViewHas('orders', function ($orders) use ($po1, $po2, $po3) {
                $ids = collect($orders->items())->pluck('id')->toArray();
                $idx1 = array_search($po1->id, $ids);
                $idx2 = array_search($po2->id, $ids);
                return $idx1 !== false && $idx2 !== false && $idx1 < $idx2 && !in_array($po3->id, $ids);
            });

        // 2. Test Search by PO Number
        Livewire::test(OutstandingPurchaseIndexPage::class, ['search' => 'PO-AAA'])
            ->assertViewHas('orders', function ($orders) use ($po1) {
                $ids = collect($orders->items())->pluck('id')->toArray();
                return in_array($po1->id, $ids);
            });

        // 3. Test Search by Supplier
        Livewire::test(OutstandingPurchaseIndexPage::class, ['search' => 'Indo'])
            ->assertViewHas('orders', function ($orders) use ($po1, $po2) {
                $ids = collect($orders->items())->pluck('id')->toArray();
                return in_array($po1->id, $ids) && in_array($po2->id, $ids);
            });

        // 4. Test Filter status Closed
        Livewire::test(OutstandingPurchaseIndexPage::class, ['filterStatus' => 'closed'])
            ->assertViewHas('orders', function ($orders) use ($po2) {
                $ids = collect($orders->items())->pluck('id')->toArray();
                return in_array($po2->id, $ids);
            });

        // 5. Test Filter status Archived
        Livewire::test(OutstandingPurchaseIndexPage::class, ['filterStatus' => 'archived'])
            ->assertViewHas('orders', function ($orders) use ($po3) {
                $ids = collect($orders->items())->pluck('id')->toArray();
                return in_array($po3->id, $ids);
            });
    }

    /**
     * Test Livewire Detail Show Page.
     */
    public function test_show_detail_page_renders_accurately()
    {
        $po = OutstandingPurchaseOrder::create([
            'warehouse_id' => $this->warehouse->id,
            'supplier_name_snapshot' => 'PT Supplier Utama',
            'po_number' => 'PO-12345',
            'po_date' => '2026-08-01',
            'status' => OutstandingPurchaseOrder::STATUS_PENDING,
        ]);

        $item = OutstandingPurchaseOrderItem::create([
            'outstanding_purchase_order_id' => $po->id,
            'erp_code' => '5.01.abc.001',
            'item_name_snapshot' => 'Detail Item Name',
            'ordered_qty' => 10,
        ]);

        $this->actingAs($this->user);

        Livewire::test(OutstandingPurchaseShowPage::class, ['id' => $po->id])
            ->assertSee('PO-12345')
            ->assertSee('PT Supplier Utama')
            ->assertSee('Detail Item Name')
            ->assertSee('5.01.abc.001');
    }

    /**
     * Test Livewire Import Page saves Handsontable data.
     */
    public function test_import_page_saves_handsontable_data_correctly()
    {
        $this->actingAs($this->user);

        $hotData = [
            ['SH01', 'Supplier HOT', '', '', 'PO-HOT-01', '2026-08-01', '5.01.abc.001', 'HOT Item', 'PCS', 50, 0, 0, 0, 50]
        ];

        Livewire::test(OutstandingPurchaseImportPage::class)
            ->call('saveFromHandsontable', $hotData)
            ->assertDispatched('importCompleted');

        $this->assertDatabaseHas('outstanding_purchase_orders', [
            'po_number' => 'PO-HOT-01',
            'supplier_name_snapshot' => 'Supplier HOT',
        ]);
        $this->assertDatabaseHas('outstanding_purchase_order_items', [
            'erp_code' => '5.01.abc.001',
            'ordered_qty' => 50,
        ]);
    }

    /**
     * Test PO readiness calculation and metrics accessors.
     */
    public function test_po_readiness_calculation_and_counts()
    {
        // 1. PO with all matched items
        $po1 = OutstandingPurchaseOrder::create([
            'warehouse_id' => $this->warehouse->id,
            'supplier_name_snapshot' => 'PT Supplier A',
            'po_number' => 'PO-READ-01',
            'po_date' => '2026-08-01',
            'status' => OutstandingPurchaseOrder::STATUS_PENDING,
        ]);

        OutstandingPurchaseOrderItem::create([
            'outstanding_purchase_order_id' => $po1->id,
            'item_variant_id' => $this->variant1->id,
            'erp_code' => $this->variant1->erp_code,
            'item_name_snapshot' => 'Item 1',
            'ordered_qty' => 10,
        ]);

        $this->assertEquals(OutstandingPurchaseOrder::READINESS_READY, $po1->receiving_readiness);
        $this->assertEquals('READY', $po1->receiving_readiness_label);
        $this->assertEquals(1, $po1->catalog_matched_count);
        $this->assertEquals(0, $po1->catalog_missing_count);
        $this->assertEquals(1, $po1->total_line_count);

        // 2. PO with unmatched items
        $po2 = OutstandingPurchaseOrder::create([
            'warehouse_id' => $this->warehouse->id,
            'supplier_name_snapshot' => 'PT Supplier B',
            'po_number' => 'PO-READ-02',
            'po_date' => '2026-08-01',
            'status' => OutstandingPurchaseOrder::STATUS_PENDING,
        ]);

        OutstandingPurchaseOrderItem::create([
            'outstanding_purchase_order_id' => $po2->id,
            'item_variant_id' => null, // Unmatched
            'erp_code' => '5.01.unmatched.code',
            'item_name_snapshot' => 'Unmatched Item',
            'ordered_qty' => 15,
        ]);

        $this->assertEquals(OutstandingPurchaseOrder::READINESS_NEEDS_CATALOG, $po2->receiving_readiness);
        $this->assertEquals('NEEDS CATALOG', $po2->receiving_readiness_label);
        $this->assertEquals(0, $po2->catalog_matched_count);
        $this->assertEquals(1, $po2->catalog_missing_count);
        $this->assertEquals(1, $po2->total_line_count);
    }

    /**
     * Test Dashboard counts and interactive filters.
     */
    public function test_dashboard_readiness_filters_and_counts()
    {
        // PO 1: Ready to Receive
        $po1 = OutstandingPurchaseOrder::create([
            'warehouse_id' => $this->warehouse->id,
            'supplier_name_snapshot' => 'PT Supplier A',
            'po_number' => 'PO-DASH-01',
            'po_date' => '2026-08-01',
            'status' => OutstandingPurchaseOrder::STATUS_PENDING,
        ]);
        OutstandingPurchaseOrderItem::create([
            'outstanding_purchase_order_id' => $po1->id,
            'item_variant_id' => $this->variant1->id,
            'erp_code' => $this->variant1->erp_code,
            'item_name_snapshot' => 'Item 1',
            'ordered_qty' => 10,
        ]);

        // PO 2: Needs Catalog
        $po2 = OutstandingPurchaseOrder::create([
            'warehouse_id' => $this->warehouse->id,
            'supplier_name_snapshot' => 'PT Supplier B',
            'po_number' => 'PO-DASH-02',
            'po_date' => '2026-08-02',
            'status' => OutstandingPurchaseOrder::STATUS_PENDING,
        ]);
        OutstandingPurchaseOrderItem::create([
            'outstanding_purchase_order_id' => $po2->id,
            'item_variant_id' => null,
            'erp_code' => '5.01.unmatched.code',
            'item_name_snapshot' => 'Item 2',
            'ordered_qty' => 10,
        ]);

        $this->actingAs($this->user);

        // Verify summary counts are calculated correctly
        Livewire::test(OutstandingPurchaseIndexPage::class)
            ->assertViewHas('counts', function ($counts) {
                return $counts['ready'] >= 1 && $counts['needs_catalog'] >= 1;
            });

        // Filter by Ready to Receive
        Livewire::test(OutstandingPurchaseIndexPage::class, ['filterStatus' => 'ready_to_receive'])
            ->assertViewHas('orders', function ($orders) use ($po1) {
                $ids = collect($orders->items())->pluck('id')->toArray();
                return in_array($po1->id, $ids);
            });

        // Filter by Needs Catalog
        Livewire::test(OutstandingPurchaseIndexPage::class, ['filterStatus' => 'needs_catalog'])
            ->assertViewHas('orders', function ($orders) use ($po2) {
                $ids = collect($orders->items())->pluck('id')->toArray();
                return in_array($po2->id, $ids);
            });
    }

    /**
     * Test Detail Show Page button states and create catalog link actions.
     */
    public function test_detail_page_button_states_and_shortcuts()
    {
        $this->actingAs($this->user);

        // PO 1: Ready to Receive -> should show enabled button
        $po1 = OutstandingPurchaseOrder::create([
            'warehouse_id' => $this->warehouse->id,
            'supplier_name_snapshot' => 'Supplier A',
            'po_number' => 'PO-DETAIL-01',
            'po_date' => '2026-08-01',
            'status' => OutstandingPurchaseOrder::STATUS_PENDING,
        ]);
        OutstandingPurchaseOrderItem::create([
            'outstanding_purchase_order_id' => $po1->id,
            'item_variant_id' => $this->variant1->id,
            'erp_code' => $this->variant1->erp_code,
            'item_name_snapshot' => 'Item 1',
            'ordered_qty' => 10,
        ]);

        Livewire::test(OutstandingPurchaseShowPage::class, ['id' => $po1->id])
            ->assertSee('READY FOR RECEIVING')
            ->assertDontSee('COMPLETE ITEM CATALOG FIRST');

        // PO 2: Needs Catalog -> should show disabled button and create catalog shortcut link
        $po2 = OutstandingPurchaseOrder::create([
            'warehouse_id' => $this->warehouse->id,
            'supplier_name_snapshot' => 'Supplier B',
            'po_number' => 'PO-DETAIL-02',
            'po_date' => '2026-08-02',
            'status' => OutstandingPurchaseOrder::STATUS_PENDING,
        ]);
        OutstandingPurchaseOrderItem::create([
            'outstanding_purchase_order_id' => $po2->id,
            'item_variant_id' => null,
            'erp_code' => '5.01.unmatched.code',
            'item_name_snapshot' => 'Unmatched Item A',
            'ordered_qty' => 10,
            'unit' => 'PCS',
        ]);

        Livewire::test(OutstandingPurchaseShowPage::class, ['id' => $po2->id])
            ->assertSee('COMPLETE ITEM CATALOG FIRST')
            ->assertSee('CREATE CATALOG')
            ->assertSee('5.01.unmatched.code')
            ->assertSee('Unmatched Item A');
    }

    /**
     * Test the complete flow: import PO with unmatched item -> create catalog -> refresh -> readiness READY.
     */
    public function test_full_validation_workflow()
    {
        $this->actingAs($this->user);

        // 1. Import a new PO with an unmatched item
        $hotData = [
            ['SH01', 'Supplier HOT', '', '', 'PO-FLOW-001', '2026-08-01', '5.01.unmatched.code', 'Unmatched Item Name', 'PCS', 50, 0, 0, 0, 50]
        ];

        Livewire::test(OutstandingPurchaseImportPage::class)
            ->call('saveFromHandsontable', $hotData)
            ->assertDispatched('importCompleted');

        $po = OutstandingPurchaseOrder::where('po_number', 'PO-FLOW-001')->first();
        $this->assertEquals(OutstandingPurchaseOrder::READINESS_NEEDS_CATALOG, $po->receiving_readiness);

        // 2. Simulate creating the Item Variant master record
        $item = Item::create(['name' => 'Unmatched Item Name']);
        $newVariant = ItemVariant::create([
            'item_id' => $item->id,
            'erp_code' => '5.01.unmatched.code',
            'sku' => 'SKU-UNMATCHED',
            'unit' => 'PCS',
        ]);

        // 3. Trigger healing of variant mapping
        $po->healVariantMappings();
        $po->refresh();

        // 4. Assert readiness switches to READY
        $this->assertEquals(OutstandingPurchaseOrder::READINESS_READY, $po->receiving_readiness);

        // 5. Verify the detail page now renders with enabled button
        Livewire::test(OutstandingPurchaseShowPage::class, ['id' => $po->id])
            ->assertSee('READY FOR RECEIVING')
            ->assertDontSee('COMPLETE ITEM CATALOG FIRST');
    }

    /**
     * Test searching by nested ERP Code and Item Name.
     */
    public function test_nested_search_by_erp_code_and_item_name()
    {
        $this->actingAs($this->user);

        // PO 1
        $po1 = OutstandingPurchaseOrder::create([
            'warehouse_id' => $this->warehouse->id,
            'supplier_name_snapshot' => 'PT Supplier A',
            'po_number' => 'PO-NESTED-A',
            'po_date' => '2026-08-01',
            'status' => OutstandingPurchaseOrder::STATUS_PENDING,
        ]);
        OutstandingPurchaseOrderItem::create([
            'outstanding_purchase_order_id' => $po1->id,
            'erp_code' => '5.01.nested.001',
            'item_name_snapshot' => 'Nested Special Item X',
            'ordered_qty' => 10,
        ]);

        // PO 2
        $po2 = OutstandingPurchaseOrder::create([
            'warehouse_id' => $this->warehouse->id,
            'supplier_name_snapshot' => 'PT Supplier B',
            'po_number' => 'PO-NESTED-B',
            'po_date' => '2026-08-01',
            'status' => OutstandingPurchaseOrder::STATUS_PENDING,
        ]);
        OutstandingPurchaseOrderItem::create([
            'outstanding_purchase_order_id' => $po2->id,
            'erp_code' => '5.01.nested.002',
            'item_name_snapshot' => 'Nested Common Item Y',
            'ordered_qty' => 20,
        ]);

        // Search by nested ERP Code
        Livewire::test(OutstandingPurchaseIndexPage::class, ['search' => 'nested.001'])
            ->assertViewHas('orders', function ($orders) use ($po1) {
                return count($orders) === 1 && $orders->first()->id === $po1->id;
            });

        // Search by nested Item Name
        Livewire::test(OutstandingPurchaseIndexPage::class, ['search' => 'Common'])
            ->assertViewHas('orders', function ($orders) use ($po2) {
                return count($orders) === 1 && $orders->first()->id === $po2->id;
            });
    }

    /**
     * Test sorting order logic: Pending first, Newer PO Date, PO Number.
     */
    public function test_sorting_order()
    {
        $this->actingAs($this->user);

        // PO 1: Closed, date = 2026-08-01
        $po1 = OutstandingPurchaseOrder::create([
            'warehouse_id' => $this->warehouse->id,
            'supplier_name_snapshot' => 'Supplier',
            'po_number' => 'PO-Z',
            'po_date' => '2026-08-01',
            'status' => OutstandingPurchaseOrder::STATUS_CLOSED,
        ]);

        // PO 2: Pending, date = 2026-08-02, number = PO-B
        $po2 = OutstandingPurchaseOrder::create([
            'warehouse_id' => $this->warehouse->id,
            'supplier_name_snapshot' => 'Supplier',
            'po_number' => 'PO-B',
            'po_date' => '2026-08-02',
            'status' => OutstandingPurchaseOrder::STATUS_PENDING,
        ]);

        // PO 3: Pending, date = 2026-08-01
        $po3 = OutstandingPurchaseOrder::create([
            'warehouse_id' => $this->warehouse->id,
            'supplier_name_snapshot' => 'Supplier',
            'po_number' => 'PO-C',
            'po_date' => '2026-08-01',
            'status' => OutstandingPurchaseOrder::STATUS_PENDING,
        ]);

        // PO 4: Pending, date = 2026-08-02, number = PO-A
        $po4 = OutstandingPurchaseOrder::create([
            'warehouse_id' => $this->warehouse->id,
            'supplier_name_snapshot' => 'Supplier',
            'po_number' => 'PO-A',
            'po_date' => '2026-08-02',
            'status' => OutstandingPurchaseOrder::STATUS_PENDING,
        ]);

        // Expected sorted sequence: PO4 (Pending, 08-02, PO-A), PO2 (Pending, 08-02, PO-B), PO3 (Pending, 08-01), PO1 (Closed, 08-01)
        Livewire::test(OutstandingPurchaseIndexPage::class)
            ->assertViewHas('orders', function ($orders) use ($po1, $po2, $po3, $po4) {
                $ids = collect($orders->items())->pluck('id')->toArray();
                $idx4 = array_search($po4->id, $ids);
                $idx2 = array_search($po2->id, $ids);
                $idx3 = array_search($po3->id, $ids);
                $idx1 = array_search($po1->id, $ids);
                return $idx4 !== false && $idx2 !== false && $idx3 !== false && $idx1 !== false 
                    && $idx4 < $idx2 && $idx2 < $idx3 && $idx3 < $idx1;
            });
    }

    /**
     * Test the hierarchical catalog matching engine scenarios.
     */
    public function test_catalog_matching_engine_scenarios()
    {
        $this->actingAs($this->user);

        $idSuffix = uniqid();
        $code1 = '5.16.TB.8M0776_exact_' . $idSuffix;
        $code2_db = '16.TB.8M0776_suffix_' . $idSuffix;
        $code2_input = '7.' . $code2_db;
        $code3 = '5.99.xyz.999_name_' . $idSuffix;

        // Setup Item Variant for Scenario 1: Exact Match
        $item1 = Item::create(['name' => 'Exact Match Item ' . $idSuffix]);
        $v1 = ItemVariant::create([
            'item_id' => $item1->id,
            'erp_code' => $code1,
            'sku' => 'SKU-E1-' . $idSuffix,
            'unit' => 'PCS',
        ]);

        // Setup Item Variant for Scenario 2: Suffix Match (without prefix family)
        $item2 = Item::create(['name' => 'Suffix Match Item ' . $idSuffix]);
        $v2 = ItemVariant::create([
            'item_id' => $item2->id,
            'erp_code' => $code2_db,
            'sku' => 'SKU-E2-' . $idSuffix,
            'unit' => 'PCS',
        ]);

        // Setup Item Variant for Scenario 3: Name Match
        $item3 = Item::create(['name' => 'Special Named Item ' . $idSuffix]);
        $v3 = ItemVariant::create([
            'item_id' => $item3->id,
            'erp_code' => $code3,
            'sku' => 'SKU-E3-' . $idSuffix,
            'unit' => 'PCS',
        ]);

        // Scenario 1: Exact ERP Match
        $res1 = ItemVariant::resolveVariant($code1, 'Some Name');
        $this->assertNotNull($res1);
        $this->assertEquals($v1->id, $res1->id);

        // Scenario 2: Match melalui ERP tanpa family prefix
        $res2 = ItemVariant::resolveVariant($code2_input, 'Other Name');
        $this->assertNotNull($res2);
        $this->assertEquals($v2->id, $res2->id);

        // Scenario 3: Match melalui Item Name
        $res3 = ItemVariant::resolveVariant('5.88.different.code.' . $idSuffix, 'Special Named Item ' . $idSuffix);
        $this->assertNotNull($res3);
        $this->assertEquals($v3->id, $res3->id);

        // Scenario 4: Item benar-benar tidak ditemukan sehingga Needs Catalog
        $res4 = ItemVariant::resolveVariant('5.99.missing.code.' . $idSuffix, 'Missing Named Item ' . $idSuffix);
        $this->assertNull($res4);
    }
}
