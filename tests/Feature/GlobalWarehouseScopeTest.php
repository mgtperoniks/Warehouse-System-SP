<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseFamilyAssignment;
use App\Models\Item;
use App\Models\ItemVariant;
use App\Models\ItemImage;
use App\Models\ItemBarcode;
use App\Models\Bin;
use App\Models\Location;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use App\Livewire\Items\ItemForm;
use App\Livewire\Barcode\PrintPage;
use App\Livewire\Scan\ScanPage;
use App\Livewire\Stock\StockInPage;
use App\Livewire\Reports\MovementLedgerReport;
use App\Models\Scopes\ActiveWarehouseDomainScope;
use Illuminate\Support\Facades\Storage;

class GlobalWarehouseScopeTest extends TestCase
{
    use DatabaseTransactions;

    protected User $user;
    protected Warehouse $sparepartWh;
    protected Warehouse $rawMaterialWh;

    protected ItemVariant $sparepartItem;
    protected ItemVariant $rawMaterialItem;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        // Create User
        $this->user = User::create([
            'name' => 'Global Scope Admin',
            'email' => 'scope_admin_' . uniqid() . '@example.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
        ]);

        $this->sparepartWh = Warehouse::firstOrCreate(
            ['code' => 'SPAREPART'],
            ['name' => 'Sparepart Warehouse', 'status' => 'ACTIVE']
        );

        $this->rawMaterialWh = Warehouse::firstOrCreate(
            ['code' => 'RAW_MATERIAL'],
            ['name' => 'Raw Material Warehouse', 'status' => 'ACTIVE']
        );

        $this->user->warehouses()->syncWithoutDetaching([
            $this->sparepartWh->id,
            $this->rawMaterialWh->id,
        ]);

        // Seed domain mappings
        WarehouseFamilyAssignment::whereIn('warehouse_id', [
            $this->sparepartWh->id,
            $this->rawMaterialWh->id,
        ])->delete();

        WarehouseFamilyAssignment::create(['warehouse_id' => $this->sparepartWh->id, 'family_code' => '5']);
        WarehouseFamilyAssignment::create(['warehouse_id' => $this->rawMaterialWh->id, 'family_code' => '1']);

        // Create items
        $itemSp = Item::create(['name' => 'Sparepart Item']);
        $itemRaw = Item::create(['name' => 'Raw Item']);

        $this->sparepartItem = ItemVariant::create([
            'item_id' => $itemSp->id,
            'erp_code' => '5.01.001',
            'sku' => 'SKU-SP-5',
            'unit' => 'PCS',
        ]);

