<?php

namespace Tests\Feature\Governance;

use Tests\TestCase;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\Location;
use App\Models\Bin;
use App\Models\Item;
use App\Models\ItemVariant;
use App\Models\StockOpname;
use App\Models\StockOpnameItem;
use App\Models\InventoryAdjustment;
use App\Models\InventoryAdjustmentItem;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use App\Livewire\Governance\AuditCoveragePage;
use Carbon\Carbon;

class AuditCoverageTest extends TestCase
{
    use DatabaseTransactions;

    protected User $operator;
    protected Warehouse $warehouse;
    protected Location $location;
    protected Bin $binAudited;
    protected Bin $binNeedsAudit;
    protected Bin $binStale;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Create operator user
        $this->operator = User::create([
            'role' => 'operator',
            'name' => 'Operator User ' . uniqid(),
            'email' => 'operator_' . uniqid() . '@peroniks.com',
            'password' => bcrypt('password'),
        ]);

        // 2. Create Warehouse
        $this->warehouse = Warehouse::create([
            'code' => 'SPAREPART_TEST_' . uniqid(),
            'name' => 'Sparepart Test Warehouse',
            'status' => 'ACTIVE',
        ]);

        // Grant access
        $this->operator->warehouses()->attach($this->warehouse->id, [
            'can_stock_in' => true,
            'can_stock_out' => true,
            'can_opname' => true,
            'can_adjust' => true,
            'can_print' => true,
            'can_view_reports' => true,
        ]);

        // 3. Setup Family Assignment
        \App\Models\WarehouseFamilyAssignment::create([
            'warehouse_id' => $this->warehouse->id,
            'family_code' => '5',
        ]);

        // 4. Create location/rack
        $this->location = Location::create([
            'code' => 'RACK-A-' . uniqid(),
            'description' => 'Test Location Rack A',
        ]);

        // 5. Create items and variants
        $item1 = Item::create(['name' => 'Bearing A', 'category_id' => 1]);
        $var1 = ItemVariant::create([
            'item_id' => $item1->id,
            'sku' => 'SKU-01-' . uniqid(),
            'erp_code' => '5.01.' . uniqid(),
            'last_opname_at' => now(),
        ]);

        $item2 = Item::create(['name' => 'Bearing B', 'category_id' => 1]);
        $var2 = ItemVariant::create([
            'item_id' => $item2->id,
            'sku' => 'SKU-02-' . uniqid(),
            'erp_code' => '5.02.' . uniqid(),
            'last_opname_at' => null,
        ]);

        $item3 = Item::create(['name' => 'Bearing C', 'category_id' => 1]);
        $var3 = ItemVariant::create([
            'item_id' => $item3->id,
            'sku' => 'SKU-03-' . uniqid(),
            'erp_code' => '5.03.' . uniqid(),
            'last_opname_at' => now()->subDays(60),
        ]);

        // 5. Create bins
        $this->binAudited = Bin::create([
            'location_id' => $this->location->id,
            'item_variant_id' => $var1->id,
            'code' => 'A-1',
            'current_qty' => 10,
            'warehouse_id' => $this->warehouse->id,
        ]);

        $this->binNeedsAudit = Bin::create([
            'location_id' => $this->location->id,
            'item_variant_id' => $var2->id,
            'code' => 'A-2',
            'current_qty' => 5,
            'warehouse_id' => $this->warehouse->id,
        ]);

        $this->binStale = Bin::create([
            'location_id' => $this->location->id,
            'item_variant_id' => $var3->id,
            'code' => 'A-3',
            'current_qty' => 8,
            'warehouse_id' => $this->warehouse->id,
        ]);

