<?php

namespace Tests\Feature;

use App\Enums\InactiveReason;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Auth\Events\Login;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Livewire\Volt\Volt;
use OwenIt\Auditing\Models\Audit;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Fasa UM11A: rekod log masuk terakhir & penyahaktifan automatik "Tidak Aktif Melebihi 6 Bulan".
 */
class DormantUserDeactivationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['pengguna_biasa', 'pentadbir_urus_setia', 'pengurusan', 'superadmin'] as $role) {
            Role::create(['name' => $role]);
        }

        $this->travelTo(now()->setDate(2028, 1, 15)->setTime(9, 0));
        // Ciri dilancar lebih setahun lalu (supaya tarikh dilancar tidak melindungi akaun dalam ujian).
        config(['sppict.dormancy.baseline' => '2026-10-21', 'sppict.dormancy.months' => 6]);
    }

    // --- Rekod log masuk ---

    public function test_successful_login_records_last_login_at(): void
    {
        $user = $this->userWithRole('pengguna_biasa');
        $this->assertNull($user->last_login_at);

        Volt::test('pages.auth.login')->set('form.email', $user->email)->set('form.password', 'password')->call('login')->assertHasNoErrors();

        $this->assertEquals(now()->startOfSecond(), $user->fresh()->last_login_at);
    }

    public function test_remember_me_login_event_records_activity_but_blocked_accounts_do_not(): void
    {
        $active = $this->userWithRole('pengguna_biasa');
        event(new Login('web', $active, true)); // log masuk melalui kuki "Ingat saya"
        $this->assertNotNull($active->fresh()->last_login_at);

        foreach ([UserStatus::Pending, UserStatus::Rejected, UserStatus::Inactive] as $status) {
            $blocked = $this->userWithRole('pengguna_biasa');
            $blocked->forceFill(['status' => $status])->save();

            Volt::test('pages.auth.login')->set('form.email', $blocked->email)->set('form.password', 'password')->call('login')->assertHasErrors(['form.email']);
            $this->assertNull($blocked->fresh()->last_login_at, "{$status->value} tidak dikira sebagai aktiviti");
        }
    }

    public function test_recording_login_does_not_create_audit_noise(): void
    {
        config(['audit.console' => true]);
        $user = $this->userWithRole('pengguna_biasa');
        $before = Audit::count();

        event(new Login('web', $user, false));

        $this->assertSame($before, Audit::count());
    }

    // --- Penyahaktifan automatik ---

    public function test_dormant_users_are_deactivated_with_reason_and_system_audit(): void
    {
        config(['audit.console' => false]); // tingkah laku production: arahan sendiri mengaktifkan audit
        $dormant = $this->userWithRole('pengurusan', ['created_at' => now()->subYears(2), 'last_login_at' => now()->subMonths(7)]);

        $this->artisan('sppict:deactivate-dormant-users')->assertSuccessful();

        $dormant->refresh();
        $this->assertSame(UserStatus::Inactive, $dormant->status);
        $this->assertSame(InactiveReason::InactiveOver6Months, $dormant->inactive_reason);
        $this->assertNull($dormant->status_changed_by, 'Diubah oleh sistem');
        $this->assertNotNull($dormant->status_changed_at);
        $this->assertSame(['pengurusan'], $dormant->getRoleNames()->all(), 'Peranan tidak berubah');

        $audit = Audit::where('auditable_type', User::class)->where('auditable_id', $dormant->id)->where('event', 'updated')->latest('id')->firstOrFail();
        $this->assertSame('active', $audit->old_values['status']);
        $this->assertSame('inactive', $audit->new_values['status']);
        $this->assertSame('inactive_over_6_months', $audit->new_values['inactive_reason']);
        $this->assertNull($audit->user_id);
        $this->assertFalse(config('audit.console'), 'Tetapan audit konsol dipulihkan');

        // Disekat daripada log masuk.
        Volt::test('pages.auth.login')->set('form.email', $dormant->email)->set('form.password', 'password')->call('login')->assertHasErrors(['form.email']);
    }

    public function test_reference_date_rules(): void
    {
        $cases = [
            'log masuk 5 bulan lalu' => [['last_login_at' => now()->subMonths(5)], true],
            'log masuk tepat 6 bulan + 1 hari' => [['last_login_at' => now()->subMonths(6)->subDay()], false],
            'dicipta 3 bulan lalu, tiada log masuk' => [['created_at' => now()->subMonths(3)], true],
            'dicipta 2 tahun lalu, tiada log masuk' => [[], false],
            'diluluskan 2 bulan lalu' => [['approved_at' => now()->subMonths(2)], true],
            'diaktifkan semula 1 bulan lalu' => [['status_changed_at' => now()->subMonth()], true],
        ];

        $users = [];
        foreach ($cases as $label => [$attributes, $staysActive]) {
            $users[$label] = [$this->userWithRole('pengguna_biasa', array_merge(['created_at' => now()->subYears(2), 'last_login_at' => null], $attributes)), $staysActive];
        }

        $this->artisan('sppict:deactivate-dormant-users')->assertSuccessful();

        foreach ($users as $label => [$user, $staysActive]) {
            $this->assertSame($staysActive ? UserStatus::Active : UserStatus::Inactive, $user->fresh()->status, $label);
        }
    }

    public function test_launch_date_protects_accounts_without_login_record(): void
    {
        // Ciri baru dilancar 2 bulan lalu: akaun lama tanpa rekod log masuk tidak dinyahaktif.
        config(['sppict.dormancy.baseline' => now()->subMonths(2)->toDateString()]);
        $old = $this->userWithRole('pengguna_biasa', ['created_at' => now()->subYears(3), 'last_login_at' => null]);

        $this->artisan('sppict:deactivate-dormant-users')->assertSuccessful();
        $this->assertSame(UserStatus::Active, $old->fresh()->status);

        // Selepas 6 bulan dari tarikh dilancar, akaun yang masih tidak log masuk dinyahaktif.
        $this->travel(5)->months();
        $this->artisan('sppict:deactivate-dormant-users')->assertSuccessful();
        $this->assertSame(UserStatus::Inactive, $old->fresh()->status);
    }

    public function test_superadmin_and_non_active_accounts_are_excluded(): void
    {
        $longAgo = ['created_at' => now()->subYears(2), 'last_login_at' => now()->subYear()];
        $superadmin = $this->userWithRole('superadmin', $longAgo);
        $pending = $this->userWithRole('pengguna_biasa', $longAgo);
        $pending->forceFill(['status' => UserStatus::Pending])->save();
        $rejected = $this->userWithRole('pengguna_biasa', $longAgo);
        $rejected->forceFill(['status' => UserStatus::Rejected])->save();
        $inactive = $this->userWithRole('pengguna_biasa', $longAgo);
        $inactive->forceFill(['status' => UserStatus::Inactive, 'inactive_reason' => InactiveReason::Retired])->save();

        $this->artisan('sppict:deactivate-dormant-users')->assertSuccessful();

        $this->assertSame(UserStatus::Active, $superadmin->fresh()->status);
        $this->assertSame(UserStatus::Pending, $pending->fresh()->status);
        $this->assertSame(UserStatus::Rejected, $rejected->fresh()->status);
        $this->assertSame(InactiveReason::Retired, $inactive->fresh()->inactive_reason, 'Sebab sedia ada tidak ditimpa');
    }

    public function test_dry_run_changes_nothing_and_command_is_idempotent(): void
    {
        $dormant = $this->userWithRole('pengguna_biasa', ['created_at' => now()->subYears(2), 'last_login_at' => now()->subYear()]);

        Artisan::call('sppict:deactivate-dormant-users', ['--dry-run' => true]);
        $this->assertStringContainsString($dormant->email, Artisan::output());
        $this->assertSame(UserStatus::Active, $dormant->fresh()->status);

        $this->artisan('sppict:deactivate-dormant-users')->assertSuccessful();
        $changedAt = $dormant->fresh()->status_changed_at;

        $this->travel(1)->week();
        $this->artisan('sppict:deactivate-dormant-users')->expectsOutputToContain('0 akaun dinyahaktifkan.')->assertSuccessful();
        $this->assertEquals($changedAt, $dormant->fresh()->status_changed_at);
    }

    public function test_command_is_scheduled_weekly_on_monday_at_1am(): void
    {
        $event = collect(app(Schedule::class)->events())
            ->first(fn ($event) => str_contains((string) $event->command, 'sppict:deactivate-dormant-users'));

        $this->assertNotNull($event, 'Arahan dijadualkan');
        $this->assertSame('0 1 * * 1', $event->expression);
        $this->assertTrue($event->withoutOverlapping);
    }

    // --- Helpers ---

    private function userWithRole(string $role, array $attributes = []): User
    {
        $user = User::factory()->create();
        if ($attributes) {
            $user->forceFill($attributes)->saveQuietly();
        }
        $user->assignRole($role);

        return $user->fresh();
    }
}