        $this->rawMaterialItem = ItemVariant::create([
            'item_id' => $itemRaw->id,
            'erp_code' => '1.01.001',
            'sku' => 'SKU-RAW-1',
            'unit' => 'PCS',
        ]);
    }

    private function setSessionActiveWarehouse(Warehouse $warehouse)
    {
        session()->put('active_warehouse_id', $warehouse->id);
        session()->put('active_warehouse_code', $warehouse->code);
        session()->put('active_warehouse_name', $warehouse->name);
    }

    /**
     * Test Route Model Binding details returns 404 for foreign domain item
     */
    public function test_route_model_binding_details_returns_404_outside_domain(): void
    {
        $this->actingAs($this->user);
        $this->setSessionActiveWarehouse($this->sparepartWh);

        // Access allowed item
        $response = $this->get(route('items.show', $this->sparepartItem));
        $response->assertStatus(200);

        // Access foreign item
        $response = $this->get(route('items.show', $this->rawMaterialItem));
        $response->assertStatus(404);
    }

    /**
     * Test Route Model Binding edit returns 404 for foreign domain item
     */
    public function test_route_model_binding_edit_returns_404_outside_domain(): void
    {
        $this->actingAs($this->user);
        $this->setSessionActiveWarehouse($this->sparepartWh);

        // Access allowed item edit
        $response = $this->get(route('items.edit', $this->sparepartItem));
        $response->assertStatus(200);

        // Access foreign item edit
        $response = $this->get(route('items.edit', $this->rawMaterialItem));
        $response->assertStatus(404);
    }

    /**
     * Test updating a foreign item is blocked
     */
    public function test_update_foreign_item_blocked(): void
    {
        $this->actingAs($this->user);
        $this->setSessionActiveWarehouse($this->sparepartWh);

        // Attempting to edit a foreign item directly via Livewire
        Livewire::test(ItemForm::class, ['mode' => 'edit', 'variant' => $this->rawMaterialItem])
            ->assertStatus(404);
    }

    /**
     * Test creating item with invalid ERP family is blocked
     */
    public function test_create_invalid_erp_family_blocked(): void
    {
        $this->actingAs($this->user);
        $this->setSessionActiveWarehouse($this->sparepartWh);

        Livewire::test(ItemForm::class, ['mode' => 'create'])
            ->set('name', 'New Bad Item')
            ->set('erp_code', '1.02.003') // Family 1 is not allowed for SparepartWh
            ->call('save')
            ->assertHasErrors(['erp_code']);
    }

    /**
     * Test deleting a foreign photo is blocked with 404
     */
    public function test_delete_foreign_photo_blocked(): void
    {
        $this->actingAs($this->user);
        $this->setSessionActiveWarehouse($this->sparepartWh);

        $image = ItemImage::create([
            'item_variant_id' => $this->rawMaterialItem->id,
            'path' => 'item-images/test.jpg',
        ]);

        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

        Livewire::test(ItemForm::class, ['mode' => 'edit', 'variant' => $this->sparepartItem])
            ->call('removeExistingPhoto', $image->id);
    }

    /**
     * Test cropping a foreign photo is blocked with 404
     */
    public function test_crop_foreign_photo_blocked(): void
    {
        $this->actingAs($this->user);
        $this->setSessionActiveWarehouse($this->sparepartWh);

        $image = ItemImage::create([
            'item_variant_id' => $this->rawMaterialItem->id,
            'path' => 'item-images/test.jpg',
        ]);

        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

        Livewire::test(ItemForm::class, ['mode' => 'edit', 'variant' => $this->sparepartItem])
            ->call('applyExistingCrop', $image->id);
    }

    /**
     * Test print barcode autocomplete excludes foreign items
     */
    public function test_print_barcode_autocomplete_excludes_foreign_items(): void
    {
        $this->actingAs($this->user);
        $this->setSessionActiveWarehouse($this->sparepartWh);

        Livewire::test(PrintPage::class)
            ->set('searchString', 'Raw')
            ->assertViewHas('searchResults', function ($searchResults) {
                return $searchResults->isEmpty();
            });

        Livewire::test(PrintPage::class)
            ->set('searchString', 'Sparepart')
            ->assertViewHas('searchResults', function ($searchResults) {
                return !$searchResults->isEmpty();
            });
    }

    /**
     * Test stock in cannot create foreign family
     */
    public function test_stock_in_cannot_create_foreign_family(): void
    {
        $this->actingAs($this->user);
        $this->setSessionActiveWarehouse($this->sparepartWh);

        Livewire::test(StockInPage::class)
            ->set('erpCode', '1.09.999') // Family 1 is not allowed for Sparepart
            ->set('itemName', 'New Bad Stock In Item')
            ->call('createNewItem')
            ->assertHasErrors(['erpCode']);
    }

    /**
     * Test stock out cannot resolve foreign item
     */
    public function test_stock_out_cannot_resolve_foreign_items(): void
    {
        $this->actingAs($this->user);
        $this->setSessionActiveWarehouse($this->sparepartWh);

        $barcode = ItemBarcode::create([
            'item_variant_id' => $this->rawMaterialItem->id,
            'barcode' => '9999999999',
            'type' => 'SUPPLIER',
        ]);

        Livewire::test(ScanPage::class)
            ->call('submitScan', '9999999999')
            ->assertDispatched('scan-failed', function ($name, $params) {
                $payload = $params[0] ?? [];
                return str_contains($payload['message'] ?? '', 'Barcode not recognized');
            });
    }

    /**
     * Test movement ledger report for foreign item is blocked
     */
    public function test_movement_ledger_foreign_item_blocked(): void
    {
        $this->actingAs($this->user);
        $this->setSessionActiveWarehouse($this->sparepartWh);

        // Printing ledger
        $response = $this->get(route('reports.movement-ledger.print', [
            'selectedVariantId' => $this->rawMaterialItem->id,
            'startDate' => '2026-01-01',
            'endDate' => '2026-12-31',
        ]));
        $response->assertStatus(404);

        // Exporting ledger CSV
        $response = $this->get(route('reports.movement-ledger.csv', [
            'selectedVariantId' => $this->rawMaterialItem->id,
            'startDate' => '2026-01-01',
            'endDate' => '2026-12-31',
        ]));
        $response->assertStatus(404);
    }

    /**
     * Test withoutGlobalScope() returns all items correctly
     */
    public function test_without_global_scope_returns_every_item_correctly(): void
    {
        $this->actingAs($this->user);
        $this->setSessionActiveWarehouse($this->sparepartWh);

        // With scope: only sparepart item
        $scoped = ItemVariant::all();
        $this->assertTrue($scoped->contains($this->sparepartItem));
        $this->assertFalse($scoped->contains($this->rawMaterialItem));

        // Without scope: both items
        $unscoped = ItemVariant::withoutGlobalScope(ActiveWarehouseDomainScope::class)->get();
        $this->assertTrue($unscoped->contains($this->sparepartItem));
        $this->assertTrue($unscoped->contains($this->rawMaterialItem));
    }

    /**
     * Test CLI compatibility
     */
    public function test_cli_compatibility(): void
    {
        // Simulate no session / CLI context
        session()->flush();
        auth()->logout();

        $all = ItemVariant::all();
        $this->assertTrue($all->contains($this->sparepartItem));
        $this->assertTrue($all->contains($this->rawMaterialItem));
    }
}
