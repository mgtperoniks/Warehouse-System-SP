<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Livewire\Auth\LoginPage;
use Livewire\Livewire;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;

class AuthenticationTest extends TestCase
{
    use DatabaseTransactions;

    public function test_login_page_renders_successfully(): void
    {
        $response = $this->get('/login');
        $response->assertStatus(200);
        $response->assertSee('Warehouse System');
    }

    public function test_user_can_login_with_valid_credentials(): void
    {
        $warehouse = \App\Models\Warehouse::firstOrCreate(['code' => 'SPAREPART'], ['name' => 'Sparepart Warehouse']);
        $user = User::factory()->create([
            'email' => 'login_valid_' . uniqid() . '@peroniks.com',
            'password' => Hash::make('secret123'),
            'is_active' => true,
        ]);
        \App\Models\UserWarehouseAccess::create([
            'user_id' => $user->id,
            'warehouse_id' => $warehouse->id,
        ]);

        Livewire::test(LoginPage::class)
            ->set('email', $user->email)
            ->set('password', 'secret123')
            ->call('login')
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_user_cannot_login_with_invalid_password(): void
    {
        $user = User::factory()->create([
            'email' => 'login_invalid_' . uniqid() . '@peroniks.com',
            'password' => Hash::make('secret123'),
            'is_active' => true,
        ]);

        Livewire::test(LoginPage::class)
            ->set('email', $user->email)
            ->set('password', 'wrongpassword')
            ->call('login')
            ->assertHasErrors(['email']);

        $this->assertGuest();
    }

    public function test_inactive_user_cannot_login(): void
    {
        $user = User::factory()->create([
            'email' => 'login_inactive_' . uniqid() . '@peroniks.com',
            'password' => Hash::make('secret123'),
            'is_active' => false,
        ]);

        Livewire::test(LoginPage::class)
            ->set('email', $user->email)
            ->set('password', 'secret123')
            ->call('login')
            ->assertHasErrors(['email']);

        $this->assertGuest();
    }

    public function test_authenticated_user_can_access_dashboard(): void
    {
        $user = User::where('email', 'adminsp@peroniks.com')->first();
        if (!$user) {
            $user = User::factory()->create(['role' => 'admin', 'is_active' => true]);
            $warehouse = \App\Models\Warehouse::firstOrCreate(['code' => 'SPAREPART'], ['name' => 'Sparepart Warehouse']);
            \App\Models\UserWarehouseAccess::create(['user_id' => $user->id, 'warehouse_id' => $warehouse->id]);
        }

        $response = $this->actingAs($user)->get('/dashboard');
        $response->assertStatus(200);
    }

    public function test_unauthenticated_user_is_redirected_to_login(): void
    {
        $response = $this->get('/dashboard');
        $response->assertRedirect('/login');
    }

    public function test_user_can_logout(): void
    {
        $warehouse = \App\Models\Warehouse::firstOrCreate(['code' => 'SPAREPART'], ['name' => 'Sparepart Warehouse']);
        $user = User::factory()->create(['is_active' => true]);
        \App\Models\UserWarehouseAccess::create(['user_id' => $user->id, 'warehouse_id' => $warehouse->id]);

        $response = $this->actingAs($user)
            ->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class)
            ->post('/logout');
        $response->assertRedirect(route('login'));
        $this->assertGuest();
    }
}
