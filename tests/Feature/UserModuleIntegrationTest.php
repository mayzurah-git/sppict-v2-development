<?php

namespace Tests\Feature;

use App\Enums\UserStatus;
use App\Livewire\Admin\UserManagement;
use App\Livewire\ApplicationForm;
use App\Livewire\Users\RegisterUser;
use App\Livewire\Users\RegistrationRequests;
use App\Models\Agency;
use App\Models\Application;
use App\Models\Grade;
use App\Models\Position;
use App\Models\ServiceScheme;
use App\Models\User;
use Database\Seeders\GradeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Livewire\Exceptions\PublicPropertyNotFoundException;
use Livewire\Livewire;
use Livewire\Volt\Volt;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Tests\Concerns\UsesApplicationMasterData;
use Tests\TestCase;

/**
 * Fasa UM8: audit integrasi Modul Pengurusan Pengguna — matriks akses, aliran hujung ke hujung,
 * sumber peranan, Profil dan hubungan data master.
 */
class UserModuleIntegrationTest extends TestCase
{
    use RefreshDatabase, UsesApplicationMasterData;

    private const STRONG = 'KataLaluanKuat#2026';

    private Agency $agency;

    private Position $position;

    private Grade $grade;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['pengguna_biasa', 'pentadbir_urus_setia', 'pengurusan', 'superadmin'] as $role) {
            Role::create(['name' => $role]);
        }

        $this->seed(GradeSeeder::class);
        $this->agency = Agency::create(['code' => 'JKR', 'name' => 'Jabatan Kerja Raya', 'category' => 'STATE_DEPT', 'is_active' => true]);
        $scheme = ServiceScheme::create(['code' => 'F', 'name' => 'Teknologi Maklumat', 'is_active' => true]);
        Position::create(['title' => 'Jawatan Legacy', 'is_active' => false]);
        $this->position = Position::create(['title' => 'Pegawai Teknologi Maklumat', 'service_scheme_id' => $scheme->id, 'is_active' => true]);
        $this->grade = Grade::where('code', '9')->firstOrFail();
        $this->position->grades()->sync([$this->grade->id, Grade::where('code', '10')->value('id')]);
    }

    // =========================================================
    // 1. Matriks akses route × pelaku
    // =========================================================

    private const ROUTES = [
        'dashboard' => '/dashboard',
        'profile' => '/profile',
        'app_create' => '/application/new',
        'admin_users' => '/admin/users',
        'admin_positions' => '/admin/positions',
        'admin_grades' => '/admin/grades',
        'admin_schemes' => '/admin/service-schemes',
        'admin_audit' => '/admin/audit-logs',
        'agencies' => '/admin/agencies',
        'us_register' => '/urus-setia/pengguna/daftar',
        'us_requests' => '/urus-setia/pengguna/permohonan',
        'us_users' => '/urus-setia/pengguna',
        'meetings' => '/urus-setia/mesyuarat',
        'change_password' => '/tukar-kata-laluan',
    ];

    public static function accessMatrixProvider(): array
    {
        $login = 'login';
        $change = 'change';

        // 200 = dibenarkan, 403 = ditolak, 'login' = redirect log masuk, 'change' = redirect tukar kata laluan,
        // 'dashboard' = redirect papan pemuka.
        return [
            'tetamu' => [null, null, array_fill_keys(array_keys(self::ROUTES), $login)],
            'pending' => ['pengguna_biasa', 'pending', array_fill_keys(array_keys(self::ROUTES), $login)],
            'rejected' => ['pentadbir_urus_setia', 'rejected', array_fill_keys(array_keys(self::ROUTES), $login)],
            'kata laluan sementara' => ['pentadbir_urus_setia', 'must_change', array_merge(
                array_fill_keys(array_keys(self::ROUTES), $change),
                ['change_password' => 200],
            )],
            'pengguna_biasa' => ['pengguna_biasa', null, [
                'dashboard' => 200, 'profile' => 200, 'app_create' => 200,
                'admin_users' => 403, 'admin_positions' => 403, 'admin_grades' => 403, 'admin_schemes' => 403, 'admin_audit' => 403,
                'agencies' => 403, 'us_register' => 403, 'us_requests' => 403, 'us_users' => 403, 'meetings' => 403, 'change_password' => 'dashboard',
            ]],
            'pentadbir_urus_setia' => ['pentadbir_urus_setia', null, [
                'dashboard' => 200, 'profile' => 200, 'app_create' => 200,
                'admin_users' => 403, 'admin_positions' => 403, 'admin_grades' => 403, 'admin_schemes' => 403, 'admin_audit' => 403,
                'agencies' => 200, 'us_register' => 200, 'us_requests' => 200, 'us_users' => 200, 'meetings' => 200, 'change_password' => 'dashboard',
            ]],
            'pengurusan' => ['pengurusan', null, [
                'dashboard' => 200, 'profile' => 200, 'app_create' => 403,
                'admin_users' => 403, 'admin_positions' => 403, 'admin_grades' => 403, 'admin_schemes' => 403, 'admin_audit' => 403,
                'agencies' => 403, 'us_register' => 403, 'us_requests' => 403, 'us_users' => 403, 'meetings' => 403, 'change_password' => 'dashboard',
            ]],
            'superadmin' => ['superadmin', null, [
                'dashboard' => 200, 'profile' => 200, 'app_create' => 403,
                'admin_users' => 200, 'admin_positions' => 200, 'admin_grades' => 200, 'admin_schemes' => 200, 'admin_audit' => 200,
                'agencies' => 403, 'us_register' => 403, 'us_requests' => 403, 'us_users' => 403, 'meetings' => 403, 'change_password' => 'dashboard',
            ]],
        ];
    }

    #[DataProvider('accessMatrixProvider')]
    public function test_route_access_matrix(?string $role, ?string $state, array $expected): void
    {
        foreach (self::ROUTES as $key => $uri) {
            Auth::logout();
            $user = $role ? $this->actor($role, $state) : null;

            $response = $user ? $this->actingAs($user)->get($uri) : $this->get($uri);

            match ($expected[$key]) {
                'login' => $response->assertRedirect(route('login')),
                'change' => $response->assertRedirect(route('password.change')),
                'dashboard' => $response->assertRedirect(route('dashboard')),
                default => $response->assertStatus($expected[$key]),
            };

            if (in_array($state, ['pending', 'rejected'], true)) {
                $this->assertGuest();
            }
        }
    }

    // =========================================================
    // 2. Aliran hujung ke hujung
    // =========================================================

    public function test_self_register_to_approval_to_application_flow(): void
    {
        // 4.1 Pendaftaran kendiri
        $this->fillProfile(Volt::test('pages.auth.register'), ['email' => 'Pemohon@NS.gov.my'])
            ->set('password', self::STRONG)->set('password_confirmation', self::STRONG)
            ->call('register')
            ->assertHasNoErrors()
            ->assertRedirect(route('register.pending', absolute: false));
        $this->assertGuest();

        $applicant = User::where('email', 'pemohon@ns.gov.my')->firstOrFail();
        $this->assertSame(UserStatus::Pending, $applicant->status);
        $this->assertSame(['pengguna_biasa'], $applicant->getRoleNames()->all());
        $this->assertNull($applicant->approved_by);
        $this->assertNull($applicant->role_id, 'role_id legacy tidak diisi; Spatie sumber peranan');

        // Log masuk disekat sebelum kelulusan
        $this->login('pemohon@ns.gov.my', self::STRONG)->assertHasErrors(['form.email']);
        $this->assertGuest();

        // 4.2 Urus Setia meluluskan
        $urusSetia = $this->actor('pentadbir_urus_setia');
        Livewire::actingAs($urusSetia)->test(RegistrationRequests::class)
            ->assertSee('Pemohon Ujian')
            ->call('approve', $applicant->uuid);
        Auth::logout();

        $applicant->refresh();
        $this->assertSame(UserStatus::Active, $applicant->status);
        $this->assertSame($urusSetia->id, $applicant->approved_by);

        // Log masuk berjaya; boleh mendaftar permohonan
        $this->login('pemohon@ns.gov.my', self::STRONG)->assertRedirect(route('dashboard', absolute: false));
        $this->assertAuthenticatedAs($applicant);
        $this->get('/application/new')->assertOk();

        Livewire::actingAs($applicant)->test(ApplicationForm::class)
            ->set('form.title', 'Projek Pemohon')
            ->call('saveDraft')
            ->assertHasNoErrors();
        $this->assertSame($this->agency->id, Application::where('applicant_id', $applicant->id)->value('agency_id'));
    }

    public function test_rejected_applicant_never_gets_access(): void
    {
        $this->fillProfile(Volt::test('pages.auth.register'))
            ->set('password', self::STRONG)->set('password_confirmation', self::STRONG)
            ->call('register');
        $applicant = User::where('email', 'pemohon@ns.gov.my')->firstOrFail();

        Livewire::actingAs($this->actor('pentadbir_urus_setia'))->test(RegistrationRequests::class)->call('reject', $applicant->uuid);
        Auth::logout();

        $this->assertNotNull(User::find($applicant->id), 'Rekod ditolak dikekalkan');
        $this->login('pemohon@ns.gov.my', self::STRONG)->assertHasErrors(['form.email']);
        $this->assertGuest();
    }

    public function test_urus_setia_created_account_must_change_password_before_use(): void
    {
        $urusSetia = $this->actor('pentadbir_urus_setia');

        $component = $this->fillProfile(Livewire::actingAs($urusSetia)->test(RegisterUser::class), ['email' => 'pengurusan@ns.gov.my'])
            ->set('role', 'pengurusan')
            ->call('save')
            ->assertHasNoErrors();
        $temporary = $component->get('temporaryPassword');
        Auth::logout();

        $user = User::where('email', 'pengurusan@ns.gov.my')->firstOrFail();
        $this->assertTrue($user->must_change_password);
        $this->assertSame(['pengurusan'], $user->getRoleNames()->all());

        // Log masuk → halaman tukar kata laluan; kawasan dalaman disekat
        $this->assertSame(route('password.change'), $this->httpLogin('pengurusan@ns.gov.my', $temporary));
        $this->get('/dashboard')->assertRedirect(route('password.change'));

        $this->httpChangePassword($temporary);

        $user->refresh();
        $this->assertFalse($user->must_change_password);
        $this->assertTrue(Hash::check(self::STRONG, $user->password));

        Auth::logout();
        $this->assertSame(route('dashboard', absolute: false), parse_url($this->httpLogin('pengurusan@ns.gov.my', self::STRONG), PHP_URL_PATH));
        $this->get('/dashboard')->assertOk();
        $this->get('/application/new')->assertForbidden();
    }

    public function test_superadmin_created_urus_setia_can_process_requests_after_password_change(): void
    {
        $superadmin = $this->actor('superadmin');

        $component = $this->fillProfile(Livewire::actingAs($superadmin)->test(UserManagement::class)->call('openModal'), ['email' => 'us.baru@ns.gov.my'])
            ->set('role', 'pentadbir_urus_setia')
            ->call('saveUser')
            ->assertHasNoErrors();
        $temporary = $component->get('temporaryPassword');
        Auth::logout();

        $this->assertSame(route('password.change'), $this->httpLogin('us.baru@ns.gov.my', $temporary));
        $this->get('/urus-setia/pengguna/permohonan')->assertRedirect(route('password.change'));

        $this->httpChangePassword($temporary);

        $this->get('/urus-setia/pengguna/permohonan')->assertOk();
    }

    // =========================================================
    // 3. Sumber peranan: Spatie vs users.role_id
    // =========================================================

    public function test_authorization_and_display_use_spatie_roles_not_legacy_role_id(): void
    {
        $superadminRoleId = Role::where('name', 'superadmin')->value('id');

        // role_id legacy menunjuk superadmin, tetapi peranan Spatie ialah pengguna_biasa.
        $user = $this->actor('pengguna_biasa');
        $user->forceFill(['role_id' => $superadminRoleId])->save();

        $this->actingAs($user)->get('/admin/users')->assertForbidden();
        $this->actingAs($user)->get('/application/new')->assertOk();

        // Senarai Superadmin memaparkan peranan Spatie.
        Livewire::actingAs($this->actor('superadmin'))->test(UserManagement::class)
            ->set('search', $user->email)
            ->assertSeeHtml('>Pemohon Agensi</span>')
            ->set('roleFilter', 'superadmin')
            ->assertDontSee($user->email);
    }

    // =========================================================
    // 4. Profil
    // =========================================================

    public function test_profile_cannot_change_protected_fields_and_uses_consistent_email_rules(): void
    {
        $user = $this->actor('pengguna_biasa', null, ['email' => 'saya@ns.gov.my']);
        $other = User::factory()->create(['email' => 'Lain@ns.gov.my']);
        $deleted = User::factory()->create(['email' => 'dipadam@ns.gov.my']);
        $deleted->delete();

        $component = Volt::actingAs($user)->test('profile.update-profile-information-form');

        foreach (['role', 'role_id', 'status', 'agency_id', 'approved_by', 'must_change_password', 'position_id', 'grade_id'] as $field) {
            try {
                $component->set($field, 'x');
                $this->fail("Medan [{$field}] tidak sepatutnya boleh diubah melalui Profil.");
            } catch (PublicPropertyNotFoundException) {
                // Dijangka.
            }
        }

        // E-mel unik tanpa mengira huruf & termasuk akaun soft-deleted.
        foreach (['LAIN@ns.gov.my', 'dipadam@ns.gov.my'] as $email) {
            Volt::actingAs($user)->test('profile.update-profile-information-form')
                ->set('email', $email)->call('updateProfileInformation')
                ->assertHasErrors(['email']);
        }

        // Normalisasi; peranan/agensi/status kekal.
        Volt::actingAs($user)->test('profile.update-profile-information-form')
            ->set('name', '  Nama   Baharu ')->set('email', ' Saya.Baru@NS.gov.my ')
            ->call('updateProfileInformation')
            ->assertHasNoErrors();

        $user->refresh();
        $this->assertSame('Nama Baharu', $user->name);
        $this->assertSame('saya.baru@ns.gov.my', $user->email);
        $this->assertSame(['pengguna_biasa'], $user->getRoleNames()->all());
        $this->assertSame($this->agency->id, $user->agency_id);
        $this->assertSame(UserStatus::Active, $user->status);
        $this->assertSame('Lain@ns.gov.my', $other->fresh()->email);
    }

    public function test_module_pages_use_malay_labels(): void
    {
        $user = $this->actor('pengguna_biasa');

        $this->actingAs($user)->get('/profile')
            ->assertSee('Maklumat Profil')->assertSee('Tukar Kata Laluan')->assertSee('Simpan')
            ->assertDontSee('Profile Information')->assertDontSee('Update Password');

        Auth::logout();
        $this->get('/forgot-password')->assertSee('Hantar Pautan Set Semula Kata Laluan')->assertDontSee('Email Password Reset Link');
        $this->get('/register')->assertSee('Daftar Akaun Pengguna');
        $this->get('/')->assertSee('Log Masuk')->assertDontSee('Log in');
    }

    // =========================================================
    // 5. Hubungan data master
    // =========================================================

    public function test_grade_must_come_from_pivot_not_legacy_position_grade_id(): void
    {
        // Jawatan dengan grade_id legacy tetapi tanpa pivot: tidak boleh dipilih.
        $legacyOnly = Position::create([
            'title' => 'Jawatan Lama', 'service_scheme_id' => $this->position->service_scheme_id,
            'grade_id' => $this->grade->id, 'is_active' => true,
        ]);
        $this->assertFalse(Position::assignable()->whereKey($legacyOnly->id)->exists());

        // Pengguna dengan Gred di luar pivot jawatan ditolak (Superadmin kemas kini).
        $outside = Grade::where('code', '14')->firstOrFail();
        $user = $this->actor('pentadbir_urus_setia', null, [
            'no_kad_pengenalan' => '800101051234', 'position_id' => $this->position->id, 'grade_id' => $this->grade->id,
            'phone_office' => '067650000', 'phone_mobile' => '0120000000',
        ]);

        Livewire::actingAs($this->actor('superadmin'))->test(UserManagement::class)
            ->call('openModal', $user->uuid)
            ->set('grade_id', (string) $outside->id)
            ->call('saveUser')
            ->assertHasErrors(['grade_id']);

        $this->assertSame($this->grade->id, $user->fresh()->grade_id, 'Tiada kemas kini separa');

        // Satu jawatan, pelbagai gred.
        $this->assertSame(2, $this->position->grades()->count());
    }

    // --- Helpers ---

    private function actor(string $role, ?string $state = null, array $attributes = []): User
    {
        $user = User::factory()->create(array_merge(['agency_id' => $this->agency->id], $attributes));
        $user->assignRole($role);

        match ($state) {
            'pending' => $user->forceFill(['status' => UserStatus::Pending])->save(),
            'rejected' => $user->forceFill(['status' => UserStatus::Rejected])->save(),
            'must_change' => $user->forceFill(['must_change_password' => true])->save(),
            default => null,
        };

        return $user->fresh();
    }

    private function fillProfile($component, array $overrides = [])
    {
        $data = array_merge([
            'no_kad_pengenalan' => '050505055555',
            'name' => 'Pemohon Ujian',
            'agency_id' => (string) $this->agency->id,
            'grade_id' => (string) $this->grade->id,
            'phone_office' => '067659999',
            'phone_mobile' => '0123456789',
            'email' => 'pemohon@ns.gov.my',
        ], $overrides);

        $component->set('position_id', (string) $this->position->id);

        foreach ($data as $field => $value) {
            $component->set($field, $value);
        }

        return $component;
    }

    private function login(string $email, string $password)
    {
        return Volt::test('pages.auth.login')
            ->set('form.email', $email)
            ->set('form.password', $password)
            ->call('login');
    }
    /**
     * Log masuk melalui HTTP + /livewire/update sebenar (middleware web, termasuk AuthenticateSession).
     * Memulangkan URL redirect selepas log masuk.
     */
    private function httpLogin(string $email, string $password): string
    {
        $response = $this->livewireAction('/login', 'pages.auth.login', ['form.email' => $email, 'form.password' => $password], 'login');
        $this->assertAuthenticated();

        return (string) $response->json('components.0.effects.redirect');
    }

    /**
     * Tukar kata laluan sementara melalui HTTP + /livewire/update sebenar.
     */
    private function httpChangePassword(string $current): void
    {
        $response = $this->livewireAction('/tukar-kata-laluan', 'pages.auth.change-password', [
            'current_password' => $current,
            'password' => self::STRONG,
            'password_confirmation' => self::STRONG,
        ], 'updatePassword');

        $this->assertSame(route('dashboard'), $response->json('components.0.effects.redirect'));
    }

    private function livewireAction(string $uri, string $component, array $updates, string $method)
    {
        preg_match_all('/wire:snapshot="([^"]+)"/', $this->get($uri)->assertOk()->getContent(), $matches);

        $snapshot = collect($matches[1])
            ->map(fn ($encoded) => html_entity_decode($encoded, ENT_QUOTES))
            ->first(fn ($snapshot) => (json_decode($snapshot, true)['memo']['name'] ?? null) === $component);
        $this->assertNotNull($snapshot, "Snapshot [{$component}] tidak dijumpai.");

        $response = $this->withHeaders(['X-Livewire' => 'true'])->postJson('/livewire/update', ['components' => [[
            'snapshot' => $snapshot,
            'updates' => $updates,
            'calls' => [['path' => '', 'method' => $method, 'params' => []]],
        ]]])->assertOk();

        // Header X-Livewire tidak boleh terbawa ke permintaan HTTP biasa seterusnya.
        $this->flushHeaders();

        return $response;
    }
}
