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
use App\Models\ReceivingSignature;
use App\Models\StockMovement;
use App\Models\StockTransaction;
use App\Livewire\Receiving\ReceivingSessionPage;
use App\Livewire\OutstandingPurchase\OutstandingPurchaseShowPage;
use App\Services\Inventory\InventoryService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class ReceivingFinalCommitTest extends TestCase
{
    use DatabaseTransactions;

    protected User $user;
    protected User $otherUser;
    protected Warehouse $warehouse;
    protected Warehouse $otherWarehouse;
    protected ItemVariant $variant1;
    protected ItemVariant $variant2;
    protected Supplier $supplier;
    protected Bin $bin1;
    protected Bin $bin2;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        // 1. Create User
        $this->user = User::create([
            'name' => 'WMS Supervisor',
            'email' => 'supervisor_' . uniqid() . '@example.com',
            'password' => bcrypt('password'),
            'role' => 'supervisor',
        ]);

        $this->otherUser = User::create([
            'name' => 'Other Warehouse User',
            'email' => 'other_' . uniqid() . '@example.com',
            'password' => bcrypt('password'),
            'role' => 'operator',
        ]);

        // 2. Warehouses
        $this->warehouse = Warehouse::firstOrCreate(
            ['code' => 'SPAREPART'],
            ['name' => 'Sparepart Warehouse', 'status' => 'ACTIVE']
        );

        $this->otherWarehouse = Warehouse::firstOrCreate(
            ['code' => 'RAW_MATERIAL'],
            ['name' => 'Raw Material Warehouse', 'status' => 'ACTIVE']
        );

        $this->user->warehouses()->syncWithoutDetaching([$this->warehouse->id]);
        $this->otherUser->warehouses()->syncWithoutDetaching([$this->otherWarehouse->id]);

        session(['active_warehouse_id' => $this->warehouse->id]);
        $this->actingAs($this->user);

        // 3. Supplier
        $this->supplier = Supplier::create([
            'name' => 'PT. INDO BEARINGS TEKNIK',
        ]);

        // 4. Master items and variants
        $item1 = Item::create(['name' => 'Deep Groove Ball Bearing 6205']);
        $this->variant1 = ItemVariant::create([
            'item_id' => $item1->id,
            'erp_code' => '5.01.BEAR6205',
            'sku' => 'SKU-BEAR-6205',
            'unit' => 'PCS',
        ]);

        $item2 = Item::create(['name' => 'V-Belt Mitsuboshi B-55']);
        $this->variant2 = ItemVariant::create([
            'item_id' => $item2->id,
            'erp_code' => '5.01.BELTB55',
            'sku' => 'SKU-BELT-B55',
            'unit' => 'PCS',
        ]);

        // 5. Locations and Bins
        $location = Location::create([
            'warehouse_id' => $this->warehouse->id,
            'name' => 'Zone B - Receiving Bin',
            'code' => 'LOC-RCV-' . uniqid(),
            'type' => 'STORAGE',
        ]);

        $this->bin1 = Bin::create([
            'warehouse_id' => $this->warehouse->id,
            'location_id' => $location->id,
            'item_variant_id' => $this->variant1->id,
            'code' => 'BIN-B1-' . uniqid(),
            'capacity' => 1000,
        ]);

        $this->bin2 = Bin::create([
            'warehouse_id' => $this->warehouse->id,
            'location_id' => $location->id,
            'item_variant_id' => $this->variant2->id,
            'code' => 'BIN-B2-' . uniqid(),
            'capacity' => 1000,
        ]);
    }

    /**
     * Helper to create a ready PO with items and a corresponding session.
     */
    protected function createPoAndSession(array $itemsData, string $sessionStatus = ReceivingSession::STATUS_READY_REVIEW): array
    {
        $po = OutstandingPurchaseOrder::create([
            'warehouse_id' => $this->warehouse->id,
            'supplier_id' => $this->supplier->id,
            'supplier_name_snapshot' => $this->supplier->name,
            'po_number' => 'PO-P3-' . uniqid(),
            'po_date' => now()->format('Y-m-d'),
            'status' => OutstandingPurchaseOrder::STATUS_PENDING,
            'is_archived' => false,
            'source' => 'ERP_IMPORT',
        ]);

        $session = ReceivingSession::create([
            'warehouse_id' => $this->warehouse->id,
            'outstanding_purchase_order_id' => $po->id,
            'status' => $sessionStatus,
            'created_by' => $this->user->id,
            'started_at' => now(),
        ]);

        $sessionItems = [];
        foreach ($itemsData as $idx => $data) {
            $poItem = OutstandingPurchaseOrderItem::create([
                'outstanding_purchase_order_id' => $po->id,
                'item_variant_id' => $data['variant']->id,
                'erp_code' => $data['variant']->erp_code,
                'item_name_snapshot' => $data['variant']->item->name,
                'ordered_qty' => $data['ordered_qty'],
                'received_qty' => $data['po_received_qty'] ?? 0.0,
                'unit' => $data['variant']->unit,
                'line_number' => $idx + 1,
            ]);

            $sessionItem = ReceivingSessionItem::create([
                'receiving_session_id' => $session->id,
                'outstanding_purchase_order_item_id' => $poItem->id,
                'item_variant_id' => $data['variant']->id,
                'expected_qty' => $data['expected_qty'] ?? $poItem->pending_qty,
                'qty_datang' => $data['qty_datang'] ?? $data['received_qty'] ?? 0.0,
                'received_qty' => $data['received_qty'] ?? 0.0,
                'check_result' => $data['check_result'] ?? 'OK',
                'check_notes' => $data['check_notes'] ?? null,
                'verification_status' => $data['verification_status'] ?? ReceivingSessionItem::STATUS_VERIFIED,
                'removed_reason' => $data['removed_reason'] ?? null,
            ]);

            $sessionItems[] = $sessionItem;
        }

        $po->receiving_session_id = $session->id;
        $po->save();

        return [$po, $session, $sessionItems];
    }

    /**
     * 1. Review changes READY_REVIEW -> REVIEWED.
     */
    public function test_1_review_changes_ready_review_to_reviewed()
    {
        [$po, $session, $items] = $this->createPoAndSession([
            ['variant' => $this->variant1, 'ordered_qty' => 5.0, 'received_qty' => 5.0]
        ], ReceivingSession::STATUS_READY_REVIEW);

        Livewire::test(ReceivingSessionPage::class, ['id' => $session->id])
            ->call('reviewAndConfirm');

        $session->refresh();
        $this->assertEquals(ReceivingSession::STATUS_REVIEWED, $session->status);
        $this->assertEquals($this->user->id, $session->reviewed_by);
        $this->assertNotNull($session->reviewed_at);
    }

    /**
     * 2. Cannot review pending session.
     */
    public function test_2_cannot_review_pending_session()
    {
        [$po, $session, $items] = $this->createPoAndSession([
            ['variant' => $this->variant1, 'ordered_qty' => 5.0, 'received_qty' => 0.0, 'verification_status' => ReceivingSessionItem::STATUS_PENDING]
        ], ReceivingSession::STATUS_DRAFT);

        Livewire::test(ReceivingSessionPage::class, ['id' => $session->id])
            ->call('reviewAndConfirm');

        $session->refresh();
        $this->assertEquals(ReceivingSession::STATUS_DRAFT, $session->status);
    }

    /**
     * 3. Optional signature allows finalize without signatures.
     */
    public function test_3_optional_signature_allows_finalize()
    {
        [$po, $session, $items] = $this->createPoAndSession([
            ['variant' => $this->variant1, 'ordered_qty' => 5.0, 'received_qty' => 5.0]
        ], ReceivingSession::STATUS_REVIEWED);

        Livewire::test(ReceivingSessionPage::class, ['id' => $session->id])
            ->call('finalizeReceiving', app(InventoryService::class));

        $session->refresh();
        $this->assertEquals(ReceivingSession::STATUS_COMPLETED, $session->status);
    }

    /**
     * 4. Signatures persist and render correctly when provided.
     */
    public function test_4_signatures_persist_correctly()
    {
        [$po, $session, $items] = $this->createPoAndSession([
            ['variant' => $this->variant1, 'ordered_qty' => 5.0, 'received_qty' => 5.0]
        ], ReceivingSession::STATUS_REVIEWED);

        $fakeBase64 = 'data:image/png;base64,' . base64_encode('fake-png-content');

        Livewire::test(ReceivingSessionPage::class, ['id' => $session->id])
            ->call('saveSignature', 'DISERAHKAN_OLEH', $fakeBase64)
            ->call('saveSignature', 'DITERIMA_OLEH', $fakeBase64)
            ->call('saveSignature', 'BAG_GUDANG', $fakeBase64);

        $this->assertEquals(3, ReceivingSignature::where('receiving_session_id', $session->id)->count());
    }

    /**
     * 5. Finalize creates exactly one StockTransaction.
     */
    public function test_5_finalize_creates_exactly_one_stock_transaction()
    {
        [$po, $session, $items] = $this->createPoAndSession([
            ['variant' => $this->variant1, 'ordered_qty' => 5.0, 'received_qty' => 5.0],
            ['variant' => $this->variant2, 'ordered_qty' => 2.0, 'received_qty' => 2.0],
        ], ReceivingSession::STATUS_REVIEWED);

        Livewire::test(ReceivingSessionPage::class, ['id' => $session->id])
            ->call('finalizeReceiving', app(InventoryService::class));

        $txs = StockTransaction::where('reference', 'RECEIVING_SESSION:' . $session->id)->get();

        $this->assertEquals(1, $txs->count());
        $this->assertEquals('IN', $txs->first()->type);
    }

    /**
     * 6. Finalize creates StockMovement records for verified items.
     */
    public function test_6_finalize_creates_stock_movement()
    {
        [$po, $session, $items] = $this->createPoAndSession([
            ['variant' => $this->variant1, 'ordered_qty' => 5.0, 'received_qty' => 5.0],
            ['variant' => $this->variant2, 'ordered_qty' => 3.0, 'received_qty' => 3.0],
        ], ReceivingSession::STATUS_REVIEWED);

        $movementsBefore = StockMovement::count();

        Livewire::test(ReceivingSessionPage::class, ['id' => $session->id])
            ->call('finalizeReceiving', app(InventoryService::class));

        $this->assertEquals($movementsBefore + 2, StockMovement::count());
    }

    /**
     * 7. Inventory increases correctly in destination bin.
     */
    public function test_7_inventory_increases_correctly()
    {
        [$po, $session, $items] = $this->createPoAndSession([
            ['variant' => $this->variant1, 'ordered_qty' => 5.0, 'received_qty' => 5.0],
        ], ReceivingSession::STATUS_REVIEWED);

        $qtyBefore = (float)$this->bin1->current_qty;

        Livewire::test(ReceivingSessionPage::class, ['id' => $session->id])
            ->call('finalizeReceiving', app(InventoryService::class));

        $this->bin1->refresh();
        $this->assertEquals($qtyBefore + 5.0, (float)$this->bin1->current_qty);
    }

    /**
     * 8. PO received_qty increments correctly.
     */
    public function test_8_po_received_qty_increments()
    {
        [$po, $session, $items] = $this->createPoAndSession([
            ['variant' => $this->variant1, 'ordered_qty' => 10.0, 'received_qty' => 4.0],
        ], ReceivingSession::STATUS_REVIEWED);

        Livewire::test(ReceivingSessionPage::class, ['id' => $session->id])
            ->call('finalizeReceiving', app(InventoryService::class));

        $poItem = $po->items()->first();
        $this->assertEquals(4.0, (float)$poItem->received_qty);
    }

    /**
     * 9. Partial receipt changes PO status to PARTIAL.
     */
    public function test_9_partial_receipt_po_status_becomes_partial()
    {
        [$po, $session, $items] = $this->createPoAndSession([
            ['variant' => $this->variant1, 'ordered_qty' => 10.0, 'received_qty' => 4.0],
        ], ReceivingSession::STATUS_REVIEWED);

        Livewire::test(ReceivingSessionPage::class, ['id' => $session->id])
            ->call('finalizeReceiving', app(InventoryService::class));

        $po->refresh();
        $this->assertEquals(OutstandingPurchaseOrder::STATUS_PARTIAL, $po->status);
    }

    /**
     * 10. Fully received PO status becomes CLOSED.
     */
    public function test_10_fully_received_po_status_becomes_closed()
    {
        [$po, $session, $items] = $this->createPoAndSession([
            ['variant' => $this->variant1, 'ordered_qty' => 5.0, 'received_qty' => 5.0],
        ], ReceivingSession::STATUS_REVIEWED);

        Livewire::test(ReceivingSessionPage::class, ['id' => $session->id])
            ->call('finalizeReceiving', app(InventoryService::class));

        $po->refresh();
        $this->assertEquals(OutstandingPurchaseOrder::STATUS_CLOSED, $po->status);
    }

    /**
     * 11. Removed items are ignored in inventory movements and PO increments.
     */
    public function test_11_removed_items_ignored_in_inventory_and_po()
    {
        [$po, $session, $items] = $this->createPoAndSession([
            ['variant' => $this->variant1, 'ordered_qty' => 5.0, 'received_qty' => 5.0, 'verification_status' => ReceivingSessionItem::STATUS_VERIFIED],
            ['variant' => $this->variant2, 'ordered_qty' => 2.0, 'received_qty' => 0.0, 'verification_status' => ReceivingSessionItem::STATUS_REMOVED, 'removed_reason' => 'CANCELLED'],
        ], ReceivingSession::STATUS_REVIEWED);

        $bin2QtyBefore = (float)$this->bin2->current_qty;

        Livewire::test(ReceivingSessionPage::class, ['id' => $session->id])
            ->call('finalizeReceiving', app(InventoryService::class));

        $this->bin2->refresh();
        $this->assertEquals($bin2QtyBefore, (float)$this->bin2->current_qty);

        $poItem2 = $po->items()->where('erp_code', $this->variant2->erp_code)->first();
        $this->assertEquals(0.0, (float)$poItem2->received_qty);
    }

    /**
     * 12. Completed session is immutable.
     */
    public function test_12_completed_session_is_immutable()
    {
        [$po, $session, $items] = $this->createPoAndSession([
            ['variant' => $this->variant1, 'ordered_qty' => 5.0, 'received_qty' => 5.0],
        ], ReceivingSession::STATUS_COMPLETED);

        Livewire::test(ReceivingSessionPage::class, ['id' => $session->id])
            ->call('setQtyTerimaManual', $items[0]->id, 99.0)
            ->call('clearSignature', 'DISERAHKAN_OLEH');

        $items[0]->refresh();
        $this->assertEquals(5.0, (float)$items[0]->received_qty);
    }

    /**
     * 13. Duplicate finalize is prevented.
     */
    public function test_13_duplicate_finalize_is_prevented()
    {
        [$po, $session, $items] = $this->createPoAndSession([
            ['variant' => $this->variant1, 'ordered_qty' => 5.0, 'received_qty' => 5.0],
        ], ReceivingSession::STATUS_REVIEWED);

        $invService = app(InventoryService::class);

        // Pass 1
        Livewire::test(ReceivingSessionPage::class, ['id' => $session->id])
            ->call('finalizeReceiving', $invService);

        $session->refresh();
        $this->assertEquals(ReceivingSession::STATUS_COMPLETED, $session->status);

        $movementsCount = StockMovement::where('reference', 'like', "%{$po->po_number}%")->count();

        // Pass 2 (simulate duplicate submit)
        Livewire::test(ReceivingSessionPage::class, ['id' => $session->id])
            ->call('finalizeReceiving', $invService);

        // No new movements created
        $this->assertEquals($movementsCount, StockMovement::where('reference', 'like', "%{$po->po_number}%")->count());
    }

    /**
     * 14. PDF generated and path stored.
     */
    public function test_14_pdf_generated_and_path_stored()
    {
        [$po, $session, $items] = $this->createPoAndSession([
            ['variant' => $this->variant1, 'ordered_qty' => 5.0, 'received_qty' => 5.0],
        ], ReceivingSession::STATUS_REVIEWED);

        Livewire::test(ReceivingSessionPage::class, ['id' => $session->id])
            ->call('finalizeReceiving', app(InventoryService::class));

        $session->refresh();
        $this->assertNotNull($session->pdf_path);
        $this->assertTrue(Storage::disk('public')->exists($session->pdf_path));
    }

    /**
     * 15. Warehouse isolation strictly blocks access across warehouse boundaries.
     */
    public function test_15_warehouse_isolation_strictly_enforced()
    {
        [$po, $session, $items] = $this->createPoAndSession([
            ['variant' => $this->variant1, 'ordered_qty' => 5.0, 'received_qty' => 5.0],
        ], ReceivingSession::STATUS_REVIEWED);

        // Switch context to raw material warehouse
        session(['active_warehouse_id' => $this->otherWarehouse->id]);
        $this->actingAs($this->otherUser);

        Livewire::test(ReceivingSessionPage::class, ['id' => $session->id])
            ->assertStatus(403);
    }

    /**
     * 16. Partial receiving session 2 correctly snapshots remaining pending quantity.
     */
    public function test_16_partial_receiving_session_2_snapshots_remaining_qty()
    {
        // 1. PO Ordered = 10 PCS
        $po = OutstandingPurchaseOrder::create([
            'warehouse_id' => $this->warehouse->id,
            'supplier_id' => $this->supplier->id,
            'supplier_name_snapshot' => $this->supplier->name,
            'po_number' => 'PO-PARTIAL-SEQ-' . uniqid(),
            'po_date' => now()->format('Y-m-d'),
            'status' => OutstandingPurchaseOrder::STATUS_PENDING,
            'is_archived' => false,
            'source' => 'ERP_IMPORT',
        ]);

        $poItem = OutstandingPurchaseOrderItem::create([
            'outstanding_purchase_order_id' => $po->id,
            'item_variant_id' => $this->variant1->id,
            'erp_code' => $this->variant1->erp_code,
            'item_name_snapshot' => $this->variant1->item->name,
            'ordered_qty' => 10.0,
            'received_qty' => 0.0,
            'unit' => 'PCS',
            'line_number' => 1,
        ]);

        // 2. First receiving session receives 6 PCS and completes
        $session1 = ReceivingSession::create([
            'warehouse_id' => $this->warehouse->id,
            'outstanding_purchase_order_id' => $po->id,
            'status' => ReceivingSession::STATUS_REVIEWED,
            'created_by' => $this->user->id,
            'started_at' => now(),
        ]);

        ReceivingSessionItem::create([
            'receiving_session_id' => $session1->id,
            'outstanding_purchase_order_item_id' => $poItem->id,
            'item_variant_id' => $this->variant1->id,
            'expected_qty' => 10.0,
            'qty_datang' => 6.0,
            'received_qty' => 6.0,
            'check_result' => 'OK',
            'verification_status' => ReceivingSessionItem::STATUS_VERIFIED,
        ]);

        Livewire::test(ReceivingSessionPage::class, ['id' => $session1->id])
            ->call('finalizeReceiving', app(InventoryService::class));

        $poItem->refresh();
        $this->assertEquals(6.0, (float)$poItem->received_qty);
        $this->assertEquals(4.0, (float)$poItem->pending_qty);

        // 3. Start receiving session 2 from show page
        Livewire::test(OutstandingPurchaseShowPage::class, ['id' => $po->id])
            ->call('startReceivingSession');

        $session2 = ReceivingSession::where('outstanding_purchase_order_id', $po->id)
            ->where('id', '!=', $session1->id)
            ->first();

        $this->assertNotNull($session2);
        $session2Item = $session2->items()->first();

        // Expected in session 2 MUST BE 4 PCS, NOT 10 PCS
        $this->assertEquals(4.0, (float)$session2Item->expected_qty);
    }
}
