<?php

namespace Tests\Feature;

use App\Livewire\OutstandingPurchase\OutstandingPurchaseShowPage;
use App\Models\Item;
use App\Models\ItemVariant;
use App\Models\OutstandingPurchaseOrder;
use App\Models\OutstandingPurchaseOrderItem;
use App\Models\ReceivingSession;
use App\Models\ReceivingSessionItem;
use App\Models\ReceivingSignature;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\OutstandingPurchase\ImportPipelineService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Regression tests for the Outstanding Purchases / Receiving Workflow.
 *
 * Business constraints enforced:
 * - REVIEWED session must never allow a new session to be created.
 * - Fully received WMS PO must show FULLY_RECEIVED, never START_RECEIVING.
 * - Stale ERP import must not affect WMS state.
 * - Digital signatures are OPTIONAL.
 * - A4 PDF is always accessible after COMPLETED.
 */
class OutstandingPurchaseWorkflowRegressionTest extends TestCase
{
    use DatabaseTransactions;

    protected User $user;
    protected Warehouse $warehouse;
    protected ItemVariant $variant1;
    protected ItemVariant $variant2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'name' => 'Workflow Tester',
            'email' => 'workflow_' . uniqid() . '@example.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
        ]);

        $this->warehouse = Warehouse::firstOrCreate(
            ['code' => 'SPAREPART'],
            ['name' => 'Sparepart Warehouse', 'status' => 'ACTIVE']
        );

        $this->user->warehouses()->syncWithoutDetaching([$this->warehouse->id]);
        session(['active_warehouse_id' => $this->warehouse->id]);

        $item1 = Item::create(['name' => 'Workflow Item A']);
        $this->variant1 = ItemVariant::create([
            'item_id' => $item1->id,
            'erp_code' => '5.01.WF.001.' . uniqid(),
            'sku' => 'SKU-WF-001-' . uniqid(),
            'unit' => 'PCS',
        ]);

        $item2 = Item::create(['name' => 'Workflow Item B']);
        $this->variant2 = ItemVariant::create([
            'item_id' => $item2->id,
            'erp_code' => '5.01.WF.002.' . uniqid(),
            'sku' => 'SKU-WF-002-' . uniqid(),
            'unit' => 'PCS',
        ]);
    }

    /**
     * Helper: create a PO with matched items.
     */
    protected function createPo(string $poNumber, array $overrides = []): OutstandingPurchaseOrder
    {
        $po = OutstandingPurchaseOrder::create(array_merge([
            'warehouse_id' => $this->warehouse->id,
            'supplier_name_snapshot' => 'PT Supplier WF',
            'po_number' => $poNumber,
            'po_date' => '2026-09-01',
            'status' => OutstandingPurchaseOrder::STATUS_PENDING,
            'erp_sync_status' => 'IN_SYNC',
            'is_archived' => false,
        ], $overrides));

        OutstandingPurchaseOrderItem::create([
            'outstanding_purchase_order_id' => $po->id,
            'item_variant_id' => $this->variant1->id,
            'erp_code' => $this->variant1->erp_code,
            'item_name_snapshot' => 'Workflow Item A',
            'ordered_qty' => 100.0,
            'received_qty' => 0.0,
            'unit' => 'PCS',
        ]);

        return $po;
    }

    /**
     * Helper: create a receiving session at a given status.
     */
    protected function createSession(OutstandingPurchaseOrder $po, string $status): ReceivingSession
    {
        $session = ReceivingSession::create([
            'warehouse_id' => $this->warehouse->id,
            'outstanding_purchase_order_id' => $po->id,
            'status' => $status,
            'created_by' => $this->user->id,
            'started_at' => now(),
            'reviewed_at' => in_array($status, [ReceivingSession::STATUS_REVIEWED, ReceivingSession::STATUS_COMPLETED]) ? now() : null,
            'reviewed_by' => in_array($status, [ReceivingSession::STATUS_REVIEWED, ReceivingSession::STATUS_COMPLETED]) ? $this->user->id : null,
            'completed_at' => $status === ReceivingSession::STATUS_COMPLETED ? now() : null,
            'completed_by' => $status === ReceivingSession::STATUS_COMPLETED ? $this->user->id : null,
        ]);

        $item = $po->items()->first();
        ReceivingSessionItem::create([
            'receiving_session_id' => $session->id,
            'outstanding_purchase_order_item_id' => $item->id,
            'item_variant_id' => $item->item_variant_id,
            'expected_qty' => (float)$item->ordered_qty,
            'received_qty' => in_array($status, [ReceivingSession::STATUS_REVIEWED, ReceivingSession::STATUS_COMPLETED]) ? (float)$item->ordered_qty : 0.0,
            'qty_datang' => in_array($status, [ReceivingSession::STATUS_REVIEWED, ReceivingSession::STATUS_COMPLETED]) ? (float)$item->ordered_qty : null,
            'verification_status' => in_array($status, [ReceivingSession::STATUS_READY_REVIEW, ReceivingSession::STATUS_REVIEWED, ReceivingSession::STATUS_COMPLETED])
                ? ReceivingSessionItem::STATUS_VERIFIED : ReceivingSessionItem::STATUS_PENDING,
            'check_result' => in_array($status, [ReceivingSession::STATUS_READY_REVIEW, ReceivingSession::STATUS_REVIEWED, ReceivingSession::STATUS_COMPLETED]) ? ReceivingSessionItem::CHECK_OK : null,
        ]);

        return $session;
    }

    // =========================================================================
    // CONSTRAINT #7: No session + positive pending → READY FOR RECEIVING
    // =========================================================================

    public function test_no_session_positive_pending_shows_ready_for_receiving()
    {
        $this->actingAs($this->user);
        $po = $this->createPo('WF-TEST-001');

        $component = Livewire::test(OutstandingPurchaseShowPage::class, ['id' => $po->id]);

        $component->assertViewHas('operatorAction', 'READY_TO_RECEIVE');
        $component->assertSee('READY FOR RECEIVING');
    }

    // =========================================================================
    // DRAFT session → RESUME RECEIVING
    // =========================================================================

    public function test_draft_session_shows_resume_receiving()
    {
        $this->actingAs($this->user);
        $po = $this->createPo('WF-TEST-002');
        $this->createSession($po, ReceivingSession::STATUS_DRAFT);

        $component = Livewire::test(OutstandingPurchaseShowPage::class, ['id' => $po->id]);

        $component->assertViewHas('operatorAction', 'RESUME_DRAFT');
        $component->assertSee('RESUME RECEIVING');
    }

    // =========================================================================
    // READY_REVIEW session → RESUME / REVIEW
    // =========================================================================

    public function test_ready_review_session_shows_resume_review()
    {
        $this->actingAs($this->user);
        $po = $this->createPo('WF-TEST-003');
        $this->createSession($po, ReceivingSession::STATUS_READY_REVIEW);

        $component = Livewire::test(OutstandingPurchaseShowPage::class, ['id' => $po->id]);

        $component->assertViewHas('operatorAction', 'RESUME_REVIEW');
        $component->assertSee('RESUME / REVIEW RECEIVING');
    }

    // =========================================================================
    // CONSTRAINT #8: REVIEWED session → VIEW / FINALIZE SESSION
    // =========================================================================

    public function test_reviewed_session_shows_view_finalize_and_not_start_receiving()
    {
        $this->actingAs($this->user);
        $po = $this->createPo('WF-TEST-004');
        $this->createSession($po, ReceivingSession::STATUS_REVIEWED);

        $component = Livewire::test(OutstandingPurchaseShowPage::class, ['id' => $po->id]);

        $component->assertViewHas('operatorAction', 'VIEW_FINALIZE');
        $component->assertSee('VIEW / FINALIZE SESSION');
        $component->assertDontSee('READY FOR RECEIVING');
        $component->assertDontSee('START RECEIVING');
    }

    // =========================================================================
    // CONSTRAINT #8: Clicking button on REVIEWED PO must NOT create new session
    // =========================================================================

    public function test_start_receiving_session_with_reviewed_session_redirects_not_creates()
    {
        $this->actingAs($this->user);
        $po = $this->createPo('WF-TEST-005');
        $reviewedSession = $this->createSession($po, ReceivingSession::STATUS_REVIEWED);

        $sessionCountBefore = ReceivingSession::count();

        Livewire::test(OutstandingPurchaseShowPage::class, ['id' => $po->id])
            ->call('startReceivingSession');

        // Must not have created a new session
        $this->assertEquals($sessionCountBefore, ReceivingSession::count());

        // The existing reviewed session is unchanged
        $reviewedSession->refresh();
        $this->assertEquals(ReceivingSession::STATUS_REVIEWED, $reviewedSession->status);
    }

    // =========================================================================
    // CONSTRAINT #5: fully received WMS PO → FULLY RECEIVED IN WMS
    // =========================================================================

    public function test_fully_received_wms_shows_fully_received_not_start_receiving()
    {
        $this->actingAs($this->user);
        $po = $this->createPo('WF-TEST-006');

        // Mark the PO item as fully received in WMS
        $po->items()->update(['received_qty' => 100.0]);
        $po->recalculateStatus();
        $po->save();

        // Add a completed session
        $this->createSession($po, ReceivingSession::STATUS_COMPLETED);

        $component = Livewire::test(OutstandingPurchaseShowPage::class, ['id' => $po->id]);

        $component->assertViewHas('operatorAction', 'FULLY_RECEIVED');
        $component->assertViewHas('isFullyReceivedInWms', true);
        $component->assertSee('FULLY RECEIVED IN WMS');
        $component->assertDontSee('READY FOR RECEIVING');
        $component->assertDontSee('START RECEIVING');
    }

    // =========================================================================
    // CONSTRAINT #2+#5: Fully received WMS + stale ERP export → WMS state wins
    // =========================================================================

    public function test_fully_received_wms_plus_stale_erp_import_preserves_wms_state()
    {
        $pipeline = new ImportPipelineService();

        // 1. Import PO
        $pipeline->process([[
            'po_number' => 'WF-ERP-001',
            'supplier_name' => 'PT Supplier WF',
            'po_date' => '2026-09-01',
            'erp_code' => $this->variant1->erp_code,
            'item_name' => 'Workflow Item A',
            'ordered_qty' => 100.0,
            'erp_received_qty' => 0.0,
            'unit' => 'PCS',
        ]], 'ERP_IMPORT');

        $po = OutstandingPurchaseOrder::where('po_number', 'WF-ERP-001')
            ->where('warehouse_id', $this->warehouse->id)
            ->first();
        $this->assertNotNull($po);

        $poItem = $po->items()->first();

        // 2. Simulate full WMS receiving
        $poItem->update(['received_qty' => 100.0]);
        $po->recalculateStatus();
        $po->save();

        $po->refresh();
        $this->assertEquals(OutstandingPurchaseOrder::STATUS_CLOSED, $po->status);
        $this->assertEquals(0.0, (float)$poItem->fresh()->pending_qty);

        // 3. Stale ERP re-import says 0 received
        $pipeline->process([[
            'po_number' => 'WF-ERP-001',
            'supplier_name' => 'PT Supplier WF',
            'po_date' => '2026-09-01',
            'erp_code' => $this->variant1->erp_code,
            'item_name' => 'Workflow Item A',
            'ordered_qty' => 100.0,
            'erp_received_qty' => 0.0,
            'erp_outstanding_qty' => 100.0,
            'unit' => 'PCS',
        ]], 'ERP_IMPORT');

        // WMS state must be fully preserved
        $poItem->refresh();
        $this->assertEquals(100.0, (float)$poItem->received_qty);
        $this->assertEquals(0.0, (float)$poItem->pending_qty);
        $this->assertEquals('ERP_BEHIND', $poItem->erp_sync_status);

        // PO remains CLOSED
        $po->refresh();
        $this->assertEquals(OutstandingPurchaseOrder::STATUS_CLOSED, $po->status);
    }

    // =========================================================================
    // CONSTRAINT #6: Partial WMS receiving + stale ERP → partial preserved
    // =========================================================================

    public function test_partial_wms_receiving_plus_stale_erp_shows_partial()
    {
        $this->actingAs($this->user);
        $po = $this->createPo('WF-TEST-007');

        // Partially received
        $po->items()->update(['received_qty' => 40.0]);
        $po->recalculateStatus();
        $po->save();

        // Import stale ERP (0 received)
        $pipeline = new ImportPipelineService();
        $pipeline->process([[
            'po_number' => 'WF-TEST-007',
            'supplier_name' => 'PT Supplier WF',
            'po_date' => '2026-09-01',
            'erp_code' => $this->variant1->erp_code,
            'item_name' => 'Workflow Item A',
            'ordered_qty' => 100.0,
            'erp_received_qty' => 0.0,
            'unit' => 'PCS',
        ]], 'ERP_IMPORT');

        // WMS partial state preserved
        $item = $po->items()->first()->fresh();
        $this->assertEquals(40.0, (float)$item->received_qty);
        $this->assertEquals(60.0, (float)$item->pending_qty);

        // UI shows PARTIAL_RECEIVED action
        $component = Livewire::test(OutstandingPurchaseShowPage::class, ['id' => $po->id]);
        $component->assertViewHas('operatorAction', 'PARTIAL_RECEIVED');
        $component->assertSee('CONTINUE RECEIVING');
    }

    // =========================================================================
    // Re-import unchanged stale ERP data → idempotent
    // =========================================================================

    public function test_reimport_unchanged_erp_data_is_idempotent()
    {
        $pipeline = new ImportPipelineService();

        $row = [
            'po_number' => 'WF-IDEMPOTENT-001',
            'supplier_name' => 'PT Supplier WF',
            'po_date' => '2026-09-01',
            'erp_code' => $this->variant1->erp_code,
            'item_name' => 'Workflow Item A',
            'ordered_qty' => 50.0,
            'unit' => 'PCS',
        ];

        $result1 = $pipeline->process([$row], 'ERP_IMPORT');
        $this->assertEquals(1, $result1['summary']['new_lines']);

        $result2 = $pipeline->process([$row], 'ERP_IMPORT');
        $this->assertEquals(0, $result2['summary']['new_lines']);
        $this->assertEquals(1, $result2['success']);

        // Exactly one PO and one item
        $this->assertEquals(1, OutstandingPurchaseOrder::where('po_number', 'WF-IDEMPOTENT-001')
            ->where('warehouse_id', $this->warehouse->id)->count());
        $this->assertEquals(1, OutstandingPurchaseOrderItem::where('erp_code', $this->variant1->erp_code)
            ->whereHas('outstandingPurchaseOrder', fn($q) => $q->where('po_number', 'WF-IDEMPOTENT-001'))->count());
    }

    // =========================================================================
    // No duplicate session for any session state
    // =========================================================================

    public function test_no_duplicate_session_when_draft_exists()
    {
        $this->actingAs($this->user);
        $po = $this->createPo('WF-TEST-DUP-DRAFT');
        $existingSession = $this->createSession($po, ReceivingSession::STATUS_DRAFT);
        $sessionCount = ReceivingSession::count();

        Livewire::test(OutstandingPurchaseShowPage::class, ['id' => $po->id])
            ->call('startReceivingSession');

        $this->assertEquals($sessionCount, ReceivingSession::count());
    }

    public function test_no_duplicate_session_when_ready_review_exists()
    {
        $this->actingAs($this->user);
        $po = $this->createPo('WF-TEST-DUP-RR');
        $this->createSession($po, ReceivingSession::STATUS_READY_REVIEW);
        $sessionCount = ReceivingSession::count();

        Livewire::test(OutstandingPurchaseShowPage::class, ['id' => $po->id])
            ->call('startReceivingSession');

        $this->assertEquals($sessionCount, ReceivingSession::count());
    }

    public function test_no_duplicate_session_when_reviewed_exists()
    {
        $this->actingAs($this->user);
        $po = $this->createPo('WF-TEST-DUP-REV');
        $this->createSession($po, ReceivingSession::STATUS_REVIEWED);
        $sessionCount = ReceivingSession::count();

        Livewire::test(OutstandingPurchaseShowPage::class, ['id' => $po->id])
            ->call('startReceivingSession');

        $this->assertEquals($sessionCount, ReceivingSession::count());
    }

    public function test_no_new_session_when_wms_fully_received_even_without_completed_session()
    {
        $this->actingAs($this->user);
        $po = $this->createPo('WF-TEST-FULL-NOCOMP');

        // Fully received in WMS but no formal completed session
        $po->items()->update(['received_qty' => 100.0]);
        $po->recalculateStatus();
        $po->save();

        $sessionCount = ReceivingSession::count();

        Livewire::test(OutstandingPurchaseShowPage::class, ['id' => $po->id])
            ->call('startReceivingSession');

        // Should have thrown an error, no new session
        $this->assertEquals($sessionCount, ReceivingSession::count());
    }

    // =========================================================================
    // CONSTRAINT #1: Digital signatures are OPTIONAL
    // =========================================================================

    public function test_digital_signature_is_optional_and_not_required_for_reviewed()
    {
        $this->actingAs($this->user);
        $po = $this->createPo('WF-TEST-SIG-OPTIONAL');
        $session = $this->createSession($po, ReceivingSession::STATUS_REVIEWED);

        // Verify: no signatures exist
        $this->assertEquals(0, ReceivingSignature::where('receiving_session_id', $session->id)->count());

        // Verify: session is still in REVIEWED state without signatures — this is valid
        $session->refresh();
        $this->assertEquals(ReceivingSession::STATUS_REVIEWED, $session->status);

        // UI must still show VIEW / FINALIZE SESSION (not blocked by lack of signatures)
        $component = Livewire::test(OutstandingPurchaseShowPage::class, ['id' => $po->id]);
        $component->assertViewHas('operatorAction', 'VIEW_FINALIZE');
        $component->assertSee('VIEW / FINALIZE SESSION');
    }

    // =========================================================================
    // CONSTRAINT #9: COMPLETED session read-only, A4 PDF available
    // =========================================================================

    public function test_completed_session_shows_fully_received_and_pdf_is_accessible()
    {
        $this->actingAs($this->user);
        $po = $this->createPo('WF-TEST-COMPLETE');

        // Mark items received
        $po->items()->update(['received_qty' => 100.0]);
        $po->recalculateStatus();
        $po->save();

        $completedSession = $this->createSession($po, ReceivingSession::STATUS_COMPLETED);

        // Update po_number on session
        $completedSession->refresh();

        $component = Livewire::test(OutstandingPurchaseShowPage::class, ['id' => $po->id]);

        // FULLY_RECEIVED action
        $component->assertViewHas('operatorAction', 'FULLY_RECEIVED');
        $component->assertSee('FULLY RECEIVED IN WMS');

        // Completed sessions history is shown
        $component->assertSee('Session #' . $completedSession->id);
        $component->assertSee('COMPLETED');

        // PDF links are present
        $component->assertSee('View Session');
        $component->assertSee('A4 PDF Form');
    }

    // =========================================================================
    // ERP_BEHIND banner shown when fully received + ERP behind
    // =========================================================================

    public function test_erp_behind_banner_shown_when_wms_received_ahead()
    {
        $this->actingAs($this->user);
        $po = $this->createPo('WF-TEST-ERP-BEHIND');

        // Directly update each item model to trigger observers and set ERP_BEHIND
        foreach ($po->items as $item) {
            $item->received_qty = 100.0;
            $item->erp_received_qty = 0.0;
            $item->erp_sync_status = 'ERP_BEHIND';
            $item->save();
        }

        // Force ERP_BEHIND on the PO header directly (recalculateStatus sets it based on items)
        $po->refresh();
        $po->erp_sync_status = 'ERP_BEHIND';
        $po->save();

        $this->createSession($po, ReceivingSession::STATUS_COMPLETED);

        $component = Livewire::test(OutstandingPurchaseShowPage::class, ['id' => $po->id]);

        $component->assertSee('FULLY RECEIVED IN WMS');
        // The ERP reconciliation section shows this text
        $component->assertSee('ERP SYNC: BEHIND');
    }

    // =========================================================================
    // CONSTRAINT #3: Reviewed session — operator action is VIEW_FINALIZE
    // =========================================================================

    public function test_reviewed_session_operatorAction_is_view_finalize()
    {
        $this->actingAs($this->user);
        $po = $this->createPo('WF-TEST-VIEW-FIN');
        $session = $this->createSession($po, ReceivingSession::STATUS_REVIEWED);

        $component = Livewire::test(OutstandingPurchaseShowPage::class, ['id' => $po->id]);

        $component->assertViewHas('operatorAction', 'VIEW_FINALIZE');
    }

    // =========================================================================
    // Detail page renders correctly for various PO states
    // =========================================================================

    public function test_show_page_renders_wms_receiving_summary()
    {
        $this->actingAs($this->user);
        $po = $this->createPo('WF-TEST-RENDER');
        $po->items()->update(['received_qty' => 40.0]);
        $po->recalculateStatus();
        $po->save();

        $component = Livewire::test(OutstandingPurchaseShowPage::class, ['id' => $po->id]);

        // View passes wmsTotalReceived and wmsTotalPending
        $component->assertViewHas('wmsTotalReceived', function ($val) {
            return abs($val - 40.0) < 0.01;
        });
        $component->assertViewHas('wmsTotalPending', function ($val) {
            return abs($val - 60.0) < 0.01;
        });
        $component->assertSee('WMS Rec.');
        $component->assertSee('Pending');
    }

    // =========================================================================
    // Constraint #4: Primary operator action from WMS, not ERP
    // =========================================================================

    public function test_operator_action_is_from_wms_not_erp()
    {
        $this->actingAs($this->user);

        // PO fully received in WMS but ERP still shows 100 outstanding
        $po = $this->createPo('WF-TEST-WMS-AUTH', ['erp_sync_status' => 'ERP_BEHIND']);
        $po->items()->update([
            'received_qty' => 100.0,
            'erp_received_qty' => 0.0,
            'erp_outstanding_qty' => 100.0,
            'erp_sync_status' => 'ERP_BEHIND',
        ]);
        $po->recalculateStatus();
        $po->save();
        $this->createSession($po, ReceivingSession::STATUS_COMPLETED);

        // Even with ERP behind, UI must show FULLY_RECEIVED, not START_RECEIVING
        $component = Livewire::test(OutstandingPurchaseShowPage::class, ['id' => $po->id]);

        $component->assertViewHas('operatorAction', 'FULLY_RECEIVED');
        $component->assertViewHas('isFullyReceivedInWms', true);
        $component->assertDontSee('READY FOR RECEIVING');
        $component->assertDontSee('START RECEIVING');
    }

    // =========================================================================
    // Stale DRAFT / READY_REVIEW on Fully Received PO (Legacy Orphan Session)
    // =========================================================================

    public function test_fully_received_po_with_stale_draft_session_shows_warning_and_view_session()
    {
        $this->actingAs($this->user);
        $po = $this->createPo('WF-TEST-STALE-DRAFT');

        // Fully receive in WMS with completed session
        $po->items()->update(['received_qty' => 100.0]);
        $po->recalculateStatus();
        $po->save();
        $this->createSession($po, ReceivingSession::STATUS_COMPLETED);

        // Create a stale legacy DRAFT session (e.g. created prior to hardening)
        $staleDraft = $this->createSession($po, ReceivingSession::STATUS_DRAFT);

        $component = Livewire::test(OutstandingPurchaseShowPage::class, ['id' => $po->id]);

        $component->assertViewHas('operatorAction', 'STALE_DRAFT_FULLY_RECEIVED');
        $component->assertSee('VIEW SESSION #' . $staleDraft->id);
        $component->assertSee('WMS already fully received — this session may be stale');
        $component->assertSee('WMS Fully Received — Stale Session Exists');
        $component->assertDontSee('READY FOR RECEIVING');
        $component->assertDontSee('RESUME RECEIVING');
    }

    public function test_fully_received_po_with_stale_ready_review_session_shows_warning_and_view_session()
    {
        $this->actingAs($this->user);
        $po = $this->createPo('WF-TEST-STALE-RR');

        // Fully receive in WMS with completed session
        $po->items()->update(['received_qty' => 100.0]);
        $po->recalculateStatus();
        $po->save();
        $this->createSession($po, ReceivingSession::STATUS_COMPLETED);

        // Create a stale legacy READY_REVIEW session
        $staleRR = $this->createSession($po, ReceivingSession::STATUS_READY_REVIEW);

        $component = Livewire::test(OutstandingPurchaseShowPage::class, ['id' => $po->id]);

        $component->assertViewHas('operatorAction', 'STALE_DRAFT_FULLY_RECEIVED');
        $component->assertSee('VIEW SESSION #' . $staleRR->id);
        $component->assertSee('WMS already fully received — this session may be stale');
        $component->assertDontSee('READY FOR RECEIVING');
        $component->assertDontSee('RESUME RECEIVING');
    }
}

