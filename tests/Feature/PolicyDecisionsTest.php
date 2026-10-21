<?php

namespace Tests\Feature;

use App\Enums\UserStatus;
use App\Livewire\Admin\UserManagement;
use App\Livewire\ApplicationForm;
use App\Livewire\Users\RegisterUser;
use App\Models\Agency;
use App\Models\Application;
use App\Models\Grade;
use App\Models\Position;
use App\Models\ServiceScheme;
use App\Models\User;
use App\Support\TemporaryPassword;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoAccountsSeeder;
use Database\Seeders\GradeSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password as PasswordBroker;
use Livewire\Livewire;
use Livewire\Volt\Volt;
use Spatie\Permission\Models\Role;
use Tests\Concerns\OpensJtictMeeting;
use Tests\Concerns\UsesApplicationMasterData;
use Tests\TestCase;

/**
 * Fasa UM7A: pelaksanaan keputusan dasar Modul Pengurusan Pengguna (3.1–3.7).
 */
class PolicyDecisionsTest extends TestCase
{
    use RefreshDatabase, OpensJtictMeeting, UsesApplicationMasterData;

    private const STRONG = 'KataLaluanKuat#2026';

    private Agency $agency;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['pengguna_biasa', 'pentadbir_urus_setia', 'pengurusan', 'superadmin'] as $role) {
            Role::create(['name' => $role]);
        }

        $this->agency = Agency::create(['code' => 'JKR', 'name' => 'Jabatan Kerja Raya', 'category' => 'STATE_DEPT', 'is_active' => true]);
    }

    // =========================================================
    // 3.1 Polisi kata laluan
    // =========================================================

    public function test_generated_temporary_passwords_always_satisfy_policy(): void
    {
        for ($i = 0; $i < 50; $i++) {
            $this->assertTrue(TemporaryPassword::satisfiesPolicy(TemporaryPassword::generate(12)));
        }
    }

    public function test_urus_setia_and_superadmin_temporary_passwords_satisfy_policy(): void
    {
        $this->seed(GradeSeeder::class);
        $scheme = ServiceScheme::create(['code' => 'F', 'name' => 'Teknologi Maklumat', 'is_active' => true]);
        Position::create(['title' => 'Jawatan Legacy', 'is_active' => false]);
        $position = Position::create(['title' => 'Pegawai Teknologi Maklumat', 'service_scheme_id' => $scheme->id, 'is_active' => true]);
        $grade = Grade::where('code', '9')->first();
        $position->grades()->sync([$grade->id]);

        $fill = fn ($component, string $email, string $ic, string $role) => $component
            ->set('position_id', (string) $position->id)
            ->set('no_kad_pengenalan', $ic)->set('name', 'Pegawai Baharu')
            ->set('agency_id', (string) $this->agency->id)->set('grade_id', (string) $grade->id)
            ->set('phone_office', '067659999')->set('phone_mobile', '0123456789')
            ->set('email', $email)->set('role', $role);

        $urusSetia = $this->userWithRole('pentadbir_urus_setia');
        $byUrusSetia = $fill(Livewire::actingAs($urusSetia)->test(RegisterUser::class), 'a@ns.gov.my', '010101010101', 'pengguna_biasa')->call('save');
        $this->assertTrue(TemporaryPassword::satisfiesPolicy($byUrusSetia->get('temporaryPassword')));

        $superadmin = $this->userWithRole('superadmin');
        $bySuperadmin = $fill(Livewire::actingAs($superadmin)->test(UserManagement::class)->call('openModal'), 'b@ns.gov.my', '020202020202', 'pentadbir_urus_setia')->call('saveUser');
        $this->assertTrue(TemporaryPassword::satisfiesPolicy($bySuperadmin->get('temporaryPassword')));

        $reset = Livewire::actingAs($superadmin)->test(UserManagement::class)->call('resetPassword', $urusSetia->uuid);
        $this->assertTrue(TemporaryPassword::satisfiesPolicy($reset->get('temporaryPassword')));
    }

    public function test_profile_password_change_rejects_current_password_and_weak_passwords_with_malay_messages(): void
    {
        $user = $this->userWithRole('pengguna_biasa', ['password' => Hash::make(self::STRONG)]);

        $same = Volt::actingAs($user)->test('profile.update-password-form')
            ->set('current_password', self::STRONG)->set('password', self::STRONG)->set('password_confirmation', self::STRONG)
            ->call('updatePassword')
            ->assertHasErrors(['password' => 'different']);
        $this->assertStringContainsString('berbeza daripada kata laluan semasa', $same->errors()->first('password'));

        $weak = Volt::actingAs($user)->test('profile.update-password-form')
            ->set('current_password', self::STRONG)->set('password', 'tiadahurufbesar#2026')->set('password_confirmation', 'tiadahurufbesar#2026')
            ->call('updatePassword')
            ->assertHasErrors(['password']);
        $this->assertStringContainsString('huruf besar dan huruf kecil', $weak->errors()->first('password'));

        $this->assertTrue(Hash::check(self::STRONG, $user->fresh()->password));
    }

    public function test_password_reset_shows_malay_policy_message(): void
    {
        $user = User::factory()->create();

        $component = Volt::test('pages.auth.reset-password', ['token' => PasswordBroker::createToken($user)])
            ->set('email', $user->email)->set('password', 'TiadaSimbolLagi2026')->set('password_confirmation', 'TiadaSimbolLagi2026')
            ->call('resetPassword')
            ->assertHasErrors(['password']);

        $this->assertStringContainsString('sekurang-kurangnya satu simbol', $component->errors()->first('password'));
        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }

    // =========================================================
    // 3.2 Kebenaran mendaftar permohonan projek
    // =========================================================

    public function test_create_policy_matrix(): void
    {
        $expected = ['pengguna_biasa' => true, 'pentadbir_urus_setia' => true, 'pengurusan' => false, 'superadmin' => false];

        foreach ($expected as $role => $allowed) {
            $user = $this->userWithRole($role);
            $this->assertSame($allowed, Gate::forUser($user)->allows('create', Application::class), "Peranan {$role}");
        }

        $this->assertFalse(Gate::forUser(User::factory()->create())->allows('create', Application::class), 'Tanpa peranan');

        $pending = $this->userWithRole('pengguna_biasa');
        $pending->forceFill(['status' => UserStatus::Pending])->save();
        $this->assertFalse(Gate::forUser($pending)->allows('create', Application::class), 'Pending');

        // Pemegang pelbagai peranan: pengurusan/superadmin sentiasa dikecualikan.
        $mixed = $this->userWithRole('pengguna_biasa');
        $mixed->assignRole('pengurusan');
        $this->assertFalse(Gate::forUser($mixed)->allows('create', Application::class), 'Pelbagai peranan');
    }

    public function test_create_routes_follow_policy(): void
    {
        foreach (['pengguna_biasa' => 200, 'pentadbir_urus_setia' => 200, 'pengurusan' => 403, 'superadmin' => 403] as $role => $status) {
            $user = $this->userWithRole($role);

            $this->actingAs($user)->get('/application/new')->assertStatus($status);
            $this->actingAs($user)->get('/permohonan/baru')->assertStatus($status);
        }
    }

    public function test_navigation_shows_create_link_only_for_permitted_roles(): void
    {
        $this->actingAs($this->userWithRole('pentadbir_urus_setia'))->get('/dashboard')->assertSee('Permohonan Baru');
        $this->actingAs($this->userWithRole('pengguna_biasa'))->get('/dashboard')->assertSee('Permohonan Baru');
        $this->actingAs($this->userWithRole('pengurusan'))->get('/dashboard')->assertDontSee('Permohonan Baru');
    }

    public function test_unauthorised_roles_cannot_create_through_livewire(): void
    {
        foreach (['pengurusan', 'superadmin'] as $role) {
            Livewire::actingAs($this->userWithRole($role))->test(ApplicationForm::class)->assertForbidden();
        }

        // Peranan berubah selepas borang dibuka: tindakan simpan disemak semula.
        $user = $this->userWithRole('pengguna_biasa');
        $component = Livewire::actingAs($user)->test(ApplicationForm::class)->set('form.title', 'Projek Ujian');
        $user->syncRoles(['pengurusan']);

        $component->call('saveDraft')->assertForbidden();
        $this->assertSame(0, Application::count());
    }

    public function test_urus_setia_can_save_a_draft_application(): void
    {
        $urusSetia = $this->userWithRole('pentadbir_urus_setia');

        Livewire::actingAs($urusSetia)->test(ApplicationForm::class)
            ->set('form.title', 'Projek Urus Setia')
            ->call('saveDraft')
            ->assertHasNoErrors();

        $application = Application::firstOrFail();
        $this->assertSame($urusSetia->id, $application->applicant_id);
        $this->assertSame($this->agency->id, $application->agency_id);
    }

    // =========================================================
    // 3.3 Agensi — tiada fallback tetap
    // =========================================================

    public function test_application_is_not_created_without_valid_active_agency(): void
    {
        // Agensi id=1 wujud dan aktif — dahulu menjadi fallback.
        $this->assertSame(1, $this->agency->id);

        $noAgency = $this->userWithRole('pengguna_biasa', ['agency_id' => null]);
        $inactiveAgency = $this->userWithRole('pengguna_biasa', [
            'agency_id' => Agency::create(['code' => 'X', 'name' => 'Tidak Aktif', 'category' => 'SUK', 'is_active' => false])->id,
        ]);

        foreach ([$noAgency, $inactiveAgency] as $user) {
            $agencyBefore = $user->agency_id;

            Livewire::actingAs($user)->test(ApplicationForm::class)
                ->set('form.title', 'Projek Tanpa Agensi')
                ->call('saveDraft')
                ->assertSee('Jabatan/Agensi yang sah dan aktif');

            $this->assertSame($agencyBefore, $user->fresh()->agency_id, 'Rekod pengguna tidak diubah');
        }

        $this->assertSame(0, Application::count());
    }

    public function test_no_user_creation_path_uses_a_fixed_agency_fallback(): void
    {
        $this->assertSame(0, preg_match('/agency_id\'?\s*(=>|=|\?\?)\s*1\b|\?\?\s*1,/', file_get_contents(app_path('Services/ApplicationSubmissionService.php'))));

        foreach (['app', 'database/seeders', 'resources/views/livewire'] as $dir) {
            $hits = collect(\Illuminate\Support\Facades\File::allFiles(base_path($dir)))
                ->filter(fn ($file) => preg_match("/'agency_id'\s*=>\s*1\b|agency_id\s*\?\?\s*1\b/", $file->getContents()))
                ->map->getRelativePathname();
            $this->assertSame([], $hits->values()->all(), "Fallback agensi tetap dijumpai dalam {$dir}");
        }
    }

    // =========================================================
    // 3.5 Pengesahan e-mel kekal tidak aktif
    // =========================================================

    public function test_email_verification_remains_inactive(): void
    {
        $this->assertNotInstanceOf(MustVerifyEmail::class, new User());

        $user = $this->userWithRole('pengguna_biasa', ['email_verified_at' => null]);

        $this->actingAs($user)->get('/dashboard')->assertOk();
        $this->actingAs($user)->get('/application/new')->assertOk();
    }

    // =========================================================
    // 3.6 Komponen legacy
    // =========================================================

    public function test_legacy_position_grade_component_is_removed_and_route_redirects(): void
    {
        $this->assertFalse(class_exists(\App\Livewire\Admin\PositionGradeManagement::class));
        $this->assertFileDoesNotExist(resource_path('views/livewire/admin/position-grade-management.blade.php'));

        $this->actingAs($this->userWithRole('superadmin'))
            ->get('/admin/positions-grades')
            ->assertRedirect('/admin/positions');
    }

    // =========================================================
    // 3.7 Seeder & provisioning
    // =========================================================

    public function test_roles_seeder_creates_reference_data_only(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->assertSame(0, User::count());
        $this->assertEqualsCanonicalizing(['pengguna_biasa', 'pentadbir_urus_setia', 'pengurusan', 'superadmin'], Role::pluck('name')->all());
    }

    public function test_demo_accounts_are_never_created_in_production(): void
    {
        $this->app['env'] = 'production';

        try {
            $this->artisan('db:seed', ['--class' => DemoAccountsSeeder::class, '--force' => true])->assertSuccessful();
            $this->artisan('db:seed', ['--class' => DatabaseSeeder::class, '--force' => true])->assertSuccessful();
        } finally {
            $this->app['env'] = 'testing';
        }

        $this->assertSame(0, User::count());
        $this->assertFalse(Agency::where('code', 'BTM')->exists());
    }

    public function test_demo_seeder_does_not_change_existing_accounts(): void
    {
        $hash = Hash::make('KataLaluanAsal#2026');
        $existing = User::factory()->create(['email' => 'urussetia.mkk@ns.gov.my', 'password' => $hash]);
        $existing->assignRole('pengurusan');
        $existing->delete();

        $this->seed(DemoAccountsSeeder::class);

        $existing = User::withTrashed()->find($existing->id);
        $this->assertSame($hash, $existing->password);
        $this->assertSame(['pengurusan'], $existing->getRoleNames()->all());
        $this->assertTrue($existing->trashed());

        foreach (['admin.sppict@ns.gov.my', 'pemohon.jkr@ns.gov.my'] as $email) {
            $seeded = User::where('email', $email)->firstOrFail();
            $this->assertTrue($seeded->must_change_password);
            $this->assertFalse(Hash::check('Password123!', $seeded->password));
        }

        $this->assertStringNotContainsString('Password123!', file_get_contents(database_path('seeders/DemoAccountsSeeder.php')));
        $this->assertStringNotContainsString('Password123!', file_get_contents(database_path('seeders/RolesAndPermissionsSeeder.php')));
    }

    public function test_create_superadmin_command_provisions_account_with_temporary_password(): void
    {
        $exitCode = Artisan::call('sppict:create-superadmin', ['email' => ' Admin.Baru@NS.gov.my ', '--name' => 'Pentadbir Baharu', '--agency' => 'JKR']);
        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $user = User::where('email', 'admin.baru@ns.gov.my')->firstOrFail();
        $this->assertSame(['superadmin'], $user->getRoleNames()->all());
        $this->assertSame(UserStatus::Active, $user->status);
        $this->assertTrue($user->must_change_password);
        $this->assertSame($this->agency->id, $user->agency_id);

        preg_match('/disimpan\): (\S+)/', $output, $match);
        $this->assertNotEmpty($match[1] ?? null);
        $this->assertTrue(Hash::check($match[1], $user->password));
        $this->assertTrue(TemporaryPassword::satisfiesPolicy($match[1]));
    }

    public function test_create_superadmin_command_refuses_existing_account_and_invalid_agency(): void
    {
        $hash = Hash::make('KataLaluanAsal#2026');
        $existing = User::factory()->create(['email' => 'ada@ns.gov.my', 'password' => $hash]);
        Agency::create(['code' => 'OFF', 'name' => 'Tidak Aktif', 'category' => 'SUK', 'is_active' => false]);

        $this->artisan('sppict:create-superadmin', ['email' => 'ADA@ns.gov.my', '--name' => 'X', '--agency' => 'JKR'])->assertFailed();
        $this->assertSame($hash, $existing->fresh()->password);
        $this->assertFalse($existing->fresh()->hasRole('superadmin'));

        $this->artisan('sppict:create-superadmin', ['email' => 'baru@ns.gov.my', '--name' => 'X', '--agency' => 'OFF'])->assertFailed();
        $this->artisan('sppict:create-superadmin', ['email' => 'baru@ns.gov.my', '--name' => 'X', '--agency' => 'TIADA'])->assertFailed();
        $this->artisan('sppict:create-superadmin', ['email' => 'baru@ns.gov.my', '--agency' => 'JKR'])->assertFailed();

        $this->assertFalse(User::where('email', 'baru@ns.gov.my')->exists());
    }

    // --- Helpers ---

    private function userWithRole(string $role, array $attributes = []): User
    {
        $user = User::factory()->create(array_merge(['agency_id' => $this->agency->id], $attributes));
        $user->assignRole($role);

        return $user;
    }
}
