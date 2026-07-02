<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\InventoryAdjustment;
use App\Models\BasoDocument;
use App\Models\StockInReceipt;
use App\Models\Bin;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class MultiWarehouseSecurityTest extends TestCase
{
    use DatabaseTransactions;

    protected Warehouse $warehouseA;
    protected Warehouse $warehouseB;
    protected Warehouse $warehouseC;

    protected function setUp(): void
    {
        parent::setUp();

        // Create 3 active warehouses
        $this->warehouseA = Warehouse::create([
            'code' => 'TEST_WH_A',
            'name' => 'Test Warehouse A',
            'status' => 'ACTIVE',
        ]);

        $this->warehouseB = Warehouse::create([
            'code' => 'TEST_WH_B',
            'name' => 'Test Warehouse B',
            'status' => 'ACTIVE',
        ]);

        $this->warehouseC = Warehouse::create([
            'code' => 'TEST_WH_C',
            'name' => 'Test Warehouse C',
            'status' => 'ACTIVE',
        ]);
    }

    public function test_user_with_multiple_mapped_warehouses_can_switch()
    {
        $user = User::create([
            'name' => 'Multi WH User',
            'email' => 'multi_' . uniqid() . '@example.com',
            'password' => bcrypt('password'),
            'role' => 'operator',
        ]);

        // Map user to Warehouse A and B
        $user->warehouses()->attach([$this->warehouseA->id, $this->warehouseB->id]);

        $this->actingAs($user);

        // Switch to Warehouse B
        $response = $this->post(route('warehouse.switch', $this->warehouseB->id));
        $response->assertRedirect();
        $this->assertEquals($this->warehouseB->id, session('active_warehouse_id'));
    }

    public function test_user_with_one_warehouse_can_re_select_the_same_warehouse_successfully()
    {
        $user = User::create([
            'name' => 'Single WH User',
            'email' => 'single_' . uniqid() . '@example.com',
            'password' => bcrypt('password'),
            'role' => 'operator',
        ]);

        // Map user only to Warehouse A
        $user->warehouses()->attach([$this->warehouseA->id]);

        $this->actingAs($user);
        session(['active_warehouse_id' => $this->warehouseA->id]);

        // Try switching to Warehouse A (same) -> allow (no-op, success redirect)
        $response = $this->post(route('warehouse.switch', $this->warehouseA->id));
        $response->assertRedirect();
        $this->assertEquals($this->warehouseA->id, session('active_warehouse_id'));
    }

    public function test_user_with_one_warehouse_cannot_switch_to_another_warehouse()
    {
        $user = User::create([
            'name' => 'Single WH User',
            'email' => 'single_' . uniqid() . '@example.com',
            'password' => bcrypt('password'),
            'role' => 'operator',
        ]);

        // Map user only to Warehouse A
        $user->warehouses()->attach([$this->warehouseA->id]);

        $this->actingAs($user);
        session(['active_warehouse_id' => $this->warehouseA->id]);

        // Try switching to Warehouse B -> 403 Forbidden
        $response = $this->post(route('warehouse.switch', $this->warehouseB->id));
        $response->assertStatus(403);
        $this->assertEquals($this->warehouseA->id, session('active_warehouse_id'));
    }

    public function test_unmapped_warehouse_returns_http_403()
    {
        $user = User::create([
            'name' => 'Multi WH User',
            'email' => 'multi_' . uniqid() . '@example.com',
            'password' => bcrypt('password'),
            'role' => 'operator',
        ]);

        // Map user to Warehouse A and B (but not C)
        $user->warehouses()->attach([$this->warehouseA->id, $this->warehouseB->id]);

        $this->actingAs($user);
        session(['active_warehouse_id' => $this->warehouseA->id]);

        // Switch to Warehouse C (unmapped) -> 403
        $response = $this->post(route('warehouse.switch', $this->warehouseC->id));
        $response->assertStatus(403);
        $this->assertEquals($this->warehouseA->id, session('active_warehouse_id'));
    }

    public function test_invalid_warehouse_id_returns_http_403()
    {
        $user = User::create([
            'name' => 'Multi WH User',
            'email' => 'multi_' . uniqid() . '@example.com',
            'password' => bcrypt('password'),
            'role' => 'operator',
        ]);

        $user->warehouses()->attach([$this->warehouseA->id, $this->warehouseB->id]);

        $this->actingAs($user);

        // Switch to non-existent warehouse ID 999999 -> 403
        $response = $this->post(route('warehouse.switch', 999999));
        $response->assertStatus(403);
    }

    public function test_invalid_warehouse_session_automatically_recovers()
    {
        $user = User::create([
            'name' => 'Test User',
            'email' => 'test_' . uniqid() . '@example.com',
            'password' => bcrypt('password'),
            'role' => 'operator',
        ]);

        $user->warehouses()->attach([$this->warehouseB->id]);

        $this->actingAs($user);

        // Set session to invalid warehouse A
        session(['active_warehouse_id' => $this->warehouseA->id]);

        // Access dashboard -> Middleware should validate and recover to B
        $response = $this->get(route('dashboard'));
        $response->assertStatus(200);

        $this->assertEquals($this->warehouseB->id, session('active_warehouse_id'));
        $this->assertEquals($this->warehouseB->code, session('active_warehouse_code'));
        $this->assertEquals($this->warehouseB->name, session('active_warehouse_name'));
    }

    public function test_revoked_warehouse_mapping_recovers_correctly()
    {
        $user = User::create([
            'name' => 'Test User',
            'email' => 'test_' . uniqid() . '@example.com',
            'password' => bcrypt('password'),
            'role' => 'operator',
        ]);

        // Map to both A and B, set session to A
        $user->warehouses()->attach([$this->warehouseA->id, $this->warehouseB->id]);
        $this->actingAs($user);
        session(['active_warehouse_id' => $this->warehouseA->id]);

        // Revoke A
        $user->warehouses()->detach($this->warehouseA->id);

        // Access dashboard -> Middleware should recover to B (first mapped warehouse now)
        $response = $this->get(route('dashboard'));
        $response->assertStatus(200);

        $this->assertEquals($this->warehouseB->id, session('active_warehouse_id'));
    }

    public function test_zero_warehouse_mappings_returns_http_403()
    {
        $user = User::create([
            'name' => 'Test User',
            'email' => 'test_' . uniqid() . '@example.com',
            'password' => bcrypt('password'),
            'role' => 'operator',
        ]);

        // Zero warehouse mappings
        $this->actingAs($user);

        $response = $this->get(route('dashboard'));
        $response->assertStatus(403);
    }

    public function test_authorized_warehouse_user_can_view_baso()
    {
        $user = User::create([
            'name' => 'Mapped User',
            'email' => 'mapped_' . uniqid() . '@example.com',
            'password' => bcrypt('password'),
            'role' => 'operator',
        ]);

        $user->warehouses()->attach([$this->warehouseA->id]);

        $this->actingAs($user);

        // Create adjustment for Warehouse A
        $adjustment = InventoryAdjustment::create([
            'adjustment_no' => 'IA-' . uniqid(),
            'warehouse_id' => $this->warehouseA->id,
            'operator_id' => $user->id,
            'date' => date('Y-m-d'),
            'status' => 'COMPLETED',
        ]);

        $baso = BasoDocument::create([
            'inventory_adjustment_id' => $adjustment->id,
            'baso_number' => 'BASO-' . uniqid(),
            'generated_by' => $user->id,
            'generated_at' => now(),
            'pdf_path' => 'baso_' . uniqid() . '.pdf',
        ]);

        // Mock Storage
        \Illuminate\Support\Facades\Storage::fake('public');
        \Illuminate\Support\Facades\Storage::disk('public')->put($baso->pdf_path, 'mock pdf content');

        // View BASO -> should allow (200)
        $response = $this->get(route('governance.baso.view', $baso->id));
        $response->assertStatus(200);
    }

    public function test_unauthorized_warehouse_user_receives_http_403()
    {
        $user = User::create([
            'name' => 'Unmapped User',
            'email' => 'unmapped_' . uniqid() . '@example.com',
            'password' => bcrypt('password'),
            'role' => 'operator',
        ]);

        // Map only to B
        $user->warehouses()->attach([$this->warehouseB->id]);

        $this->actingAs($user);

        // Create adjustment for Warehouse A
        $adjustment = InventoryAdjustment::create([
            'adjustment_no' => 'IA-' . uniqid(),
            'warehouse_id' => $this->warehouseA->id,
            'operator_id' => $user->id,
            'date' => date('Y-m-d'),
            'status' => 'COMPLETED',
        ]);

        $baso = BasoDocument::create([
            'inventory_adjustment_id' => $adjustment->id,
            'baso_number' => 'BASO-' . uniqid(),
            'generated_by' => $user->id,
            'generated_at' => now(),
            'pdf_path' => 'baso_' . uniqid() . '.pdf',
        ]);

        // View BASO for warehouse A -> 403 Forbidden
        $response = $this->get(route('governance.baso.view', $baso->id));
        $response->assertStatus(403);
    }

    public function test_authorized_request_for_missing_baso_returns_http_404()
    {
        $user = User::create([
            'name' => 'Mapped User',
            'email' => 'mapped_' . uniqid() . '@example.com',
            'password' => bcrypt('password'),
            'role' => 'operator',
        ]);

        $user->warehouses()->attach([$this->warehouseA->id]);

        $this->actingAs($user);

        // Access non-existent BASO ID 999999 -> 404
        $response = $this->get(route('governance.baso.view', 999999));
        $response->assertStatus(404);
    }

    public function test_active_warehouse_context_scopes_queries()
    {
        $user = User::create([
            'name' => 'Test User',
            'email' => 'test_' . uniqid() . '@example.com',
            'password' => bcrypt('password'),
            'role' => 'operator',
        ]);

        $user->warehouses()->attach([$this->warehouseA->id, $this->warehouseB->id]);
        $this->actingAs($user);

        // Switch to Warehouse A
        session(['active_warehouse_id' => $this->warehouseA->id]);

        // 1. Inventory Adjustment query uses active warehouse
        $adjQuery = InventoryAdjustment::forActiveWarehouse();
        $this->assertStringContainsString('`warehouse_id` = ?', $adjQuery->toSql());
        $this->assertEquals($this->warehouseA->id, $adjQuery->getBindings()[0] ?? null);

        // 2. Stock In uses active warehouse
        $stockInQuery = StockInReceipt::forActiveWarehouse();
        $this->assertStringContainsString('`warehouse_id` = ?', $stockInQuery->toSql());
        $this->assertEquals($this->warehouseA->id, $stockInQuery->getBindings()[0] ?? null);

        // 3. Bin (used for stock check in inventory planning) uses active warehouse
        $binQuery = Bin::forActiveWarehouse();
        $this->assertStringContainsString('`warehouse_id` = ?', $binQuery->toSql());
        $this->assertEquals($this->warehouseA->id, $binQuery->getBindings()[0] ?? null);
    }
}
