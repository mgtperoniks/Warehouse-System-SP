<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Item;
use App\Models\ItemVariant;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use App\Livewire\Items\InventoryPlanningPage;
use Illuminate\Support\Facades\DB;

class InventoryPlanningInteractiveDashboardTest extends TestCase
{
    use DatabaseTransactions;

    protected User $adminUser;
    protected Warehouse $warehouse;
    protected ItemVariant $criticalItem;
    protected ItemVariant $healthyItem;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminUser = User::create([
            'role' => 'admin',
            'name' => 'Admin Test User',
            'email' => 'admin_dashboard_' . uniqid() . '@peroniks.com',
            'password' => bcrypt('password'),
        ]);

        $this->warehouse = Warehouse::firstOrCreate(
            ['code' => 'SPAREPART'],
            ['name' => 'Sparepart Warehouse', 'status' => 'ACTIVE']
        );
        session([
            'active_warehouse_id' => $this->warehouse->id,
            'active_warehouse_name' => $this->warehouse->name,
            'active_warehouse_code' => $this->warehouse->code,
        ]);

        // Create family mappings
        foreach (['ERP-CRIT', 'ERP-HLTH'] as $family) {
            \App\Models\WarehouseFamilyAssignment::firstOrCreate([
                'warehouse_id' => $this->warehouse->id,
                'family_code' => $family
            ]);
        }

        // 1. Critical Item
        $itemA = Item::create(['name' => 'DashboardTestUnique-CriticalItem']);
        $this->criticalItem = ItemVariant::create([
            'item_id' => $itemA->id,
            'sku' => 'SKU-CRIT',
            'erp_code' => 'ERP-CRIT.001',
            'unit' => 'PCS',
            'procurement_type' => 'LOCAL',
            'inventory_class' => 'SPAREPART',
            'lead_time_days' => 10,
        ]);

        // Stock = 5, OUT qty = 28 in last 28 days -> Weekly avg = 7, Daily avg = 1. Days left = 5.
        // Days Left (5) <= Lead Time (10) -> CRITICAL
        DB::table('bins')->insert([
            'item_variant_id' => $this->criticalItem->id,
            'code' => 'BIN-CRIT',
            'warehouse_id' => $this->warehouse->id,
            'location_id' => 1,
            'current_qty' => 5,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('stock_movements')->insert([
            'item_variant_id' => $this->criticalItem->id,
            'type' => 'OUT',
            'qty' => 28,
            'reference' => 'Usage Crit',
            'created_at' => now()->subDays(5),
            'updated_at' => now()->subDays(5),
            'warehouse_id' => $this->warehouse->id,
            'operator_id' => $this->adminUser->id,
            'terminal_id' => 'TEST',
            'terminal_session_id' => 'TEST',
        ]);

        // 2. Healthy Item
        $itemB = Item::create(['name' => 'DashboardTestUnique-HealthyItem']);
        $this->healthyItem = ItemVariant::create([
            'item_id' => $itemB->id,
            'sku' => 'SKU-HLTH',
            'erp_code' => 'ERP-HLTH.001',
            'unit' => 'PCS',
            'procurement_type' => 'IMPORT',
            'inventory_class' => 'CONSUMABLE',
            'lead_time_days' => 10,
        ]);

        // Stock = 100, OUT qty = 28 in last 28 days -> Weekly avg = 7, Daily avg = 1. Days left = 100.
        // Days Left (100) > Lead Time * 2 (20) -> HEALTHY
        DB::table('bins')->insert([
            'item_variant_id' => $this->healthyItem->id,
            'code' => 'BIN-HLTH',
            'warehouse_id' => $this->warehouse->id,
            'location_id' => 1,
            'current_qty' => 100,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('stock_movements')->insert([
            'item_variant_id' => $this->healthyItem->id,
            'type' => 'OUT',
            'qty' => 28,
            'reference' => 'Usage Hlth',
            'created_at' => now()->subDays(5),
            'updated_at' => now()->subDays(5),
            'warehouse_id' => $this->warehouse->id,
            'operator_id' => $this->adminUser->id,
            'terminal_id' => 'TEST',
            'terminal_session_id' => 'TEST',
        ]);
    }

    public function test_dashboard_counts_reflect_correct_states()
    {
        $this->actingAs($this->adminUser);

        Livewire::test(InventoryPlanningPage::class)
            ->set('search', 'DashboardTestUnique-')
            ->assertViewHas('dashboardCounts', function ($counts) {
                return $counts['TOTAL'] == 2 &&
                       $counts['CRITICAL'] == 1 &&
                       $counts['HEALTHY'] == 1;
            });
    }

    public function test_dashboard_filters_interactivity_and_dropdown_synchronization()
    {
        $this->actingAs($this->adminUser);

        Livewire::test(InventoryPlanningPage::class)
            ->set('search', 'DashboardTestUnique-')
            // 1. Test clicking Critical badge set statusFilter and filters list
            ->call('toggleStatusFilter', 'CRITICAL')
            ->assertSet('statusFilter', 'CRITICAL')
            ->assertViewHas('variants', function ($variants) {
                $ids = collect($variants->items())->pluck('id')->all();
                return in_array($this->criticalItem->id, $ids) && !in_array($this->healthyItem->id, $ids);
            })
            // 2. Test clicking Critical again clears filter (toggle off)
            ->call('toggleStatusFilter', 'CRITICAL')
            ->assertSet('statusFilter', '')
            ->assertViewHas('variants', function ($variants) {
                $ids = collect($variants->items())->pluck('id')->all();
                return in_array($this->criticalItem->id, $ids) && in_array($this->healthyItem->id, $ids);
            })
            // 3. Test changing dropdown (sets statusFilter directly)
            ->set('statusFilter', 'HEALTHY')
            ->assertViewHas('variants', function ($variants) {
                $ids = collect($variants->items())->pluck('id')->all();
                return !in_array($this->criticalItem->id, $ids) && in_array($this->healthyItem->id, $ids);
            });
    }

    public function test_dashboard_counts_are_context_aware()
    {
        $this->actingAs($this->adminUser);

        // Test search filter recalcs counts within our test item context
        Livewire::test(InventoryPlanningPage::class)
            ->set('search', 'DashboardTestUnique-CriticalItem')
            ->assertViewHas('dashboardCounts', function ($counts) {
                // Only Critical item matches
                return $counts['TOTAL'] == 1 &&
                       $counts['CRITICAL'] == 1 &&
                       $counts['HEALTHY'] == 0;
            });

        Livewire::test(InventoryPlanningPage::class)
            ->set('search', 'DashboardTestUnique-')
            // Test class filter recalcs counts
            ->set('classFilter', 'CONSUMABLE')
            ->assertViewHas('dashboardCounts', function ($counts) {
                // Only Healthy item is CONSUMABLE
                return $counts['TOTAL'] == 1 &&
                       $counts['CRITICAL'] == 0 &&
                       $counts['HEALTHY'] == 1;
            })
            ->set('classFilter', '')
            // Test procurement filter recalcs counts
            ->set('procurementFilter', 'LOCAL')
            ->assertViewHas('dashboardCounts', function ($counts) {
                // Only Critical item is LOCAL
                return $counts['TOTAL'] == 1 &&
                       $counts['CRITICAL'] == 1 &&
                       $counts['HEALTHY'] == 0;
            });
    }
}
