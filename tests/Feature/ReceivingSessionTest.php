<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Warehouse;
use App\Models\Item;
use App\Models\ItemVariant;
use App\Models\Supplier;
use App\Models\Location;
use App\Models\OutstandingPurchaseOrder;
use App\Models\OutstandingPurchaseOrderItem;
use App\Models\ReceivingSession;
use App\Models\ReceivingSessionItem;
use App\Models\StockMovement;
use App\Models\Bin;
use App\Livewire\OutstandingPurchase\OutstandingPurchaseShowPage;
use App\Livewire\Receiving\ReceivingSessionPage;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

class ReceivingSessionTest extends TestCase
{
    use DatabaseTransactions;

    protected User $user;
    protected Warehouse $warehouse;
    protected Supplier $supplier;
    protected ItemVariant $variant;

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

        // Set active warehouse context
        session(['active_warehouse_id' => $this->warehouse->id]);

        // 3. Create Supplier
        $this->supplier = Supplier::create([
            'code' => 'SU001',
            'name' => 'Test Supplier',
        ]);

        // 4. Create Item and Variant
        $item = Item::create(['name' => 'Test Item']);
        $this->variant = ItemVariant::create([
            'item_id' => $item->id,
            'erp_code' => '5.01.abc.001',
            'sku' => 'SKU-ABC-001',
            'unit' => 'PCS',
        ]);
    }

    /**
     * Helper to create a basic valid PO and its items.
     */
    protected function createValidPO($poNumber = 'PO-TEST-123', $itemCount = 1)
    {
        $po = OutstandingPurchaseOrder::create([
            'warehouse_id' => $this->warehouse->id,
            'supplier_id' => $this->supplier->id,
            'supplier_name_snapshot' => $this->supplier->name,
            'po_number' => $poNumber,
            'po_date' => now(),
            'status' => OutstandingPurchaseOrder::STATUS_PENDING,
        ]);

        $items = [];
        for ($i = 1; $i <= $itemCount; $i++) {
            $items[] = OutstandingPurchaseOrderItem::create([
                'outstanding_purchase_order_id' => $po->id,
                'item_variant_id' => $this->variant->id,
                'erp_code' => $this->variant->erp_code,
                'item_name_snapshot' => "Item Variant {$i}",
                'ordered_qty' => 10,
            ]);
        }

        return [$po, $items];
    }

    /**
     * 1. READY PO can create session.
     */
    public function test_ready_po_can_create_session()
    {
        $this->actingAs($this->user);

        [$po, $items] = $this->createValidPO('PO-READY-1');

        $this->assertEquals('READY', $po->receiving_readiness_label);

        Livewire::test(OutstandingPurchaseShowPage::class, ['id' => $po->id])
            ->call('startReceivingSession')
            ->assertRedirect();

        $this->assertDatabaseHas('receiving_sessions', [
            'outstanding_purchase_order_id' => $po->id,
            'status' => ReceivingSession::STATUS_DRAFT,
        ]);
    }

    /**
     * 2. NEEDS CATALOG PO cannot create session.
     */
    public function test_needs_catalog_po_cannot_create_session()
    {
        $this->actingAs($this->user);

        $po = OutstandingPurchaseOrder::create([
            'warehouse_id' => $this->warehouse->id,
            'supplier_id' => $this->supplier->id,
            'supplier_name_snapshot' => $this->supplier->name,
            'po_number' => 'PO-NC-1',
            'po_date' => now(),
            'status' => OutstandingPurchaseOrder::STATUS_PENDING,
        ]);

        OutstandingPurchaseOrderItem::create([
            'outstanding_purchase_order_id' => $po->id,
            'item_variant_id' => null, // Needs catalog matching
            'erp_code' => '5.01.unmatched',
            'item_name_snapshot' => 'Unmatched Item',
            'ordered_qty' => 10,
        ]);

        $this->assertEquals('NEEDS CATALOG', $po->receiving_readiness_label);

        // Clear existing session error just in case
        session()->forget('error');

        // Instantiate component and call method directly to capture session error in the test thread
        $component = new OutstandingPurchaseShowPage();
        $component->orderId = $po->id;
        $component->startReceivingSession();

        $this->assertEquals('Cannot start receiving session: Item catalog mapping is incomplete.', session('error'));

        $this->assertDatabaseMissing('receiving_sessions', [
            'outstanding_purchase_order_id' => $po->id,
        ]);
    }

    /**
     * 3. PO with no lines cannot create session.
     */
    public function test_po_with_no_lines_cannot_create_session()
    {
        $this->actingAs($this->user);

        $po = OutstandingPurchaseOrder::create([
            'warehouse_id' => $this->warehouse->id,
            'supplier_id' => $this->supplier->id,
            'supplier_name_snapshot' => $this->supplier->name,
            'po_number' => 'PO-EMPTY-1',
            'po_date' => now(),
            'status' => OutstandingPurchaseOrder::STATUS_PENDING,
        ]);

        // Clear existing session error
        session()->forget('error');

        // Instantiate component and call method directly
        $component = new OutstandingPurchaseShowPage();
        $component->orderId = $po->id;
        $component->startReceivingSession();

        $this->assertEquals('Cannot start receiving session: Purchase order has no line items.', session('error'));

        $this->assertDatabaseMissing('receiving_sessions', [
            'outstanding_purchase_order_id' => $po->id,
        ]);
    }

    /**
     * 4. All PO lines are copied.
     * 5. expected_qty is snapshotted.
     */
    public function test_all_po_lines_are_copied_and_expected_qty_snapshotted()
    {
        $this->actingAs($this->user);

        [$po, $items] = $this->createValidPO('PO-COPY-1');
        $poItem = $items[0];

        Livewire::test(OutstandingPurchaseShowPage::class, ['id' => $po->id])
            ->call('startReceivingSession');

        $session = ReceivingSession::where('outstanding_purchase_order_id', $po->id)->first();
        $this->assertNotNull($session);

        $this->assertDatabaseHas('receiving_session_items', [
            'receiving_session_id' => $session->id,
            'outstanding_purchase_order_item_id' => $poItem->id,
            'item_variant_id' => $this->variant->id,
            'expected_qty' => 10,
            'received_qty' => 0,
            'verification_status' => ReceivingSessionItem::STATUS_PENDING,
        ]);
    }

    /**
     * 6. Duplicate active session is prevented.
     * 7. Concurrent/session creation logic is protected.
     */
    public function test_duplicate_active_session_is_prevented_and_redirects()
    {
        $this->actingAs($this->user);

        [$po, $items] = $this->createValidPO('PO-DUP-1');

        // Create the first session
        Livewire::test(OutstandingPurchaseShowPage::class, ['id' => $po->id])
            ->call('startReceivingSession');

        $this->assertEquals(1, ReceivingSession::where('outstanding_purchase_order_id', $po->id)->count());
        $session1 = ReceivingSession::where('outstanding_purchase_order_id', $po->id)->first();

        // Attempt to create second session - should resume the existing one instead
        Livewire::test(OutstandingPurchaseShowPage::class, ['id' => $po->id])
            ->call('startReceivingSession')
            ->assertRedirect(route('receiving.session', ['id' => $session1->id]));

        $this->assertEquals(1, ReceivingSession::where('outstanding_purchase_order_id', $po->id)->count());
    }

    /**
     * 8. Quantity cannot become negative.
     */
    public function test_quantity_cannot_become_negative()
    {
        $this->actingAs($this->user);

        [$po, $items] = $this->createValidPO('PO-QTY-NEG');
        $poItem = $items[0];

        $session = ReceivingSession::create([
            'warehouse_id' => $this->warehouse->id,
            'outstanding_purchase_order_id' => $po->id,
            'status' => ReceivingSession::STATUS_DRAFT,
            'created_by' => $this->user->id,
            'started_at' => now(),
        ]);

        $item = ReceivingSessionItem::create([
            'receiving_session_id' => $session->id,
            'outstanding_purchase_order_item_id' => $poItem->id,
            'item_variant_id' => $this->variant->id,
            'expected_qty' => 5,
            'received_qty' => 0,
            'verification_status' => ReceivingSessionItem::STATUS_PENDING,
        ]);

        Livewire::test(ReceivingSessionPage::class, ['id' => $session->id])
            ->call('decrementQty', $item->id);

        $item->refresh();
        $this->assertEquals(0, $item->received_qty);

        // Test manual set negative qty
        Livewire::test(ReceivingSessionPage::class, ['id' => $session->id])
            ->call('setQtyManual', $item->id, -5);

        $item->refresh();
        $this->assertEquals(0, $item->received_qty);
    }

    /**
     * 9. Partial receipt is allowed.
     */
    public function test_partial_receipt_is_allowed()
    {
        $this->actingAs($this->user);

        [$po, $items] = $this->createValidPO('PO-PARTIAL');
        $poItem = $items[0];

        $session = ReceivingSession::create([
            'warehouse_id' => $this->warehouse->id,
            'outstanding_purchase_order_id' => $po->id,
            'status' => ReceivingSession::STATUS_DRAFT,
            'created_by' => $this->user->id,
            'started_at' => now(),
        ]);

        $item = ReceivingSessionItem::create([
            'receiving_session_id' => $session->id,
            'outstanding_purchase_order_item_id' => $poItem->id,
            'item_variant_id' => $this->variant->id,
            'expected_qty' => 5,
            'received_qty' => 0,
            'verification_status' => ReceivingSessionItem::STATUS_PENDING,
        ]);

        Livewire::test(ReceivingSessionPage::class, ['id' => $session->id])
            ->call('setQtyManual', $item->id, 3)
            ->call('verifyLine', $item->id);

        $item->refresh();
        $this->assertEquals(3, $item->received_qty);
        $this->assertTrue($item->isVerified());
    }

    /**
     * 10. Explicit VERIFY is required.
     */
    public function test_explicit_verify_is_required()
    {
        $this->actingAs($this->user);

        [$po, $items] = $this->createValidPO('PO-EXP-VERIFY');
        $poItem = $items[0];

        $session = ReceivingSession::create([
            'warehouse_id' => $this->warehouse->id,
            'outstanding_purchase_order_id' => $po->id,
            'status' => ReceivingSession::STATUS_DRAFT,
            'created_by' => $this->user->id,
            'started_at' => now(),
        ]);

        $item = ReceivingSessionItem::create([
            'receiving_session_id' => $session->id,
            'outstanding_purchase_order_item_id' => $poItem->id,
            'item_variant_id' => $this->variant->id,
            'expected_qty' => 5,
            'received_qty' => 0,
            'verification_status' => ReceivingSessionItem::STATUS_PENDING,
        ]);

        // Set quantity but do not click verify
        Livewire::test(ReceivingSessionPage::class, ['id' => $session->id])
            ->call('setQtyManual', $item->id, 5);

        $item->refresh();
        $this->assertTrue($item->isPending()); // Still pending
    }

    /**
     * 11. REMOVE stores reason.
     * 12. REMOVE does not delete original PO data.
     */
    public function test_remove_stores_reason_and_retains_original_po_data()
    {
        $this->actingAs($this->user);

        [$po, $items] = $this->createValidPO('PO-REM-1');
        $poItem = $items[0];

        $session = ReceivingSession::create([
            'warehouse_id' => $this->warehouse->id,
            'outstanding_purchase_order_id' => $po->id,
            'status' => ReceivingSession::STATUS_DRAFT,
            'created_by' => $this->user->id,
            'started_at' => now(),
        ]);

        $item = ReceivingSessionItem::create([
            'receiving_session_id' => $session->id,
            'outstanding_purchase_order_item_id' => $poItem->id,
            'item_variant_id' => $this->variant->id,
            'expected_qty' => 10,
            'received_qty' => 0,
            'verification_status' => ReceivingSessionItem::STATUS_PENDING,
        ]);

        Livewire::test(ReceivingSessionPage::class, ['id' => $session->id])
            ->set('activeRemoveItemId', $item->id)
            ->set('removeReason', 'WRONG WAREHOUSE')
            ->set('removeRemarks', 'Should go to RAW_MATERIAL')
            ->call('removeLine');

        $item->refresh();
        $this->assertTrue($item->isRemoved());
        $this->assertEquals('WRONG WAREHOUSE', $item->removed_reason);
        $this->assertEquals('Should go to RAW_MATERIAL', $item->remarks);

        // Assert original PO and item still exist intact
        $this->assertDatabaseHas('outstanding_purchase_orders', ['id' => $po->id]);
        $this->assertDatabaseHas('outstanding_purchase_order_items', ['id' => $poItem->id]);
    }

    /**
     * 13. Pending lines prevent READY_REVIEW.
     */
    public function test_pending_lines_prevent_ready_review()
    {
        $this->actingAs($this->user);

        [$po, $items] = $this->createValidPO('PO-PEND-PREV', 2);
        $poItem1 = $items[0];
        $poItem2 = $items[1];

        $session = ReceivingSession::create([
            'warehouse_id' => $this->warehouse->id,
            'outstanding_purchase_order_id' => $po->id,
            'status' => ReceivingSession::STATUS_DRAFT,
            'created_by' => $this->user->id,
            'started_at' => now(),
        ]);

        // Two items, one is verified, one is pending
        $item1 = ReceivingSessionItem::create([
            'receiving_session_id' => $session->id,
            'outstanding_purchase_order_item_id' => $poItem1->id,
            'item_variant_id' => $this->variant->id,
            'expected_qty' => 5,
            'received_qty' => 5,
            'verification_status' => ReceivingSessionItem::STATUS_VERIFIED,
        ]);

        $item2 = ReceivingSessionItem::create([
            'receiving_session_id' => $session->id,
            'outstanding_purchase_order_item_id' => $poItem2->id,
            'item_variant_id' => $this->variant->id,
            'expected_qty' => 10,
            'received_qty' => 0,
            'verification_status' => ReceivingSessionItem::STATUS_PENDING,
        ]);

        Livewire::test(ReceivingSessionPage::class, ['id' => $session->id])
            ->call('completeVerification')
            ->assertDispatched('message-dispatched', message: 'Cannot complete verification: There are still pending lines.', type: 'error');

        $session->refresh();
        $this->assertEquals(ReceivingSession::STATUS_DRAFT, $session->status);
    }

    /**
     * 14. All lines processed allows READY_REVIEW.
     */
    public function test_all_lines_processed_allows_ready_review()
    {
        $this->actingAs($this->user);

        [$po, $items] = $this->createValidPO('PO-ALL-PROC', 2);
        $poItem1 = $items[0];
        $poItem2 = $items[1];

        $session = ReceivingSession::create([
            'warehouse_id' => $this->warehouse->id,
            'outstanding_purchase_order_id' => $po->id,
            'status' => ReceivingSession::STATUS_DRAFT,
            'created_by' => $this->user->id,
            'started_at' => now(),
        ]);

        // One item is verified, one is removed
        $item1 = ReceivingSessionItem::create([
            'receiving_session_id' => $session->id,
            'outstanding_purchase_order_item_id' => $poItem1->id,
            'item_variant_id' => $this->variant->id,
            'expected_qty' => 5,
            'received_qty' => 5,
            'verification_status' => ReceivingSessionItem::STATUS_VERIFIED,
        ]);

        $item2 = ReceivingSessionItem::create([
            'receiving_session_id' => $session->id,
            'outstanding_purchase_order_item_id' => $poItem2->id,
            'item_variant_id' => $this->variant->id,
            'expected_qty' => 10,
            'received_qty' => 0,
            'verification_status' => ReceivingSessionItem::STATUS_REMOVED,
            'removed_reason' => 'WRONG WAREHOUSE',
        ]);

        Livewire::test(ReceivingSessionPage::class, ['id' => $session->id])
            ->call('completeVerification')
            ->assertDispatched('message-dispatched', message: 'Verification complete. Ready for final review.', type: 'success');

        $session->refresh();
        $this->assertEquals(ReceivingSession::STATUS_READY_REVIEW, $session->status);
    }

    /**
     * 15. Draft survives reload.
     */
    public function test_draft_survives_reload()
    {
        $this->actingAs($this->user);

        [$po, $items] = $this->createValidPO('PO-DRAFT-RELOAD');
        $poItem = $items[0];

        $session = ReceivingSession::create([
            'warehouse_id' => $this->warehouse->id,
            'outstanding_purchase_order_id' => $po->id,
            'status' => ReceivingSession::STATUS_DRAFT,
            'created_by' => $this->user->id,
            'started_at' => now(),
        ]);

        $item = ReceivingSessionItem::create([
            'receiving_session_id' => $session->id,
            'outstanding_purchase_order_item_id' => $poItem->id,
            'item_variant_id' => $this->variant->id,
            'expected_qty' => 5,
            'received_qty' => 0,
            'verification_status' => ReceivingSessionItem::STATUS_PENDING,
        ]);

        // Modify quantity and save to database
        Livewire::test(ReceivingSessionPage::class, ['id' => $session->id])
            ->call('incrementQty', $item->id)
            ->call('incrementQty', $item->id)
            ->call('verifyLine', $item->id);

        // Instantiate new component to simulate reload
        Livewire::test(ReceivingSessionPage::class, ['id' => $session->id])
            ->assertViewHas('items', function ($items) use ($item) {
                $dbItem = $items->firstWhere('id', $item->id);
                return $dbItem->received_qty === 2 && $dbItem->verification_status === ReceivingSessionItem::STATUS_VERIFIED;
            });
    }

    /**
     * 16. Warehouse isolation works.
     */
    public function test_warehouse_isolation_prevents_unauthorized_access()
    {
        $this->actingAs($this->user);

        // Create other warehouse context using firstOrCreate to prevent uniqueness errors
        $otherWarehouse = Warehouse::firstOrCreate(
            ['code' => 'RAW_MATERIAL'],
            ['name' => 'Raw Materials', 'status' => 'ACTIVE']
        );

        $po = OutstandingPurchaseOrder::create([
            'warehouse_id' => $otherWarehouse->id,
            'supplier_id' => $this->supplier->id,
            'supplier_name_snapshot' => $this->supplier->name,
            'po_number' => 'PO-OTHER-WH',
            'po_date' => now(),
            'status' => OutstandingPurchaseOrder::STATUS_PENDING,
        ]);

        // Create session in another warehouse context (e.g. RAW_MATERIAL)
        $session = ReceivingSession::create([
            'warehouse_id' => $otherWarehouse->id, // Mismatched warehouse
            'outstanding_purchase_order_id' => $po->id,
            'status' => ReceivingSession::STATUS_DRAFT,
            'created_by' => $this->user->id,
            'started_at' => now(),
        ]);

        Livewire::test(ReceivingSessionPage::class, ['id' => $session->id])
            ->assertStatus(403);
    }

    /**
     * 17. No inventory quantity changes.
     * 18. No StockMovement is created.
     * 19. PO is not automatically Closed.
     * 20. Session never becomes COMPLETED in REC-02A.
     */
    public function test_receiving_session_verification_does_not_change_stock_create_movement_close_po_or_mark_completed()
    {
        $this->actingAs($this->user);

        // Create PO
        [$po, $items] = $this->createValidPO('PO-FINAL-TEST');
        $poItem = $items[0];

        // Create Location first
        $location = Location::create(['code' => 'LOC-TEST-2', 'name' => 'Location Test 2']);

        // Create Bin
        $bin = Bin::create([
            'code' => 'BIN-TEST-1',
            'warehouse_id' => $this->warehouse->id,
            'item_variant_id' => $this->variant->id,
            'location_id' => $location->id,
            'current_qty' => 50,
        ]);

        // Create Receiving Session
        $session = ReceivingSession::create([
            'warehouse_id' => $this->warehouse->id,
            'outstanding_purchase_order_id' => $po->id,
            'status' => ReceivingSession::STATUS_DRAFT,
            'created_by' => $this->user->id,
            'started_at' => now(),
        ]);

        $item = ReceivingSessionItem::create([
            'receiving_session_id' => $session->id,
            'outstanding_purchase_order_item_id' => $poItem->id,
            'item_variant_id' => $this->variant->id,
            'expected_qty' => 10,
            'received_qty' => 10,
            'verification_status' => ReceivingSessionItem::STATUS_PENDING,
        ]);

        $initialMovementCount = StockMovement::count();

        // Process session
        Livewire::test(ReceivingSessionPage::class, ['id' => $session->id])
            ->call('verifyLine', $item->id)
            ->call('completeVerification');

        $session->refresh();
        $po->refresh();
        $poItem->refresh();
        $bin->refresh();

        // Assert session is READY_REVIEW, not COMPLETED
        $this->assertEquals(ReceivingSession::STATUS_READY_REVIEW, $session->status);
        $this->assertNotEquals(ReceivingSession::STATUS_COMPLETED, $session->status);

        // Assert bin quantity did not change (was 50, should remain 50)
        $this->assertEquals(50, $bin->current_qty);

        // Assert no new StockMovements were created
        $this->assertEquals($initialMovementCount, StockMovement::count());

        // Assert PO status is still PENDING
        $this->assertEquals(OutstandingPurchaseOrder::STATUS_PENDING, $po->status);
        $this->assertEquals(0, $poItem->received_qty);
    }
}
