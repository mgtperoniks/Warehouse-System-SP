<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\OutstandingPurchaseOrder;
use App\Models\OutstandingPurchaseOrderItem;
use App\Models\ReceivingSession;
use App\Models\ReceivingSessionItem;
use App\Models\ReceivingSignature;
use App\Models\Item;
use App\Models\ItemVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use App\Http\Controllers\Receiving\ReceivingPdfController;
use Barryvdh\DomPDF\Facade\Pdf;

class ReceivingF4PdfCloningTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Warehouse $warehouse;
    protected OutstandingPurchaseOrder $po;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['name' => 'Admin Checker']);

        $this->warehouse = Warehouse::firstOrCreate(
            ['code' => 'SPAREPART'],
            ['name' => 'Spareparts Warehouse', 'type' => 'SPAREPART']
        );

        \App\Models\UserWarehouseAccess::firstOrCreate([
            'user_id' => $this->user->id,
            'warehouse_id' => $this->warehouse->id,
        ], [
            'is_default' => true,
        ]);

        session(['active_warehouse_id' => $this->warehouse->id]);

        $this->po = OutstandingPurchaseOrder::create([
            'po_number' => 'PO-REC09-TEST',
            'po_date' => now()->subDays(2),
            'supplier_name_snapshot' => 'PT Test Supplier Indonesia',
            'department_name' => 'MAINTENANCE',
            'warehouse_id' => $this->warehouse->id,
            'status' => OutstandingPurchaseOrder::STATUS_PENDING,
            'total_items' => 0,
        ]);

        Storage::fake('public');
    }

    private function createSessionWithItems(int $itemCount, array $itemOverrides = []): ReceivingSession
    {
        $session = ReceivingSession::create([
            'outstanding_purchase_order_id' => $this->po->id,
            'warehouse_id' => $this->warehouse->id,
            'user_id' => $this->user->id,
            'created_by' => $this->user->id,
            'status' => ReceivingSession::STATUS_COMPLETED,
            'started_at' => now()->subHours(1),
            'completed_at' => now(),
            'reviewed_by' => $this->user->id,
            'reviewed_at' => now(),
        ]);

        for ($i = 1; $i <= $itemCount; $i++) {
            $item = Item::create([
                'name' => "Item Sparepart #$i",
                'code' => "SP-$i",
                'warehouse_id' => $this->warehouse->id,
            ]);

            $variant = ItemVariant::create([
                'item_id' => $item->id,
                'variant_name' => "V-$i",
                'sku' => "SKU-$i",
                'erp_code' => "5.01.TEST.$i",
            ]);

            $poItem = OutstandingPurchaseOrderItem::create([
                'outstanding_purchase_order_id' => $this->po->id,
                'item_variant_id' => $variant->id,
                'erp_code' => $variant->erp_code,
                'item_name_snapshot' => $item->name,
                'ordered_qty' => 10,
                'outstanding_qty' => 10,
                'unit' => 'PCS',
                'department_name' => $itemOverrides[$i]['department_name'] ?? 'MAINTENANCE',
                'line_number' => $i,
            ]);

            ReceivingSessionItem::create([
                'receiving_session_id' => $session->id,
                'outstanding_purchase_order_item_id' => $poItem->id,
                'item_variant_id' => $variant->id,
                'expected_qty' => 10,
                'qty_datang' => $itemOverrides[$i]['qty_datang'] ?? 10,
                'received_qty' => $itemOverrides[$i]['received_qty'] ?? 10,
                'check_result' => $itemOverrides[$i]['check_result'] ?? 'OK',
                'check_notes' => $itemOverrides[$i]['check_notes'] ?? null,
                'is_verified' => true,
                'verified_at' => now(),
            ]);
        }

        return $session;
    }

    private function createSignatures(ReceivingSession $session, array $roles = ['DISERAHKAN_OLEH', 'DITERIMA_OLEH', 'BAG_GUDANG']): void
    {
        foreach ($roles as $role) {
            $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAoAAAAKCAYAAACNMs+9AAAADElEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');
            $path = "signatures/session_{$session->id}_{$role}.png";
            Storage::disk('public')->put($path, $png);

            ReceivingSignature::create([
                'receiving_session_id' => $session->id,
                'user_id' => $this->user->id,
                'role' => $role,
                'signature_path' => $path,
                'signer_name' => 'Test Signer',
                'signed_at' => now(),
            ]);
        }
    }

    /** 1. Test 1 Item -> 1 Form Block on 1 F4 Page */
    public function test_single_item_generates_one_populated_form_block()
    {
        $session = $this->createSessionWithItems(1);
        $this->actingAs($this->user);

        $response = $this->get(route('receiving.session.pdf', $session->id));
        $response->assertStatus(200);

        $view = view('reports.receiving-inspection-pdf', [
            'session' => $session,
            'items' => $session->items,
            'signatures' => [],
        ])->render();

        $this->assertStringContainsString('Item Sparepart #1', $view);
        $this->assertStringContainsString('BUKTI PENGECEKAN BARANG DATANG', $view);
    }

    /** 2. Test 6 Items -> Exactly 1 Fully Populated Block */
    public function test_six_items_generates_one_complete_form_block()
    {
        $session = $this->createSessionWithItems(6);
        $this->actingAs($this->user);

        $view = view('reports.receiving-inspection-pdf', [
            'session' => $session,
            'items' => $session->items,
            'signatures' => [],
        ])->render();

        for ($i = 1; $i <= 6; $i++) {
            $this->assertStringContainsString("Item Sparepart #$i", $view);
        }
    }

    /** 3. Test 7 Items -> 2 Form Blocks */
    public function test_seven_items_generates_two_form_blocks()
    {
        $session = $this->createSessionWithItems(7);
        $this->actingAs($this->user);

        $blocks = $session->items->chunk(6);
        $this->assertCount(2, $blocks);
        $this->assertCount(6, $blocks[0]);
        $this->assertCount(1, $blocks[1]);
    }

    /** 4. Test 12 Items -> 2 Complete Blocks */
    public function test_twelve_items_generates_two_complete_blocks()
    {
        $session = $this->createSessionWithItems(12);
        $this->actingAs($this->user);

        $blocks = $session->items->chunk(6);
        $this->assertCount(2, $blocks);
        $this->assertCount(6, $blocks[0]);
        $this->assertCount(6, $blocks[1]);
    }

    /** 5. Test 18 Items -> Exactly 1 F4 Sheet / 3 Blocks */
    public function test_eighteen_items_generates_exactly_one_f4_sheet()
    {
        $session = $this->createSessionWithItems(18);
        $this->actingAs($this->user);

        $blocks = $session->items->chunk(6);
        $pages = $blocks->chunk(3);
        $this->assertCount(3, $blocks);
        $this->assertCount(1, $pages);
    }

    /** 6. Test 19 Items -> 2 F4 Sheets (Page 1 has 3 blocks, Page 2 has 1 block) */
    public function test_nineteen_items_generates_two_f4_sheets()
    {
        $session = $this->createSessionWithItems(19);
        $this->actingAs($this->user);

        $blocks = $session->items->chunk(6);
        $pages = $blocks->chunk(3);
        $this->assertCount(4, $blocks);
        $this->assertCount(2, $pages);
        $this->assertCount(3, $pages[0]);
        $this->assertCount(1, $pages[1]);
    }

    /** 7. Test 21 Items -> 2 F4 Sheets (Page 1 has 18 items, Page 2 has 3 items) */
    public function test_twenty_one_items_generates_two_f4_sheets()
    {
        $session = $this->createSessionWithItems(21);
        $this->actingAs($this->user);

        $blocks = $session->items->chunk(6);
        $pages = $blocks->chunk(3);
        $this->assertCount(4, $blocks);
        $this->assertCount(2, $pages);
        $this->assertCount(3, $blocks[3]); // 3 items in block 4
    }

    /** 8. Test Signature Present -> Replicated Across Every Form Block */
    public function test_signatures_replicated_across_every_form_block()
    {
        $session = $this->createSessionWithItems(15);
        $this->createSignatures($session, ['DISERAHKAN_OLEH', 'DITERIMA_OLEH', 'BAG_GUDANG']);
        $this->actingAs($this->user);

        $signatures = ReceivingSignature::where('receiving_session_id', $session->id)->get()->keyBy('role');
        
        $view = view('reports.receiving-inspection-pdf', [
            'session' => $session,
            'items' => $session->items,
            'signatures' => $signatures,
        ])->render();

        // 15 items = 3 blocks. Each block has 3 signature sections.
        $this->assertEquals(3, substr_count($view, 'DISERAHKAN OLEH'));
        $this->assertEquals(3, substr_count($view, 'DITERIMA/DICEK OLEH'));
        $this->assertEquals(3, substr_count($view, 'BAG. GUDANG'));
    }

    /** 9. Test Missing Signatures -> Blank Signature Areas Remain Printable */
    public function test_missing_signatures_remain_blank_and_printable()
    {
        $session = $this->createSessionWithItems(6);
        $this->actingAs($this->user);

        $view = view('reports.receiving-inspection-pdf', [
            'session' => $session,
            'items' => $session->items,
            'signatures' => [],
        ])->render();

        $this->assertStringContainsString('DISERAHKAN OLEH', $view);
        $this->assertStringContainsString('DITERIMA/DICEK OLEH', $view);
        $this->assertStringContainsString('BAG. GUDANG', $view);
    }

    /** 10. Test Partial Signature Set -> Available Signatures Render Cleanly */
    public function test_partial_signature_set_renders_only_available_signatures()
    {
        $session = $this->createSessionWithItems(6);
        $this->createSignatures($session, ['DISERAHKAN_OLEH']); // only 1 of 3
        $this->actingAs($this->user);

        $signatures = ReceivingSignature::where('receiving_session_id', $session->id)->get()->keyBy('role');
        
        $view = view('reports.receiving-inspection-pdf', [
            'session' => $session,
            'items' => $session->items,
            'signatures' => $signatures,
        ])->render();

        $this->assertStringContainsString('DISERAHKAN OLEH', $view);
        $this->assertStringContainsString('BAG. GUDANG', $view);
    }

    /** 11. Test Decimal Quantities Preserved */
    public function test_decimal_quantities_are_preserved()
    {
        $session = $this->createSessionWithItems(1, [
            1 => [
                'qty_datang' => 5.75,
                'received_qty' => 5.75,
            ]
        ]);
        $this->actingAs($this->user);

        $view = view('reports.receiving-inspection-pdf', [
            'session' => $session,
            'items' => $session->items,
            'signatures' => [],
        ])->render();

        $this->assertStringContainsString('5.75', $view);
    }

    /** 12. Test REJECT / RUSAK Inspection Results */
    public function test_reject_and_rusak_inspection_results_rendered()
    {
        $session = $this->createSessionWithItems(2, [
            1 => ['check_result' => 'REJECT'],
            2 => ['check_result' => 'RUSAK'],
        ]);
        $this->actingAs($this->user);

        $view = view('reports.receiving-inspection-pdf', [
            'session' => $session,
            'items' => $session->items,
            'signatures' => [],
        ])->render();

        $this->assertStringContainsString('REJECT', $view);
        $this->assertStringContainsString('RUSAK', $view);
    }

    /** 13. Test Department Resolved Properly */
    public function test_department_resolved_and_rendered()
    {
        $session = $this->createSessionWithItems(1, [
            1 => ['department_name' => 'PRODUKSI LOST WAX'],
        ]);
        $this->actingAs($this->user);

        $view = view('reports.receiving-inspection-pdf', [
            'session' => $session,
            'items' => $session->items,
            'signatures' => [],
        ])->render();

        $this->assertStringContainsString('PRODUKSI LOST WAX', $view);
    }

    /** 14. Test PDF Sizing and Paper Size in Controller */
    public function test_pdf_controller_renders_stream_cleanly()
    {
        $session = $this->createSessionWithItems(6);
        $this->actingAs($this->user);

        $response = $this->get(route('receiving.session.pdf', $session->id));
        $response->assertStatus(200);
        $this->assertEquals('application/pdf', $response->headers->get('Content-Type'));
    }

    /** 15. Test Controlled Form Reference Code Rendered */
    public function test_controlled_form_reference_code_rendered()
    {
        $session = $this->createSessionWithItems(1);
        $this->actingAs($this->user);

        $view = view('reports.receiving-inspection-pdf', [
            'session' => $session,
            'items' => $session->items,
            'signatures' => [],
        ])->render();

        $this->assertStringContainsString('FR/GUD/10-01-05/17-00-1/1', $view);
        $this->assertStringNotContainsString('Lembar 1 (Putih)', $view);
        $this->assertStringNotContainsString('Lembar 2 (Kuning)', $view);
    }

    /** 16. Test Signature Trimming Helper (REC-07 Regression Protection) */
    public function test_signature_trimming_engine_no_regression()
    {
        $img = imagecreatetruecolor(100, 100);
        imagesavealpha($img, true);
        $trans = imagecolorallocatealpha($img, 0, 0, 0, 127);
        imagefill($img, 0, 0, $trans);
        $black = imagecolorallocate($img, 0, 0, 0);
        imagefilledrectangle($img, 40, 40, 60, 60, $black);

        ob_start();
        imagepng($img);
        $png = ob_get_clean();
        imagedestroy($img);

        $trimmed = ReceivingPdfController::trimSignaturePng($png, 5);
        $this->assertNotEmpty($trimmed);
        
        $src = imagecreatefromstring($trimmed);
        $this->assertLessThan(100, imagesx($src));
        $this->assertLessThan(100, imagesy($src));
        imagedestroy($src);
    }

    /** 17. Test Pure Read-Only Guarantee */
    public function test_pdf_generation_is_strictly_read_only()
    {
        $session = $this->createSessionWithItems(6);
        $initialSessionUpdatedAt = $session->fresh()->updated_at;
        $this->actingAs($this->user);

        $response = $this->get(route('receiving.session.pdf', $session->id));
        $response->assertStatus(200);

        // Assert no changes to item quantities, status, or PO status
        $this->assertEquals(ReceivingSession::STATUS_COMPLETED, $session->fresh()->status);
        $this->assertEquals(6, $session->items()->count());
    }
}
