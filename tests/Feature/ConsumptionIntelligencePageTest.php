<?php

namespace Tests\Feature;

use App\Livewire\Intelligence\ConsumptionIntelligencePage;
use App\Models\ConsumptionSnapshot;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

class ConsumptionIntelligencePageTest extends TestCase
{
    use DatabaseTransactions;

    protected User $user;
    protected Warehouse $warehouse;

    protected function setUp(): void
    {
        parent::setUp();

        $this->warehouse = Warehouse::first() ?? Warehouse::create(['code' => 'SP', 'name' => 'Main Warehouse']);
        $this->user = User::first() ?? User::factory()->create(['is_active' => true]);
    }

    public function test_consumption_intelligence_page_requires_auth(): void
    {
        $response = $this->get('/consumption-intelligence');
        $response->assertRedirect('/login');
    }

    public function test_authenticated_user_can_access_consumption_intelligence_page(): void
    {
        $response = $this->actingAs($this->user)
            ->withSession(['active_warehouse_id' => $this->warehouse->id])
            ->get('/consumption-intelligence');

        $response->assertStatus(200);
        $response->assertSee('Consumption Intelligence');
        $response->assertSee('Strong Rising');
        $response->assertSee('Top Rising Items');
    }

    public function test_livewire_component_renders_and_filters_correctly(): void
    {
        $component = Livewire::actingAs($this->user)
            ->withQueryParams(['rankingTab' => 'TOP_RISING'])
            ->test(ConsumptionIntelligencePage::class);

        $component->assertStatus(200)
            ->set('rankingTab', 'TOP_ACCELERATING')
            ->assertSet('rankingTab', 'TOP_ACCELERATING')
            ->set('rankingTab', 'TOP_VOLATILE')
            ->assertSet('rankingTab', 'TOP_VOLATILE')
            ->set('rankingTab', 'TOP_CONSUMPTION')
            ->assertSet('rankingTab', 'TOP_CONSUMPTION');
    }

    public function test_drill_down_modal_opens_and_closes(): void
    {
        $snapshot = ConsumptionSnapshot::where('active_weeks_count', '>', 0)->first();

        if ($snapshot) {
            $component = Livewire::actingAs($this->user)
                ->test(ConsumptionIntelligencePage::class)
                ->call('openDetail', $snapshot->item_variant_id);

            $component->assertSet('showDetailModal', true)
                ->assertSet('selectedVariantId', $snapshot->item_variant_id)
                ->assertSee($snapshot->variant->item->name ?? '')
                ->call('closeDetail')
                ->assertSet('showDetailModal', false)
                ->assertSet('selectedVariantId', null);
        }
    }
}
