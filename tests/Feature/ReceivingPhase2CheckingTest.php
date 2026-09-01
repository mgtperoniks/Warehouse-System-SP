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
use App\Livewire\Receiving\ReceivingSessionPage;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

class ReceivingPhase2CheckingTest extends TestCase
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

        // 1. Create User
        $this->user = User::create([
            'name' => 'Field Inspector',
            'email' => 'inspector_' . uniqid() . '@example.com',
            'password' => bcrypt('password'),
            'role' => 'operator',
        ]);

        $this->otherUser = User::create([
            'name' => 'Other Warehouse User',
            'email' => 'other_' . uniqid() . '@example.com',
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

        $this->user->warehouses()->syncWithoutDetaching([$this->warehouse->id]);
        $this->otherUser->warehouses()->syncWithoutDetaching([$this->otherWarehouse->id]);

        session(['active_warehouse_id' => $this->warehouse->id]);
        $this->actingAs($this->user);

        // 3. Create Supplier
        $this->supplier = Supplier::create([
            'name' => 'PT. TEKNIK MANDIRI JAYA',
        ]);

        // 4. Create Master Items and Variants
        $item1 = Item::create(['name' => 'Roller Bearing 6204']);
        $this->variant1 = ItemVariant::create([
            'item_id' => $item1->id,
            'erp_code' => '5.01.BEAR6204',
            'sku' => 'SKU-BEAR-6204',
            'unit' => 'PCS',
        ]);

        $item2 = Item::create(['name' => 'Industrial Grease Lithium']);
        $this->variant2 = ItemVariant::create([
            'item_id' => $item2->id,
            'erp_code' => '2.01.GREASE',
            'sku' => 'SKU-GREASE-LITH',
            'unit' => 'KG',
        ]);

        // 5. Create Bins
        $location = Location::create([
            'warehouse_id' => $this->warehouse->id,
            'name' => 'Zone A - Receiving Staging',
            'code' => 'LOC-STG-' . uniqid(),
            'type' => 'STORAGE',
        ]);

        $this->bin1 = Bin::create([
            'location_id' => $location->id,
            'item_variant_id' => $this->variant1->id,
            'code' => 'BIN-A1-' . uniqid(),
            'capacity' => 1000,
        ]);

        $this->bin2 = Bin::create([
            'location_id' => $location->id,
            'item_variant_id' => $this->variant2->id,
            'code' => 'BIN-A2-' . uniqid(),
            'capacity' => 1000,
        ]);
    }

    /**
     * Helper to create a ready PO with items and a corresponding DRAFT ReceivingSession.
     */
    protected function createPoAndSession(array $itemsData): array
    {
        $po = OutstandingPurchaseOrder::create([
            'warehouse_id' => $this->warehouse->id,
            'supplier_id' => $this->supplier->id,
            'supplier_name_snapshot' => $this->supplier->name,
            'po_number' => 'PO-P2-' . uniqid(),
            'po_date' => now()->format('Y-m-d'),
            'status' => OutstandingPurchaseOrder::STATUS_PENDING,
            'is_archived' => false,
            'source' => 'ERP_IMPORT',
        ]);

        $session = ReceivingSession::create([
            'warehouse_id' => $this->warehouse->id,
            'outstanding_purchase_order_id' => $po->id,
            'status' => ReceivingSession::STATUS_DRAFT,
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
                'received_qty' => $data['received_qty'] ?? 0.0,
                'unit' => $data['variant']->unit,
                'line_number' => $idx + 1,
            ]);

            $sessionItem = ReceivingSessionItem::create([
                'receiving_session_id' => $session->id,
                'outstanding_purchase_order_item_id' => $poItem->id,
                'item_variant_id' => $data['variant']->id,
                'expected_qty' => $poItem->pending_qty,
                'qty_datang' => $data['qty_datang'] ?? 0.0,
                'received_qty' => $data['received_qty'] ?? 0.0,
                'check_result' => $data['check_result'] ?? null,
                'check_notes' => $data['check_notes'] ?? null,
                'verification_status' => $data['verification_status'] ?? ReceivingSessionItem::STATUS_PENDING,
            ]);

            $sessionItems[] = $sessionItem;
        }

        $po->receiving_session_id = $session->id;
        $po->save();

        return [$po, $session, $sessionItems];
    }

    /**
     * TEST 1: Session item supports qty_datang.
     */
    public function test_1_session_item_supports_qty_datang()
    {
        [$po, $session, $items] = $this->createPoAndSession([
            ['variant' => $this->variant1, 'ordered_qty' => 10.0]
        ]);
        $item = $items[0];

        Livewire::test(ReceivingSessionPage::class, ['id' => $session->id])
            ->call('setQtyDatangManual', $item->id, 8.0);

        $item->refresh();
        $this->assertEquals(8.0, (float)$item->qty_datang);
    }

    /**
     * TEST 2: Session item supports received_qty.
     */
    public function test_2_session_item_supports_received_qty()
    {
        [$po, $session, $items] = $this->createPoAndSession([
            ['variant' => $this->variant1, 'ordered_qty' => 10.0]
        ]);
        $item = $items[0];

        Livewire::test(ReceivingSessionPage::class, ['id' => $session->id])
            ->call('setQtyTerimaManual', $item->id, 7.0);

        $item->refresh();
        $this->assertEquals(7.0, (float)$item->received_qty);
    }

    /**
     * TEST 3: Decimal qty_datang is preserved.
     */
    public function test_3_decimal_qty_datang_is_preserved()
    {
        [$po, $session, $items] = $this->createPoAndSession([
            ['variant' => $this->variant2, 'ordered_qty' => 2.5]
        ]);
        $item = $items[0];

        Livewire::test(ReceivingSessionPage::class, ['id' => $session->id])
            ->call('setQtyDatangManual', $item->id, 1.25);

        $item->refresh();
        $this->assertEquals(1.25, (float)$item->qty_datang);
    }

    /**
     * TEST 4: Decimal received_qty is preserved.
     */
    public function test_4_decimal_received_qty_is_preserved()
    {
        [$po, $session, $items] = $this->createPoAndSession([
            ['variant' => $this->variant2, 'ordered_qty' => 2.5]
        ]);
        $item = $items[0];

        Livewire::test(ReceivingSessionPage::class, ['id' => $session->id])
            ->call('setQtyTerimaManual', $item->id, 0.85);

        $item->refresh();
        $this->assertEquals(0.85, (float)$item->received_qty);
    }

    /**
     * TEST 5: Expected qty remains immutable.
     */
    public function test_5_expected_qty_remains_immutable()
    {
        [$po, $session, $items] = $this->createPoAndSession([
            ['variant' => $this->variant1, 'ordered_qty' => 10.0]
        ]);
        $item = $items[0];

        Livewire::test(ReceivingSessionPage::class, ['id' => $session->id])
            ->call('incrementQtyDatang', $item->id, 5)
            ->call('incrementQtyTerima', $item->id, 12);

        $item->refresh();
        $this->assertEquals(10.0, (float)$item->expected_qty);
    }

    /**
     * TEST 6: Partial arrival is allowed.
     */
    public function test_6_partial_arrival_is_allowed()
    {
        [$po, $session, $items] = $this->createPoAndSession([
            ['variant' => $this->variant1, 'ordered_qty' => 10.0]
        ]);
        $item = $items[0];

        Livewire::test(ReceivingSessionPage::class, ['id' => $session->id])
            ->call('setQtyDatangManual', $item->id, 4.0)
            ->call('setQtyTerimaManual', $item->id, 4.0)
            ->call('setCheckResult', $item->id, 'OK')
            ->call('verifyLine', $item->id);

        $item->refresh();
        $this->assertEquals(4.0, (float)$item->qty_datang);
        $this->assertEquals(4.0, (float)$item->received_qty);
        $this->assertEquals(ReceivingSessionItem::STATUS_VERIFIED, $item->verification_status);
    }

    /**
     * TEST 7: Over arrival is allowed.
     */
    public function test_7_over_arrival_is_allowed()
    {
        [$po, $session, $items] = $this->createPoAndSession([
            ['variant' => $this->variant1, 'ordered_qty' => 5.0]
        ]);
        $item = $items[0];

        Livewire::test(ReceivingSessionPage::class, ['id' => $session->id])
            ->call('setQtyDatangManual', $item->id, 7.0)
            ->call('setQtyTerimaManual', $item->id, 7.0)
            ->call('setCheckResult', $item->id, 'OK')
            ->call('verifyLine', $item->id);

        $item->refresh();
        $this->assertEquals(7.0, (float)$item->qty_datang);
        $this->assertEquals(7.0, (float)$item->received_qty);
        $this->assertEquals(ReceivingSessionItem::STATUS_VERIFIED, $item->verification_status);
    }

    /**
     * TEST 8: Partial receiving is allowed (e.g. Datang 5, Terima 3).
     */
    public function test_8_partial_receiving_is_allowed()
    {
        [$po, $session, $items] = $this->createPoAndSession([
            ['variant' => $this->variant1, 'ordered_qty' => 5.0]
        ]);
        $item = $items[0];

        Livewire::test(ReceivingSessionPage::class, ['id' => $session->id])
            ->call('setQtyDatangManual', $item->id, 5.0)
            ->call('setQtyTerimaManual', $item->id, 3.0)
            ->call('setCheckResult', $item->id, 'RUSAK')
            ->call('setCheckNotes', $item->id, '2 pcs bearing cacat')
            ->call('verifyLine', $item->id);

        $item->refresh();
        $this->assertEquals(5.0, (float)$item->qty_datang);
        $this->assertEquals(3.0, (float)$item->received_qty);
        $this->assertEquals('RUSAK', $item->check_result);
        $this->assertEquals('2 pcs bearing cacat', $item->check_notes);
        $this->assertEquals(ReceivingSessionItem::STATUS_VERIFIED, $item->verification_status);
    }

    /**
     * TEST 9: Inspection result OK can be saved.
     */
    public function test_9_inspection_result_ok_can_be_saved()
    {
        [$po, $session, $items] = $this->createPoAndSession([
            ['variant' => $this->variant1, 'ordered_qty' => 10.0]
        ]);
        $item = $items[0];

        Livewire::test(ReceivingSessionPage::class, ['id' => $session->id])
            ->call('setCheckResult', $item->id, 'OK');

        $item->refresh();
        $this->assertEquals('OK', $item->check_result);
    }

    /**
     * TEST 10: Inspection result REJECT can be saved.
     */
    public function test_10_inspection_result_reject_can_be_saved()
    {
        [$po, $session, $items] = $this->createPoAndSession([
            ['variant' => $this->variant1, 'ordered_qty' => 10.0]
        ]);
        $item = $items[0];

        Livewire::test(ReceivingSessionPage::class, ['id' => $session->id])
            ->call('setCheckResult', $item->id, 'REJECT');

        $item->refresh();
        $this->assertEquals('REJECT', $item->check_result);
    }

    /**
     * TEST 11: Inspection result RUSAK can be saved.
     */
    public function test_11_inspection_result_rusak_can_be_saved()
    {
        [$po, $session, $items] = $this->createPoAndSession([
            ['variant' => $this->variant1, 'ordered_qty' => 10.0]
        ]);
        $item = $items[0];

        Livewire::test(ReceivingSessionPage::class, ['id' => $session->id])
            ->call('setCheckResult', $item->id, 'RUSAK');

        $item->refresh();
        $this->assertEquals('RUSAK', $item->check_result);
    }

    /**
     * TEST 12: Check notes are persisted.
     */
    public function test_12_check_notes_are_persisted()
    {
        [$po, $session, $items] = $this->createPoAndSession([
            ['variant' => $this->variant1, 'ordered_qty' => 10.0]
        ]);
        $item = $items[0];

        Livewire::test(ReceivingSessionPage::class, ['id' => $session->id])
            ->call('setCheckNotes', $item->id, 'Kemasan drum penyok saat diturunkan');

        $item->refresh();
        $this->assertEquals('Kemasan drum penyok saat diturunkan', $item->check_notes);
    }

    /**
     * TEST 13: Quantity entry alone does not verify item.
     */
    public function test_13_quantity_entry_alone_does_not_verify_item()
    {
        [$po, $session, $items] = $this->createPoAndSession([
            ['variant' => $this->variant1, 'ordered_qty' => 10.0]
        ]);
        $item = $items[0];

        Livewire::test(ReceivingSessionPage::class, ['id' => $session->id])
            ->call('setQtyDatangManual', $item->id, 10.0)
            ->call('setQtyTerimaManual', $item->id, 10.0);

        $item->refresh();
        $this->assertEquals(ReceivingSessionItem::STATUS_PENDING, $item->verification_status);
        $this->assertFalse($item->isVerified());
    }

    /**
     * TEST 14: Explicit VERIFY changes status to VERIFIED.
     */
    public function test_14_explicit_verify_changes_status_to_verified()
    {
        [$po, $session, $items] = $this->createPoAndSession([
            ['variant' => $this->variant1, 'ordered_qty' => 10.0]
        ]);
        $item = $items[0];

        Livewire::test(ReceivingSessionPage::class, ['id' => $session->id])
            ->call('setQtyDatangManual', $item->id, 10.0)
            ->call('setQtyTerimaManual', $item->id, 10.0)
            ->call('setCheckResult', $item->id, 'OK')
            ->call('verifyLine', $item->id);

        $item->refresh();
        $this->assertEquals(ReceivingSessionItem::STATUS_VERIFIED, $item->verification_status);
        $this->assertTrue($item->isVerified());
    }

    /**
     * TEST 15: Removed item stores removed_reason.
     */
    public function test_15_removed_item_stores_removed_reason()
    {
        [$po, $session, $items] = $this->createPoAndSession([
            ['variant' => $this->variant1, 'ordered_qty' => 10.0]
        ]);
        $item = $items[0];

        Livewire::test(ReceivingSessionPage::class, ['id' => $session->id])
            ->call('openRemoveModal', $item->id)
            ->set('removeReason', 'WRONG WAREHOUSE')
            ->call('removeLine');

        $item->refresh();
        $this->assertEquals(ReceivingSessionItem::STATUS_REMOVED, $item->verification_status);
        $this->assertEquals('WRONG WAREHOUSE', $item->removed_reason);
    }

    /**
     * TEST 16: Removed item does not delete original PO data.
     */
    public function test_16_removed_item_does_not_delete_original_po_data()
    {
        [$po, $session, $items] = $this->createPoAndSession([
            ['variant' => $this->variant1, 'ordered_qty' => 10.0]
        ]);
        $item = $items[0];
        $poItemId = $item->outstanding_purchase_order_item_id;

        Livewire::test(ReceivingSessionPage::class, ['id' => $session->id])
            ->call('openRemoveModal', $item->id)
            ->set('removeReason', 'CANCELLED')
            ->call('removeLine');

        // Original PO item must exist untouched
        $this->assertDatabaseHas('outstanding_purchase_order_items', [
            'id' => $poItemId,
            'ordered_qty' => 10.0,
        ]);
        $this->assertDatabaseHas('outstanding_purchase_orders', [
            'id' => $po->id,
        ]);
    }

    /**
     * TEST 17: Pending line prevents READY_REVIEW.
     */
    public function test_17_pending_line_prevents_ready_review()
    {
        [$po, $session, $items] = $this->createPoAndSession([
            ['variant' => $this->variant1, 'ordered_qty' => 10.0],
            ['variant' => $this->variant2, 'ordered_qty' => 5.0],
        ]);

        // Verify only 1 of 2 lines
        Livewire::test(ReceivingSessionPage::class, ['id' => $session->id])
            ->call('setQtyDatangManual', $items[0]->id, 10.0)
            ->call('setQtyTerimaManual', $items[0]->id, 10.0)
            ->call('setCheckResult', $items[0]->id, 'OK')
            ->call('verifyLine', $items[0]->id)
            ->call('completeChecking');

        $session->refresh();
        $this->assertEquals(ReceivingSession::STATUS_DRAFT, $session->status);
    }

    /**
     * TEST 18: Verified + removed lines allow READY_REVIEW.
     */
    public function test_18_verified_and_removed_lines_allow_ready_review()
    {
        [$po, $session, $items] = $this->createPoAndSession([
            ['variant' => $this->variant1, 'ordered_qty' => 10.0],
            ['variant' => $this->variant2, 'ordered_qty' => 5.0],
        ]);

        // Line 1: VERIFY
        // Line 2: REMOVE
        Livewire::test(ReceivingSessionPage::class, ['id' => $session->id])
            ->call('setQtyDatangManual', $items[0]->id, 10.0)
            ->call('setQtyTerimaManual', $items[0]->id, 10.0)
            ->call('setCheckResult', $items[0]->id, 'OK')
            ->call('verifyLine', $items[0]->id)
            ->call('openRemoveModal', $items[1]->id)
            ->set('removeReason', 'CANCELLED')
            ->call('removeLine')
            ->call('completeChecking');

        $session->refresh();
        $this->assertEquals(ReceivingSession::STATUS_READY_REVIEW, $session->status);
    }

    /**
     * TEST 19: Draft survives reload.
     */
    public function test_19_draft_survives_reload()
    {
        [$po, $session, $items] = $this->createPoAndSession([
            ['variant' => $this->variant1, 'ordered_qty' => 10.0]
        ]);
        $item = $items[0];

        // Component session 1
        Livewire::test(ReceivingSessionPage::class, ['id' => $session->id])
            ->call('setQtyDatangManual', $item->id, 8.0)
            ->call('setQtyTerimaManual', $item->id, 8.0)
            ->call('setCheckResult', $item->id, 'OK')
            ->call('setCheckNotes', $item->id, 'Inspected batch #123')
            ->call('saveDraft');

        // Simulate new browser reload / fresh component instance
        Livewire::test(ReceivingSessionPage::class, ['id' => $session->id])
            ->assertViewHas('items', function ($loadedItems) use ($item) {
                $dbItem = $loadedItems->firstWhere('id', $item->id);
                return (float)$dbItem->qty_datang == 8.0 &&
                       (float)$dbItem->received_qty == 8.0 &&
                       $dbItem->check_result === 'OK' &&
                       $dbItem->check_notes === 'Inspected batch #123';
            });
    }

    /**
     * TEST 20: READY_REVIEW becomes read-only.
     */
    public function test_20_ready_review_becomes_read_only()
    {
        [$po, $session, $items] = $this->createPoAndSession([
            ['variant' => $this->variant1, 'ordered_qty' => 10.0]
        ]);
        $item = $items[0];

        // Complete checking to READY_REVIEW
        Livewire::test(ReceivingSessionPage::class, ['id' => $session->id])
            ->call('setQtyDatangManual', $item->id, 10.0)
            ->call('setQtyTerimaManual', $item->id, 10.0)
            ->call('setCheckResult', $item->id, 'OK')
            ->call('verifyLine', $item->id)
            ->call('completeChecking');

        $session->refresh();
        $this->assertEquals(ReceivingSession::STATUS_READY_REVIEW, $session->status);

        // Attempting to modify quantity or inspection result in READY_REVIEW must be rejected
        Livewire::test(ReceivingSessionPage::class, ['id' => $session->id])
            ->call('setQtyTerimaManual', $item->id, 99.0)
            ->call('setCheckResult', $item->id, 'REJECT');

        $item->refresh();
        $this->assertEquals(10.0, (float)$item->received_qty);
        $this->assertEquals('OK', $item->check_result);
    }

    /**
     * TEST 21: Warehouse isolation works.
     */
    public function test_21_warehouse_isolation_works()
    {
        [$po, $session, $items] = $this->createPoAndSession([
            ['variant' => $this->variant1, 'ordered_qty' => 10.0]
        ]);

        // Attempt access from other warehouse context
        session(['active_warehouse_id' => $this->otherWarehouse->id]);
        $this->actingAs($this->otherUser);

        Livewire::test(ReceivingSessionPage::class, ['id' => $session->id])
            ->assertStatus(403);
    }

    /**
     * TEST 22: No inventory mutation.
     */
    public function test_22_no_inventory_mutation()
    {
        $txCountBefore = StockTransaction::where('warehouse_id', $this->warehouse->id)->count();

        [$po, $session, $items] = $this->createPoAndSession([
            ['variant' => $this->variant1, 'ordered_qty' => 10.0]
        ]);
        $item = $items[0];

        Livewire::test(ReceivingSessionPage::class, ['id' => $session->id])
            ->call('setQtyDatangManual', $item->id, 10.0)
            ->call('setQtyTerimaManual', $item->id, 10.0)
            ->call('setCheckResult', $item->id, 'OK')
            ->call('verifyLine', $item->id)
            ->call('completeChecking');

        // Bins stock must not change and no new transaction created
        $this->assertEquals($txCountBefore, StockTransaction::where('warehouse_id', $this->warehouse->id)->count());
    }

    /**
     * TEST 23: No StockMovement creation.
     */
    public function test_23_no_stock_movement_creation()
    {
        $movementCountBefore = StockMovement::count();

        [$po, $session, $items] = $this->createPoAndSession([
            ['variant' => $this->variant1, 'ordered_qty' => 10.0]
        ]);
        $item = $items[0];

        Livewire::test(ReceivingSessionPage::class, ['id' => $session->id])
            ->call('setQtyDatangManual', $item->id, 10.0)
            ->call('setQtyTerimaManual', $item->id, 10.0)
            ->call('setCheckResult', $item->id, 'OK')
            ->call('verifyLine', $item->id)
            ->call('completeChecking');

        $this->assertEquals($movementCountBefore, StockMovement::count());
    }

    /**
     * TEST 24: No StockTransaction creation.
     */
    public function test_24_no_stock_transaction_creation()
    {
        $txCountBefore = StockTransaction::count();

        [$po, $session, $items] = $this->createPoAndSession([
            ['variant' => $this->variant1, 'ordered_qty' => 10.0]
        ]);
        $item = $items[0];

        Livewire::test(ReceivingSessionPage::class, ['id' => $session->id])
            ->call('setQtyDatangManual', $item->id, 10.0)
            ->call('setQtyTerimaManual', $item->id, 10.0)
            ->call('setCheckResult', $item->id, 'OK')
            ->call('verifyLine', $item->id)
            ->call('completeChecking');

        $this->assertEquals($txCountBefore, StockTransaction::count());
    }

    /**
     * TEST 25: Session never becomes COMPLETED in Phase 2.
     */
    public function test_25_session_never_becomes_completed_in_phase_2()
    {
        [$po, $session, $items] = $this->createPoAndSession([
            ['variant' => $this->variant1, 'ordered_qty' => 10.0]
        ]);
        $item = $items[0];

        Livewire::test(ReceivingSessionPage::class, ['id' => $session->id])
            ->call('setQtyDatangManual', $item->id, 10.0)
            ->call('setQtyTerimaManual', $item->id, 10.0)
            ->call('setCheckResult', $item->id, 'OK')
            ->call('verifyLine', $item->id)
            ->call('completeChecking');

        $session->refresh();
        $this->assertNotEquals(ReceivingSession::STATUS_COMPLETED, $session->status);
        $this->assertEquals(ReceivingSession::STATUS_READY_REVIEW, $session->status);

        // PO received_qty must also remain 0
        $poItem = $po->items()->first();
        $this->assertEquals(0.0, (float)$poItem->received_qty);
        $this->assertEquals(OutstandingPurchaseOrder::STATUS_PENDING, $po->status);
    }
}
