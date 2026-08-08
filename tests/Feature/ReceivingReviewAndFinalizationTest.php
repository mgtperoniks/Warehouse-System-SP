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
use App\Models\ReceivingSignature;
use App\Models\StockMovement;
use App\Models\Bin;
use App\Livewire\Receiving\ReceivingSessionPage;
use App\Services\Inventory\InventoryService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class ReceivingReviewAndFinalizationTest extends TestCase
{
    use DatabaseTransactions;

    protected User $user;
    protected Warehouse $warehouse;
    protected Supplier $supplier;
    protected ItemVariant $variant1;
    protected ItemVariant $variant2;
    protected Bin $bin1;
    protected Bin $bin2;

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
        session(['active_warehouse_id' => $this->warehouse->id]);

        // 3. Create Supplier
        $this->supplier = Supplier::create([
            'name' => 'PT. JAYA UTAMA BEARINGS',
        ]);

        // 4. Create Locations, Items and Variants
        $location = Location::create(['code' => 'LOC-TEST-3', 'name' => 'Location Test 3']);

        $item1 = Item::create(['name' => 'Bearing 6204']);
        $this->variant1 = ItemVariant::create([
            'item_id' => $item1->id,
            'erp_code' => '5.01.BRG.6204',
            'sku' => 'SKU-BRG-6204',
            'unit' => 'PCS',
        ]);

        $item2 = Item::create(['name' => 'Bearing 6205']);
        $this->variant2 = ItemVariant::create([
            'item_id' => $item2->id,
            'erp_code' => '5.01.BRG.6205',
            'sku' => 'SKU-BRG-6205',
            'unit' => 'PCS',
        ]);

        // 5. Mapped Bins in active warehouse
        $this->bin1 = Bin::create([
            'code' => 'BIN-6204',
            'warehouse_id' => $this->warehouse->id,
            'item_variant_id' => $this->variant1->id,
            'location_id' => $location->id,
            'current_qty' => 10,
        ]);

        $this->bin2 = Bin::create([
            'code' => 'BIN-6205',
            'warehouse_id' => $this->warehouse->id,
            'item_variant_id' => $this->variant2->id,
            'location_id' => $location->id,
            'current_qty' => 20,
        ]);

        Storage::fake('public');
    }

    /**
     * Helper to create a basic session in DRAFT or READY_REVIEW status.
     */
    protected function createSession($status = ReceivingSession::STATUS_DRAFT)
    {
        $po = OutstandingPurchaseOrder::create([
            'warehouse_id' => $this->warehouse->id,
            'supplier_id' => $this->supplier->id,
            'supplier_name_snapshot' => $this->supplier->name,
            'po_number' => 'L-2026-1323',
            'po_date' => now(),
            'status' => OutstandingPurchaseOrder::STATUS_PENDING,
        ]);

        $poItem1 = OutstandingPurchaseOrderItem::create([
            'outstanding_purchase_order_id' => $po->id,
            'item_variant_id' => $this->variant1->id,
            'erp_code' => $this->variant1->erp_code,
            'item_name_snapshot' => $this->variant1->item->name,
            'ordered_qty' => 2,
        ]);

        $poItem2 = OutstandingPurchaseOrderItem::create([
            'outstanding_purchase_order_id' => $po->id,
            'item_variant_id' => $this->variant2->id,
            'erp_code' => $this->variant2->erp_code,
            'item_name_snapshot' => $this->variant2->item->name,
            'ordered_qty' => 5,
        ]);

        $session = ReceivingSession::create([
            'warehouse_id' => $this->warehouse->id,
            'outstanding_purchase_order_id' => $po->id,
            'status' => $status,
            'created_by' => $this->user->id,
            'started_at' => now(),
        ]);

        $sessionItem1 = ReceivingSessionItem::create([
            'receiving_session_id' => $session->id,
            'outstanding_purchase_order_item_id' => $poItem1->id,
            'item_variant_id' => $this->variant1->id,
            'expected_qty' => 2,
            'received_qty' => 2,
            'verification_status' => $status === ReceivingSession::STATUS_DRAFT ? ReceivingSessionItem::STATUS_PENDING : ReceivingSessionItem::STATUS_VERIFIED,
        ]);

        $sessionItem2 = ReceivingSessionItem::create([
            'receiving_session_id' => $session->id,
            'outstanding_purchase_order_item_id' => $poItem2->id,
            'item_variant_id' => $this->variant2->id,
            'expected_qty' => 5,
            'received_qty' => 4, // partial
            'verification_status' => $status === ReceivingSession::STATUS_DRAFT ? ReceivingSessionItem::STATUS_PENDING : ReceivingSessionItem::STATUS_VERIFIED,
        ]);

        return [$session, [$sessionItem1, $sessionItem2], $po, [$poItem1, $poItem2]];
    }

    /**
     * Add the three required signatures to a session.
     */
    protected function signSession(ReceivingSession $session)
    {
        $roles = ['DISERAHKAN_OLEH', 'DITERIMA_OLEH', 'BAG_GUDANG'];
        foreach ($roles as $role) {
            ReceivingSignature::firstOrCreate(
                ['receiving_session_id' => $session->id, 'role' => $role],
                [
                    'signature_path' => "signatures/session_{$session->id}_{$role}.png",
                    'signed_by' => $this->user->id,
                    'signed_at' => now(),
                ]
            );
        }
    }

    /**
     * 1. READY_REVIEW can transition to REVIEWED.
     */
    public function test_ready_review_can_transition_to_reviewed()
    {
        $this->actingAs($this->user);
        [$session] = $this->createSession(ReceivingSession::STATUS_READY_REVIEW);

        Livewire::test(ReceivingSessionPage::class, ['id' => $session->id])
            ->call('reviewAndConfirm')
            ->assertDispatched('message-dispatched', message: 'Session reviewed and confirmed. Ready for digital signatures.', type: 'success');

        $session->refresh();
        $this->assertEquals(ReceivingSession::STATUS_REVIEWED, $session->status);
        $this->assertEquals($this->user->id, $session->reviewed_by);
        $this->assertNotNull($session->reviewed_at);
    }

    /**
     * 2. DRAFT cannot transition directly to REVIEWED.
     */
    public function test_draft_cannot_transition_directly_to_reviewed()
    {
        $this->actingAs($this->user);
        [$session] = $this->createSession(ReceivingSession::STATUS_DRAFT);

        Livewire::test(ReceivingSessionPage::class, ['id' => $session->id])
            ->call('reviewAndConfirm');

        $session->refresh();
        $this->assertEquals(ReceivingSession::STATUS_DRAFT, $session->status);
    }

    /**
     * 3. REVIEWED requires all verification lines resolved.
     */
    public function test_reviewed_requires_all_verification_lines_resolved()
    {
        $this->actingAs($this->user);
        [$session, $items] = $this->createSession(ReceivingSession::STATUS_READY_REVIEW);

        // Make one line pending again
        $items[0]->verification_status = ReceivingSessionItem::STATUS_PENDING;
        $items[0]->save();

        Livewire::test(ReceivingSessionPage::class, ['id' => $session->id])
            ->call('reviewAndConfirm')
            ->assertDispatched('message-dispatched', message: 'Cannot review session: There are still pending lines.', type: 'error');

        $session->refresh();
        $this->assertEquals(ReceivingSession::STATUS_READY_REVIEW, $session->status);
    }

    /**
     * 4. REVIEWED requires required signatures.
     */
    public function test_reviewed_requires_required_signatures_for_finalization()
    {
        $this->actingAs($this->user);
        [$session] = $this->createSession(ReceivingSession::STATUS_REVIEWED);

        // Attempt finalization with NO signatures
        Livewire::test(ReceivingSessionPage::class, ['id' => $session->id])
            ->call('finalizeReceiving')
            ->assertDispatched('message-dispatched', message: 'Finalization failed: All three signatures are required to finalize receiving.', type: 'error');

        // Add 2 signatures only
        ReceivingSignature::create([
            'receiving_session_id' => $session->id,
            'role' => 'DISERAHKAN_OLEH',
            'signature_path' => 'sig1.png',
            'signed_at' => now(),
        ]);
        ReceivingSignature::create([
            'receiving_session_id' => $session->id,
            'role' => 'DITERIMA_OLEH',
            'signature_path' => 'sig2.png',
            'signed_at' => now(),
        ]);

        Livewire::test(ReceivingSessionPage::class, ['id' => $session->id])
            ->call('finalizeReceiving')
            ->assertDispatched('message-dispatched', message: 'Finalization failed: All three signatures are required to finalize receiving.', type: 'error');

        $session->refresh();
        $this->assertEquals(ReceivingSession::STATUS_REVIEWED, $session->status);
    }

    /**
     * 5. Signature data persists.
     */
    public function test_signature_data_persists()
    {
        $this->actingAs($this->user);
        [$session] = $this->createSession(ReceivingSession::STATUS_REVIEWED);

        $fakeBase64 = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAUA';

        Livewire::test(ReceivingSessionPage::class, ['id' => $session->id])
            ->call('saveSignature', 'DISERAHKAN_OLEH', $fakeBase64)
            ->assertDispatched('message-dispatched', message: 'Signature saved successfully.', type: 'success');

        $this->assertDatabaseHas('receiving_signatures', [
            'receiving_session_id' => $session->id,
            'role' => 'DISERAHKAN_OLEH',
            'signed_by' => $this->user->id,
        ]);
    }

    /**
     * 6. Completed signatures cannot be silently overwritten.
     */
    public function test_completed_signatures_cannot_be_silently_overwritten()
    {
        $this->actingAs($this->user);
        [$session] = $this->createSession(ReceivingSession::STATUS_REVIEWED);

        $fakeBase64 = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAUA';

        // Save first signature
        Livewire::test(ReceivingSessionPage::class, ['id' => $session->id])
            ->call('saveSignature', 'DISERAHKAN_OLEH', $fakeBase64);

        $this->assertEquals(1, ReceivingSignature::where('receiving_session_id', $session->id)->count());

        // Try to save again for same role - should block
        Livewire::test(ReceivingSessionPage::class, ['id' => $session->id])
            ->call('saveSignature', 'DISERAHKAN_OLEH', $fakeBase64)
            ->assertDispatched('message-dispatched', message: 'Signature already exists for this role. Clear it first to re-sign.', type: 'error');

        // Finalize/Complete the session
        $this->signSession($session); // add remaining 2 signatures
        $session->status = ReceivingSession::STATUS_COMPLETED;
        $session->save();

        // Try to clear a completed signature - should block
        Livewire::test(ReceivingSessionPage::class, ['id' => $session->id])
            ->call('clearSignature', 'DISERAHKAN_OLEH')
            ->assertDispatched('message-dispatched', message: 'Cannot clear signature: Session is finalized.', type: 'error');

        $this->assertDatabaseHas('receiving_signatures', [
            'receiving_session_id' => $session->id,
            'role' => 'DISERAHKAN_OLEH',
        ]);
    }

    /**
     * 7. Missing required bin blocks finalization.
     */
    public function test_missing_required_bin_blocks_finalization()
    {
        $this->actingAs($this->user);
        [$session] = $this->createSession(ReceivingSession::STATUS_REVIEWED);
        $this->signSession($session);

        // Delete bin of variant2 to simulate missing bin
        $this->bin2->delete();

        Livewire::test(ReceivingSessionPage::class, ['id' => $session->id])
            ->call('finalizeReceiving')
            ->assertDispatched('message-dispatched', message: 'Finalization failed: Location (Bin) is required for item [Bearing 6205] in this warehouse. Please map a location in the catalog first.', type: 'error');

        $session->refresh();
        $this->assertEquals(ReceivingSession::STATUS_REVIEWED, $session->status);
    }

    /**
     * 8. Partial receipt creates only physically received quantity.
     * 9. Removed line creates no stock movement.
     * 10. Final commit creates correct StockMovement.
     * 11. Final commit updates correct inventory/bin quantity.
     * 12. Final commit updates OutstandingPurchaseOrderItem.received_qty.
     * 13. Partial PO remains PARTIAL.
     * 14. Session becomes COMPLETED only after successful commit.
     */
    public function test_final_commit_logic_and_partial_po_status()
    {
        $this->actingAs($this->user);

        // Session setup:
        // Item 1 (expected 2, received 2)
        // Item 2 (expected 5, received 4) -> partial
        [$session, $sessionItems, $po, $poItems] = $this->createSession(ReceivingSession::STATUS_REVIEWED);
        $this->signSession($session);

        $initialMovementCount = StockMovement::count();

        Livewire::test(ReceivingSessionPage::class, ['id' => $session->id])
            ->call('finalizeReceiving')
            ->assertDispatched('message-dispatched', message: 'Receiving successfully committed and finalized!', type: 'success');

        $session->refresh();
        $po->refresh();
        $poItems[0]->refresh();
        $poItems[1]->refresh();
        $this->bin1->refresh();
        $this->bin2->refresh();

        // 14. Session becomes COMPLETED
        $this->assertEquals(ReceivingSession::STATUS_COMPLETED, $session->status);

        // 8. Inventory increased only by received quantities
        // Bin 1: 10 initial + 2 received = 12
        $this->assertEquals(12, $this->bin1->current_qty);
        // Bin 2: 20 initial + 4 received = 24
        $this->assertEquals(24, $this->bin2->current_qty);

        // 10. Stock Movements created
        $this->assertEquals($initialMovementCount + 2, StockMovement::count());
        $this->assertDatabaseHas('stock_movements', [
            'item_variant_id' => $this->variant1->id,
            'qty' => 2,
            'type' => 'IN',
        ]);
        $this->assertDatabaseHas('stock_movements', [
            'item_variant_id' => $this->variant2->id,
            'qty' => 4,
            'type' => 'IN',
        ]);

        // 12. PO Item received_qty updated
        $this->assertEquals(2, $poItems[0]->received_qty);
        $this->assertEquals(4, $poItems[1]->received_qty);

        // 13. PO remains PARTIAL
        $this->assertEquals(OutstandingPurchaseOrder::STATUS_PARTIAL, $po->status);
    }

    /**
     * 15. Fully received PO becomes CLOSED according to existing PO rules.
     */
    public function test_fully_received_po_becomes_closed()
    {
        $this->actingAs($this->user);
        [$session, $sessionItems] = $this->createSession(ReceivingSession::STATUS_REVIEWED);
        $this->signSession($session);

        // Make second item fully received
        $sessionItems[1]->received_qty = 5;
        $sessionItems[1]->save();

        Livewire::test(ReceivingSessionPage::class, ['id' => $session->id])
            ->call('finalizeReceiving');

        $session->refresh();
        $this->assertEquals(ReceivingSession::STATUS_COMPLETED, $session->status);

        // Recalculated PO should be CLOSED
        $this->assertEquals(OutstandingPurchaseOrder::STATUS_CLOSED, $session->outstandingPurchaseOrder->status);
    }

    /**
     * 16. Removed lines never create stock movement.
     */
    public function test_removed_lines_never_create_stock_movement()
    {
        $this->actingAs($this->user);
        [$session, $sessionItems] = $this->createSession(ReceivingSession::STATUS_REVIEWED);
        $this->signSession($session);

        // Mark second item as REMOVED
        $sessionItems[1]->verification_status = ReceivingSessionItem::STATUS_REMOVED;
        $sessionItems[1]->removed_reason = 'WRONG WAREHOUSE';
        $sessionItems[1]->save();

        $initialMovementCount = StockMovement::count();

        Livewire::test(ReceivingSessionPage::class, ['id' => $session->id])
            ->call('finalizeReceiving');

        // Only verified item (variant1) should create stock movement (1 movement)
        $this->assertEquals($initialMovementCount + 1, StockMovement::count());
        $this->assertDatabaseHas('stock_movements', [
            'item_variant_id' => $this->variant1->id,
            'qty' => 2,
        ]);
        $this->assertDatabaseMissing('stock_movements', [
            'item_variant_id' => $this->variant2->id,
        ]);
    }

    /**
     * 17. Failed commit rolls back all inventory/PO/session changes.
     */
    public function test_failed_commit_rolls_back_all_changes()
    {
        $this->actingAs($this->user);
        [$session] = $this->createSession(ReceivingSession::STATUS_REVIEWED);
        $this->signSession($session);

        // Force a DB crash by injecting an invalid column save during transaction inside finalizer
        // Or we can mock InventoryService to throw an exception
        $this->mock(InventoryService::class, function ($mock) {
            $mock->shouldReceive('moveStock')
                ->andThrow(new \RuntimeException("Database connection lost during write."));
        });

        Livewire::test(ReceivingSessionPage::class, ['id' => $session->id])
            ->call('finalizeReceiving')
            ->assertDispatched('message-dispatched', message: 'Finalization failed: Database connection lost during write.', type: 'error');

        $session->refresh();
        // Session remains REVIEWED (rolled back)
        $this->assertEquals(ReceivingSession::STATUS_REVIEWED, $session->status);

        // Inventory is not changed (rolled back)
        $this->bin1->refresh();
        $this->assertEquals(10, $this->bin1->current_qty);

        // PO items not updated (rolled back)
        $this->assertEquals(0, $session->outstandingPurchaseOrder->items->first()->received_qty);
    }

    /**
     * 18. Double finalization cannot create duplicate stock movements.
     */
    public function test_double_finalization_cannot_create_duplicate_stock_movements()
    {
        $this->actingAs($this->user);
        [$session] = $this->createSession(ReceivingSession::STATUS_REVIEWED);
        $this->signSession($session);

        $page = Livewire::test(ReceivingSessionPage::class, ['id' => $session->id]);
        
        // First finalization
        $page->call('finalizeReceiving')
            ->assertDispatched('message-dispatched', message: 'Receiving successfully committed and finalized!', type: 'success');

        $session->refresh();
        $this->assertEquals(ReceivingSession::STATUS_COMPLETED, $session->status);
        $this->bin1->refresh();
        $this->assertEquals(12, $this->bin1->current_qty);

        $movementCount = StockMovement::where('item_variant_id', $this->variant1->id)->count();
        $this->assertEquals(1, $movementCount);

        // Second finalization - should immediately stop and output error message
        $page->call('finalizeReceiving')
            ->assertDispatched('message-dispatched', message: 'Finalization failed: This receiving session is not in REVIEWED status or has already been finalized.', type: 'error');

        // Confirm inventory and stock movements remain unchanged
        $this->bin1->refresh();
        $this->assertEquals(12, $this->bin1->current_qty);
        $this->assertEquals(1, StockMovement::where('item_variant_id', $this->variant1->id)->count());
    }

    /**
     * 19. Warehouse isolation remains enforced.
     */
    public function test_warehouse_isolation_remains_enforced()
    {
        $this->actingAs($this->user);

        // Create session in another warehouse
        $otherWarehouse = Warehouse::create([
            'code' => 'RAW_MAT',
            'name' => 'Raw Materials Warehouse',
        ]);
        
        $po = OutstandingPurchaseOrder::create([
            'warehouse_id' => $otherWarehouse->id,
            'supplier_id' => $this->supplier->id,
            'supplier_name_snapshot' => $this->supplier->name,
            'po_number' => 'PO-OTHER-WH',
            'po_date' => now(),
            'status' => OutstandingPurchaseOrder::STATUS_PENDING,
        ]);

        $session = ReceivingSession::create([
            'warehouse_id' => $otherWarehouse->id,
            'outstanding_purchase_order_id' => $po->id,
            'status' => ReceivingSession::STATUS_REVIEWED,
            'created_by' => $this->user->id,
            'started_at' => now(),
        ]);

        // Attempting to test the component with mismatched active warehouse context
        Livewire::test(ReceivingSessionPage::class, ['id' => $session->id])
            ->assertStatus(403);
    }

    /**
     * 20. PDF uses ReceivingSessionItem.expected_qty rather than current PO quantity.
     * 21. PDF uses ReceivingSessionItem.received_qty.
     * 22. PDF contains ISO document code.
     * 23. PDF contains all three signature positions.
     */
    public function test_pdf_uses_receiving_session_data_and_details()
    {
        $this->actingAs($this->user);
        [$session, $sessionItems, $po, $poItems] = $this->createSession(ReceivingSession::STATUS_REVIEWED);
        $this->signSession($session);

        // Finalize to trigger PDF generation
        Livewire::test(ReceivingSessionPage::class, ['id' => $session->id])
            ->call('finalizeReceiving');

        $session->refresh();
        $this->assertNotNull($session->pdf_path);
        $this->assertTrue(Storage::disk('public')->exists($session->pdf_path));

        // Modify the PO original item expected qty now to simulate an external change
        $poItems[0]->ordered_qty = 100;
        $poItems[0]->save();

        // Render PDF view directly
        $pdfHtml = view('reports.receiving-inspection-pdf', [
            'session' => $session,
            'items' => $session->items()->with(['outstandingPurchaseOrderItem', 'variant.item'])->get(),
            'signatures' => [
                'DISERAHKAN_OLEH' => $session->signatures->firstWhere('role', 'DISERAHKAN_OLEH'),
                'DITERIMA_OLEH' => $session->signatures->firstWhere('role', 'DITERIMA_OLEH'),
                'BAG_GUDANG' => $session->signatures->firstWhere('role', 'BAG_GUDANG'),
            ],
        ])->render();

        // 20. Must use expected_qty from ReceivingSessionItem (which is 2), NOT original PO ordered_qty (which is 100)
        $this->assertStringContainsString('2</td>', $pdfHtml);
        $this->assertStringNotContainsString('100</td>', $pdfHtml);

        // 21. Contains received qty (which is 2 and 4)
        $this->assertStringContainsString('4</td>', $pdfHtml);

        // 22. PDF contains ISO document code
        $this->assertStringContainsString('FR/GUD/10-01-05/17-00-1/1', $pdfHtml);

        // 23. PDF contains all three signature positions / labels
        $this->assertStringContainsString('DISERAHKAN OLEH', $pdfHtml);
        $this->assertStringContainsString('DITERIMA/DICEK OLEH', $pdfHtml);
        $this->assertStringContainsString('BAG. GUDANG', $pdfHtml);
    }

    /**
     * 24. PDF generation failure does not rollback already committed inventory transaction.
     */
    public function test_pdf_generation_failure_does_not_rollback_already_committed_inventory_transaction()
    {
        $this->actingAs($this->user);
        [$session] = $this->createSession(ReceivingSession::STATUS_REVIEWED);
        $this->signSession($session);

        // Force generatePdfDocument to throw exception by modifying it temporarily in test double or mocking
        // Since we cannot mock internal protected method directly without mocking component, we can mock PDF Facade
        \Barryvdh\DomPDF\Facade\Pdf::shouldReceive('loadView')
            ->andThrow(new \RuntimeException("DomPDF failed to render font."));

        Livewire::test(ReceivingSessionPage::class, ['id' => $session->id])
            ->call('finalizeReceiving')
            ->assertDispatched('message-dispatched', message: 'Commit successful, but PDF generation failed. The document can be regenerated later.', type: 'error');

        $session->refresh();
        // Session status is still COMPLETED (commit succeeded, did not roll back!)
        $this->assertEquals(ReceivingSession::STATUS_COMPLETED, $session->status);
        $this->bin1->refresh();
        // Inventory was increased
        $this->assertEquals(12, $this->bin1->current_qty);
    }
}
