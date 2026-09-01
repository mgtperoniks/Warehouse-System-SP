<?php

namespace Tests\Feature;

use App\Models\OutstandingPurchaseOrder;
use App\Models\OutstandingPurchaseOrderItem;
use App\Models\ReceivingSession;
use App\Models\ReceivingSessionItem;
use App\Models\ReceivingSignature;
use App\Models\ItemVariant;
use App\Models\Item;
use App\Models\Bin;
use App\Models\Warehouse;
use App\Models\User;
use App\Models\StockTransaction;
use App\Models\StockMovement;
use App\Livewire\Receiving\ReceivingSessionPage;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class ReceivingUXFinalizationREC04Test extends TestCase
{
    use DatabaseTransactions;

    protected $user;
    protected $warehouse;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->warehouse = Warehouse::firstOrCreate(
            ['code' => 'SPAREPART'],
            ['name' => 'Sparepart Warehouse']
        );

        \App\Models\UserWarehouseAccess::firstOrCreate([
            'user_id' => $this->user->id,
            'warehouse_id' => $this->warehouse->id,
        ], [
            'is_default' => true,
        ]);

        session(['active_warehouse_id' => $this->warehouse->id]);
    }

    private function createSession($status = ReceivingSession::STATUS_DRAFT, $linesCount = 2)
    {
        $po = OutstandingPurchaseOrder::create([
            'warehouse_id' => $this->warehouse->id,
            'po_number' => 'PO-REC04-' . uniqid(),
            'po_date' => now(),
            'supplier_name_snapshot' => 'PT Test Supplier REC04',
            'department_name' => 'MAINTENANCE',
            'status' => OutstandingPurchaseOrder::STATUS_PENDING,
            'created_by' => $this->user->id,
        ]);

        $session = ReceivingSession::create([
            'warehouse_id' => $this->warehouse->id,
            'outstanding_purchase_order_id' => $po->id,
            'status' => $status,
            'started_at' => now(),
            'created_by' => $this->user->id,
        ]);

        $location = \App\Models\Location::create([
            'code' => 'LOC-REC04-' . uniqid(),
            'name' => 'Loc REC04',
            'warehouse_id' => $this->warehouse->id,
        ]);

        $items = [];
        for ($i = 1; $i <= $linesCount; $i++) {
            $item = Item::create([
                'name' => "Item Sparepart REC04 {$i} - " . uniqid(),
                'item_type_id' => 1,
            ]);

            $variant = ItemVariant::create([
                'item_id' => $item->id,
                'sku' => "SKU-REC04-{$i}-" . uniqid(),
                'erp_code' => "500-REC04-{$i}",
                'name' => $item->name,
            ]);

            $bin = Bin::create([
                'location_id' => $location->id,
                'name' => "BIN-REC04-{$i}-" . uniqid(),
                'code' => "B-{$i}-" . uniqid(),
                'warehouse_id' => $this->warehouse->id,
                'item_variant_id' => $variant->id,
                'current_qty' => 0,
            ]);

            $poItem = OutstandingPurchaseOrderItem::create([
                'outstanding_purchase_order_id' => $po->id,
                'item_variant_id' => $variant->id,
                'erp_code' => $variant->erp_code,
                'item_name_snapshot' => $item->name,
                'ordered_qty' => 10,
                'received_qty' => 0,
                'pending_qty' => 10,
                'unit' => 'PCS',
                'department_name' => 'MAINTENANCE',
            ]);

            $sessionItem = ReceivingSessionItem::create([
                'receiving_session_id' => $session->id,
                'outstanding_purchase_order_item_id' => $poItem->id,
                'item_variant_id' => $variant->id,
                'expected_qty' => 10,
                'received_qty' => 0,
                'qty_datang' => 10,
                'check_result' => ReceivingSessionItem::CHECK_OK,
                'verification_status' => ReceivingSessionItem::STATUS_PENDING,
            ]);

            $items[] = $sessionItem;
        }

        return [$session, $po, $items];
    }

    /**
     * 1. Changing qty_datang automatically updates qty_terima for normal receiving.
     */
    public function test_qty_datang_auto_syncs_to_qty_terima()
    {
        $this->actingAs($this->user);
        [$session, $po, $items] = $this->createSession(ReceivingSession::STATUS_DRAFT, 1);
        $item = $items[0];

        // Increment Qty Datang
        Livewire::test(ReceivingSessionPage::class, ['id' => $session->id])
            ->call('setQtyDatangManual', $item->id, 7);

        $item->refresh();
        $this->assertEquals(7.0, (float)$item->qty_datang);
        $this->assertEquals(7.0, (float)$item->received_qty);

        // Increment step
        Livewire::test(ReceivingSessionPage::class, ['id' => $session->id])
            ->call('incrementQtyDatang', $item->id, 2);

        $item->refresh();
        $this->assertEquals(9.0, (float)$item->qty_datang);
        $this->assertEquals(9.0, (float)$item->received_qty);

        // Decrement step
        Livewire::test(ReceivingSessionPage::class, ['id' => $session->id])
            ->call('decrementQtyDatang', $item->id, 1);

        $item->refresh();
        $this->assertEquals(8.0, (float)$item->qty_datang);
        $this->assertEquals(8.0, (float)$item->received_qty);
    }

    /**
     * 2. Decimal quantities are preserved accurately and synchronized.
     */
    public function test_decimal_quantities_sync_accurately()
    {
        $this->actingAs($this->user);
        [$session, $po, $items] = $this->createSession(ReceivingSession::STATUS_DRAFT, 1);
        $item = $items[0];

        Livewire::test(ReceivingSessionPage::class, ['id' => $session->id])
            ->call('setQtyDatangManual', $item->id, 0.85);

        $item->refresh();
        $this->assertEquals(0.85, (float)$item->qty_datang);
        $this->assertEquals(0.85, (float)$item->received_qty);
    }

    /**
     * 3. Inspection results and adjust QTY TERIMA for REJECT / RUSAK works.
     */
    public function test_reject_and_rusak_allows_custom_qty_terima()
    {
        $this->actingAs($this->user);
        [$session, $po, $items] = $this->createSession(ReceivingSession::STATUS_DRAFT, 1);
        $item = $items[0];

        // Set Datang to 5, result to RUSAK
        Livewire::test(ReceivingSessionPage::class, ['id' => $session->id])
            ->call('setQtyDatangManual', $item->id, 5)
            ->call('setCheckResult', $item->id, 'RUSAK')
            ->call('setQtyTerimaManual', $item->id, 3)
            ->call('setCheckNotes', $item->id, '2 PCS kemasan rusak');

        $item->refresh();
        $this->assertEquals(5.0, (float)$item->qty_datang);
        $this->assertEquals(3.0, (float)$item->received_qty);
        $this->assertEquals('RUSAK', $item->check_result);
        $this->assertEquals('2 PCS kemasan rusak', $item->check_notes);
    }

    /**
     * 4. Verify item and unverify item allows re-editing.
     */
    public function test_verify_and_unverify_item()
    {
        $this->actingAs($this->user);
        [$session, $po, $items] = $this->createSession(ReceivingSession::STATUS_DRAFT, 1);
        $item = $items[0];

        // Verify item
        Livewire::test(ReceivingSessionPage::class, ['id' => $session->id])
            ->call('setQtyDatangManual', $item->id, 10)
            ->call('verifyLine', $item->id)
            ->assertDispatched('message-dispatched', message: 'Line verified successfully.', type: 'success');

        $item->refresh();
        $this->assertEquals(ReceivingSessionItem::STATUS_VERIFIED, $item->verification_status);

        // Unverify / return to pending for editing
        Livewire::test(ReceivingSessionPage::class, ['id' => $session->id])
            ->call('unverifyLine', $item->id)
            ->assertDispatched('message-dispatched', message: 'Item returned to pending for editing.', type: 'success');

        $item->refresh();
        $this->assertEquals(ReceivingSessionItem::STATUS_PENDING, $item->verification_status);
    }

    /**
     * 5. Pending lines prevent review; all verified allows review.
     */
    public function test_all_verified_allows_ready_review()
    {
        $this->actingAs($this->user);
        [$session, $po, $items] = $this->createSession(ReceivingSession::STATUS_DRAFT, 2);

        // Attempt complete checking while both lines are pending
        Livewire::test(ReceivingSessionPage::class, ['id' => $session->id])
            ->call('completeChecking')
            ->assertDispatched('message-dispatched', message: 'Cannot complete verification: There are still pending lines.', type: 'error');

        // Verify line 1
        Livewire::test(ReceivingSessionPage::class, ['id' => $session->id])
            ->call('verifyLine', $items[0]->id);

        // Still 1 pending
        Livewire::test(ReceivingSessionPage::class, ['id' => $session->id])
            ->call('completeChecking')
            ->assertDispatched('message-dispatched', message: 'Cannot complete verification: There are still pending lines.', type: 'error');

        // Verify line 2
        Livewire::test(ReceivingSessionPage::class, ['id' => $session->id])
            ->call('verifyLine', $items[1]->id);

        // Now complete checking succeeds and transitions to READY_REVIEW
        Livewire::test(ReceivingSessionPage::class, ['id' => $session->id])
            ->call('completeChecking')
            ->assertDispatched('message-dispatched', message: 'Verification complete. Ready for final review.', type: 'success');

        $session->refresh();
        $this->assertEquals(ReceivingSession::STATUS_READY_REVIEW, $session->status);
    }

    /**
     * 6. READY_REVIEW transition to REVIEWED.
     */
    public function test_ready_review_transitions_to_reviewed()
    {
        $this->actingAs($this->user);
        [$session, $po, $items] = $this->createSession(ReceivingSession::STATUS_READY_REVIEW, 1);
        $items[0]->verification_status = ReceivingSessionItem::STATUS_VERIFIED;
        $items[0]->received_qty = 10;
        $items[0]->qty_datang = 10;
        $items[0]->save();

        Livewire::test(ReceivingSessionPage::class, ['id' => $session->id])
            ->call('reviewAndConfirm')
            ->assertDispatched('message-dispatched', message: 'Session reviewed and confirmed. Ready for digital signatures.', type: 'success');

        $session->refresh();
        $this->assertEquals(ReceivingSession::STATUS_REVIEWED, $session->status);
        $this->assertEquals($this->user->id, $session->reviewed_by);
        $this->assertNotNull($session->reviewed_at);
    }

    /**
     * 7. Optional digital signature and final commit.
     */
    public function test_final_commit_without_signature_succeeds_and_mutates_inventory()
    {
        $this->actingAs($this->user);
        [$session, $po, $items] = $this->createSession(ReceivingSession::STATUS_REVIEWED, 1);
        $items[0]->verification_status = ReceivingSessionItem::STATUS_VERIFIED;
        $items[0]->received_qty = 10;
        $items[0]->qty_datang = 10;
        $items[0]->save();

        $bin = Bin::where('item_variant_id', $items[0]->item_variant_id)->first();
        $this->assertEquals(0, $bin->current_qty);

        // Finalize Receiving without any signatures
        Livewire::test(ReceivingSessionPage::class, ['id' => $session->id])
            ->call('finalizeReceiving')
            ->assertDispatched('message-dispatched', message: 'Receiving successfully committed and finalized!', type: 'success');

        $session->refresh();
        $this->assertEquals(ReceivingSession::STATUS_COMPLETED, $session->status);
        $this->assertEquals($this->user->id, $session->completed_by);
        $this->assertNotNull($session->completed_at);

        // Inventory increased
        $bin->refresh();
        $this->assertEquals(10, $bin->current_qty);

        // Stock transaction created
        $tx = StockTransaction::where('reference', 'RECEIVING_SESSION:' . $session->id)->first();
        $this->assertNotNull($tx);
        $this->assertEquals('IN', $tx->type);
        $this->assertEquals('CONFIRMED', $tx->status);

        // Stock movement created
        $movement = StockMovement::where('bin_id', $bin->id)->latest('id')->first();
        $this->assertNotNull($movement);
        $this->assertEquals(10, $movement->qty);
        $this->assertEquals('IN', $movement->type);

        // PO updated
        $poItem = OutstandingPurchaseOrderItem::find($items[0]->outstanding_purchase_order_item_id);
        $this->assertEquals(10, $poItem->received_qty);

        $po->refresh();
        $this->assertEquals(OutstandingPurchaseOrder::STATUS_CLOSED, $po->status);
    }

    /**
     * 8. A4 PDF Route is accessible in READY_REVIEW, REVIEWED, and COMPLETED.
     */
    public function test_pdf_route_is_accessible()
    {
        $this->actingAs($this->user);
        [$session, $po, $items] = $this->createSession(ReceivingSession::STATUS_READY_REVIEW, 1);
        $items[0]->verification_status = ReceivingSessionItem::STATUS_VERIFIED;
        $items[0]->received_qty = 10;
        $items[0]->qty_datang = 10;
        $items[0]->save();

        $response = $this->withSession(['active_warehouse_id' => $this->warehouse->id])
            ->get(route('receiving.session.pdf', $session->id));
        $response->assertStatus(200);
        $this->assertStringContainsString('application/pdf', $response->headers->get('Content-Type'));
    }

    /**
     * 9. PDF contains actual receiving quantities, check results, and department.
     */
    public function test_pdf_contains_actual_quantities_and_details()
    {
        $this->actingAs($this->user);
        [$session, $po, $items] = $this->createSession(ReceivingSession::STATUS_REVIEWED, 1);
        $items[0]->verification_status = ReceivingSessionItem::STATUS_VERIFIED;
        $items[0]->qty_datang = 7.5;
        $items[0]->received_qty = 7.5;
        $items[0]->check_result = 'OK';
        $items[0]->check_notes = 'Pengecekan mulus';
        $items[0]->save();

        $pdfHtml = view('reports.receiving-inspection-pdf', [
            'session' => $session,
            'items' => $session->items()->with(['outstandingPurchaseOrderItem', 'variant.item'])->get(),
            'signatures' => [],
        ])->render();

        $this->assertStringContainsString('BUKTI PENGECEKAN BARANG DATANG', $pdfHtml);
        $this->assertStringContainsString('FR/GUD/10-01-05/17-00-1/1', $pdfHtml);
        $this->assertStringContainsString('7.50', $pdfHtml);
        $this->assertStringContainsString('MAINTENANCE', $pdfHtml);
        $this->assertStringContainsString('Pengecekan mulus', $pdfHtml);
    }

    /**
     * 10. Completed session is strictly immutable and cannot be re-verified or modified.
     */
    public function test_completed_session_is_immutable()
    {
        $this->actingAs($this->user);
        [$session, $po, $items] = $this->createSession(ReceivingSession::STATUS_COMPLETED, 1);

        Livewire::test(ReceivingSessionPage::class, ['id' => $session->id])
            ->call('incrementQtyDatang', $items[0]->id)
            ->assertDispatched('message-dispatched', message: 'Cannot modify quantity. Session is not in DRAFT status.', type: 'error');

        Livewire::test(ReceivingSessionPage::class, ['id' => $session->id])
            ->call('verifyLine', $items[0]->id)
            ->assertDispatched('message-dispatched', message: 'Cannot verify line. Session is not in DRAFT status.', type: 'error');

        Livewire::test(ReceivingSessionPage::class, ['id' => $session->id])
            ->call('unverifyLine', $items[0]->id)
            ->assertDispatched('message-dispatched', message: 'Cannot edit line. Session is not in DRAFT status.', type: 'error');
    }

    /**
     * 11. Mobile safe area classes are properly rendered.
     */
    public function test_mobile_safe_area_classes_are_rendered()
    {
        $this->actingAs($this->user);
        [$session, $po, $items] = $this->createSession(ReceivingSession::STATUS_DRAFT, 1);

        Livewire::test(ReceivingSessionPage::class, ['id' => $session->id])
            ->assertSee('receiving-page-container', false)
            ->assertSee('receiving-bottom-action-bar', false)
            ->assertSee('receiving-toast-container', false);
    }

    /**
     * 12. Mobile digital signature modal has proper responsive width and button grid.
     */
    public function test_mobile_signature_modal_classes_are_rendered()
    {
        $this->actingAs($this->user);
        [$session, $po, $items] = $this->createSession(ReceivingSession::STATUS_REVIEWED, 1);

        Livewire::test(ReceivingSessionPage::class, ['id' => $session->id])
            ->assertSee('w-[94vw]', false)
            ->assertSee('max-w-lg', false)
            ->assertSee('grid-cols-3', false)
            ->assertSee('z-[60]', false);
    }

    /**
     * 13. Transparent whitespace trimming works and preserves signature fidelity.
     */
    public function test_signature_trimming_engine()
    {
        // Create 200x100 transparent image with small 20x20 black box in center
        $im = imagecreatetruecolor(200, 100);
        imagesavealpha($im, true);
        $transparent = imagecolorallocatealpha($im, 0, 0, 0, 127);
        imagefill($im, 0, 0, $transparent);
        $black = imagecolorallocatealpha($im, 15, 23, 42, 0);
        imagefilledrectangle($im, 90, 40, 110, 60, $black);

        ob_start();
        imagepng($im);
        $png = ob_get_clean();
        imagedestroy($im);

        $trimmed = \App\Http\Controllers\Receiving\ReceivingPdfController::trimSignaturePng($png, 5);
        $info = getimagesizefromstring($trimmed);

        // Expected trimmed width: 21 + 10 = 31, height: 21 + 10 = 31
        $this->assertLessThan(200, $info[0]);
        $this->assertLessThan(100, $info[1]);
        $this->assertEquals(31, $info[0]);
        $this->assertEquals(31, $info[1]);
    }

    /**
     * 14. PDF generates cleanly with partial and all signatures.
     */
    public function test_pdf_generates_cleanly_with_signatures()
    {
        $this->actingAs($this->user);
        [$session, $po, $items] = $this->createSession(ReceivingSession::STATUS_REVIEWED, 1);

        // Generate a 100x50 transparent PNG signature
        $im = imagecreatetruecolor(100, 50);
        imagesavealpha($im, true);
        $transparent = imagecolorallocatealpha($im, 0, 0, 0, 127);
        imagefill($im, 0, 0, $transparent);
        $black = imagecolorallocatealpha($im, 15, 23, 42, 0);
        imagefilledrectangle($im, 20, 20, 80, 30, $black);
        ob_start();
        imagepng($im);
        $png = ob_get_clean();
        imagedestroy($im);

        \Illuminate\Support\Facades\Storage::fake('public');
        \Illuminate\Support\Facades\Storage::disk('public')->put('signatures/test_diserahkan.png', $png);
        \Illuminate\Support\Facades\Storage::disk('public')->put('signatures/test_diterima.png', $png);

        \App\Models\ReceivingSignature::create([
            'receiving_session_id' => $session->id,
            'role' => 'DISERAHKAN_OLEH',
            'signature_path' => 'signatures/test_diserahkan.png',
            'signed_by' => $this->user->id,
            'signed_at' => now(),
        ]);

        \App\Models\ReceivingSignature::create([
            'receiving_session_id' => $session->id,
            'role' => 'DITERIMA_OLEH',
            'signature_path' => 'signatures/test_diterima.png',
            'signed_by' => $this->user->id,
            'signed_at' => now(),
        ]);

        $response = $this->get(route('receiving.session.pdf', $session->id));
        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/pdf');
    }

    /**
     * 15. Completed session renders full read-only information and rejects all mutation attempts.
     */
    public function test_completed_session_is_strictly_read_only_in_ui_and_backend()
    {
        $this->actingAs($this->user);
        [$session, $po, $items] = $this->createSession(ReceivingSession::STATUS_COMPLETED, 2);

        $test = Livewire::test(ReceivingSessionPage::class, ['id' => $session->id]);

        // 1. Verify read-only UI presence
        $test->assertSee('RECEIVING COMPLETED', false)
            ->assertSee('READ ONLY — RECEIVING FINALIZED', false)
            ->assertSee('Verified Receiving Items', false)
            ->assertSee('Digital Signatures Record', false)
            ->assertSee('VIEW / PRINT A4 FORM (ISO PDF)', false)
            ->assertSee('Back to Outstanding Purchases', false);

        // 2. Verify editing/verification buttons are NOT rendered
        $test->assertDontSee('VERIFY ITEM', false)
            ->assertDontSee('Edit / Re-check', false)
            ->assertDontSee('CONTINUE TO REVIEW', false)
            ->assertDontSee('CONFIRM REVIEW', false)
            ->assertDontSee('SAVE DRAFT', false)
            ->assertDontSee('Sign (Optional)', false);

        // 3. Verify backend mutation guards reject execution
        $test->call('incrementQtyDatang', $items[0]->id)
            ->assertDispatched('message-dispatched', message: 'Cannot modify quantity. Session is not in DRAFT status.', type: 'error');

        $test->call('decrementQtyDatang', $items[0]->id)
            ->assertDispatched('message-dispatched', message: 'Cannot modify quantity. Session is not in DRAFT status.', type: 'error');

        $test->call('setQtyDatangManual', $items[0]->id, 99)
            ->assertDispatched('message-dispatched', message: 'Cannot modify quantity. Session is not in DRAFT status.', type: 'error');

        $test->call('incrementQtyTerima', $items[0]->id)
            ->assertDispatched('message-dispatched', message: 'Cannot modify quantity. Session is not in DRAFT status.', type: 'error');

        $test->call('decrementQtyTerima', $items[0]->id)
            ->assertDispatched('message-dispatched', message: 'Cannot modify quantity. Session is not in DRAFT status.', type: 'error');

        $test->call('setQtyTerimaManual', $items[0]->id, 99)
            ->assertDispatched('message-dispatched', message: 'Cannot modify quantity. Session is not in DRAFT status.', type: 'error');

        $test->call('setCheckResult', $items[0]->id, 'REJECT')
            ->assertDispatched('message-dispatched', message: 'Cannot modify inspection result. Session is not in DRAFT status.', type: 'error');

        $test->call('verifyLine', $items[0]->id)
            ->assertDispatched('message-dispatched', message: 'Cannot verify line. Session is not in DRAFT status.', type: 'error');

        $test->call('unverifyLine', $items[0]->id)
            ->assertDispatched('message-dispatched', message: 'Cannot edit line. Session is not in DRAFT status.', type: 'error');

        $test->call('openRemoveModal', $items[0]->id)
            ->assertDispatched('message-dispatched', message: 'Cannot remove line. Session is not in DRAFT status.', type: 'error');

        $test->call('removeLine')
            ->assertDispatched('message-dispatched', message: 'Cannot remove line. Session is not in DRAFT status.', type: 'error');

        $test->call('saveDraft')
            ->assertDispatched('message-dispatched', message: 'Cannot save draft. Session is not in DRAFT status.', type: 'error');

        $test->call('completeChecking')
            ->assertDispatched('message-dispatched', message: 'Cannot complete verification. Session is not in DRAFT status.', type: 'error');

        $test->call('reviewAndConfirm')
            ->assertDispatched('message-dispatched', message: 'Cannot review session: Status is not READY_REVIEW.', type: 'error');

        $test->call('saveSignature', 'DISERAHKAN_OLEH', 'data:image/png;base64,AAAA')
            ->assertDispatched('message-dispatched', message: 'Cannot save signature: Session is finalized and immutable.', type: 'error');

        $test->call('clearSignature', 'DISERAHKAN_OLEH')
            ->assertDispatched('message-dispatched', message: 'Cannot clear signature: Session is finalized.', type: 'error');
    }
}
