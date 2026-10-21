<?php

namespace Tests\Feature;

use App\Enums\UserStatus;
use App\Livewire\Users\RegisterUser;
use App\Livewire\Users\RegistrationRequests;
use App\Models\Agency;
use App\Models\Grade;
use App\Models\Meeting;
use App\Models\Position;
use App\Models\ServiceScheme;
use App\Models\User;
use Database\Seeders\GradeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Exceptions\PublicPropertyNotFoundException;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Livewire\Volt\Volt;
use OwenIt\Auditing\Models\Audit;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Fasa UM4: pendaftaran pengguna oleh Urus Setia serta kelulusan/penolakan Self Register.
 */
class UrusSetiaUserManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $urusSetia;

    private Agency $agency;

    private Position $pegawaiIt;

    private Position $pembantuTadbir;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['pengguna_biasa', 'pentadbir_urus_setia', 'pengurusan', 'superadmin'] as $role) {
            Role::create(['name' => $role]);
        }

        $this->seed(GradeSeeder::class);

        $this->agency = Agency::create(['code' => 'JKRNS', 'name' => 'Jabatan Kerja Raya Negeri Sembilan', 'category' => 'STATE_DEPT', 'is_active' => true]);
        $schemeF = ServiceScheme::create(['code' => 'F', 'name' => 'Teknologi Maklumat', 'is_active' => true]);
        $schemeN = ServiceScheme::create(['code' => 'N', 'name' => 'Tadbir dan Sokongan', 'is_active' => true]);

        Position::create(['title' => 'Jawatan Legacy', 'is_active' => false]);
        $this->pegawaiIt = $this->makePosition('Pegawai Teknologi Maklumat', $schemeF, ['9', '10']);
        $this->pembantuTadbir = $this->makePosition('Pembantu Tadbir', $schemeN, ['1']);

        $this->urusSetia = User::factory()->create(['name' => 'Urus Setia MKK']);
        $this->urusSetia->assignRole('pentadbir_urus_setia');
    }

    // =========================================================
    // A. Pendaftaran oleh Urus Setia
    // =========================================================

    public function test_urus_setia_can_open_registration_and_requests_pages(): void
    {
        $this->actingAs($this->urusSetia)->get(route('users.create'))
            ->assertOk()
            ->assertSee('Daftar Pengguna')
            ->assertSee('Tahap Capaian')
            ->assertDontSee('value="superadmin"', false);

        $this->actingAs($this->urusSetia)->get(route('users.requests'))->assertOk();
    }

    public static function unauthorisedRoleProvider(): array
    {
        return [
            'pengguna biasa' => ['pengguna_biasa'],
            'pengurusan' => ['pengurusan'],
            'superadmin' => ['superadmin'],
            'tanpa peranan' => [null],
        ];
    }

    #[DataProvider('unauthorisedRoleProvider')]
    public function test_unauthorised_users_cannot_open_pages_or_call_actions(?string $role): void
    {
        $user = User::factory()->create();
        if ($role) {
            $user->assignRole($role);
        }
        $pending = $this->makePendingApplicant();

        $this->actingAs($user)->get(route('users.create'))->assertForbidden();
        $this->actingAs($user)->get(route('users.requests'))->assertForbidden();

        Livewire::actingAs($user)->test(RegisterUser::class)->assertForbidden();
        Livewire::actingAs($user)->test(RegistrationRequests::class)->assertForbidden();

        $this->assertTrue($pending->fresh()->isPending());
    }

    public function test_actions_are_rechecked_when_role_is_revoked_mid_session(): void
    {
        $pending = $this->makePendingApplicant();

        $register = Livewire::actingAs($this->urusSetia)->test(RegisterUser::class);
        $requests = Livewire::actingAs($this->urusSetia)->test(RegistrationRequests::class);

        $this->urusSetia->syncRoles(['pengguna_biasa']);

        $this->fill($register)->call('save')->assertForbidden();
        $requests->call('approve', $pending->uuid)->assertForbidden();

        $this->assertSame(0, User::where('email', 'siti@ns.gov.my')->count());
        $this->assertTrue($pending->fresh()->isPending());
    }

    #[DataProvider('allowedRoleProvider')]
    public function test_urus_setia_can_register_whitelisted_roles(string $role): void
    {
        $this->fill(Livewire::actingAs($this->urusSetia)->test(RegisterUser::class), ['role' => $role])
            ->call('save')
            ->assertHasNoErrors();

        $user = User::where('email', 'siti@ns.gov.my')->firstOrFail();
        $this->assertSame([$role], $user->getRoleNames()->all());
    }

    public static function allowedRoleProvider(): array
    {
        return [
            'pengguna biasa' => ['pengguna_biasa'],
            'urus setia' => ['pentadbir_urus_setia'],
            'pengurusan' => ['pengurusan'],
        ];
    }

    public function test_superadmin_or_unknown_role_is_rejected_even_if_sent_manually(): void
    {
        foreach (['superadmin', 'tidak_wujud', ''] as $role) {
            $this->fill(Livewire::actingAs($this->urusSetia)->test(RegisterUser::class), ['role' => $role])
                ->call('save')
                ->assertHasErrors(['role']);
        }

        $this->assertSame(0, User::where('email', 'siti@ns.gov.my')->count());
        $this->assertSame(0, User::whereHas('roles', fn ($query) => $query->where('name', 'superadmin'))->count());
    }

    public function test_grade_not_linked_to_position_is_rejected(): void
    {
        $this->fill(Livewire::actingAs($this->urusSetia)->test(RegisterUser::class), ['grade_id' => (string) $this->grade('1')->id])
            ->call('save')
            ->assertHasErrors(['grade_id' => 'exists']);
    }

    public function test_duplicate_email_and_ic_are_rejected_including_soft_deleted(): void
    {
        $existing = User::factory()->create(['email' => 'Siti@ns.gov.my', 'no_kad_pengenalan' => '050303051111']);
        $existing->delete();

        $this->fill(Livewire::actingAs($this->urusSetia)->test(RegisterUser::class))
            ->call('save')
            ->assertHasErrors(['email', 'no_kad_pengenalan' => 'unique']);
    }

    public function test_sensitive_fields_cannot_be_set_through_input(): void
    {
        $component = $this->fill(Livewire::actingAs($this->urusSetia)->test(RegisterUser::class));

        foreach (['status' => 'pending', 'approved_by' => 1, 'approved_at' => now(), 'must_change_password' => false, 'password' => 'Pilihan#123', 'role_id' => 4] as $property => $value) {
            try {
                $component->set($property, $value);
                $this->fail("Property [{$property}] tidak sepatutnya boleh ditetapkan.");
            } catch (PublicPropertyNotFoundException) {
                // Dijangka.
            }
        }

        try {
            $component->set('temporaryPassword', 'Pilihan#123');
            $this->fail('temporaryPassword tidak sepatutnya boleh ditetapkan.');
        } catch (CannotUpdateLockedPropertyException) {
            // Dijangka: property dikunci.
        }

        $component->call('save')->assertHasNoErrors();

        $user = User::where('email', 'siti@ns.gov.my')->firstOrFail();
        $this->assertSame(UserStatus::Active, $user->status);
        $this->assertTrue($user->must_change_password);
        $this->assertNull($user->approved_by);
        $this->assertFalse(Hash::check('Pilihan#123', $user->password));
    }

    public function test_created_account_uses_generated_temporary_password_and_must_change_it(): void
    {
        $component = $this->fill(Livewire::actingAs($this->urusSetia)->test(RegisterUser::class))->call('save');

        $temporaryPassword = $component->get('temporaryPassword');
        $this->assertIsString($temporaryPassword);
        $this->assertGreaterThanOrEqual(12, strlen($temporaryPassword));
        $this->assertNotSame('Password123!', $temporaryPassword);
        $component->assertSee($temporaryPassword);

        $user = User::where('email', 'siti@ns.gov.my')->firstOrFail();
        $this->assertTrue(Hash::check($temporaryPassword, $user->password));
        $this->assertTrue($user->must_change_password);
        $this->assertSame(UserStatus::Active, $user->status);
        $this->assertSame('050303051111', $user->no_kad_pengenalan);
        $this->assertSame($this->grade('10')->id, $user->grade_id);

        // Tidak disimpan dalam session; hilang selepas ditutup.
        $this->assertStringNotContainsString($temporaryPassword, json_encode(session()->all()));
        $component->call('dismissTemporaryPassword')->assertSet('temporaryPassword', null)->assertDontSee($temporaryPassword);

        // Setiap pendaftaran menjana kata laluan berbeza.
        $second = $this->fill(Livewire::actingAs($this->urusSetia)->test(RegisterUser::class), [
            'email' => 'ahmad@ns.gov.my',
            'no_kad_pengenalan' => '900101051234',
        ])->call('save');
        $this->assertNotSame($temporaryPassword, $second->get('temporaryPassword'));
    }

    public function test_password_is_not_recorded_in_audit_log(): void
    {
        config(['audit.console' => true]);

        $component = $this->fill(Livewire::actingAs($this->urusSetia)->test(RegisterUser::class))->call('save');
        $temporaryPassword = $component->get('temporaryPassword');
        $user = User::where('email', 'siti@ns.gov.my')->firstOrFail();

        $audits = Audit::where('auditable_type', User::class)->where('auditable_id', $user->id)->get();
        $this->assertNotEmpty($audits);

        foreach ($audits as $audit) {
            $this->assertArrayNotHasKey('password', $audit->new_values ?? []);
            $this->assertStringNotContainsString($temporaryPassword, json_encode($audit->getAttributes()));
            $this->assertStringNotContainsString($user->password, json_encode($audit->getAttributes()));
        }
    }

    public function test_failed_registration_leaves_no_partial_account(): void
    {
        User::created(function (): void {
            throw new RuntimeException('Simulasi kegagalan.');
        });

        try {
            $this->fill(Livewire::actingAs($this->urusSetia)->test(RegisterUser::class))->call('save');
            $this->fail('Exception dijangka.');
        } catch (RuntimeException) {
            // Dijangka.
        }

        $this->assertSame(0, User::where('email', 'siti@ns.gov.my')->count());
    }

    // =========================================================
    // B. Kelulusan / penolakan Self Register
    // =========================================================

    public function test_urus_setia_sees_pending_requests_with_masked_ic(): void
    {
        $pending = $this->makePendingApplicant(['name' => 'Pemohon Pending', 'no_kad_pengenalan' => '050101051234']);
        $active = User::factory()->create(['name' => 'Pengguna Aktif']);
        $rejected = $this->makePendingApplicant(['name' => 'Pemohon Ditolak']);
        $rejected->forceFill(['status' => UserStatus::Rejected])->save();

        Livewire::actingAs($this->urusSetia)->test(RegistrationRequests::class)
            ->assertSee('Pemohon Pending')
            ->assertSee('050101****34')
            ->assertDontSee('050101051234')
            ->assertDontSee('Pengguna Aktif')
            ->assertDontSee('Pemohon Ditolak')
            ->call('showDetail', $pending->uuid)
            ->assertSee('050101051234')
            ->assertSee('Pegawai Teknologi Maklumat')
            ->assertSee('F — Teknologi Maklumat')
            ->set('statusFilter', 'rejected')
            ->assertSee('Pemohon Ditolak');
    }

    public function test_active_users_cannot_be_opened_as_requests(): void
    {
        $active = User::factory()->create();

        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        Livewire::actingAs($this->urusSetia)->test(RegistrationRequests::class)->call('showDetail', $active->uuid);
    }

    public function test_approval_activates_account_and_records_approver(): void
    {
        config(['audit.console' => true]);
        $pending = $this->makePendingApplicant();

        $this->travelTo(now()->setTime(10, 30));

        Livewire::actingAs($this->urusSetia)->test(RegistrationRequests::class)
            ->call('approve', $pending->uuid)
            ->assertHasNoErrors();

        $pending->refresh();
        $this->assertSame(UserStatus::Active, $pending->status);
        $this->assertSame($this->urusSetia->id, $pending->approved_by);
        $this->assertEquals(now()->startOfSecond(), $pending->approved_at);
        $this->assertSame(['pengguna_biasa'], $pending->getRoleNames()->all());

        $audit = Audit::where('auditable_type', User::class)->where('auditable_id', $pending->id)->where('event', 'updated')->latest('id')->first();
        $this->assertSame('pending', $audit->old_values['status']);
        $this->assertSame('active', $audit->new_values['status']);
        $this->assertSame($this->urusSetia->id, (int) $audit->user_id);

        // Selepas diluluskan, pengguna boleh log masuk.
        Volt::test('pages.auth.login')
            ->set('form.email', $pending->email)
            ->set('form.password', 'password')
            ->call('login')
            ->assertHasNoErrors();
        $this->assertAuthenticatedAs($pending);
    }

    public function test_rejection_keeps_record_without_approval_fields(): void
    {
        $pending = $this->makePendingApplicant();

        Livewire::actingAs($this->urusSetia)->test(RegistrationRequests::class)->call('reject', $pending->uuid);

        $rejected = User::withTrashed()->find($pending->id);
        $this->assertNotNull($rejected);
        $this->assertFalse($rejected->trashed());
        $this->assertSame(UserStatus::Rejected, $rejected->status);
        $this->assertNull($rejected->approved_by);
        $this->assertNull($rejected->approved_at);
    }

    public function test_requests_that_are_no_longer_pending_cannot_be_processed_again(): void
    {
        $pending = $this->makePendingApplicant();
        $otherUrusSetia = User::factory()->create();
        $otherUrusSetia->assignRole('pentadbir_urus_setia');

        Livewire::actingAs($otherUrusSetia)->test(RegistrationRequests::class)->call('reject', $pending->uuid);

        Livewire::actingAs($this->urusSetia)->test(RegistrationRequests::class)
            ->call('approve', $pending->uuid)
            ->assertSee('telah diproses');

        $pending->refresh();
        $this->assertSame(UserStatus::Rejected, $pending->status);
        $this->assertNull($pending->approved_by);

        // Akaun aktif sedia ada juga tidak boleh "ditolak".
        $active = User::factory()->create();
        Livewire::actingAs($this->urusSetia)->test(RegistrationRequests::class)->call('reject', $active->uuid);
        $this->assertSame(UserStatus::Active, $active->fresh()->status);
    }

    public function test_user_cannot_approve_own_registration(): void
    {
        // Senario: Urus Setia yang akaunnya sendiri masih berstatus pending (contoh: data tidak konsisten).
        $self = User::factory()->create();
        $self->assignRole('pentadbir_urus_setia');
        $self->forceFill(['status' => UserStatus::Pending])->save();

        // ensureUrusSetia menolak akaun tidak aktif lebih awal; sahkan juga lapisan semakan kendiri.
        Livewire::actingAs($self)->test(RegistrationRequests::class)->assertForbidden();

        $component = new class extends RegistrationRequests
        {
            protected function ensureUrusSetia(): void
            {
                // Pintas semakan peranan untuk menguji sekatan kelulusan kendiri secara terasing.
            }
        };
        $this->actingAs($self);
        $component->approve($self->uuid);

        $this->assertTrue($self->fresh()->isPending());
        $this->assertNull($self->fresh()->approved_by);
        $this->assertSame('Anda tidak boleh memproses permohonan pendaftaran anda sendiri.', session('error'));
    }

    public function test_invalid_or_unknown_uuid_cannot_be_processed(): void
    {
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

        Livewire::actingAs($this->urusSetia)->test(RegistrationRequests::class)->call('approve', 'bukan-uuid-sah');
    }

    public function test_viewing_uuid_is_locked_against_manipulation(): void
    {
        $this->expectException(CannotUpdateLockedPropertyException::class);

        Livewire::actingAs($this->urusSetia)->test(RegistrationRequests::class)->set('viewingUuid', $this->urusSetia->uuid);
    }

    public function test_failed_approval_leaves_no_partial_update(): void
    {
        $pending = $this->makePendingApplicant();

        User::updated(function (): void {
            throw new RuntimeException('Simulasi kegagalan.');
        });

        try {
            Livewire::actingAs($this->urusSetia)->test(RegistrationRequests::class)->call('approve', $pending->uuid);
            $this->fail('Exception dijangka.');
        } catch (RuntimeException) {
            // Dijangka.
        }

        $fresh = User::query()->find($pending->id);
        $this->assertSame('pending', $fresh->getRawOriginal('status'));
        $this->assertNull($fresh->getRawOriginal('approved_by'));
    }

    // =========================================================
    // Kesan status kepada modul lain & regresi UM2
    // =========================================================

    public function test_pending_and_rejected_users_are_excluded_from_meeting_recipients_and_officers(): void
    {
        $active = User::factory()->create(['agency_id' => $this->agency->id]);
        $active->assignRole('pengguna_biasa');
        $pending = $this->makePendingApplicant();
        $rejected = $this->makePendingApplicant(['email' => 'tolak@ns.gov.my', 'no_kad_pengenalan' => '770707057777']);
        $rejected->forceFill(['status' => UserStatus::Rejected])->save();

        $recipients = Meeting::notificationRecipients()->pluck('id');
        $this->assertTrue($recipients->contains($active->id));
        $this->assertFalse($recipients->contains($pending->id));
        $this->assertFalse($recipients->contains($rejected->id));

        $officers = User::eligibleOfficersFor($this->agency->id)->pluck('id');
        $this->assertTrue($officers->contains($active->id));
        $this->assertFalse($officers->contains($pending->id));
        $this->assertFalse($officers->contains($rejected->id));
    }

    public function test_self_register_still_creates_pending_account(): void
    {
        Volt::test('pages.auth.register')
            ->set('position_id', (string) $this->pegawaiIt->id)
            ->set('no_kad_pengenalan', '010101051010')
            ->set('name', 'Pemohon Kendiri')
            ->set('agency_id', (string) $this->agency->id)
            ->set('grade_id', (string) $this->grade('9')->id)
            ->set('phone_office', '067659999')
            ->set('phone_mobile', '0123456789')
            ->set('email', 'kendiri@ns.gov.my')
            ->set('password', 'KataLaluan#2026')
            ->set('password_confirmation', 'KataLaluan#2026')
            ->call('register')
            ->assertHasNoErrors()
            ->assertRedirect(route('register.pending', absolute: false));

        $user = User::where('email', 'kendiri@ns.gov.my')->firstOrFail();
        $this->assertSame(UserStatus::Pending, $user->status);
        $this->assertFalse($user->must_change_password);
        $this->assertSame(['pengguna_biasa'], $user->getRoleNames()->all());
    }

    // --- Helpers ---

    private function fill($component, array $overrides = [])
    {
        $data = array_merge([
            'no_kad_pengenalan' => '050303051111',
            'name' => 'Siti binti Hassan',
            'agency_id' => (string) $this->agency->id,
            'position_id' => (string) $this->pegawaiIt->id,
            'grade_id' => (string) $this->grade('10')->id,
            'phone_office' => '067651234',
            'phone_mobile' => '0198765432',
            'email' => 'siti@ns.gov.my',
            'role' => 'pengguna_biasa',
        ], $overrides);

        $component->set('position_id', $data['position_id']);
        unset($data['position_id']);

        foreach ($data as $field => $value) {
            $component->set($field, $value);
        }

        return $component;
    }

    private function makePendingApplicant(array $attributes = []): User
    {
        $user = User::factory()->pending()->create(array_merge([
            'no_kad_pengenalan' => '990909059999',
            'agency_id' => $this->agency->id,
            'position_id' => $this->pegawaiIt->id,
            'grade_id' => $this->grade('9')->id,
            'phone_office' => '067650000',
            'phone_mobile' => '0130000000',
        ], $attributes));
        $user->assignRole('pengguna_biasa');

        return $user;
    }

    private function grade(string $code): Grade
    {
        return Grade::where('code', $code)->firstOrFail();
    }

    private function makePosition(string $title, ServiceScheme $scheme, array $gradeCodes): Position
    {
        $position = Position::create(['title' => $title, 'service_scheme_id' => $scheme->id, 'is_active' => true]);
        $position->grades()->sync(Grade::whereIn('code', $gradeCodes)->pluck('id'));

        return $position;
    }
}
