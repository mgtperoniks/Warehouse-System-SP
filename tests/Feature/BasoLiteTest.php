<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\Location;
use App\Models\Bin;
use App\Models\Item;
use App\Models\ItemVariant;
use App\Models\InventoryAdjustment;
use App\Models\InventoryAdjustmentItem;
use App\Models\BasoDocument;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use App\Livewire\Governance\InventoryAdjustmentsPage;

class BasoLiteTest extends TestCase
{
    use DatabaseTransactions;

    protected User $manager;
    protected User $adminUser;
    protected Warehouse $warehouse;
    protected Bin $bin;
    protected ItemVariant $variant;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Create Roles
        $this->manager = User::create([
            'role' => 'manager',
            'name' => 'Manager PPIC ' . uniqid(),
            'email' => 'manager_' . uniqid() . '@peroniks.com',
            'password' => bcrypt('password'),
        ]);

        $this->adminUser = User::create([
            'role' => 'admin',
            'name' => 'Admin Sparepart ' . uniqid(),
            'email' => 'admin_' . uniqid() . '@peroniks.com',
            'password' => bcrypt('password'),
        ]);

        // 2. Create Warehouse
        $this->warehouse = Warehouse::firstOrCreate(
            ['code' => 'SPAREPART'],
            ['name' => 'Sparepart Warehouse', 'status' => 'ACTIVE']
        );
        $this->manager->warehouses()->attach($this->warehouse->id);
        $this->adminUser->warehouses()->attach($this->warehouse->id);
        session(['active_warehouse_id' => $this->warehouse->id]);

        \App\Models\WarehouseFamilyAssignment::firstOrCreate([
            'warehouse_id' => $this->warehouse->id,
            'family_code' => 'ERP'
        ]);
        \App\Models\WarehouseFamilyAssignment::firstOrCreate([
            'warehouse_id' => $this->warehouse->id,
            'family_code' => 'ERP001'
        ]);

        // 3. Create Item & Variant
        $item = Item::firstOrCreate(
            ['name' => 'Test Item']
        );
        
        $this->variant = ItemVariant::firstOrCreate(
            ['sku' => 'VAR001'],
            [
                'item_id' => $item->id,
                'name' => 'Default Variant',
                'erp_code' => 'ERP001',
                'unit' => 'PCS',
            ]
        );

        // 4. Create Location
        $location = Location::firstOrCreate(
            ['code' => 'LOC-A'],
            ['description' => 'Location A']
        );

        // 5. Create Bin
        $this->bin = Bin::firstOrCreate(
            ['warehouse_id' => $this->warehouse->id, 'code' => 'BIN-A1'],
            [
                'location_id' => $location->id,
                'item_variant_id' => $this->variant->id,
                'current_qty' => 10,
                'min_qty' => 1,
                'max_qty' => 100,
            ]
        );

