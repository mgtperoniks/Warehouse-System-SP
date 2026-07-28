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
    protected Bin $binOverdue;

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

        // 5. Create items and variants with prefix '5.' to bypass global warehouse scope
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
            'last_opname_at' => now()->subDays(80),
        ]);

        $item4 = Item::create(['name' => 'Bearing D', 'category_id' => 1]);
        $var4 = ItemVariant::create([
            'item_id' => $item4->id,
            'sku' => 'SKU-04-' . uniqid(),
            'erp_code' => '5.04.' . uniqid(),
            'last_opname_at' => now()->subDays(135),
        ]);

        // 6. Create bins
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
            'code' => 'A-1', // Same bin code
            'current_qty' => 8,
            'warehouse_id' => $this->warehouse->id,
        ]);

        $this->binOverdue = Bin::create([
            'location_id' => $this->location->id,
            'item_variant_id' => $var4->id,
            'code' => 'A-1', // Same bin code
            'current_qty' => 12,
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

        // 2. Create a physical opname for binStale (Yellow status: 80 days ago)
        $opname2 = StockOpname::create([
            'code' => 'OPN-TEST-2',
            'scope_type' => 'LOCATION',
            'scope_id' => $this->location->id,
            'status' => 'COMPLETED',
            'created_by' => (string)$this->operator->id,
        ]);
        \Illuminate\Support\Facades\DB::table('stock_opnames')->where('id', $opname2->id)->update(['created_at' => now()->subDays(80)]);

        $opnameItem2 = StockOpnameItem::create([
            'stock_opname_id' => $opname2->id,
            'bin_id' => $this->binStale->id,
            'system_qty' => 8,
            'actual_qty' => 8,
            'difference' => 0,
        ]);
        \Illuminate\Support\Facades\DB::table('stock_opname_items')->where('id', $opnameItem2->id)->update(['created_at' => now()->subDays(80)]);

        // 3. Create a physical opname for binOverdue (Red status, Overdue: 135 days ago)
        $opname3 = StockOpname::create([
            'code' => 'OPN-TEST-3',
            'scope_type' => 'LOCATION',
            'scope_id' => $this->location->id,
            'status' => 'COMPLETED',
            'created_by' => (string)$this->operator->id,
        ]);
        \Illuminate\Support\Facades\DB::table('stock_opnames')->where('id', $opname3->id)->update(['created_at' => now()->subDays(135)]);

        $opnameItem3 = StockOpnameItem::create([
            'stock_opname_id' => $opname3->id,
            'bin_id' => $this->binOverdue->id,
            'system_qty' => 12,
            'actual_qty' => 12,
            'difference' => 0,
        ]);
        \Illuminate\Support\Facades\DB::table('stock_opname_items')->where('id', $opnameItem3->id)->update(['created_at' => now()->subDays(135)]);

        // Compute expected dates for assertions
        $expectedDueAudited = now()->subDays(5)->addDays(120)->format('d M Y');
        $expectedDueStale = now()->subDays(80)->addDays(120)->format('d M Y');

        // Start Livewire testing
        Livewire::actingAs($this->operator)
            ->test(AuditCoveragePage::class)
            ->assertSet('warehouseId', $this->warehouse->id)
            ->assertSet('hasGenerated', false)
            ->assertSee('Pilih Lokasi untuk Memulai Audit')
            ->assertDontSee('Currently Viewing Bin Location')
            ->assertDontSee('Total:')
            
            // Search bin and select it
            ->set('binSearch', 'A')
            ->call('selectBinCode', 'A-1')
            
            // Click Generate Coverage
            ->call('generateCoverage')
            ->assertSet('activeBinCode', 'A-1')
            ->assertSet('hasGenerated', true)
            
            // Verify items are loaded and summary calculations are correct
            // For A-1, there are 3 items:
            // 1. binAudited (Green status, 5 days ago)
            // 2. binStale (Yellow status, 80 days ago)
            // 3. binOverdue (Red status, 135 days ago)
            // Summary: Total=3, Audited=1, Aging=1, Needs Audit=1
            // Coverage: round((1/3)*100) = 33%
            ->assertSee('Currently Viewing Bin Location:')
            ->assertSee('A-1')
            ->assertSeeHtml('<span class="text-sm font-mono font-black text-slate-850">3</span>')
            ->assertSeeHtml('<span class="text-sm font-mono font-black text-emerald-650">1</span>')
            ->assertSeeHtml('<span class="text-sm font-mono font-black text-amber-605">1</span>')
            ->assertSeeHtml('<span class="text-sm font-mono font-black text-rose-655">1</span>')
            ->assertSeeHtml('<span class="text-sm font-mono font-black text-slate-800">33%</span>')
            
            // Verify table lists A-1 variants
            ->assertSee($this->binAudited->itemVariant->erp_code)
            ->assertSee($this->binStale->itemVariant->erp_code)
            ->assertSee($this->binOverdue->itemVariant->erp_code)
            ->assertDontSee($this->binNeedsAudit->itemVariant->erp_code)
            
            // Verify next due and overdue displays
            ->assertSee('Next Due ' . $expectedDueAudited)
            ->assertSee('Next Due ' . $expectedDueStale)
            ->assertSee('Overdue 15 days')
            
            // Change input / selection to A-2 (without clicking Generate yet)
            ->call('selectBinCode', 'A-2')
            ->assertSet('selectedBinCode', 'A-2')
            // The active displayed coverage must STILL be A-1!
            ->assertSet('activeBinCode', 'A-1')
            ->assertSee('Currently Viewing Bin Location:')
            ->assertSee('A-1')
            
            // Click Generate Coverage to switch to A-2
            ->call('generateCoverage')
            ->assertSet('activeBinCode', 'A-2')
            // For A-2: 1 item: binNeedsAudit (Red status, never audited)
            // Total: 1, Audited: 0, Aging: 0, Needs Audit: 1, Coverage: 0%
            ->assertSee('Currently Viewing Bin Location:')
            ->assertSee('A-2')
            ->assertSeeHtml('<span class="text-sm font-mono font-black text-slate-850">1</span>')
            ->assertSeeHtml('<span class="text-sm font-mono font-black text-emerald-650">0</span>')
            ->assertSeeHtml('<span class="text-sm font-mono font-black text-amber-605">0</span>')
            ->assertSeeHtml('<span class="text-sm font-mono font-black text-rose-655">1</span>')
            ->assertSeeHtml('<span class="text-sm font-mono font-black text-slate-800">0%</span>')
            ->assertSee($this->binNeedsAudit->itemVariant->erp_code)
            ->assertDontSee($this->binAudited->itemVariant->erp_code)
            ->assertDontSee($this->binStale->itemVariant->erp_code)
            ->assertDontSee($this->binOverdue->itemVariant->erp_code);
    }

    /** @test */
    public function test_audit_coverage_pdf_report_generates_and_streams_correctly()
    {
        $this->actingAs($this->operator)
            ->get(route('governance.audit-coverage.pdf', [
                'warehouse_id' => $this->warehouse->id,
                'bin_code' => 'A-1',
                'filter' => 'all'
            ]))
            ->assertStatus(200)
            ->assertHeader('Content-Type', 'application/pdf');
    }
}
