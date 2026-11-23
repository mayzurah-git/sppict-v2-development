<?php

namespace Tests\Feature\Auth;

use App\Enums\UserStatus;
use App\Models\Agency;
use App\Models\Grade;
use App\Models\Position;
use App\Models\ServiceScheme;
use App\Models\User;
use Database\Seeders\GradeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Livewire\Exceptions\PublicPropertyNotFoundException;
use Livewire\Volt\Volt;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Fasa UM2: Self Register pegawai Jabatan/Agensi.
 */
class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    private Agency $agency;

    private ServiceScheme $schemeF;

    private ServiceScheme $schemeN;

    private Position $pegawaiIt;

    private Position $pembantuTadbir;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['pengguna_biasa', 'pentadbir_urus_setia', 'pengurusan', 'superadmin'] as $role) {
            Role::create(['name' => $role]);
        }

        $this->seed(GradeSeeder::class);

        $this->agency = $this->makeAgency('JKRNS', 'Jabatan Kerja Raya Negeri Sembilan');
        $this->schemeF = ServiceScheme::create(['code' => 'F', 'name' => 'Teknologi Maklumat', 'is_active' => true]);
        $this->schemeN = ServiceScheme::create(['code' => 'N', 'name' => 'Tadbir dan Sokongan', 'is_active' => true]);

        // Jawatan id=1 ialah legacy tanpa skim/gred (seperti data sebenar).
        Position::create(['title' => 'Jawatan Legacy', 'is_active' => false]);

        $this->pegawaiIt = $this->makePosition('Pegawai Teknologi Maklumat', $this->schemeF, ['9', '10', '12']);
        $this->pembantuTadbir = $this->makePosition('Pembantu Tadbir', $this->schemeN, ['1', '3']);
    }

    // --- Paparan borang ---

    public function test_registration_screen_shows_required_fields_without_role_selection(): void
    {
        $this->get('/register')
            ->assertOk()
            ->assertSeeVolt('pages.auth.register')
            ->assertSee('No. Kad Pengenalan')
            ->assertSee('Nama Penuh')
            ->assertSee('Jabatan/Agensi')
            ->assertSee('Jawatan')
            ->assertSee('Skim Perkhidmatan')
            ->assertSee('Gred')
            ->assertSee('No. Telefon Pejabat')
            ->assertSee('No. Telefon HP')
            ->assertSee('E-mel Rasmi')
            ->assertSee('Pengesahan Kata Laluan')
            ->assertSee($this->agency->name)
            ->assertSee('Pegawai Teknologi Maklumat')
            ->assertDontSee('Jawatan Legacy')
            ->assertDontSee('Tahap Capaian')
            ->assertDontSee('pentadbir_urus_setia');
    }

    public function test_inactive_and_deleted_master_data_are_not_listed(): void
    {
        $inactiveAgency = $this->makeAgency('X1', 'Agensi Tidak Aktif', active: false);
        $deletedAgency = $this->makeAgency('X2', 'Agensi Dipadam');
        $deletedAgency->delete();
        $this->makePosition('Jawatan Tidak Aktif', $this->schemeF, ['9'], active: false);
        $this->makePosition('Jawatan Tanpa Gred', $this->schemeF, []);

        Volt::test('pages.auth.register')
            ->assertDontSee($inactiveAgency->name)
            ->assertDontSee($deletedAgency->name)
            ->assertDontSee('Jawatan Tidak Aktif')
            ->assertDontSee('Jawatan Tanpa Gred');
    }

    // --- Pendaftaran berjaya ---

    public function test_valid_registration_creates_pending_pengguna_biasa_without_logging_in(): void
    {
        $grade = $this->grade('10');

        $this->fillForm(['grade_id' => (string) $grade->id, 'email' => '  Ali.Ahmad@NS.gov.my '])
            ->call('register')
            ->assertHasNoErrors()
            ->assertRedirect(route('register.pending', absolute: false));

        $this->assertGuest();

        $user = User::where('email', 'ali.ahmad@ns.gov.my')->firstOrFail();
        $this->assertSame('050101051234', $user->no_kad_pengenalan);
        $this->assertSame('Ali bin Ahmad', $user->name);
        $this->assertSame($this->agency->id, $user->agency_id);
        $this->assertSame($this->pegawaiIt->id, $user->position_id);
        $this->assertSame($grade->id, $user->grade_id);
        $this->assertSame('067659999', $user->phone_office);
        $this->assertSame('0123456789', $user->phone_mobile);

        // Status & role ditentukan oleh server
        $this->assertSame(UserStatus::Pending, $user->status);
        $this->assertNull($user->approved_by);
        $this->assertNull($user->approved_at);
        $this->assertFalse($user->must_change_password);
        $this->assertSame(['pengguna_biasa'], $user->getRoleNames()->all());

        // Password di-hash, tidak disimpan sebagai plaintext
        $this->assertNotSame('KataLaluan#2026', $user->password);
        $this->assertTrue(Hash::check('KataLaluan#2026', $user->password));

        // Medan legacy tidak diisi oleh Self Register
        $this->assertNull($user->role_id);
        $this->assertNull($user->getAttribute('position'));
        $this->assertNull($user->phone_number);
    }

    public function test_registration_pending_page_is_shown(): void
    {
        $this->get(route('register.pending'))
            ->assertOk()
            ->assertSee('menunggu kelulusan Urus Setia', false);
    }

    // --- Manipulasi request ---

    public function test_role_status_and_approval_fields_cannot_be_set_by_client(): void
    {
        $component = $this->fillForm();

        foreach (['role' => 'superadmin', 'role_id' => 4, 'status' => 'active', 'approved_by' => 1, 'approved_at' => now(), 'must_change_password' => false] as $property => $value) {
            try {
                $component->set($property, $value);
                $this->fail("Property [{$property}] tidak sepatutnya boleh ditetapkan oleh client.");
            } catch (PublicPropertyNotFoundException) {
                // Dijangka: tiada property sedemikian pada komponen.
            }
        }

        $component->call('register')->assertHasNoErrors();

        $user = User::where('email', 'ali.ahmad@ns.gov.my')->firstOrFail();
        $this->assertSame(UserStatus::Pending, $user->status);
        $this->assertFalse($user->hasAnyRole(['superadmin', 'pentadbir_urus_setia', 'pengurusan']));
        $this->assertSame(['pengguna_biasa'], $user->getRoleNames()->all());
    }

    // --- Validation ---

    public function test_ic_number_must_be_exactly_twelve_digits(): void
    {
        foreach (['05010105123', '0501010512345', '050101-05-1234', 'ABCDEFGHIJKL'] as $ic) {
            $this->fillForm(['no_kad_pengenalan' => $ic])
                ->call('register')
                ->assertHasErrors(['no_kad_pengenalan']);
        }

        $this->assertSame(0, User::count());
    }

    public function test_duplicate_ic_number_is_rejected(): void
    {
        User::factory()->create(['no_kad_pengenalan' => '050101051234']);

        $this->fillForm()->call('register')->assertHasErrors(['no_kad_pengenalan' => 'unique']);
    }

    public function test_duplicate_email_is_rejected_case_insensitively(): void
    {
        $existing = User::factory()->create(['email' => 'Ali.Ahmad@ns.gov.my']);

        $this->fillForm(['email' => 'ali.ahmad@ns.gov.my'])->call('register')->assertHasErrors(['email']);

        $existing->delete();
        $this->fillForm(['email' => 'ALI.AHMAD@ns.gov.my'])->call('register')->assertHasErrors(['email']);
    }

    public function test_inactive_or_deleted_agency_is_rejected(): void
    {
        $inactive = $this->makeAgency('X1', 'Agensi Tidak Aktif', active: false);
        $deleted = $this->makeAgency('X2', 'Agensi Dipadam');
        $deleted->delete();

        foreach ([$inactive, $deleted] as $agency) {
            $this->fillForm(['agency_id' => (string) $agency->id])->call('register')->assertHasErrors(['agency_id']);
        }
    }

    public function test_inactive_or_deleted_position_is_rejected(): void
    {
        $inactive = $this->makePosition('Jawatan Tidak Aktif', $this->schemeF, ['9'], active: false);
        $deleted = $this->makePosition('Jawatan Dipadam', $this->schemeF, ['9']);
        $deleted->delete();

        foreach ([$inactive, $deleted] as $position) {
            $this->fillForm(['position_id' => (string) $position->id, 'grade_id' => (string) $this->grade('9')->id])
                ->call('register')
                ->assertHasErrors(['position_id']);
        }
    }

    public function test_inactive_or_deleted_grade_is_rejected(): void
    {
        $inactive = $this->grade('9');
        $inactive->update(['is_active' => false]);
        $deleted = $this->grade('12');
        $deleted->delete();

        foreach ([$inactive, $deleted] as $grade) {
            $this->fillForm(['grade_id' => (string) $grade->id])->call('register')->assertHasErrors(['grade_id']);
        }
    }

    public function test_grade_not_linked_to_selected_position_is_rejected(): void
    {
        // Gred 1 sah untuk Pembantu Tadbir, bukan Pegawai Teknologi Maklumat.
        $this->fillForm(['grade_id' => (string) $this->grade('1')->id])
            ->call('register')
            ->assertHasErrors(['grade_id' => 'exists']);
    }

    public function test_position_without_valid_grade_cannot_register(): void
    {
        $noGrade = $this->makePosition('Jawatan Tanpa Gred', $this->schemeF, []);
        $onlyInactiveGrade = $this->makePosition('Jawatan Gred Tidak Aktif', $this->schemeF, ['15']);
        $this->grade('15')->update(['is_active' => false]);

        $this->fillForm(['position_id' => (string) $noGrade->id, 'grade_id' => ''])
            ->call('register')
            ->assertHasErrors(['position_id', 'grade_id']);

        $this->fillForm(['position_id' => (string) $onlyInactiveGrade->id, 'grade_id' => (string) $this->grade('15')->id])
            ->call('register')
            ->assertHasErrors(['position_id', 'grade_id']);

        $this->assertSame(0, User::count());
    }

    public function test_phone_numbers_must_be_digits_only(): void
    {
        foreach (['06-7659999', '012 3456789', '12345', '+60123456789', '012345678901'] as $phone) {
            $this->fillForm(['phone_office' => $phone, 'phone_mobile' => $phone])
                ->call('register')
                ->assertHasErrors(['phone_office', 'phone_mobile']);
        }
    }

    public function test_password_confirmation_must_match(): void
    {
        $this->fillForm(['password_confirmation' => 'LainLain#2026'])
            ->call('register')
            ->assertHasErrors(['password' => 'confirmed']);

        $this->assertSame(0, User::count());
    }

    // --- Dropdown dinamik ---

    public function test_service_scheme_and_grades_follow_selected_position(): void
    {
        $component = Volt::test('pages.auth.register')
            ->assertDontSee('F — Teknologi Maklumat')
            ->set('position_id', (string) $this->pegawaiIt->id)
            ->assertSee('F — Teknologi Maklumat')
            ->assertViewHas('grades', fn ($grades) => $grades->pluck('code')->all() === ['9', '10', '12'])
            ->set('grade_id', (string) $this->grade('10')->id)
            ->set('position_id', (string) $this->pembantuTadbir->id)
            ->assertSet('grade_id', '')
            ->assertSee('N — Tadbir dan Sokongan')
            ->assertDontSee('F — Teknologi Maklumat')
            ->assertViewHas('grades', fn ($grades) => $grades->pluck('code')->all() === ['1', '3']);

        // Gred tidak aktif yang dipautkan tidak dipaparkan
        $this->grade('3')->update(['is_active' => false]);
        $component->set('position_id', (string) $this->pembantuTadbir->id)
            ->assertViewHas('grades', fn ($grades) => $grades->pluck('code')->all() === ['1']);
    }

    // --- Kawalan akses ---

    public function test_pending_or_rejected_user_cannot_log_in(): void
    {
        $pending = User::factory()->pending()->create();
        $rejected = User::factory()->create();
        $rejected->forceFill(['status' => UserStatus::Rejected])->save();

        Volt::test('pages.auth.login')
            ->set('form.email', $pending->email)
            ->set('form.password', 'password')
            ->call('login')
            ->assertHasErrors(['form.email']);
        $this->assertGuest();

        Volt::test('pages.auth.login')
            ->set('form.email', $rejected->email)
            ->set('form.password', 'password')
            ->call('login')
            ->assertHasErrors(['form.email']);
        $this->assertGuest();
    }

    public function test_pending_user_session_cannot_access_protected_modules(): void
    {
        $pending = User::factory()->pending()->create();
        $pending->assignRole('pengguna_biasa');

        foreach (['/dashboard', '/profile', '/application/new'] as $uri) {
            $this->actingAs($pending)
                ->get($uri)
                ->assertRedirect(route('login'));
            $this->assertGuest();
        }
    }

    public function test_login_logout_and_status_pages_still_work_for_active_users(): void
    {
        $this->get('/login')->assertOk();
        $this->get(route('register.pending'))->assertOk();

        $active = User::factory()->create();
        $active->assignRole('pengguna_biasa');

        Volt::test('pages.auth.login')
            ->set('form.email', $active->email)
            ->set('form.password', 'password')
            ->call('login')
            ->assertHasNoErrors()
            ->assertRedirect(route('dashboard', absolute: false));
        $this->assertAuthenticatedAs($active);

        $this->get('/dashboard')->assertOk();

        Volt::test('layout.navigation')->call('logout')->assertRedirect('/');
        $this->assertGuest();
    }

    public function test_registration_does_not_affect_existing_users_or_roles(): void
    {
        $superadmin = User::factory()->create();
        $superadmin->assignRole('superadmin');
        $urusSetia = User::factory()->create();
        $urusSetia->assignRole('pentadbir_urus_setia');

        $this->fillForm()->call('register')->assertHasNoErrors();

        $this->assertSame(['superadmin'], $superadmin->fresh()->getRoleNames()->all());
        $this->assertSame(['pentadbir_urus_setia'], $urusSetia->fresh()->getRoleNames()->all());
        $this->assertSame(UserStatus::Active, $superadmin->fresh()->status);
        $this->assertSame(UserStatus::Active, $urusSetia->fresh()->status);
        $this->assertSame(3, User::count());
    }

    // --- Helpers ---

    private function fillForm(array $overrides = [])
    {
        $data = array_merge([
            'no_kad_pengenalan' => '050101051234',
            'name' => ' Ali  bin Ahmad ',
            'agency_id' => (string) $this->agency->id,
            'position_id' => (string) $this->pegawaiIt->id,
            'grade_id' => (string) $this->grade('9')->id,
            'phone_office' => '067659999',
            'phone_mobile' => '0123456789',
            'email' => 'ali.ahmad@ns.gov.my',
            'password' => 'KataLaluan#2026',
            'password_confirmation' => 'KataLaluan#2026',
        ], $overrides);

        $component = Volt::test('pages.auth.register');

        // Jawatan dahulu: menukar Jawatan mengosongkan Gred.
        $component->set('position_id', $data['position_id']);
        unset($data['position_id']);

        foreach ($data as $field => $value) {
            $component->set($field, $value);
        }

        return $component;
    }

    private function grade(string $code): Grade
    {
        return Grade::withTrashed()->where('code', $code)->firstOrFail();
    }

    private function makeAgency(string $code, string $name, bool $active = true): Agency
    {
        return Agency::create(['code' => $code, 'name' => $name, 'category' => 'STATE_DEPT', 'is_active' => $active]);
    }

    private function makePosition(string $title, ServiceScheme $scheme, array $gradeCodes, bool $active = true): Position
    {
        $position = Position::create(['title' => $title, 'service_scheme_id' => $scheme->id, 'is_active' => $active]);
        $position->grades()->sync(Grade::whereIn('code', $gradeCodes)->pluck('id'));

        return $position;
    }
}
