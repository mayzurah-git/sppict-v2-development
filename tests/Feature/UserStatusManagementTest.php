<?php

namespace Tests\Feature;

use App\Enums\InactiveReason;
use App\Enums\UserStatus;
use App\Livewire\Admin\UserManagement;
use App\Livewire\Users\ManageUsers;
use App\Livewire\Users\RegistrationRequests;
use App\Models\Agency;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Livewire\Volt\Volt;
use OwenIt\Auditing\Models\Audit;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Fasa UM11: status akaun Aktif / Tidak Aktif (sebab wajib) dan pengaktifan semula
 * oleh Urus Setia & Superadmin.
 */
class UserStatusManagementTest extends TestCase
{
    use RefreshDatabase;

    private Agency $agency;

    private User $superadmin;

    private User $urusSetia;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['pengguna_biasa', 'pentadbir_urus_setia', 'pengurusan', 'superadmin'] as $role) {
            Role::create(['name' => $role]);
        }

        $this->agency = Agency::create(['code' => 'JKR', 'name' => 'Jabatan Kerja Raya', 'category' => 'STATE_DEPT', 'is_active' => true]);
        $this->superadmin = $this->userWithRole('superadmin');
        $this->urusSetia = $this->userWithRole('pentadbir_urus_setia');
    }

    // 1–2. Status lalai & aliran pending/rejected
    public function test_approved_accounts_default_to_active_and_registration_statuses_are_untouched(): void
    {
        $this->assertSame(UserStatus::Active, User::factory()->create()->fresh()->status);

        $pending = $this->pendingApplicant();
        Livewire::actingAs($this->urusSetia)->test(RegistrationRequests::class)->call('approve', $pending->uuid);
        $this->assertSame(UserStatus::Active, $pending->fresh()->status);
        $this->assertNull($pending->fresh()->inactive_reason);

        // Pending/rejected tidak boleh dinyahaktif atau "diaktifkan semula" melalui fungsi status akaun.
        $stillPending = $this->pendingApplicant();
        $rejected = $this->pendingApplicant();
        $rejected->forceFill(['status' => UserStatus::Rejected])->save();

        foreach ([$stillPending, $rejected] as $user) {
            try {
                Livewire::actingAs($this->superadmin)->test(UserManagement::class)->call('openDeactivate', $user->uuid);
                $this->fail('ModelNotFoundException dijangka.');
            } catch (ModelNotFoundException) {
                // Dijangka: hanya akaun Aktif boleh dinyahaktifkan.
            }

            Livewire::actingAs($this->superadmin)->test(UserManagement::class)
                ->call('reactivate', $user->uuid)
                ->assertSee('tidak boleh diaktifkan semula');
        }

        $this->assertSame(UserStatus::Pending, $stillPending->fresh()->status);
        $this->assertSame(UserStatus::Rejected, $rejected->fresh()->status);
    }

    public static function actorReasonProvider(): array
    {
        $cases = [];
        foreach (['urus_setia' => ManageUsers::class, 'superadmin' => UserManagement::class] as $actor => $component) {
            foreach (InactiveReason::cases() as $reason) {
                $cases["{$actor} — {$reason->value}"] = [$actor, $component, $reason->value];
            }
        }

        return $cases;
    }

    // 3–5, 11–12. Nyahaktif oleh Urus Setia & Superadmin dengan setiap sebab
    #[DataProvider('actorReasonProvider')]
    public function test_admins_can_deactivate_active_users_with_each_reason(string $actorKey, string $component, string $reason): void
    {
        config(['audit.console' => true]);
        $actor = $actorKey === 'superadmin' ? $this->superadmin : $this->urusSetia;
        $target = $this->userWithRole('pengurusan', ['name' => 'Pegawai Sasaran']);

        Livewire::actingAs($actor)->test($component)
            ->call('openDeactivate', $target->uuid)
            ->assertSee('Sebab Tidak Aktif')
            ->set('inactiveReason', $reason)
            ->assertSeeHtml('atas sebab ' . InactiveReason::from($reason)->label())
            ->call('deactivate')
            ->assertHasNoErrors()
            ->assertSee('telah ditukar kepada Tidak Aktif')
            ->assertSee(InactiveReason::from($reason)->label());

        $target->refresh();
        $this->assertSame(UserStatus::Inactive, $target->status);
        $this->assertSame($reason, $target->inactive_reason->value);
        $this->assertSame($actor->id, $target->status_changed_by);
        $this->assertNotNull($target->status_changed_at);
        $this->assertSame(['pengurusan'], $target->getRoleNames()->all(), 'Peranan Spatie tidak berubah');

        $audit = Audit::where('auditable_type', User::class)->where('auditable_id', $target->id)->where('event', 'updated')->latest('id')->firstOrFail();
        $this->assertSame('active', $audit->old_values['status']);
        $this->assertSame('inactive', $audit->new_values['status']);
        $this->assertSame($reason, $audit->new_values['inactive_reason']);
        $this->assertSame($actor->id, (int) $audit->user_id);
        $this->assertStringNotContainsString('password', json_encode($audit->getAttributes()));
    }

    // 6. Sebab kosong / tidak sah
    public function test_missing_or_invalid_reason_is_rejected(): void
    {
        $target = $this->userWithRole('pengguna_biasa');

        foreach (['', 'dibuang_kerja', 'retired ', 'inactive'] as $reason) {
            Livewire::actingAs($this->urusSetia)->test(ManageUsers::class)
                ->call('openDeactivate', $target->uuid)
                ->set('inactiveReason', $reason)
                ->call('deactivate')
                ->assertHasErrors(['inactiveReason']);
        }

        $this->assertSame(UserStatus::Active, $target->fresh()->status);
        $this->assertNull($target->fresh()->inactive_reason);
    }

    // 7. Tanpa kebenaran
    public function test_unauthorised_users_cannot_change_status(): void
    {
        $target = $this->userWithRole('pengguna_biasa');

        foreach (['pengguna_biasa', 'pengurusan'] as $role) {
            $actor = $this->userWithRole($role);
            Livewire::actingAs($actor)->test(ManageUsers::class)->assertForbidden();
            Livewire::actingAs($actor)->test(UserManagement::class)->assertForbidden();
        }

        // Urus Setia tidak boleh menyentuh Superadmin atau akaun sendiri.
        foreach ([$this->superadmin, $this->urusSetia] as $outOfScope) {
            try {
                Livewire::actingAs($this->urusSetia)->test(ManageUsers::class)->call('openDeactivate', $outOfScope->uuid);
                $this->fail('ModelNotFoundException dijangka.');
            } catch (ModelNotFoundException) {
                // Dijangka.
            }
        }

        // Sasaran dikunci.
        try {
            Livewire::actingAs($this->urusSetia)->test(ManageUsers::class)->call('openDeactivate', $target->uuid)->set('deactivateUuid', $this->superadmin->uuid);
            $this->fail('CannotUpdateLockedPropertyException dijangka.');
        } catch (CannotUpdateLockedPropertyException) {
            // Dijangka.
        }

        // Peranan Urus Setia ditarik semasa sesi: tindakan Livewire (komponen & payload HTTP) ditolak.
        $component = Livewire::actingAs($this->urusSetia)->test(ManageUsers::class)->call('openDeactivate', $target->uuid)->set('inactiveReason', 'retired');
        $snapshot = $this->snapshot($this->actingAs($this->urusSetia)->get('/urus-setia/pengguna')->assertOk()->getContent(), 'users.manage-users');
        $this->urusSetia->syncRoles(['pengguna_biasa']);

        $component->call('deactivate')->assertForbidden();
        $this->withHeaders(['X-Livewire' => 'true'])->postJson('/livewire/update', ['components' => [[
            'snapshot' => $snapshot, 'updates' => (object) [],
            'calls' => [['path' => '', 'method' => 'reactivate', 'params' => [$target->uuid]]],
        ]]])->assertForbidden();

        $this->assertSame(UserStatus::Active, $target->fresh()->status);
    }

    // 8–10. Pengguna Tidak Aktif disekat
    public function test_inactive_user_cannot_log_in(): void
    {
        $user = $this->userWithRole('pengguna_biasa');
        $this->deactivateDirectly($user, InactiveReason::Retired);

        $component = Volt::test('pages.auth.login')
            ->set('form.email', $user->email)->set('form.password', 'password')
            ->call('login')
            ->assertHasErrors(['form.email']);

        $this->assertStringContainsString('Akaun anda tidak aktif', $component->errors()->first('form.email'));
        $this->assertGuest();
    }

    public function test_logged_in_user_is_blocked_immediately_after_deactivation(): void
    {
        $user = $this->userWithRole('pentadbir_urus_setia');
        $this->actingAs($user)->get('/dashboard')->assertOk();

        // Dinyahaktifkan oleh Superadmin semasa pengguna masih log masuk.
        Livewire::actingAs($this->superadmin)->test(UserManagement::class)
            ->call('openDeactivate', $user->uuid)->set('inactiveReason', 'transferred_out')->call('deactivate');

        Auth::setUser($user->fresh());
        $this->get('/urus-setia/pengguna')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_livewire_requests_from_inactive_user_are_blocked(): void
    {
        $user = $this->userWithRole('pentadbir_urus_setia');
        $target = $this->userWithRole('pengguna_biasa');
        $snapshot = $this->snapshot($this->actingAs($user)->get('/urus-setia/pengguna')->assertOk()->getContent(), 'users.manage-users');

        $this->deactivateDirectly($user, InactiveReason::InactiveOver6Months);

        $this->actingAs($user->fresh())->withHeaders(['X-Livewire' => 'true'])->postJson('/livewire/update', ['components' => [[
            'snapshot' => $snapshot, 'updates' => ['inactiveReason' => 'retired'],
            'calls' => [['path' => '', 'method' => 'openDeactivate', 'params' => [$target->uuid]]],
        ]]])->assertForbidden();

        $this->assertGuest();
        $this->assertSame(UserStatus::Active, $target->fresh()->status);
    }

    // 13. Pengaktifan semula
    #[DataProvider('reactivatorProvider')]
    public function test_admins_can_reactivate_inactive_users(string $actorKey, string $component): void
    {
        config(['audit.console' => true]);
        $actor = $actorKey === 'superadmin' ? $this->superadmin : $this->urusSetia;
        $user = $this->userWithRole('pengguna_biasa');
        $this->deactivateDirectly($user, InactiveReason::TransferredOut);

        Livewire::actingAs($actor)->test($component)
            ->assertSee('Aktifkan Semula')
            ->call('reactivate', $user->uuid)
            ->assertSee('telah diaktifkan semula');

        $user->refresh();
        $this->assertSame(UserStatus::Active, $user->status);
        $this->assertNull($user->inactive_reason);
        $this->assertSame($actor->id, $user->status_changed_by);
        $this->assertSame(['pengguna_biasa'], $user->getRoleNames()->all());

        $audit = Audit::where('auditable_type', User::class)->where('auditable_id', $user->id)->where('event', 'updated')->latest('id')->firstOrFail();
        $this->assertSame('inactive', $audit->old_values['status']);
        $this->assertSame('transferred_out', $audit->old_values['inactive_reason']);
        $this->assertSame('active', $audit->new_values['status']);
        $this->assertSame($actor->id, (int) $audit->user_id);

        // Boleh log masuk semula.
        Auth::logout();
        Volt::test('pages.auth.login')->set('form.email', $user->email)->set('form.password', 'password')->call('login')->assertHasNoErrors();
        $this->assertAuthenticatedAs($user);
    }

    public static function reactivatorProvider(): array
    {
        return [
            'Urus Setia' => ['urus_setia', ManageUsers::class],
            'Superadmin' => ['superadmin', UserManagement::class],
        ];
    }

    public function test_urus_setia_cannot_reactivate_out_of_scope_accounts(): void
    {
        $inactiveSuperadmin = $this->userWithRole('superadmin');
        $this->deactivateDirectly($inactiveSuperadmin, InactiveReason::Retired);

        try {
            Livewire::actingAs($this->urusSetia)->test(ManageUsers::class)->call('reactivate', $inactiveSuperadmin->uuid);
            $this->fail('ModelNotFoundException dijangka.');
        } catch (ModelNotFoundException) {
            // Dijangka: Superadmin di luar skop Urus Setia.
        }

        $this->assertSame(UserStatus::Inactive, $inactiveSuperadmin->fresh()->status);
    }

    // 14. Perlindungan Superadmin
    public function test_last_active_superadmin_cannot_be_deactivated(): void
    {
        Livewire::actingAs($this->superadmin)->test(UserManagement::class)
            ->call('openDeactivate', $this->superadmin->uuid)
            ->set('inactiveReason', 'retired')
            ->call('deactivate')
            ->assertHasErrors(['inactiveReason']);
        $this->assertSame(UserStatus::Active, $this->superadmin->fresh()->status);

        $other = $this->userWithRole('superadmin');
        Livewire::actingAs($this->superadmin)->test(UserManagement::class)
            ->call('openDeactivate', $other->uuid)->set('inactiveReason', 'retired')->call('deactivate')
            ->assertHasNoErrors();
        $this->assertSame(UserStatus::Inactive, $other->fresh()->status);
        $this->assertSame(['superadmin'], $other->fresh()->getRoleNames()->all());
    }

    // Paparan status & penapis
    public function test_lists_show_status_reason_and_changer(): void
    {
        $inactive = $this->userWithRole('pengurusan', ['name' => 'Pegawai Bersara']);
        $active = $this->userWithRole('pengguna_biasa', ['name' => 'Pegawai Aktif']);
        Livewire::actingAs($this->urusSetia)->test(ManageUsers::class)
            ->call('openDeactivate', $inactive->uuid)->set('inactiveReason', 'retired')->call('deactivate');

        Livewire::actingAs($this->urusSetia)->test(ManageUsers::class)
            ->assertSee('Pegawai Bersara')->assertSee('Tidak Aktif')->assertSee('Bersara')->assertSee($this->urusSetia->name)
            ->set('statusFilter', 'inactive')
            ->assertSee('Pegawai Bersara')->assertDontSee('Pegawai Aktif')
            ->set('statusFilter', 'active')
            ->assertSee('Pegawai Aktif')->assertDontSee('Pegawai Bersara');

        Livewire::actingAs($this->superadmin)->test(UserManagement::class)
            ->set('search', 'Pegawai Bersara')
            ->assertSee('Tidak Aktif')->assertSee('Bersara')->assertSee('Aktifkan Semula');
    }

    public function test_inactive_users_cannot_be_edited_by_urus_setia(): void
    {
        $inactive = $this->userWithRole('pengguna_biasa');
        $this->deactivateDirectly($inactive, InactiveReason::Retired);

        $this->expectException(ModelNotFoundException::class);
        Livewire::actingAs($this->urusSetia)->test(ManageUsers::class)->call('openModal', $inactive->uuid);
    }

    // --- Helpers ---

    private function userWithRole(string $role, array $attributes = []): User
    {
        $user = User::factory()->create(array_merge(['agency_id' => $this->agency->id], $attributes));
        $user->assignRole($role);

        return $user;
    }

    private function pendingApplicant(): User
    {
        $user = User::factory()->pending()->create(['agency_id' => $this->agency->id]);
        $user->assignRole('pengguna_biasa');

        return $user;
    }

    private function deactivateDirectly(User $user, InactiveReason $reason): void
    {
        $user->forceFill([
            'status' => UserStatus::Inactive,
            'inactive_reason' => $reason,
            'status_changed_at' => now(),
            'status_changed_by' => $this->superadmin->id,
        ])->save();
    }

    private function snapshot(string $html, string $component): string
    {
        preg_match_all('/wire:snapshot="([^"]+)"/', $html, $matches);

        foreach ($matches[1] as $encoded) {
            $snapshot = html_entity_decode($encoded, ENT_QUOTES);
            if ((json_decode($snapshot, true)['memo']['name'] ?? null) === $component) {
                return $snapshot;
            }
        }

        $this->fail("Snapshot [{$component}] tidak dijumpai.");
    }
}
