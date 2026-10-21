<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/profile');

        $response
            ->assertOk()
            ->assertSeeVolt('profile.update-profile-information-form')
            ->assertSeeVolt('profile.update-password-form')
            ->assertDontSee('Delete Account');
    }

    public function test_profile_information_can_be_updated(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $component = Volt::test('profile.update-profile-information-form')
            ->set('name', 'Test User')
            ->set('email', 'test@example.com')
            ->call('updateProfileInformation');

        $component
            ->assertHasNoErrors()
            ->assertNoRedirect();

        $user->refresh();

        $this->assertSame('Test User', $user->name);
        $this->assertSame('test@example.com', $user->email);
        $this->assertNull($user->email_verified_at);
    }

    public function test_email_verification_status_is_unchanged_when_the_email_address_is_unchanged(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $component = Volt::test('profile.update-profile-information-form')
            ->set('name', 'Test User')
            ->set('email', $user->email)
            ->call('updateProfileInformation');

        $component
            ->assertHasNoErrors()
            ->assertNoRedirect();

        $this->assertNotNull($user->refresh()->email_verified_at);
    }

    /**
     * UM7A: fungsi padam akaun sendiri dinyahaktifkan bagi semua peranan. Komponen
     * `profile.delete-user-form` telah dibuang, jadi tiada tindakan Livewire untuk dipanggil.
     */
    public function test_user_cannot_delete_their_own_account(): void
    {
        foreach (['pengguna_biasa', 'pentadbir_urus_setia', 'pengurusan', 'superadmin'] as $role) {
            Role::findOrCreate($role);
            $user = User::factory()->create();
            $user->assignRole($role);

            $this->actingAs($user)->get('/profile')
                ->assertOk()
                ->assertDontSee('Delete Account')
                ->assertDontSee('deleteUser');

            try {
                Volt::test('profile.delete-user-form')->set('password', 'password')->call('deleteUser');
                $this->fail('Komponen padam akaun sendiri tidak sepatutnya wujud.');
            } catch (\Throwable $e) {
                $this->assertStringNotContainsString('tidak sepatutnya wujud', $e->getMessage());
            }

            $fresh = User::withTrashed()->find($user->id);
            $this->assertNotNull($fresh);
            $this->assertFalse($fresh->trashed());
            $this->assertAuthenticatedAs($user);
        }
    }
}
