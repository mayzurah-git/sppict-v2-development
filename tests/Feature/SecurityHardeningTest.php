<?php

namespace Tests\Feature;

use App\Enums\UserStatus;
use App\Http\Middleware\EnsurePasswordIsChanged;
use App\Http\Middleware\EnsureUserDoesNotHaveRole;
use App\Http\Middleware\EnsureUserIsActive;
use App\Livewire\Admin\AuditLog;
use App\Livewire\Admin\GradeManagement;
use App\Livewire\Admin\PositionManagement;
use App\Livewire\Admin\ServiceSchemeManagement;
use App\Livewire\Admin\UserManagement;
use App\Livewire\Meetings\MeetingForm;
use App\Livewire\Meetings\MeetingList;
use App\Models\Agency;
use App\Models\Grade;
use App\Models\Position;
use App\Models\ServiceScheme;
use App\Models\User;
use Database\Seeders\GradeSeeder;
use Database\Seeders\DemoAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password as PasswordBroker;
use Illuminate\Validation\Rules\Password;
use Livewire\Livewire;
use Livewire\Mechanisms\PersistentMiddleware\PersistentMiddleware;
use Livewire\Volt\Volt;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Fasa UM7: pengerasan keselamatan — status aktif (HTTP & Livewire), authorization komponen,
 * rate limiting, sesi selepas pertukaran kata laluan, polisi kata laluan dan seeder.
 */
class SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    private const STRONG = 'KataLaluanKuat#2026';

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['pengguna_biasa', 'pentadbir_urus_setia', 'pengurusan', 'superadmin'] as $role) {
            Role::create(['name' => $role]);
        }
    }

    // =========================================================
    // A. Status akaun aktif & persistent middleware
    // =========================================================

    public function test_security_middleware_is_registered_as_livewire_persistent_middleware(): void
    {
        $persistent = app(PersistentMiddleware::class)->getPersistentMiddleware();

        foreach ([EnsureUserIsActive::class, EnsurePasswordIsChanged::class, RoleMiddleware::class, EnsureUserDoesNotHaveRole::class] as $middleware) {
            $this->assertContains($middleware, $persistent);
        }
    }

    public function test_active_user_can_use_internal_pages(): void
    {
        $user = $this->userWithRole('pengguna_biasa');

        $this->actingAs($user)->get('/dashboard')->assertOk();
        $this->actingAs($user)->get('/profile')->assertOk();
    }

    public function test_guest_is_redirected_from_protected_pages(): void
    {
        foreach (['/dashboard', '/profile', '/admin/users', '/urus-setia/pengguna/permohonan', '/tukar-kata-laluan', '/verify-email'] as $uri) {
            $this->get($uri)->assertRedirect(route('login'));
        }
    }

    public function test_pending_and_rejected_sessions_are_logged_out_on_every_protected_route(): void
    {
        foreach ([UserStatus::Pending, UserStatus::Rejected] as $status) {
            $user = $this->userWithRole('pentadbir_urus_setia');
            $user->forceFill(['status' => $status])->save();

            foreach (['/dashboard', '/urus-setia/pengguna/permohonan', '/verify-email', '/tukar-kata-laluan'] as $uri) {
                $this->actingAs($user)->get($uri)->assertRedirect(route('login'));
                $this->assertGuest();
            }
        }
    }

    public function test_livewire_action_is_rejected_when_status_changes_mid_session(): void
    {
        $superadmin = $this->userWithRole('superadmin');
        $target = User::factory()->create();

        $snapshot = $this->snapshotFor($this->actingAs($superadmin)->get('/admin/users')->assertOk()->getContent(), 'admin.user-management');

        // Kawalan: payload sah ketika akaun aktif.
        $snapshot = $this->livewireCall($superadmin, $snapshot, 'resetPassword', [$target->uuid])->assertOk()->json('components.0.snapshot');
        $hashAfterControl = $target->fresh()->password;

        $superadmin->forceFill(['status' => UserStatus::Pending])->save();

        $this->livewireCall($superadmin->fresh(), $snapshot, 'resetPassword', [$target->uuid])->assertForbidden();
        $this->assertGuest();
        $this->assertSame($hashAfterControl, $target->fresh()->password);
    }

    public function test_livewire_request_is_rejected_when_role_is_revoked_mid_session(): void
    {
        $superadmin = $this->userWithRole('superadmin');

        $html = $this->actingAs($superadmin)->get('/admin/audit-logs')->assertOk()->getContent();
        $snapshot = $this->snapshotFor($html, 'admin.audit-log');

        // Kawalan: kemas kini carian berjaya.
        $snapshot = $this->livewireUpdate($superadmin, $snapshot, ['search' => 'a'])->assertOk()->json('components.0.snapshot');

        $superadmin->syncRoles(['pentadbir_urus_setia']);

        $this->livewireUpdate($superadmin->fresh(), $snapshot, ['search' => 'b'])->assertForbidden();
    }

    public function test_guest_cannot_replay_livewire_snapshot(): void
    {
        $superadmin = $this->userWithRole('superadmin');
        $target = User::factory()->create();
        $hash = $target->password;
        $snapshot = $this->snapshotFor($this->actingAs($superadmin)->get('/admin/users')->getContent(), 'admin.user-management');

        Auth::logout();

        $response = $this->withHeaders(['X-Livewire' => 'true'])
            ->postJson('/livewire/update', ['components' => [[
                'snapshot' => $snapshot,
                'updates' => (object) [],
                'calls' => [['path' => '', 'method' => 'resetPassword', 'params' => [$target->uuid]]],
            ]]]);

        // Middleware `auth` (persistent lalai Livewire) menolak: redirect ke log masuk / 401, tindakan tidak dijalankan.
        $this->assertContains($response->getStatusCode(), [302, 401]);
        $this->assertSame($hash, $target->fresh()->password);
        $this->assertGuest();
    }

    // =========================================================
    // B. Authorization komponen
    // =========================================================

    public function test_master_data_components_reject_non_superadmin_on_mount_and_actions(): void
    {
        $urusSetia = $this->userWithRole('pentadbir_urus_setia');

        foreach ([GradeManagement::class, PositionManagement::class, ServiceSchemeManagement::class] as $component) {
            Livewire::actingAs($urusSetia)->test($component)->assertForbidden();
        }

        $superadmin = $this->userWithRole('superadmin');
        $component = Livewire::actingAs($superadmin)->test(GradeManagement::class)->assertOk();
        $superadmin->syncRoles(['pentadbir_urus_setia']);
        $component->call('openModal')->assertForbidden();
    }

    public function test_audit_log_requires_active_superadmin_on_every_request(): void
    {
        Livewire::actingAs($this->userWithRole('pentadbir_urus_setia'))->test(AuditLog::class)->assertForbidden();

        $superadmin = $this->userWithRole('superadmin');
        $component = Livewire::actingAs($superadmin)->test(AuditLog::class)->assertOk();

        $superadmin->forceFill(['must_change_password' => true])->save();
        $component->set('search', 'x')->assertForbidden();
    }

    public function test_meeting_pages_reject_non_urus_setia_on_mount(): void
    {
        $superadmin = $this->userWithRole('superadmin');

        Livewire::actingAs($superadmin)->test(MeetingList::class)->assertForbidden();
        Livewire::actingAs($superadmin)->test(MeetingForm::class)->assertForbidden();
    }

    public function test_ensure_guards_reject_inactive_or_temporary_password_accounts(): void
    {
        $pending = $this->userWithRole('superadmin');
        $pending->forceFill(['status' => UserStatus::Pending])->save();
        Livewire::actingAs($pending)->test(UserManagement::class)->assertForbidden();

        $temporary = $this->userWithRole('superadmin');
        $temporary->forceFill(['must_change_password' => true])->save();
        Livewire::actingAs($temporary)->test(PositionManagement::class)->assertForbidden();
    }

    // =========================================================
    // C. Rate limiting
    // =========================================================

    public function test_self_register_is_rate_limited_per_ip_and_recovers(): void
    {
        for ($i = 0; $i < 10; $i++) {
            Volt::test('pages.auth.register')->call('register')->assertHasErrors(['no_kad_pengenalan'])->assertHasNoErrors(['throttle']);
        }

        Volt::test('pages.auth.register')->call('register')->assertHasErrors(['throttle']);

        $this->travel(601)->seconds();
        Volt::test('pages.auth.register')->call('register')->assertHasNoErrors(['throttle']);
    }

    public function test_password_reset_request_is_limited_by_normalised_email_without_revealing_accounts(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'pegawai@ns.gov.my']);

        // E-mel tidak berdaftar: mesej sama seperti e-mel berdaftar (tiada ralat).
        Volt::test('pages.auth.forgot-password')->set('email', 'tiada@ns.gov.my')->call('sendPasswordResetLink')
            ->assertHasNoErrors();
        $unknownStatus = session('status');

        Volt::test('pages.auth.forgot-password')->set('email', 'pegawai@ns.gov.my')->call('sendPasswordResetLink')
            ->assertHasNoErrors();
        $this->assertSame($unknownStatus, session('status'));

        // Variasi huruf/ruang dikira sebagai e-mel yang sama.
        foreach (['PEGAWAI@ns.gov.my', ' Pegawai@NS.gov.my '] as $variant) {
            Volt::test('pages.auth.forgot-password')->set('email', $variant)->call('sendPasswordResetLink')->assertHasNoErrors();
        }

        Volt::test('pages.auth.forgot-password')->set('email', 'pEgAwAi@ns.gov.my')->call('sendPasswordResetLink')
            ->assertHasErrors(['email']);

        $this->travel(901)->seconds();
        Volt::test('pages.auth.forgot-password')->set('email', 'pegawai@ns.gov.my')->call('sendPasswordResetLink')
            ->assertHasNoErrors();

        Notification::assertSentTo($user, \Illuminate\Auth\Notifications\ResetPassword::class);
    }

    public function test_password_reset_submission_is_rate_limited(): void
    {
        $user = User::factory()->create();
        $hash = $user->password;

        for ($i = 0; $i < 10; $i++) {
            Volt::test('pages.auth.reset-password', ['token' => 'token-palsu'])
                ->set('email', $user->email)->set('password', self::STRONG)->set('password_confirmation', self::STRONG)
                ->call('resetPassword')
                ->assertHasErrors(['email']);
        }

        $component = Volt::test('pages.auth.reset-password', ['token' => PasswordBroker::createToken($user)])
            ->set('email', $user->email)->set('password', self::STRONG)->set('password_confirmation', self::STRONG)
            ->call('resetPassword')
            ->assertHasErrors(['email']);

        $this->assertStringContainsString('Terlalu banyak cubaan', $component->errors()->first('email'));
        $this->assertSame($hash, $user->fresh()->password);
    }

    public function test_verification_resend_is_rate_limited(): void
    {
        $user = User::factory()->unverified()->create();

        for ($i = 0; $i < 6; $i++) {
            Volt::actingAs($user)->test('pages.auth.verify-email')->call('sendVerification')->assertHasNoErrors();
        }

        Volt::actingAs($user)->test('pages.auth.verify-email')->call('sendVerification')->assertHasErrors(['throttle']);
    }

    public function test_change_password_attempts_are_rate_limited(): void
    {
        $user = $this->userWithRole('pengguna_biasa', ['password' => Hash::make('Sementara#2026A')]);
        $user->forceFill(['must_change_password' => true])->save();

        for ($i = 0; $i < 5; $i++) {
            $this->changePassword($user, 'SalahSalah#1')->assertHasErrors(['current_password']);
        }

        // Walaupun kata laluan semasa betul, had telah dicapai.
        $this->changePassword($user, 'Sementara#2026A')->assertHasErrors(['current_password']);
        $this->assertTrue($user->fresh()->must_change_password);

        $this->travel(301)->seconds();
        $this->changePassword($user, 'Sementara#2026A')->assertHasNoErrors();
        $this->assertFalse($user->fresh()->must_change_password);
    }

    public function test_superadmin_password_reset_is_rate_limited_per_target_and_actor(): void
    {
        $superadmin = $this->userWithRole('superadmin');
        $target = User::factory()->create();
        $other = User::factory()->create();

        for ($i = 0; $i < 3; $i++) {
            Livewire::actingAs($superadmin)->test(UserManagement::class)->call('resetPassword', $target->uuid)
                ->assertNotSet('temporaryPassword', null);
        }

        $hash = $target->fresh()->password;
        $blocked = Livewire::actingAs($superadmin)->test(UserManagement::class)->call('resetPassword', $target->uuid)
            ->assertSet('temporaryPassword', null)
            ->assertSee('Terlalu banyak cubaan');
        $this->assertSame($hash, $target->fresh()->password);

        // Sasaran lain masih boleh direset (operasi pentadbiran biasa tidak tersekat).
        Livewire::actingAs($superadmin)->test(UserManagement::class)->call('resetPassword', $other->uuid)
            ->assertNotSet('temporaryPassword', null);

        $this->travel(601)->seconds();
        Livewire::actingAs($superadmin)->test(UserManagement::class)->call('resetPassword', $target->uuid)
            ->assertNotSet('temporaryPassword', null);
    }

    public function test_login_rate_limit_uses_normalised_email(): void
    {
        $user = User::factory()->create(['email' => 'pegawai@ns.gov.my']);

        foreach (['pegawai@ns.gov.my', 'PEGAWAI@ns.gov.my', 'Pegawai@NS.gov.my', 'pegawai@NS.GOV.MY', 'PeGaWaI@ns.gov.my'] as $variant) {
            Volt::test('pages.auth.login')->set('form.email', $variant)->set('form.password', 'salah')->call('login');
        }

        $component = Volt::test('pages.auth.login')->set('form.email', 'pegawai@ns.gov.my')->set('form.password', 'password')->call('login')
            ->assertHasErrors(['form.email']);
        $this->assertStringNotContainsString('credentials', (string) $component->errors()->first('form.email'));
        $this->assertGuest();
    }

    // =========================================================
    // D. Sesi selepas pertukaran / reset kata laluan
    // =========================================================

    public function test_old_session_is_invalidated_after_password_reset_by_superadmin(): void
    {
        $target = $this->userWithRole('pengguna_biasa');

        // Sesi sasaran aktif (hash kata laluan disimpan dalam sesi oleh AuthenticateSession).
        $this->actingAs($target)->get('/dashboard')->assertOk();

        Livewire::actingAs($superadmin = $this->userWithRole('superadmin'))->test(UserManagement::class)->call('resetPassword', $target->uuid);

        // Sesi lama sasaran tidak lagi sah.
        Auth::setUser($target->fresh());
        $this->get('/dashboard')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_change_password_keeps_current_session_and_invalidates_other_sessions(): void
    {
        $user = $this->userWithRole('pengguna_biasa', ['password' => Hash::make('Sementara#2026A')]);
        $user->forceFill(['must_change_password' => true])->save();
        $oldCookieHash = Auth::guard('web')->hashPasswordForCookie($user->password);

        // Aliran sebenar melalui HTTP + /livewire/update (middleware web, termasuk AuthenticateSession).
        $snapshot = $this->snapshotFor($this->actingAs($user)->get('/tukar-kata-laluan')->assertOk()->getContent(), 'pages.auth.change-password');

        $this->withHeaders(['X-Livewire' => 'true'])->postJson('/livewire/update', ['components' => [[
            'snapshot' => $snapshot,
            'updates' => ['current_password' => 'Sementara#2026A', 'password' => self::STRONG, 'password_confirmation' => self::STRONG],
            'calls' => [['path' => '', 'method' => 'updatePassword', 'params' => []]],
        ]]])->assertOk();

        $this->assertFalse($user->fresh()->must_change_password);

        // Sesi semasa kekal sah.
        $this->get('/dashboard')->assertOk();
        $this->assertAuthenticatedAs($user);

        // Sesi lain (peranti lain) yang masih menyimpan hash kata laluan sementara dibatalkan.
        $this->withSession(['password_hash_web' => $oldCookieHash])->get('/dashboard')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    // =========================================================
    // E. Polisi kata laluan berpusat
    // =========================================================

    public function test_central_password_policy_is_configured(): void
    {
        $rules = Password::default();

        $this->assertTrue(validator(['p' => self::STRONG], ['p' => $rules])->passes());

        foreach (['Pendek#1Aa', 'tiadahurufbesar#2026', 'TIADAHURUFKECIL#2026', 'TiadaNomborLagi#', 'TiadaSimbolLagi2026'] as $weak) {
            $this->assertTrue(validator(['p' => $weak], ['p' => $rules])->fails(), "Kata laluan lemah diterima: {$weak}");
        }
    }

    public function test_policy_is_applied_to_every_password_form_without_side_effects(): void
    {
        $weak = 'lemah12345';

        // Self Register
        Volt::test('pages.auth.register')->set('password', $weak)->set('password_confirmation', $weak)->call('register')
            ->assertHasErrors(['password']);
        $this->assertSame(0, User::where('status', 'pending')->count());

        // Tukar kata laluan wajib
        $temporary = $this->userWithRole('pengguna_biasa', ['password' => Hash::make('Sementara#2026A')]);
        $temporary->forceFill(['must_change_password' => true])->save();
        $this->changePassword($temporary, 'Sementara#2026A', $weak)->assertHasErrors(['password']);
        $this->assertTrue($temporary->fresh()->must_change_password);
        $this->assertTrue(Hash::check('Sementara#2026A', $temporary->fresh()->password));

        // Reset kata laluan melalui e-mel
        $user = User::factory()->create();
        Volt::test('pages.auth.reset-password', ['token' => PasswordBroker::createToken($user)])
            ->set('email', $user->email)->set('password', $weak)->set('password_confirmation', $weak)
            ->call('resetPassword')->assertHasErrors(['password']);
        $this->assertTrue(Hash::check('password', $user->fresh()->password));

        // Profil
        Volt::actingAs($user)->test('profile.update-password-form')
            ->set('current_password', 'password')->set('password', $weak)->set('password_confirmation', $weak)
            ->call('updatePassword')->assertHasErrors(['password']);
        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }

    // =========================================================
    // F. Seeder
    // =========================================================

    public function test_seeder_creates_accounts_with_random_temporary_passwords_and_keeps_existing_accounts(): void
    {
        // Akaun sedia ada (sebelum seeder) tidak boleh diubah.
        $existingHash = Hash::make('KataLaluanAsal#2026');
        $agency = Agency::create(['code' => 'BTM', 'name' => 'BTM', 'category' => 'SUK', 'is_active' => true]);
        $existing = User::factory()->create(['email' => 'admin.sppict@ns.gov.my', 'password' => $existingHash, 'agency_id' => $agency->id]);

        $this->seed(DemoAccountsSeeder::class);

        $existing->refresh();
        $this->assertSame($existingHash, $existing->password);
        $this->assertFalse($existing->must_change_password);

        foreach (['urussetia.mkk@ns.gov.my', 'pemohon.jkr@ns.gov.my'] as $email) {
            $seeded = User::where('email', $email)->firstOrFail();
            $this->assertFalse(Hash::check('Password123!', $seeded->password));
            $this->assertTrue($seeded->must_change_password);
        }

        // Dijalankan semula: tiada perubahan kata laluan.
        $hashes = User::pluck('password', 'email');
        $this->seed(DemoAccountsSeeder::class);
        $this->assertEquals($hashes, User::pluck('password', 'email'));
    }

    // --- Helpers ---

    private function userWithRole(string $role, array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $user->assignRole($role);

        return $user;
    }

    private function changePassword(User $user, string $current, string $new = self::STRONG)
    {
        return Volt::actingAs($user)->test('pages.auth.change-password')
            ->set('current_password', $current)
            ->set('password', $new)
            ->set('password_confirmation', $new)
            ->call('updatePassword');
    }

    private function livewireCall(User $user, string $snapshot, string $method, array $params)
    {
        return $this->actingAs($user)->withHeaders(['X-Livewire' => 'true'])->postJson('/livewire/update', ['components' => [[
            'snapshot' => $snapshot,
            'updates' => (object) [],
            'calls' => [['path' => '', 'method' => $method, 'params' => $params]],
        ]]]);
    }

    private function livewireUpdate(User $user, string $snapshot, array $updates)
    {
        return $this->actingAs($user)->withHeaders(['X-Livewire' => 'true'])->postJson('/livewire/update', ['components' => [[
            'snapshot' => $snapshot,
            'updates' => $updates,
            'calls' => [],
        ]]]);
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
