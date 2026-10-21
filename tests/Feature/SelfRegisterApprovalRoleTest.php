<?php

namespace Tests\Feature;

use App\Enums\UserStatus;
use App\Livewire\Users\RegistrationRequests;
use App\Models\Agency;
use App\Models\Grade;
use App\Models\Position;
use App\Models\ServiceScheme;
use App\Models\User;
use Database\Seeders\GradeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Livewire\Volt\Volt;
use OwenIt\Auditing\Models\Audit;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Fasa UM9: Urus Setia menetapkan Tahap Capaian semasa meluluskan Self Register.
 */
class SelfRegisterApprovalRoleTest extends TestCase
{
    use RefreshDatabase;

    private User $urusSetia;

    private Agency $agency;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['pengguna_biasa', 'pentadbir_urus_setia', 'pengurusan', 'superadmin'] as $role) {
            Role::create(['name' => $role]);
        }

        $this->agency = Agency::create(['code' => 'JKR', 'name' => 'Jabatan Kerja Raya', 'category' => 'STATE_DEPT', 'is_active' => true]);
        $this->urusSetia = $this->userWithRole('pentadbir_urus_setia');
    }

    // 1–2. Self Register: lalai pengguna_biasa, pending, tiada akses
    public function test_self_register_defaults_to_pending_pengguna_biasa_without_access(): void
    {
        $this->seed(GradeSeeder::class);
        $scheme = ServiceScheme::create(['code' => 'F', 'name' => 'Teknologi Maklumat', 'is_active' => true]);
        Position::create(['title' => 'Jawatan Legacy', 'is_active' => false]);
        $position = Position::create(['title' => 'Pegawai Teknologi Maklumat', 'service_scheme_id' => $scheme->id, 'is_active' => true]);
        $grade = Grade::where('code', '9')->first();
        $position->grades()->sync([$grade->id]);

        Volt::test('pages.auth.register')
            ->set('position_id', (string) $position->id)
            ->set('no_kad_pengenalan', '050505055555')->set('name', 'Pemohon Kendiri')
            ->set('agency_id', (string) $this->agency->id)->set('grade_id', (string) $grade->id)
            ->set('phone_office', '067659999')->set('phone_mobile', '0123456789')
            ->set('email', 'kendiri@ns.gov.my')
            ->set('password', 'KataLaluanKuat#2026')->set('password_confirmation', 'KataLaluanKuat#2026')
            ->call('register')
            ->assertHasNoErrors();

        $user = User::where('email', 'kendiri@ns.gov.my')->firstOrFail();
        $this->assertSame(UserStatus::Pending, $user->status);
        $this->assertSame(['pengguna_biasa'], $user->getRoleNames()->all());

        $this->actingAs($user)->get('/dashboard')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    // UI: pilihan, lalai & dialog pengesahan
    public function test_detail_offers_three_roles_defaulting_to_pengguna_biasa_and_confirm_shows_choice(): void
    {
        $applicant = $this->pendingApplicant();

        Livewire::actingAs($this->urusSetia)->test(RegistrationRequests::class)
            ->call('showDetail', $applicant->uuid)
            ->assertSet('approvalRole', 'pengguna_biasa')
            ->assertViewHas('approvalRoleOptions', fn ($options) => array_keys($options) === ['pengguna_biasa', 'pentadbir_urus_setia', 'pengurusan'])
            ->assertSee('Tahap Capaian')
            ->assertDontSeeHtml('value="superadmin"')
            ->assertSeeHtml('Tahap Capaian: Pengguna Biasa (Pemohon Agensi)?')
            ->set('approvalRole', 'pengurusan')
            ->assertSeeHtml('Tahap Capaian: Pengurusan?');
    }

    public static function approvalRoleProvider(): array
    {
        return [
            'pengguna biasa' => ['pengguna_biasa', '/application/new', 200, '/urus-setia/pengguna/permohonan', 403],
            'urus setia' => ['pentadbir_urus_setia', '/urus-setia/pengguna/permohonan', 200, '/admin/users', 403],
            'pengurusan' => ['pengurusan', '/dashboard', 200, '/application/new', 403],
        ];
    }

    // 3–6. Lulus dengan setiap peranan; peranan digunakan oleh authorization
    #[DataProvider('approvalRoleProvider')]
    public function test_urus_setia_approves_with_selected_role_and_authorization_follows_it(
        string $role, string $allowedUri, int $allowedStatus, string $deniedUri, int $deniedStatus,
    ): void {
        config(['audit.console' => true]);
        $applicant = $this->pendingApplicant();

        Livewire::actingAs($this->urusSetia)->test(RegistrationRequests::class)
            ->call('showDetail', $applicant->uuid)
            ->set('approvalRole', $role)
            ->call('approve', $applicant->uuid)
            ->assertHasNoErrors()
            ->assertSee('telah diluluskan dengan Tahap Capaian');

        $applicant->refresh();
        $this->assertSame(UserStatus::Active, $applicant->status);
        $this->assertSame([$role], $applicant->getRoleNames()->all());
        $this->assertSame($this->urusSetia->id, $applicant->approved_by);
        $this->assertNotNull($applicant->approved_at);
        $this->assertNull($applicant->role_id, 'Sumber peranan ialah Spatie; role_id legacy tidak diisi');

        // Audit keputusan kelulusan & tahap capaian (OwenIt custom event), tanpa maklumat rahsia.
        $audit = Audit::where('auditable_type', User::class)->where('auditable_id', $applicant->id)->where('event', 'approved')->firstOrFail();
        $this->assertSame(['pengguna_biasa'], $audit->old_values['roles']);
        $this->assertSame([$role], $audit->new_values['roles']);
        $this->assertSame('active', $audit->new_values['status']);
        $this->assertSame($this->urusSetia->id, (int) $audit->user_id);
        $this->assertStringNotContainsString('password', json_encode($audit->getAttributes()));

        Auth::logout();
        $this->actingAs($applicant->fresh())->get($allowedUri)->assertStatus($allowedStatus);
        $this->actingAs($applicant->fresh())->get($deniedUri)->assertStatus($deniedStatus);
    }

    // 7–8. superadmin / nilai tidak sah ditolak
    public function test_superadmin_or_invalid_roles_are_rejected_on_server(): void
    {
        $applicant = $this->pendingApplicant();

        foreach (['superadmin', '', 'tidak_wujud', 'admin', 'pentadbir_urus_setia '] as $role) {
            Livewire::actingAs($this->urusSetia)->test(RegistrationRequests::class)
                ->call('showDetail', $applicant->uuid)
                ->set('approvalRole', $role)
                ->call('approve', $applicant->uuid)
                ->assertHasErrors(['approvalRole']);
        }

        $applicant->refresh();
        $this->assertSame(UserStatus::Pending, $applicant->status);
        $this->assertSame(['pengguna_biasa'], $applicant->getRoleNames()->all());
        $this->assertNull($applicant->approved_by);
    }

    public function test_manual_livewire_payload_cannot_grant_superadmin(): void
    {
        $applicant = $this->pendingApplicant();

        $snapshot = $this->snapshot($this->actingAs($this->urusSetia)->get('/urus-setia/pengguna/permohonan')->assertOk()->getContent());

        $this->withHeaders(['X-Livewire' => 'true'])->postJson('/livewire/update', ['components' => [[
            'snapshot' => $snapshot,
            'updates' => ['approvalRole' => 'superadmin'],
            'calls' => [['path' => '', 'method' => 'approve', 'params' => [$applicant->uuid]]],
        ]]])->assertOk();

        $applicant->refresh();
        $this->assertSame(UserStatus::Pending, $applicant->status);
        $this->assertFalse($applicant->hasRole('superadmin'));
    }

    // 9. Tanpa kebenaran Urus Setia
    public function test_users_without_urus_setia_permission_cannot_set_roles(): void
    {
        $applicant = $this->pendingApplicant();

        $actors = [
            $this->userWithRole('pengguna_biasa'),
            $this->userWithRole('pengurusan'),
            $this->userWithRole('superadmin'),
            tap($this->userWithRole('pentadbir_urus_setia'), fn ($u) => $u->forceFill(['status' => UserStatus::Pending])->save()),
            tap($this->userWithRole('pentadbir_urus_setia'), fn ($u) => $u->forceFill(['must_change_password' => true])->save()),
        ];

        foreach ($actors as $actor) {
            Livewire::actingAs($actor->fresh())->test(RegistrationRequests::class)->assertForbidden();
        }

        // Peranan ditarik semasa sesi: tindakan lulus ditolak (Livewire & HTTP persistent middleware).
        $component = Livewire::actingAs($this->urusSetia)->test(RegistrationRequests::class)
            ->call('showDetail', $applicant->uuid)->set('approvalRole', 'pengurusan');
        $snapshot = $this->snapshot($this->actingAs($this->urusSetia)->get('/urus-setia/pengguna/permohonan')->getContent());
        $this->urusSetia->syncRoles(['pengguna_biasa']);

        $component->call('approve', $applicant->uuid)->assertForbidden();
        $this->withHeaders(['X-Livewire' => 'true'])->postJson('/livewire/update', ['components' => [[
            'snapshot' => $snapshot,
            'updates' => ['approvalRole' => 'pengurusan'],
            'calls' => [['path' => '', 'method' => 'approve', 'params' => [$applicant->uuid]]],
        ]]])->assertForbidden();

        $applicant->refresh();
        $this->assertSame(UserStatus::Pending, $applicant->status);
        $this->assertSame(['pengguna_biasa'], $applicant->getRoleNames()->all());
    }

    // 10. Bukan pending tidak boleh diluluskan semula
    public function test_already_processed_applications_cannot_be_approved_again(): void
    {
        $rejected = $this->pendingApplicant();
        $rejected->forceFill(['status' => UserStatus::Rejected])->save();
        $active = $this->userWithRole('pengguna_biasa');

        foreach ([$rejected, $active] as $user) {
            Livewire::actingAs($this->urusSetia)->test(RegistrationRequests::class)
                ->set('approvalRole', 'pentadbir_urus_setia')
                ->call('approve', $user->uuid)
                ->assertSee('telah diproses');

            $this->assertSame(['pengguna_biasa'], $user->fresh()->getRoleNames()->all());
        }

        $this->assertSame(UserStatus::Rejected, $rejected->fresh()->status);
    }

    // 11. Penolakan tidak memberikan peranan/akses
    public function test_rejection_ignores_selected_role_and_grants_no_access(): void
    {
        $applicant = $this->pendingApplicant();

        Livewire::actingAs($this->urusSetia)->test(RegistrationRequests::class)
            ->call('showDetail', $applicant->uuid)
            ->set('approvalRole', 'pentadbir_urus_setia')
            ->call('reject', $applicant->uuid);

        $applicant->refresh();
        $this->assertSame(UserStatus::Rejected, $applicant->status);
        $this->assertSame(['pengguna_biasa'], $applicant->getRoleNames()->all());
        $this->assertNull($applicant->approved_by);

        Auth::logout();
        $this->actingAs($applicant)->get('/urus-setia/pengguna/permohonan')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    // 12. Kegagalan penetapan peranan membatalkan kelulusan
    public function test_role_assignment_failure_rolls_back_approval(): void
    {
        $applicant = $this->pendingApplicant();

        DB::listen(function ($query): void {
            if (str_contains($query->sql, 'insert into "model_has_roles"')) {
                throw new RuntimeException('Simulasi kegagalan penetapan peranan.');
            }
        });

        try {
            Livewire::actingAs($this->urusSetia)->test(RegistrationRequests::class)
                ->call('showDetail', $applicant->uuid)
                ->set('approvalRole', 'pengurusan')
                ->call('approve', $applicant->uuid);
            $this->fail('Exception dijangka.');
        } catch (RuntimeException) {
            // Dijangka.
        }

        $fresh = User::query()->find($applicant->id);
        $this->assertSame('pending', $fresh->getRawOriginal('status'));
        $this->assertNull($fresh->getRawOriginal('approved_by'));
        $this->assertSame(['pengguna_biasa'], $fresh->getRoleNames()->all());
    }

    // 13. Kawalan persaingan & kelulusan kendiri kekal
    public function test_concurrency_and_self_approval_guards_remain(): void
    {
        $applicant = $this->pendingApplicant();
        $otherUrusSetia = $this->userWithRole('pentadbir_urus_setia');

        $first = Livewire::actingAs($this->urusSetia)->test(RegistrationRequests::class)
            ->call('showDetail', $applicant->uuid)->set('approvalRole', 'pengurusan');
        $second = Livewire::actingAs($otherUrusSetia)->test(RegistrationRequests::class)
            ->call('showDetail', $applicant->uuid)->set('approvalRole', 'pentadbir_urus_setia');

        $first->call('approve', $applicant->uuid)->assertHasNoErrors();
        $second->call('approve', $applicant->uuid)->assertSee('telah diproses');

        $this->assertSame(['pengurusan'], $applicant->fresh()->getRoleNames()->all());

        // Kelulusan kendiri: lapisan semakan dalam decide() diuji terasing (ensureUrusSetia dipintas).
        $self = $this->userWithRole('pentadbir_urus_setia');
        $self->forceFill(['status' => UserStatus::Pending])->save();
        $component = new class extends RegistrationRequests
        {
            protected function ensureUrusSetia(): void
            {
            }
        };
        $this->actingAs($self);
        $component->approvalRole = 'pentadbir_urus_setia';
        $component->approve($self->uuid);

        $this->assertTrue($self->fresh()->isPending());
        $this->assertSame(['pentadbir_urus_setia'], $self->fresh()->getRoleNames()->all());
        $this->assertSame('Anda tidak boleh memproses permohonan pendaftaran anda sendiri.', session('error'));
    }

    // --- Helpers ---

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create(['agency_id' => $this->agency->id]);
        $user->assignRole($role);

        return $user;
    }

    private function pendingApplicant(): User
    {
        $user = User::factory()->pending()->create(['agency_id' => $this->agency->id, 'name' => 'Pemohon Kendiri']);
        $user->assignRole('pengguna_biasa');

        return $user;
    }

    private function snapshot(string $html): string
    {
        preg_match_all('/wire:snapshot="([^"]+)"/', $html, $matches);

        foreach ($matches[1] as $encoded) {
            $snapshot = html_entity_decode($encoded, ENT_QUOTES);
            if ((json_decode($snapshot, true)['memo']['name'] ?? null) === 'users.registration-requests') {
                return $snapshot;
            }
        }

        $this->fail('Snapshot RegistrationRequests tidak dijumpai.');
    }
}
