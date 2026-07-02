<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\UserWarehouseAccess;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;

class ProductionWarehouseAccessSeederTest extends TestCase
{
    use DatabaseTransactions;

    protected User $adminSp;
    protected User $adminBahanBaku;
    protected User $managerPpic;

    protected Warehouse $spWh;
    protected Warehouse $rmWh;
    protected Warehouse $csWh;
    protected Warehouse $fgWh;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Ensure Warehouses exist
        $this->spWh = Warehouse::firstOrCreate(['code' => 'SPAREPART'], ['name' => 'Spareparts Warehouse', 'status' => 'ACTIVE']);
        $this->rmWh = Warehouse::firstOrCreate(['code' => 'RAW_MATERIAL'], ['name' => 'Raw Materials Warehouse', 'status' => 'ACTIVE']);
        $this->csWh = Warehouse::firstOrCreate(['code' => 'CONSUMABLE'], ['name' => 'Consumables Warehouse', 'status' => 'ACTIVE']);
        $this->fgWh = Warehouse::firstOrCreate(['code' => 'FINISHED_GOODS'], ['name' => 'Finished Goods Warehouse', 'status' => 'ACTIVE']);

        // 2. Create Users
        $this->adminSp = User::firstOrCreate(
            ['email' => 'adminsp@peroniks.com'],
            [
                'name' => 'Admin Sparepart',
                'password' => Hash::make('password'),
                'role' => 'admin',
            ]
        );

        $this->adminBahanBaku = User::firstOrCreate(
            ['email' => 'adminbahanbaku@peroniks.com'],
            [
                'name' => 'SPV BAHAN BAKU',
                'password' => Hash::make('password'),
                'role' => 'admin',
            ]
        );

        $this->managerPpic = User::firstOrCreate(
            ['email' => 'managerppic@peroniks.com'],
            [
                'name' => 'Manager PPIC',
                'password' => Hash::make('password'),
                'role' => 'manager',
            ]
        );

        // Pre-populate some dirty/old warehouse mappings to test cleanup
        UserWarehouseAccess::whereIn('user_id', [
            $this->adminSp->id,
            $this->adminBahanBaku->id,
            $this->managerPpic->id
        ])->delete();

        // Admin SP gets mapped to Consumables too (simulating dirty state)
        UserWarehouseAccess::create(['user_id' => $this->adminSp->id, 'warehouse_id' => $this->csWh->id]);
        UserWarehouseAccess::create(['user_id' => $this->adminSp->id, 'warehouse_id' => $this->spWh->id]);

        // Bahan baku gets mapped to Spareparts (dirty state)
        UserWarehouseAccess::create(['user_id' => $this->adminBahanBaku->id, 'warehouse_id' => $this->spWh->id]);
    }

    /**
     * Test warehouse user mapping configuration.
     */
    public function test_production_mappings_are_correctly_synchronized(): void
    {
        // 1. Assert adminpb does not exist before seeder
        User::where('email', 'adminpb@peroniks.com')->delete();
        $this->assertNull(User::where('email', 'adminpb@peroniks.com')->first());

        // 2. Execute Seeder
        $this->seed(\Database\Seeders\ProductionWarehouseAccessSeeder::class);

        // 3. Verify adminpb has been provisioned
        $adminPb = User::where('email', 'adminpb@peroniks.com')->first();
        $this->assertNotNull($adminPb);
        $this->assertEquals('SPV GUDANG PEMBANTU', $adminPb->name);
        $this->assertEquals('admin', $adminPb->role);

        // 4. Verify adminsp mappings (Exactly SPAREPART only)
        $adminSpAccesses = UserWarehouseAccess::where('user_id', $this->adminSp->id)->get();
        $this->assertCount(1, $adminSpAccesses);
        $this->assertEquals($this->spWh->id, $adminSpAccesses->first()->warehouse_id);

        // 5. Verify adminbahanbaku mappings (Exactly RAW_MATERIAL only)
        $adminBbAccesses = UserWarehouseAccess::where('user_id', $this->adminBahanBaku->id)->get();
        $this->assertCount(1, $adminBbAccesses);
        $this->assertEquals($this->rmWh->id, $adminBbAccesses->first()->warehouse_id);

        // 6. Verify adminpb mappings (Exactly CONSUMABLE only)
        $adminPbAccesses = UserWarehouseAccess::where('user_id', $adminPb->id)->get();
        $this->assertCount(1, $adminPbAccesses);
        $this->assertEquals($this->csWh->id, $adminPbAccesses->first()->warehouse_id);

        // 7. Verify managerppic mappings (Exactly 4 mappings)
        $managerAccesses = UserWarehouseAccess::where('user_id', $this->managerPpic->id)->get();
        $this->assertCount(4, $managerAccesses);
        $managerWhIds = $managerAccesses->pluck('warehouse_id')->toArray();
        $this->assertContains($this->spWh->id, $managerWhIds);
        $this->assertContains($this->rmWh->id, $managerWhIds);
        $this->assertContains($this->csWh->id, $managerWhIds);
        $this->assertContains($this->fgWh->id, $managerWhIds);
    }

    /**
     * Test seeder is fully idempotent.
     */
    public function test_production_seeder_is_idempotent(): void
    {
        // Execute first time
        $this->seed(\Database\Seeders\ProductionWarehouseAccessSeeder::class);

        $initialCount = UserWarehouseAccess::count();
        $initialMappings = UserWarehouseAccess::orderBy('user_id')->orderBy('warehouse_id')
            ->get(['user_id', 'warehouse_id'])->toArray();

        // Execute second time
        $this->seed(\Database\Seeders\ProductionWarehouseAccessSeeder::class);

        $secondCount = UserWarehouseAccess::count();
        $secondMappings = UserWarehouseAccess::orderBy('user_id')->orderBy('warehouse_id')
            ->get(['user_id', 'warehouse_id'])->toArray();

        $this->assertEquals($initialCount, $secondCount);
        $this->assertEquals($initialMappings, $secondMappings);
    }
}