        // Clean up any existing adjustments/documents to prevent test pollution
        BasoDocument::query()->delete();
        InventoryAdjustmentItem::query()->delete();
        InventoryAdjustment::query()->delete();
    }

    protected function createAdjustmentItem($headerId, $status)
    {
        return InventoryAdjustmentItem::create([
            'inventory_adjustment_id' => $headerId,
            'bin_id' => $this->bin->id,
            'item_variant_id' => $this->variant->id,
            'system_qty' => 10,
            'physical_qty' => 12,
            'variance' => 2,
            'reason_code' => 'FOUND_ITEM',
            'notes' => 'Found',
            'status' => $status,
            'item_name_snapshot' => 'Test Item',
            'erp_code_snapshot' => 'ERP001',
            'bin_code_snapshot' => 'BIN-A1',
            'unit_snapshot' => 'PCS',
            'warehouse_name_snapshot' => 'Sparepart Warehouse',
            'operator_name_snapshot' => 'Admin Operator',
        ]);
    }

    public function test_generate_button_only_appears_after_completed_status()
    {
        $this->actingAs($this->manager);

        // 1. WAITING_APPROVAL adjustment should not show "Generate BASO" or allow it
        $headerWaiting = InventoryAdjustment::create([
            'adjustment_no' => 'IA-SP-' . date('Ymd') . '-901-' . uniqid(),
            'warehouse_id' => $this->warehouse->id,
            'operator_id' => $this->adminUser->id,
            'date' => date('Y-m-d'),
            'status' => 'WAITING_APPROVAL',
        ]);
        $this->createAdjustmentItem($headerWaiting->id, 'WAITING');

        $lw = Livewire::test(InventoryAdjustmentsPage::class);
        $lw->assertDontSeeHtml("generateBaso({$headerWaiting->id})");

        // Attempting to generate BASO on waiting status should fail
        $lw->call('generateBaso', $headerWaiting->id);
        $this->assertDatabaseMissing('baso_documents', [
            'inventory_adjustment_id' => $headerWaiting->id,
        ]);

        // 2. COMPLETED status adjustment should display "Generate BASO"
        $headerCompleted = InventoryAdjustment::create([
            'adjustment_no' => 'IA-SP-' . date('Ymd') . '-902-' . uniqid(),
            'warehouse_id' => $this->warehouse->id,
            'operator_id' => $this->adminUser->id,
            'date' => date('Y-m-d'),
            'status' => 'COMPLETED',
        ]);
        $this->createAdjustmentItem($headerCompleted->id, 'APPROVED');

        Livewire::test(InventoryAdjustmentsPage::class)
            ->assertSeeHtml("generateBaso({$headerCompleted->id})");
    }

    public function test_view_baso_route_returns_inline_pdf()
    {
        Storage::fake('public');
        $this->actingAs($this->manager);

        $header = InventoryAdjustment::create([
            'adjustment_no' => 'IA-SP-' . date('Ymd') . '-903-' . uniqid(),
            'warehouse_id' => $this->warehouse->id,
            'operator_id' => $this->adminUser->id,
            'date' => date('Y-m-d'),
            'status' => 'COMPLETED',
        ]);
        $this->createAdjustmentItem($header->id, 'APPROVED');

        // 1. Generate BASO first
        $lw = Livewire::test(InventoryAdjustmentsPage::class);
        $lw->call('generateBaso', $header->id);
        $baso = BasoDocument::where('inventory_adjustment_id', $header->id)->firstOrFail();

        // 2. Request GET /governance/baso/view/{id}
        $response = $this->get(route('governance.baso.view', $baso->id));

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/pdf');
        $response->assertHeader('Content-Disposition', 'inline; filename="' . basename($baso->pdf_path) . '"');
    }

    public function test_missing_pdf_file_regenerates_automatically_on_view()
    {
        Storage::fake('public');
        $this->actingAs($this->manager);

        $header = InventoryAdjustment::create([
            'adjustment_no' => 'IA-SP-' . date('Ymd') . '-904-' . uniqid(),
            'warehouse_id' => $this->warehouse->id,
            'operator_id' => $this->adminUser->id,
            'date' => date('Y-m-d'),
            'status' => 'COMPLETED',
        ]);
        $this->createAdjustmentItem($header->id, 'APPROVED');

        // 1. Generate BASO
        $lw = Livewire::test(InventoryAdjustmentsPage::class);
        $lw->call('generateBaso', $header->id);
        $baso = BasoDocument::where('inventory_adjustment_id', $header->id)->firstOrFail();

        // Delete the physical PDF file from fake disk
        Storage::disk('public')->delete($baso->pdf_path);
        Storage::disk('public')->assertMissing($baso->pdf_path);

        // 2. Request GET /governance/baso/view/{id} - should regenerate and return 200
        $response = $this->get(route('governance.baso.view', $baso->id));

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/pdf');
        
        // Assert file is regenerated in storage
        Storage::disk('public')->assertExists($baso->pdf_path);
    }

    public function test_duplicate_baso_is_never_created()
    {
        Storage::fake('public');
        $this->actingAs($this->manager);

        $header = InventoryAdjustment::create([
            'adjustment_no' => 'IA-SP-' . date('Ymd') . '-905-' . uniqid(),
            'warehouse_id' => $this->warehouse->id,
            'operator_id' => $this->adminUser->id,
            'date' => date('Y-m-d'),
            'status' => 'COMPLETED',
        ]);
        $this->createAdjustmentItem($header->id, 'APPROVED');

        $lw = Livewire::test(InventoryAdjustmentsPage::class);
        $lw->call('generateBaso', $header->id);
        $lw->call('generateBaso', $header->id);

        $this->assertEquals(1, BasoDocument::where('inventory_adjustment_id', $header->id)->count());
    }

    public function test_manager_vs_operator_visibility()
    {
        Storage::fake('public');

        $header = InventoryAdjustment::create([
            'adjustment_no' => 'IA-SP-' . date('Ymd') . '-906-' . uniqid(),
            'warehouse_id' => $this->warehouse->id,
            'operator_id' => $this->adminUser->id,
            'date' => date('Y-m-d'),
            'status' => 'COMPLETED',
        ]);
        $this->createAdjustmentItem($header->id, 'APPROVED');

        // 1. Operator view before generation
        $this->actingAs($this->adminUser);
        $lw = Livewire::test(InventoryAdjustmentsPage::class);
        $lw->assertSee('Awaiting BASO');
        $lw->assertDontSee('Generate BASO');

        // 2. Manager view before generation
        $this->actingAs($this->manager);
        $lw = Livewire::test(InventoryAdjustmentsPage::class);
        $lw->assertSee('Generate BASO');
        $lw->assertDontSee('Awaiting BASO');

        // Manager generates
        $lw->call('generateBaso', $header->id);
        $baso = BasoDocument::where('inventory_adjustment_id', $header->id)->firstOrFail();

        // 3. Manager view after generation
        $lw = Livewire::test(InventoryAdjustmentsPage::class);
        $lw->assertDontSee('Generate BASO');
        $lw->assertSeeHtml(route('governance.baso.view', $baso->id));

        // 4. Operator view after generation
        $this->actingAs($this->adminUser);
        $lw = Livewire::test(InventoryAdjustmentsPage::class);
        $lw->assertDontSee('Awaiting BASO');
        $lw->assertSeeHtml(route('governance.baso.view', $baso->id));
    }

    public function test_daily_consolidation_and_freeze_flow()
    {
        Storage::fake('public');

        // 1. Initial State: No header exists for today.
        // We simulate operator scanning first item.
        $this->actingAs($this->adminUser);
        
        Livewire::test(\App\Livewire\Opname\OpnamePage::class)
            ->set('binScan', $this->bin->code)
            ->set('actualQty', 12) // System is 10, variance = +2
            ->set('reasonCode', 'FOUND_ITEM')
            ->set('notes', 'Found Bearing A')
            ->call('saveItem')
            ->assertHasNoErrors();

        // Assert header is created
        $header1 = InventoryAdjustment::orderBy('id', 'desc')->firstOrFail();
        $this->assertEquals('IA-SPAREPART-' . date('Ymd'), $header1->adjustment_no);
        $this->assertEquals(1, $header1->items()->count());

        // 2. Second Scan before BASO generation: should consolidate into same header
        Livewire::test(\App\Livewire\Opname\OpnamePage::class)
            ->set('binScan', $this->bin->code)
            ->set('actualQty', 8) // variance = -2
            ->set('reasonCode', 'DAMAGED_ITEM')
            ->set('notes', 'Damaged B')
            ->call('saveItem')
            ->assertHasNoErrors();

        $header1->refresh();
        $this->assertEquals(2, $header1->items()->count());
        $this->assertEquals('WAITING_APPROVAL', $header1->status);

        // 3. Manager approves all items -> Status becomes COMPLETED
        $this->actingAs($this->manager);
        $lwAdjustments = Livewire::test(InventoryAdjustmentsPage::class);
        foreach ($header1->items as $item) {
            $lwAdjustments->call('approveItem', $item->id);
        }

        $header1->refresh();
        $this->assertEquals('COMPLETED', $header1->status);

        // 4. Generate BASO for header 1 -> locks it
        $lwAdjustments->call('generateBaso', $header1->id);
        $this->assertTrue($header1->basoDocument()->exists());

        // 5. Operator scans another item after BASO generation -> should create a new header (IA-SPAREPART-YYYYMMDD-2)
        $this->actingAs($this->adminUser);
        Livewire::test(\App\Livewire\Opname\OpnamePage::class)
            ->set('binScan', $this->bin->code)
            ->set('actualQty', 15) // variance = +5
            ->set('reasonCode', 'FOUND_ITEM')
            ->set('notes', 'Found C')
            ->call('saveItem')
            ->assertHasNoErrors();

        $header2 = InventoryAdjustment::orderBy('id', 'desc')->firstOrFail();
        $this->assertNotEquals($header1->id, $header2->id);
        $this->assertEquals('IA-SPAREPART-' . date('Ymd') . '-2', $header2->adjustment_no);
        $this->assertEquals(1, $header2->items()->count());

        // 6. Generate BASO for header 2 and scan third time -> should create IA-SPAREPART-YYYYMMDD-3
        // Manager approves header 2 items
        $this->actingAs($this->manager);
        $lwAdjustments = Livewire::test(InventoryAdjustmentsPage::class);
        foreach ($header2->items as $item) {
            $lwAdjustments->call('approveItem', $item->id);
        }
        $header2->refresh();
        $this->assertEquals('COMPLETED', $header2->status);

        // Generate BASO for header 2
        $lwAdjustments->call('generateBaso', $header2->id);
        $this->assertTrue($header2->basoDocument()->exists());

        // Third scan
        $this->actingAs($this->adminUser);
        Livewire::test(\App\Livewire\Opname\OpnamePage::class)
            ->set('binScan', $this->bin->code)
            ->set('actualQty', 11) // variance = +1
            ->set('reasonCode', 'FOUND_ITEM')
            ->set('notes', 'Found D')
            ->call('saveItem')
            ->assertHasNoErrors();

        $header3 = InventoryAdjustment::orderBy('id', 'desc')->firstOrFail();
        $this->assertNotEquals($header2->id, $header3->id);
        $this->assertEquals('IA-SPAREPART-' . date('Ymd') . '-3', $header3->adjustment_no);

        // Assert 2 BASO documents exist for today
        $this->assertEquals(2, BasoDocument::count());
    }
}
