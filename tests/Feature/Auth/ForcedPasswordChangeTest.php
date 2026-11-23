<?php

namespace Tests\Feature\Auth;

use App\Enums\UserStatus;
use App\Http\Middleware\EnsurePasswordIsChanged;
use App\Livewire\Users\RegistrationRequests;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Livewire\Livewire;
use Livewire\Mechanisms\PersistentMiddleware\PersistentMiddleware;
use Livewire\Volt\Volt;
use OwenIt\Auditing\Models\Audit;
use RuntimeException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Fasa UM6: penguatkuasaan pertukaran kata laluan sementara (`must_change_password`).
 */
class ForcedPasswordChangeTest extends TestCase
{
    use RefreshDatabase;

    private const TEMP_PASSWORD = 'Sementara#2026';

    private const NEW_PASSWORD = 'KataLaluanBaru#2026';

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['pengguna_biasa', 'pentadbir_urus_setia', 'pengurusan', 'superadmin'] as $role) {
            Role::create(['name' => $role]);
        }
    }

    // 1. Diarahkan ke halaman tukar kata laluan selepas log masuk
    public function test_user_with_temporary_password_is_sent_to_change_page_after_login(): void
    {
        $user = $this->temporaryUser();

        Volt::test('pages.auth.login')
            ->set('form.email', $user->email)
            ->set('form.password', self::TEMP_PASSWORD)
            ->call('login')
            ->assertHasNoErrors()
            ->assertRedirect(route('password.change'));

        $this->assertAuthenticatedAs($user);
    }

    // 2. Dashboard dan laluan dalaman disekat
    public function test_internal_routes_are_blocked_until_password_is_changed(): void
    {
        $user = $this->temporaryUser('pentadbir_urus_setia');

        foreach (['/dashboard', '/profile', '/application/new', '/urus-setia/mesyuarat', '/urus-setia/pengguna/daftar', '/urus-setia/pengguna/permohonan', '/verify-email', '/confirm-password'] as $uri) {
            $this->actingAs($user)->get($uri)->assertRedirect(route('password.change'));
        }

        // Permintaan AJAX/Livewire ditolak terus (tiada redirect HTML).
        $this->actingAs($user)->getJson('/dashboard')->assertForbidden();
        $this->actingAs($user)->get('/dashboard', ['X-Livewire' => 'true'])->assertForbidden();
    }

    // 3. Halaman tukar kata laluan boleh dibuka (tiada redirect loop)
    public function test_change_password_page_is_accessible(): void
    {
        $this->actingAs($this->temporaryUser())
            ->get(route('password.change'))
            ->assertOk()
            ->assertSeeVolt('pages.auth.change-password')
            ->assertSee('Kata Laluan Semasa')
            ->assertSee('Pengesahan Kata Laluan Baharu');
    }

    public function test_change_password_page_requires_login_and_active_account(): void
    {
        $this->get(route('password.change'))->assertRedirect(route('login'));

        $pending = $this->temporaryUser();
        $pending->forceFill(['status' => UserStatus::Pending])->save();
        $this->actingAs($pending)->get(route('password.change'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    // 4–7. Validation
    public function test_wrong_current_password_is_rejected(): void
    {
        $user = $this->temporaryUser();

        $this->changeForm($user, ['current_password' => 'SalahSama#1'])
            ->assertHasErrors(['current_password' => 'current_password'])
            ->assertSet('current_password', '')
            ->assertSet('password', '');

        $this->assertUnchanged($user);
    }

    public function test_new_password_must_satisfy_policy(): void
    {
        $user = $this->temporaryUser();

        $this->changeForm($user, ['password' => 'pendek', 'password_confirmation' => 'pendek'])
            ->assertHasErrors(['password']);

        $this->assertUnchanged($user);
    }

    public function test_password_confirmation_must_match(): void
    {
        $user = $this->temporaryUser();

        $this->changeForm($user, ['password_confirmation' => 'LainLain#2026'])
            ->assertHasErrors(['password' => 'confirmed']);

        $this->assertUnchanged($user);
    }

    public function test_new_password_must_differ_from_current_password(): void
    {
        $user = $this->temporaryUser();

        $this->changeForm($user, ['password' => self::TEMP_PASSWORD, 'password_confirmation' => self::TEMP_PASSWORD])
            ->assertHasErrors(['password' => 'different']);

        $this->assertUnchanged($user);
    }

    // 8–10. Berjaya
    public function test_successful_change_hashes_password_clears_flag_and_unlocks_dashboard(): void
    {
        $user = $this->temporaryUser('pengguna_biasa', ['agency_id' => null]);
        $before = $user->fresh();

        $this->changeForm($user)
            ->assertHasNoErrors()
            ->assertRedirect(route('dashboard'));

        $user->refresh();
        $this->assertFalse($user->must_change_password);
        $this->assertNotSame(self::NEW_PASSWORD, $user->password);
        $this->assertTrue(Hash::check(self::NEW_PASSWORD, $user->password));
        $this->assertFalse(Hash::check(self::TEMP_PASSWORD, $user->password));

        // Status, peranan, profil dan pengesahan e-mel tidak berubah.
        $this->assertSame(UserStatus::Active, $user->status);
        $this->assertSame(['pengguna_biasa'], $user->getRoleNames()->all());
        $this->assertSame($before->email_verified_at?->toIso8601String(), $user->email_verified_at?->toIso8601String());
        $this->assertSame($before->approved_by, $user->approved_by);

        $this->actingAs($user)->get('/dashboard')->assertOk();
        $this->actingAs($user)->get(route('password.change'))->assertRedirect(route('dashboard'));

        // Boleh log masuk dengan kata laluan baharu.
        auth()->logout();
        Volt::test('pages.auth.login')
            ->set('form.email', $user->email)
            ->set('form.password', self::NEW_PASSWORD)
            ->call('login')
            ->assertRedirect(route('dashboard', absolute: false));
    }

    // 11. Flag kekal jika gagal
    public function test_flag_stays_set_when_save_fails(): void
    {
        $user = $this->temporaryUser();

        User::saving(function (User $model): void {
            if ($model->isDirty('must_change_password')) {
                throw new RuntimeException('Simulasi kegagalan.');
            }
        });

        try {
            $this->changeForm($user);
            $this->fail('Exception dijangka.');
        } catch (RuntimeException) {
            // Dijangka.
        }

        $this->assertUnchanged($user);
    }

    // 12. Pending / rejected tetap tidak boleh log masuk
    public function test_pending_and_rejected_users_still_cannot_log_in(): void
    {
        foreach ([UserStatus::Pending, UserStatus::Rejected] as $status) {
            $user = $this->temporaryUser();
            $user->forceFill(['status' => $status])->save();

            Volt::test('pages.auth.login')
                ->set('form.email', $user->email)
                ->set('form.password', self::TEMP_PASSWORD)
                ->call('login')
                ->assertHasErrors(['form.email']);

            $this->assertGuest();
            $this->assertTrue($user->fresh()->must_change_password);
        }
    }

    // 13. Pengguna tanpa flag tidak terjejas
    public function test_users_without_flag_are_unaffected(): void
    {
        $user = User::factory()->create();
        $user->assignRole('pengguna_biasa');

        Volt::test('pages.auth.login')
            ->set('form.email', $user->email)
            ->set('form.password', 'password')
            ->call('login')
            ->assertRedirect(route('dashboard', absolute: false));

        $this->get('/dashboard')->assertOk();
        $this->get('/profile')->assertOk();
        $this->get(route('password.change'))->assertRedirect(route('dashboard'));
        $this->assertFalse($user->fresh()->must_change_password);
    }

    // 14. Pintasan melalui URL / Livewire
    public function test_livewire_actions_on_protected_pages_are_blocked(): void
    {
        $this->assertContains(EnsurePasswordIsChanged::class, app(PersistentMiddleware::class)->getPersistentMiddleware());

        // Superadmin membuka /admin/users semasa kata laluan normal, kemudian akaun diberi kata laluan sementara.
        $superadmin = User::factory()->create();
        $superadmin->assignRole('superadmin');
        $victim = User::factory()->create();
        $victimHash = $victim->password;

        $html = $this->actingAs($superadmin)->get('/admin/users')->assertOk()->getContent();
        $snapshot = $this->snapshotFor($html, 'admin.user-management');

        // Kawalan: payload yang sama BERJAYA ketika flag false (membuktikan 403 di bawah datang daripada middleware).
        $control = User::factory()->create();
        $controlHash = $control->password;
        $response = $this->actingAs($superadmin)
            ->withHeaders(['X-Livewire' => 'true'])
            ->postJson('/livewire/update', [
                'components' => [[
                    'snapshot' => $snapshot,
                    'updates' => (object) [],
                    'calls' => [['path' => '', 'method' => 'resetPassword', 'params' => [$control->uuid]]],
                ]],
            ])
            ->assertOk();
        $this->assertNotSame($controlHash, $control->fresh()->password);
        $snapshot = $response->json('components.0.snapshot');

        $superadmin->forceFill(['must_change_password' => true])->save();

        $this->actingAs($superadmin->fresh())
            ->withHeaders(['X-Livewire' => 'true'])
            ->postJson('/livewire/update', [
                'components' => [[
                    'snapshot' => $snapshot,
                    'updates' => (object) [],
                    'calls' => [['path' => '', 'method' => 'resetPassword', 'params' => [$victim->uuid]]],
                ]],
            ])
            ->assertForbidden();

        $this->assertSame($victimHash, $victim->fresh()->password);
    }

    public function test_urus_setia_actions_are_blocked_with_temporary_password(): void
    {
        $urusSetia = $this->temporaryUser('pentadbir_urus_setia');
        $pending = User::factory()->pending()->create();

        Livewire::actingAs($urusSetia)->test(RegistrationRequests::class)->assertForbidden();
        $this->assertTrue($pending->fresh()->isPending());
    }

    public function test_flag_cannot_be_changed_through_component_input(): void
    {
        $component = Volt::actingAs($this->temporaryUser())->test('pages.auth.change-password');

        $this->expectException(\Livewire\Exceptions\PublicPropertyNotFoundException::class);
        $component->set('must_change_password', false);
    }

    // 15. Kata laluan tiada dalam audit
    public function test_password_is_not_recorded_in_audit(): void
    {
        config(['audit.console' => true]);
        $user = $this->temporaryUser();

        $this->changeForm($user)->assertHasNoErrors();

        $audit = Audit::where('auditable_type', User::class)
            ->where('auditable_id', $user->id)
            ->where('event', 'updated')
            ->latest('id')
            ->firstOrFail();

        $this->assertEquals(['must_change_password' => true], $audit->old_values);
        $this->assertEquals(['must_change_password' => false], $audit->new_values);

        $raw = json_encode($audit->getAttributes());
        $this->assertStringNotContainsString(self::NEW_PASSWORD, $raw);
        $this->assertStringNotContainsString(self::TEMP_PASSWORD, $raw);
        $this->assertStringNotContainsString($user->fresh()->password, $raw);
    }

    // Reset kata laluan melalui e-mel juga menggantikan kata laluan sementara.
    public function test_password_reset_via_email_clears_flag(): void
    {
        $user = $this->temporaryUser();
        $token = Password::createToken($user);

        Volt::test('pages.auth.reset-password', ['token' => $token])
            ->set('email', $user->email)
            ->set('password', self::NEW_PASSWORD)
            ->set('password_confirmation', self::NEW_PASSWORD)
            ->call('resetPassword')
            ->assertHasNoErrors();

        $user->refresh();
        $this->assertFalse($user->must_change_password);
        $this->assertTrue(Hash::check(self::NEW_PASSWORD, $user->password));
    }

    // --- Helpers ---

    private function temporaryUser(string $role = 'pengguna_biasa', array $attributes = []): User
    {
        $user = User::factory()->create(array_merge(['password' => Hash::make(self::TEMP_PASSWORD)], $attributes));
        $user->forceFill(['must_change_password' => true])->save();
        $user->assignRole($role);

        return $user->fresh();
    }

    private function changeForm(User $user, array $overrides = [])
    {
        $data = array_merge([
            'current_password' => self::TEMP_PASSWORD,
            'password' => self::NEW_PASSWORD,
            'password_confirmation' => self::NEW_PASSWORD,
        ], $overrides);

        $component = Volt::actingAs($user)->test('pages.auth.change-password');
        foreach ($data as $field => $value) {
            $component->set($field, $value);
        }

        return $component->call('updatePassword');
    }

    private function assertUnchanged(User $user): void
    {
        $fresh = $user->fresh();
        $this->assertTrue($fresh->must_change_password);
        $this->assertTrue(Hash::check(self::TEMP_PASSWORD, $fresh->password));
    }

    private function snapshotFor(string $html, string $componentName): string
    {
        preg_match_all('/wire:snapshot="([^"]+)"/', $html, $matches);

        foreach ($matches[1] as $encoded) {
            $snapshot = html_entity_decode($encoded, ENT_QUOTES);
            if ((json_decode($snapshot, true)['memo']['name'] ?? null) === $componentName) {
                return $snapshot;
            }
        }

        $this->fail("Snapshot komponen [{$componentName}] tidak dijumpai.");
    }
}
