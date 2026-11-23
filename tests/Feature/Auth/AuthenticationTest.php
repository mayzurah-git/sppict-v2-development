<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response
            ->assertOk()
            ->assertSeeVolt('pages.auth.login');
    }

    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        $user = User::factory()->create();

        $component = Volt::test('pages.auth.login')
            ->set('form.email', $user->email)
            ->set('form.password', 'password');

        $component->call('login');

        $component
            ->assertHasNoErrors()
            ->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticated();
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create();

        $component = Volt::test('pages.auth.login')
            ->set('form.email', $user->email)
            ->set('form.password', 'wrong-password');

        $component->call('login');

        $component
            ->assertHasErrors()
            ->assertNoRedirect();

        $this->assertGuest();
    }

    public function test_navigation_menu_can_be_rendered(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $response = $this->get('/dashboard');

        $response
            ->assertOk()
            ->assertSeeVolt('layout.navigation');
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $component = Volt::test('layout.navigation');

        $component->call('logout');

        $component
            ->assertHasNoErrors()
            ->assertRedirect('/');

        $this->assertGuest();
    }

    public function test_intended_url_the_user_cannot_access_falls_back_to_dashboard(): void
    {
        // Sesi Urus Setia tamat di /admin/agencies, kemudian Pengguna Biasa log masuk dalam pelayar sama.
        Role::create(['name' => 'pentadbir_urus_setia']);
        Role::create(['name' => 'pengguna_biasa']);
        $user = User::factory()->create();
        $user->assignRole('pengguna_biasa');

        session(['url.intended' => url('/admin/agencies')]);

        Volt::test('pages.auth.login')
            ->set('form.email', $user->email)
            ->set('form.password', 'password')
            ->call('login')
            ->assertHasNoErrors()
            ->assertRedirect(route('dashboard', absolute: false));

        $this->assertFalse(session()->has('url.intended'));
    }

    public function test_intended_url_blocked_by_not_role_falls_back_to_dashboard(): void
    {
        Role::create(['name' => 'superadmin']);
        $user = User::factory()->create();
        $user->assignRole('superadmin');

        session(['url.intended' => url('/application/view/some-uuid')]);

        Volt::test('pages.auth.login')
            ->set('form.email', $user->email)
            ->set('form.password', 'password')
            ->call('login')
            ->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_unknown_intended_url_falls_back_to_dashboard(): void
    {
        $user = User::factory()->create();

        session(['url.intended' => url('/laluan-tidak-wujud')]);

        Volt::test('pages.auth.login')
            ->set('form.email', $user->email)
            ->set('form.password', 'password')
            ->call('login')
            ->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_intended_url_the_user_can_access_is_kept(): void
    {
        Role::create(['name' => 'pentadbir_urus_setia']);
        $user = User::factory()->create();
        $user->assignRole('pentadbir_urus_setia');

        session(['url.intended' => url('/admin/agencies')]);

        Volt::test('pages.auth.login')
            ->set('form.email', $user->email)
            ->set('form.password', 'password')
            ->call('login')
            ->assertRedirect(url('/admin/agencies'));
    }
}