        // Set session active warehouse context
        session(['active_warehouse_id' => $this->warehouse->id]);
    }

    /** @test */
    public function test_guests_cannot_access_audit_coverage_page()
    {
        $this->get(route('governance.audit-coverage'))
            ->assertRedirect(route('login'));
    }

    /** @test */
    public function test_authenticated_users_can_load_audit_coverage_page()
    {
        $this->actingAs($this->operator)
            ->get(route('governance.audit-coverage'))
            ->assertStatus(200)
            ->assertSee('Audit Coverage');
    }

    /** @test */
    public function test_audit_coverage_page_queries_and_renders_correctly()
    {
        // 1. Create a physical opname for binAudited (Green status: 5 days ago)
        $opname1 = StockOpname::create([
            'code' => 'OPN-TEST-1',
            'scope_type' => 'LOCATION',
            'scope_id' => $this->location->id,
            'status' => 'COMPLETED',
            'created_by' => (string)$this->operator->id,
        ]);
        \Illuminate\Support\Facades\DB::table('stock_opnames')->where('id', $opname1->id)->update(['created_at' => now()->subDays(5)]);

        $opnameItem1 = StockOpnameItem::create([
            'stock_opname_id' => $opname1->id,
            'bin_id' => $this->binAudited->id,
            'system_qty' => 10,
            'actual_qty' => 10,
            'difference' => 0,
        ]);
        \Illuminate\Support\Facades\DB::table('stock_opname_items')->where('id', $opnameItem1->id)->update(['created_at' => now()->subDays(5)]);

        // 2. Create a physical opname for binStale (Yellow status: 60 days ago)
        $opname2 = StockOpname::create([
            'code' => 'OPN-TEST-2',
            'scope_type' => 'LOCATION',
            'scope_id' => $this->location->id,
            'status' => 'COMPLETED',
            'created_by' => (string)$this->operator->id,
        ]);
        \Illuminate\Support\Facades\DB::table('stock_opnames')->where('id', $opname2->id)->update(['created_at' => now()->subDays(60)]);

        $opnameItem2 = StockOpnameItem::create([
            'stock_opname_id' => $opname2->id,
            'bin_id' => $this->binStale->id,
            'system_qty' => 8,
            'actual_qty' => 8,
            'difference' => 0,
        ]);
        \Illuminate\Support\Facades\DB::table('stock_opname_items')->where('id', $opnameItem2->id)->update(['created_at' => now()->subDays(60)]);

        // 3. Create an inventory adjustment for binNeedsAudit (to ensure it is NOT treated as a physical audit)
        $adjustment = InventoryAdjustment::create([
            'adjustment_no' => 'IA-TEST-1',
            'warehouse_id' => $this->warehouse->id,
            'operator_id' => $this->operator->id,
            'date' => now()->format('Y-m-d'),
            'status' => 'COMPLETED',
        ]);

        InventoryAdjustmentItem::create([
            'inventory_adjustment_id' => $adjustment->id,
            'bin_id' => $this->binNeedsAudit->id,
            'item_variant_id' => $this->binNeedsAudit->item_variant_id,
            'system_qty' => 5,
            'physical_qty' => 7,
            'variance' => 2,
            'reason_code' => 'FOUND_ITEM',
            'status' => 'APPROVED',
            'created_at' => now(),
            'item_name_snapshot' => $this->binNeedsAudit->itemVariant->item->name ?? 'Bearing B',
            'erp_code_snapshot' => $this->binNeedsAudit->itemVariant->erp_code ?? 'ERP-02',
            'bin_code_snapshot' => $this->binNeedsAudit->code,
            'unit_snapshot' => 'PCS',
            'warehouse_name_snapshot' => $this->warehouse->name,
            'operator_name_snapshot' => $this->operator->name,
        ]);

        // Start Livewire testing
        Livewire::actingAs($this->operator)
            ->test(AuditCoveragePage::class)
            ->assertSet('warehouseId', $this->warehouse->id)
            // Welcome state is shown
            ->assertSee('Pilih Lokasi untuk Mulai Audit')
            
            // Search rack and open dropdown
            ->set('rackSearch', substr($this->location->code, 0, 8))
            ->assertSet('selectedRackId', null)
            
            // Select Rack
            ->call('selectRack', $this->location->id, $this->location->code)
            ->assertSet('selectedRackId', $this->location->id)
            ->assertSet('rackSearch', $this->location->code)
            
            // Verify items are loaded and summary calculations are correct
            // Total Items: 3 (binAudited, binNeedsAudit, binStale)
            // Audited: 1 (binAudited audited 5 days ago)
            // Needs Audit: 2 (binNeedsAudit has no opname record, binStale audited 60 days ago which is >30 days threshold)
            // Coverage: round((1/3)*100) = 33%
            ->assertSeeHtml('<span class="text-2xl font-mono font-black text-slate-850 block">3</span>')
            ->assertSeeHtml('<span class="text-2xl font-mono font-black text-emerald-650 block">1</span>')
            ->assertSeeHtml('<span class="text-2xl font-mono font-black text-rose-650 block">2</span>')
            ->assertSeeHtml('<span class="text-2xl font-mono font-black text-slate-800">33%</span>')
            
            // Verify items table lists the items and their audit states
            ->assertSee($this->binAudited->itemVariant->erp_code)
            ->assertSee($this->binNeedsAudit->itemVariant->erp_code)
            ->assertSee($this->binStale->itemVariant->erp_code)
            
            // Default sorting should put binNeedsAudit first (Never audited), then binStale (60 days ago), then binAudited (5 days ago)
            // Let's assert that quick filters work
            ->set('quickFilter', 'audited')
            ->assertSee($this->binAudited->itemVariant->erp_code)
            ->assertDontSee($this->binNeedsAudit->itemVariant->erp_code)
            ->assertDontSee($this->binStale->itemVariant->erp_code)
            
            ->set('quickFilter', 'needs_audit')
            ->assertDontSee($this->binAudited->itemVariant->erp_code)
            ->assertSee($this->binNeedsAudit->itemVariant->erp_code)
            ->assertDontSee($this->binStale->itemVariant->erp_code)

            ->set('quickFilter', 'stale')
            ->assertDontSee($this->binAudited->itemVariant->erp_code)
            ->assertDontSee($this->binNeedsAudit->itemVariant->erp_code)
            ->assertSee($this->binStale->itemVariant->erp_code);
    }
}
