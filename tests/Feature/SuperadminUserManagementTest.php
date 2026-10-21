<?php

namespace Tests\Feature;

use App\Enums\UserStatus;
use App\Livewire\Admin\UserManagement;
use App\Models\Agency;
use App\Models\Grade;
use App\Models\Position;
use App\Models\ServiceScheme;
use App\Models\User;
use Database\Seeders\GradeSeeder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Exceptions\PublicPropertyNotFoundException;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use OwenIt\Auditing\Models\Audit;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Fasa UM5: Pengurusan Pengguna oleh Superadmin.
 */
class SuperadminUserManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $superadmin;

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

        $this->agency = Agency::create(['code' => 'BTM', 'name' => 'Bahagian Teknologi Maklumat', 'category' => 'SUK', 'is_active' => true]);
        $schemeF = ServiceScheme::create(['code' => 'F', 'name' => 'Teknologi Maklumat', 'is_active' => true]);
        $schemeN = ServiceScheme::create(['code' => 'N', 'name' => 'Tadbir dan Sokongan', 'is_active' => true]);

        Position::create(['title' => 'Jawatan Legacy', 'is_active' => false]);
        $this->pegawaiIt = $this->makePosition('Pegawai Teknologi Maklumat', $schemeF, ['9', '10']);
        $this->pembantuTadbir = $this->makePosition('Pembantu Tadbir', $schemeN, ['1']);

        $this->superadmin = User::factory()->create(['name' => 'Pentadbir Sistem']);
        $this->superadmin->assignRole('superadmin');
    }

    // =========================================================
    // Akses
    // =========================================================

    public function test_superadmin_can_open_user_management(): void
    {
        $this->actingAs($this->superadmin)->get(route('admin.users'))
            ->assertOk()
            ->assertSeeLivewire(UserManagement::class);
    }

    public static function unauthorisedRoleProvider(): array
    {
        return [
            'urus setia' => ['pentadbir_urus_setia'],
            'pengurusan' => ['pengurusan'],
            'pengguna biasa' => ['pengguna_biasa'],
            'tanpa peranan' => [null],
        ];
    }

    #[DataProvider('unauthorisedRoleProvider')]
    public function test_unauthorised_users_cannot_open_or_mount_component(?string $role): void
    {
        $user = User::factory()->create();
        if ($role) {
            $user->assignRole($role);
        }

        $this->actingAs($user)->get(route('admin.users'))->assertForbidden();
        Livewire::actingAs($user)->test(UserManagement::class)->assertForbidden();
    }

    public function test_every_action_rechecks_superadmin_role(): void
    {
        $target = User::factory()->create();
        $targetHash = $target->password;

        $creating = $this->fill(Livewire::actingAs($this->superadmin)->test(UserManagement::class)->call('openModal'));
        $resetting = Livewire::actingAs($this->superadmin)->test(UserManagement::class);
        $opening = Livewire::actingAs($this->superadmin)->test(UserManagement::class);

        // Peranan ditarik semasa sesi: semua tindakan seterusnya ditolak di server.
        $this->superadmin->syncRoles(['pentadbir_urus_setia']);

        $creating->call('saveUser')->assertForbidden();
        $resetting->call('resetPassword', $target->uuid)->assertForbidden();
        $opening->call('openModal', $target->uuid)->assertForbidden();

        $this->assertSame(0, User::where('email', 'aminah@ns.gov.my')->count());
        $this->assertSame($targetHash, $target->fresh()->password);
    }

    // =========================================================
    // Pendaftaran & polisi peranan
    // =========================================================

    public static function assignableRoleProvider(): array
    {
        return [
            'urus setia' => ['pentadbir_urus_setia'],
            'superadmin' => ['superadmin'],
        ];
    }

    #[DataProvider('assignableRoleProvider')]
    public function test_superadmin_can_register_whitelisted_roles(string $role): void
    {
        $this->fill(Livewire::actingAs($this->superadmin)->test(UserManagement::class)->call('openModal'), ['role' => $role])
            ->call('saveUser')
            ->assertHasNoErrors();

        $user = User::where('email', 'aminah@ns.gov.my')->firstOrFail();
        $this->assertSame([$role], $user->getRoleNames()->all());
        $this->assertSame(UserStatus::Active, $user->status);
        $this->assertTrue($user->must_change_password);
        $this->assertSame('060606056666', $user->no_kad_pengenalan);
        $this->assertSame($this->pegawaiIt->id, $user->position_id);
        $this->assertSame($this->grade('10')->id, $user->grade_id);
        $this->assertNull($user->approved_by);
    }

    public function test_dropdown_only_offers_superadmin_whitelist_for_new_account(): void
    {
        Livewire::actingAs($this->superadmin)->test(UserManagement::class)
            ->call('openModal')
            ->assertViewHas('roleOptions', ['pentadbir_urus_setia' => 'Urus Setia', 'superadmin' => 'Superadmin']);
    }

    public function test_non_whitelisted_or_unknown_roles_are_rejected_on_create(): void
    {
        foreach (['pengguna_biasa', 'pengurusan', 'tidak_wujud', ''] as $role) {
            $this->fill(Livewire::actingAs($this->superadmin)->test(UserManagement::class)->call('openModal'), ['role' => $role])
                ->call('saveUser')
                ->assertHasErrors(['role'])
                ->assertSet('temporaryPassword', null);
        }

        $this->assertSame(0, User::where('email', 'aminah@ns.gov.my')->count());
    }

    // =========================================================
    // Validasi master data
    // =========================================================

    public function test_master_data_and_identity_validation(): void
    {
        $inactiveAgency = Agency::create(['code' => 'X1', 'name' => 'Agensi Tidak Aktif', 'category' => 'SUK', 'is_active' => false]);
        $inactivePosition = $this->makePosition('Jawatan Tidak Aktif', ServiceScheme::first(), ['9'], active: false);
        $existing = User::factory()->create(['email' => 'Aminah@ns.gov.my', 'no_kad_pengenalan' => '060606056666']);
        $existing->delete();

        $cases = [
            'no_kad_pengenalan' => ['no_kad_pengenalan' => '0606060566'],
            'agency_id' => ['agency_id' => (string) $inactiveAgency->id],
            'position_id' => ['position_id' => (string) $inactivePosition->id, 'grade_id' => (string) $this->grade('9')->id],
            'phone_mobile' => ['phone_mobile' => '012-3456789'],
        ];

        foreach ($cases as $field => $overrides) {
            $this->fill(Livewire::actingAs($this->superadmin)->test(UserManagement::class)->call('openModal'), $overrides + ['email' => 'baru@ns.gov.my', 'no_kad_pengenalan' => $overrides['no_kad_pengenalan'] ?? '070707057777'])
                ->call('saveUser')
                ->assertHasErrors([$field]);
        }

        // E-mel & No. KP milik rekod soft-deleted kekal dianggap digunakan (polisi UM2/UM4).
        $this->fill(Livewire::actingAs($this->superadmin)->test(UserManagement::class)->call('openModal'))
            ->call('saveUser')
            ->assertHasErrors(['email', 'no_kad_pengenalan' => 'unique']);
    }

    public function test_grade_not_linked_to_position_is_rejected(): void
    {
        $this->fill(Livewire::actingAs($this->superadmin)->test(UserManagement::class)->call('openModal'), ['grade_id' => (string) $this->grade('1')->id])
            ->call('saveUser')
            ->assertHasErrors(['grade_id' => 'exists']);
    }

    public function test_service_scheme_is_derived_by_server(): void
    {
        $component = Livewire::actingAs($this->superadmin)->test(UserManagement::class)
            ->call('openModal')
            ->set('position_id', (string) $this->pembantuTadbir->id)
            ->assertSee('N — Tadbir dan Sokongan')
            ->set('position_id', (string) $this->pegawaiIt->id)
            ->assertSee('F — Teknologi Maklumat')
            ->assertSet('grade_id', '');

        // Tiada property skim yang boleh dihantar oleh browser.
        $this->expectException(PublicPropertyNotFoundException::class);
        $component->set('service_scheme_id', 999);
    }

    // =========================================================
    // Kata laluan sementara
    // =========================================================

    public function test_temporary_password_is_generated_hashed_and_shown_once_after_success(): void
    {
        $component = Livewire::actingAs($this->superadmin)->test(UserManagement::class)->call('openModal');
        $component->assertSet('temporaryPassword', null);

        $this->fill($component)->call('saveUser')->assertHasNoErrors();

        $temporary = $component->get('temporaryPassword');
        $this->assertIsString($temporary);
        $this->assertGreaterThanOrEqual(12, strlen($temporary));
        $this->assertNotSame('Password123!', $temporary);
        $component->assertSee($temporary)->assertSet('temporaryPasswordFor', 'Aminah binti Ali');

        $user = User::where('email', 'aminah@ns.gov.my')->firstOrFail();
        $this->assertTrue(Hash::check($temporary, $user->password));
        $this->assertNotSame($temporary, $user->password);
        $this->assertStringNotContainsString($temporary, json_encode(session()->all()));

        $component->call('dismissTemporaryPassword')->assertSet('temporaryPassword', null)->assertDontSee($temporary);
    }

    public function test_sensitive_fields_and_locked_properties_cannot_be_set(): void
    {
        $component = Livewire::actingAs($this->superadmin)->test(UserManagement::class);

        foreach (['password', 'status', 'approved_by', 'approved_at', 'must_change_password', 'role_id', 'userId'] as $property) {
            try {
                $component->set($property, 'x');
                $this->fail("Property [{$property}] tidak sepatutnya wujud.");
            } catch (PublicPropertyNotFoundException) {
                // Dijangka.
            }
        }

        foreach (['userUuid', 'temporaryPassword', 'temporaryPasswordFor'] as $property) {
            try {
                $component->set($property, 'x');
                $this->fail("Property [{$property}] tidak sepatutnya boleh diubah.");
            } catch (CannotUpdateLockedPropertyException) {
                // Dijangka.
            }
        }
    }

    public function test_failed_creation_leaves_no_account_and_no_password(): void
    {
        $component = $this->fill(Livewire::actingAs($this->superadmin)->test(UserManagement::class)->call('openModal'));

        User::created(function (): void {
            throw new RuntimeException('Simulasi kegagalan.');
        });

        try {
            $component->call('saveUser');
            $this->fail('Exception dijangka.');
        } catch (RuntimeException) {
            // Dijangka.
        }

        $this->assertSame(0, User::where('email', 'aminah@ns.gov.my')->count());
        $component->assertSet('temporaryPassword', null);
    }

    public function test_reset_password_generates_new_temporary_password_for_target_only(): void
    {
        $target = User::factory()->create(['name' => 'Sasaran']);
        $target->assignRole('pengguna_biasa');
        $bystander = User::factory()->create();
        $bystanderHash = $bystander->password;
        $oldRememberToken = $target->remember_token;

        $component = Livewire::actingAs($this->superadmin)->test(UserManagement::class)
            ->assertSet('temporaryPassword', null)
            ->call('resetPassword', $target->uuid);

        $temporary = $component->get('temporaryPassword');
        $this->assertIsString($temporary);
        $this->assertNotSame('Password123!', $temporary);
        $component->assertSee($temporary)->assertSet('temporaryPasswordFor', 'Sasaran');

        $target->refresh();
        $this->assertTrue(Hash::check($temporary, $target->password));
        $this->assertTrue($target->must_change_password);
        $this->assertNotSame($oldRememberToken, $target->remember_token);
        $this->assertSame(UserStatus::Active, $target->status);
        $this->assertSame(['pengguna_biasa'], $target->getRoleNames()->all());

        $this->assertSame($bystanderHash, $bystander->fresh()->password);
        $this->assertFalse($bystander->fresh()->must_change_password);
        $this->assertStringNotContainsString($temporary, json_encode(session()->all()));

        // UM6: akaun sasaran wajib menukar kata laluan sebelum mengakses kawasan dalaman.
        $this->actingAs($target)->get('/dashboard')->assertRedirect(route('password.change'));
    }

    public function test_page_load_does_not_reset_any_password(): void
    {
        $target = User::factory()->create();
        $hash = $target->password;

        $this->actingAs($this->superadmin)->get(route('admin.users'))->assertOk();
        Livewire::actingAs($this->superadmin)->test(UserManagement::class)->set('search', $target->email);

        $this->assertSame($hash, $target->fresh()->password);
        $this->assertFalse($target->fresh()->must_change_password);
    }

    public function test_reset_with_unknown_uuid_changes_nothing(): void
    {
        $target = User::factory()->create();
        $hash = $target->password;

        try {
            Livewire::actingAs($this->superadmin)->test(UserManagement::class)->call('resetPassword', (string) $target->id);
            $this->fail('ModelNotFoundException dijangka.');
        } catch (ModelNotFoundException) {
            // Dijangka: ID integer / UUID palsu tidak dipadankan.
        }

        $this->assertSame($hash, $target->fresh()->password);
    }

    public function test_passwords_are_not_recorded_in_audit_or_log(): void
    {
        config(['audit.console' => true]);
        $logPath = storage_path('logs/laravel.log');
        $logBefore = is_file($logPath) ? (string) file_get_contents($logPath) : '';

        $component = $this->fill(Livewire::actingAs($this->superadmin)->test(UserManagement::class)->call('openModal'))->call('saveUser');
        $created = $component->get('temporaryPassword');
        $user = User::where('email', 'aminah@ns.gov.my')->firstOrFail();

        $reset = Livewire::actingAs($this->superadmin)->test(UserManagement::class)->call('resetPassword', $user->uuid)->get('temporaryPassword');

        $audits = Audit::where('auditable_type', User::class)->where('auditable_id', $user->id)->get();
        $this->assertGreaterThanOrEqual(2, $audits->count());

        foreach ($audits as $audit) {
            $raw = json_encode($audit->getAttributes());
            $this->assertArrayNotHasKey('password', $audit->new_values ?? []);
            $this->assertStringNotContainsString($created, $raw);
            $this->assertStringNotContainsString($reset, $raw);
        }

        $logAfter = is_file($logPath) ? (string) file_get_contents($logPath) : '';
        $newLog = substr($logAfter, strlen($logBefore));
        $this->assertStringNotContainsString($created, $newLog);
        $this->assertStringNotContainsString($reset, $newLog);
    }

    // =========================================================
    // Kemas kini akaun sedia ada
    // =========================================================

    public function test_editing_keeps_existing_role_when_unchanged(): void
    {
        $pengurusan = $this->profiledUser('pengurusan', ['name' => 'Pegawai Pengurusan']);
        $hash = $pengurusan->password;

        // UM10: kemas kini pengguna sedia ada menawarkan keempat-empat peranan.
        Livewire::actingAs($this->superadmin)->test(UserManagement::class)
            ->call('openModal', $pengurusan->uuid)
            ->assertSet('role', 'pengurusan')
            ->assertViewHas('roleOptions', fn ($options) => array_keys($options) === ['superadmin', 'pentadbir_urus_setia', 'pengurusan', 'pengguna_biasa'])
            ->set('name', 'Pegawai Pengurusan Baharu')
            ->call('saveUser')
            ->assertHasNoErrors();

        $pengurusan->refresh();
        $this->assertSame('Pegawai Pengurusan Baharu', $pengurusan->name);
        $this->assertSame(['pengurusan'], $pengurusan->getRoleNames()->all());
        $this->assertSame($hash, $pengurusan->password);
        $this->assertFalse($pengurusan->must_change_password);
    }

    public function test_editing_rejects_unknown_roles(): void
    {
        // UM10: Superadmin boleh menetapkan mana-mana 4 peranan semasa kemas kini (diuji dalam
        // UserRoleManagementTest); nilai di luar senarai peranan sistem tetap ditolak.
        $urusSetia = $this->profiledUser('pentadbir_urus_setia');

        foreach (['tidak_wujud', 'admin', ''] as $role) {
            Livewire::actingAs($this->superadmin)->test(UserManagement::class)
                ->call('openModal', $urusSetia->uuid)
                ->set('role', $role)
                ->call('saveUser')
                ->assertHasErrors(['role']);
        }

        $this->assertSame(['pentadbir_urus_setia'], $urusSetia->fresh()->getRoleNames()->all());
    }

    public function test_editing_can_change_role_within_whitelist_and_ignores_own_unique_values(): void
    {
        $pemohon = $this->profiledUser('pengguna_biasa');

        Livewire::actingAs($this->superadmin)->test(UserManagement::class)
            ->call('openModal', $pemohon->uuid)
            ->set('role', 'pentadbir_urus_setia')
            ->call('saveUser')
            ->assertHasNoErrors();

        $this->assertSame(['pentadbir_urus_setia'], $pemohon->fresh()->getRoleNames()->all());
    }

    public function test_edit_target_cannot_be_switched_by_payload(): void
    {
        $first = $this->profiledUser('pentadbir_urus_setia');
        $second = $this->profiledUser('pentadbir_urus_setia', ['email' => 'kedua@ns.gov.my', 'no_kad_pengenalan' => '880808058888']);

        $this->expectException(CannotUpdateLockedPropertyException::class);

        Livewire::actingAs($this->superadmin)->test(UserManagement::class)
            ->call('openModal', $first->uuid)
            ->set('userUuid', $second->uuid);
    }

    // =========================================================
    // Carian & paparan
    // =========================================================

    public function test_search_does_not_bypass_role_filter(): void
    {
        $pemohon = User::factory()->create(['name' => 'Ali Pemohon', 'email' => 'ali.pemohon@ns.gov.my']);
        $pemohon->assignRole('pengguna_biasa');
        $admin = User::factory()->create(['name' => 'Ali Admin', 'email' => 'ali.admin@ns.gov.my', 'no_kad_pengenalan' => '770707051234']);
        $admin->assignRole('superadmin');

        Livewire::actingAs($this->superadmin)->test(UserManagement::class)
            ->set('roleFilter', 'superadmin')
            ->set('search', 'ali')
            ->assertSee('Ali Admin')
            ->assertDontSee('Ali Pemohon')
            ->set('search', 'ali.pemohon')
            ->assertDontSee('Ali Pemohon')
            ->set('roleFilter', '')
            ->set('search', '7707070512')
            ->assertSee('Ali Admin')
            ->assertDontSee('Ali Pemohon');
    }

    public function test_actual_roles_including_pengurusan_are_displayed(): void
    {
        $this->profiledUser('pengurusan', ['name' => 'Pegawai Pengurusan']);

        Livewire::actingAs($this->superadmin)->test(UserManagement::class)
            ->set('roleFilter', 'pengurusan')
            ->assertSee('Pegawai Pengurusan')
            ->assertSeeHtml('>Pengurusan</span>')
            ->assertDontSeeHtml('>Pemohon Agensi</span>');
    }

    // --- Helpers ---

    private function fill($component, array $overrides = [])
    {
        $data = array_merge([
            'no_kad_pengenalan' => '060606056666',
            'name' => 'Aminah binti Ali',
            'agency_id' => (string) $this->agency->id,
            'position_id' => (string) $this->pegawaiIt->id,
            'grade_id' => (string) $this->grade('10')->id,
            'phone_office' => '067659999',
            'phone_mobile' => '0123456789',
            'email' => 'aminah@ns.gov.my',
            'role' => 'pentadbir_urus_setia',
        ], $overrides);

        $component->set('position_id', $data['position_id']);
        unset($data['position_id']);

        foreach ($data as $field => $value) {
            $component->set($field, $value);
        }

        return $component;
    }

    private function profiledUser(string $role, array $attributes = []): User
    {
        $user = User::factory()->create(array_merge([
            'no_kad_pengenalan' => '990909059999',
            'agency_id' => $this->agency->id,
            'position_id' => $this->pegawaiIt->id,
            'grade_id' => $this->grade('9')->id,
            'phone_office' => '067650000',
            'phone_mobile' => '0130000000',
        ], $attributes));
        $user->assignRole($role);

        return $user;
    }

    private function grade(string $code): Grade
    {
        return Grade::where('code', $code)->firstOrFail();
    }

    private function makePosition(string $title, ServiceScheme $scheme, array $gradeCodes, bool $active = true): Position
    {
        $position = Position::create(['title' => $title, 'service_scheme_id' => $scheme->id, 'is_active' => $active]);
        $position->grades()->sync(Grade::whereIn('code', $gradeCodes)->pluck('id'));

        return $position;
    }
}
