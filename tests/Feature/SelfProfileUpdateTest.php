<?php

namespace Tests\Feature;

use App\Enums\InactiveReason;
use App\Enums\UserStatus;
use App\Models\Agency;
use App\Models\Grade;
use App\Models\Position;
use App\Models\ServiceScheme;
use App\Models\User;
use Database\Seeders\GradeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Livewire\Exceptions\PublicPropertyNotFoundException;
use Livewire\Volt\Volt;
use OwenIt\Auditing\Models\Audit;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Fasa UM12: pengemaskinian profil pengguna sendiri (telefon pejabat, telefon HP, gred, e-mel).
 */
class SelfProfileUpdateTest extends TestCase
{
    use RefreshDatabase;

    private Agency $agency;

    private Position $position;

    private Grade $grade9;

    private Grade $grade10;

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
        $this->grade9 = Grade::where('code', '9')->firstOrFail();
        $this->grade10 = Grade::where('code', '10')->firstOrFail();
        $this->position->grades()->sync([$this->grade9->id, $this->grade10->id]);
    }

    public static function roleProvider(): array
    {
        return [
            'Superadmin' => ['superadmin'],
            'Urus Setia' => ['pentadbir_urus_setia'],
            'Pengurusan' => ['pengurusan'],
            'Pengguna Biasa' => ['pengguna_biasa'],
        ];
    }

    // 1–5. Semua tahap capaian boleh membuka & mengemas kini profil sendiri
    #[DataProvider('roleProvider')]
    public function test_every_role_can_view_and_update_own_profile(string $role): void
    {
        $user = $this->profiled($role, ['email' => "{$role}@ns.gov.my"]);

        $this->actingAs($user)->get('/profile')
            ->assertOk()
            ->assertSee('Maklumat Profil')
            ->assertSee($user->no_kad_pengenalan)
            ->assertSee('Pegawai Teknologi Maklumat')
            ->assertSee('F — Teknologi Maklumat')
            ->assertSee('Jabatan Kerja Raya');

        Volt::actingAs($user)->test('profile.update-profile-information-form')
            ->assertSet('phone_office', '067650000')
            ->assertSet('grade_id', (string) $this->grade9->id)
            ->set('phone_office', '061234567')
            ->set('phone_mobile', '0198765432')
            ->set('grade_id', (string) $this->grade10->id)
            ->set('email', " Baharu.{$role}@NS.gov.my ")
            ->call('updateProfileInformation')
            ->assertHasNoErrors()
            ->assertDispatched('profile-updated');

        $user->refresh();
        $this->assertSame('061234567', $user->phone_office, 'Sifar di hadapan dikekalkan');
        $this->assertSame('0198765432', $user->phone_mobile);
        $this->assertSame($this->grade10->id, $user->grade_id);
        $this->assertSame("baharu.{$role}@ns.gov.my", $user->email);
        $this->assertNull($user->email_verified_at, 'E-mel baharu belum disahkan');
        $this->assertSame([$role], $user->getRoleNames()->all());
    }

    // 6–7. E-mel
    public function test_invalid_or_duplicate_email_is_rejected(): void
    {
        $user = $this->profiled('pengguna_biasa', ['email' => 'saya@ns.gov.my']);
        User::factory()->create(['email' => 'Lain@ns.gov.my']);
        User::factory()->create(['email' => 'dipadam@ns.gov.my'])->delete();

        foreach (['bukan-emel', '', 'LAIN@ns.gov.my', 'dipadam@ns.gov.my'] as $email) {
            $component = Volt::actingAs($user)->test('profile.update-profile-information-form')
                ->set('email', $email)
                ->call('updateProfileInformation')
                ->assertHasErrors(['email'])
                ->assertSet('email', strtolower(trim($email)));
        }

        $this->assertStringNotContainsString('SQLSTATE', $component->errors()->first('email'));
        $this->assertSame('saya@ns.gov.my', $user->fresh()->email);
    }

    // 10. Gred tidak sah / tidak aktif / bukan milik jawatan
    public function test_invalid_inactive_or_unlinked_grades_are_rejected(): void
    {
        $user = $this->profiled('pengguna_biasa');
        $inactive = $this->grade10;
        $inactive->update(['is_active' => false]);
        $unlinked = Grade::where('code', '14')->firstOrFail();

        foreach (['', '999999', (string) $inactive->id, (string) $unlinked->id] as $gradeId) {
            Volt::actingAs($user)->test('profile.update-profile-information-form')
                ->set('grade_id', $gradeId)
                ->call('updateProfileInformation')
                ->assertHasErrors(['grade_id']);
        }

        // Pilihan dropdown hanya gred aktif yang dipautkan.
        Volt::actingAs($user)->test('profile.update-profile-information-form')
            ->assertViewHas('grades', fn ($grades) => $grades->pluck('code')->all() === ['9']);

        $this->assertSame($this->grade9->id, $user->fresh()->grade_id);
    }

    public function test_grade_is_locked_when_user_has_no_valid_position(): void
    {
        $legacy = $this->profiled('pengguna_biasa', ['position_id' => null, 'grade_id' => null]);

        Volt::actingAs($legacy)->test('profile.update-profile-information-form')
            ->assertViewHas('canChangeGrade', false)
            ->assertSee('Sila hubungi Urus Setia')
            ->set('grade_id', (string) $this->grade9->id)
            ->set('phone_office', '061111111')
            ->call('updateProfileInformation')
            ->assertHasNoErrors();

        $legacy->refresh();
        $this->assertNull($legacy->grade_id, 'Gred tidak diubah tanpa Jawatan yang sah');
        $this->assertSame('061111111', $legacy->phone_office);
    }

    public function test_phone_numbers_must_be_digits_only(): void
    {
        $user = $this->profiled('pengguna_biasa');

        foreach (['06-7659999', '012 3456789', '12345', '+60123456789', ''] as $phone) {
            Volt::actingAs($user)->test('profile.update-profile-information-form')
                ->set('phone_office', $phone)->set('phone_mobile', $phone)
                ->call('updateProfileInformation')
                ->assertHasErrors(['phone_office', 'phone_mobile']);
        }

        $this->assertSame('067650000', $user->fresh()->phone_office);
    }

    // 8–9. Hanya profil sendiri; medan sensitif tidak boleh diubah
    public function test_only_own_profile_is_updated_and_sensitive_fields_are_protected(): void
    {
        $user = $this->profiled('pengguna_biasa', ['email' => 'saya@ns.gov.my']);
        $other = $this->profiled('pengguna_biasa', ['email' => 'orang.lain@ns.gov.my']);
        $otherSnapshot = $other->only(['phone_office', 'phone_mobile', 'grade_id', 'email']);

        $component = Volt::actingAs($user)->test('profile.update-profile-information-form');

        foreach (['user_id', 'id', 'uuid', 'userUuid', 'name', 'no_kad_pengenalan', 'agency_id', 'position_id', 'role', 'role_id', 'status', 'inactive_reason', 'approved_by', 'approved_at', 'must_change_password', 'password'] as $field) {
            try {
                $component->set($field, 'x');
                $this->fail("Medan [{$field}] tidak sepatutnya wujud pada borang Profil.");
            } catch (PublicPropertyNotFoundException) {
                // Dijangka.
            }
        }

        $component->set('phone_office', '062222222')->call('updateProfileInformation')->assertHasNoErrors();

        $this->assertSame('062222222', $user->fresh()->phone_office);
        $this->assertSame($otherSnapshot, $other->fresh()->only(['phone_office', 'phone_mobile', 'grade_id', 'email']));

        $user->refresh();
        $this->assertSame(['pengguna_biasa'], $user->getRoleNames()->all());
        $this->assertSame(UserStatus::Active, $user->status);
        $this->assertSame($this->agency->id, $user->agency_id);
        $this->assertSame($this->position->id, $user->position_id);
    }

    // 11. Pengguna tidak aktif / kata laluan sementara tidak boleh memintas sekatan
    public function test_inactive_or_blocked_users_cannot_use_profile(): void
    {
        $inactive = $this->profiled('pengguna_biasa');
        $inactive->forceFill(['status' => UserStatus::Inactive, 'inactive_reason' => InactiveReason::Retired])->save();

        $this->actingAs($inactive)->get('/profile')->assertRedirect(route('login'));
        $this->assertGuest();

        // Tindakan Livewire terus (lapisan kedua dalam komponen).
        Volt::actingAs($inactive)->test('profile.update-profile-information-form')
            ->set('phone_office', '063333333')
            ->call('updateProfileInformation')
            ->assertForbidden();

        $this->assertSame('067650000', $inactive->fresh()->phone_office);
    }

    public function test_livewire_payload_from_deactivated_session_is_blocked(): void
    {
        $user = $this->profiled('pengurusan');
        $snapshot = $this->snapshot($this->actingAs($user)->get('/profile')->assertOk()->getContent());

        $user->forceFill(['status' => UserStatus::Inactive, 'inactive_reason' => InactiveReason::TransferredOut])->save();

        $this->actingAs($user->fresh())->withHeaders(['X-Livewire' => 'true'])->postJson('/livewire/update', ['components' => [[
            'snapshot' => $snapshot,
            'updates' => ['phone_office' => '064444444'],
            'calls' => [['path' => '', 'method' => 'updateProfileInformation', 'params' => []]],
        ]]])->assertForbidden();

        $this->assertSame('067650000', $user->fresh()->phone_office);

        $temporary = $this->profiled('pengguna_biasa');
        $temporary->forceFill(['must_change_password' => true])->save();
        $this->flushHeaders();
        $this->actingAs($temporary)->get('/profile')->assertRedirect(route('password.change'));
    }

    // 13. Audit
    public function test_profile_update_is_audited_once_without_secrets(): void
    {
        config(['audit.console' => true]);
        $user = $this->profiled('pengguna_biasa', ['email' => 'saya@ns.gov.my']);
        $before = Audit::where('auditable_id', $user->id)->where('auditable_type', User::class)->count();

        Volt::actingAs($user)->test('profile.update-profile-information-form')
            ->set('phone_mobile', '0177777777')->set('email', 'saya.baru@ns.gov.my')
            ->call('updateProfileInformation')->assertHasNoErrors();

        $audits = Audit::where('auditable_id', $user->id)->where('auditable_type', User::class)->get();
        $this->assertSame($before + 1, $audits->count(), 'Satu rekod audit sahaja');

        $audit = $audits->last();
        $this->assertSame('updated', $audit->event);
        $this->assertSame('0177777777', $audit->new_values['phone_mobile']);
        $this->assertSame('saya.baru@ns.gov.my', $audit->new_values['email']);
        $this->assertSame($user->id, (int) $audit->user_id);
        $raw = json_encode($audit->getAttributes());
        $this->assertStringNotContainsString('password', $raw);
        $this->assertStringNotContainsString('remember_token', $raw);
    }

    public function test_failed_save_leaves_no_partial_update(): void
    {
        $user = $this->profiled('pengguna_biasa', ['email' => 'saya@ns.gov.my']);

        User::saving(function (User $model): void {
            if ($model->isDirty('phone_mobile')) {
                throw new RuntimeException('Simulasi kegagalan.');
            }
        });

        try {
            Volt::actingAs($user)->test('profile.update-profile-information-form')
                ->set('phone_office', '065555555')->set('phone_mobile', '0155555555')->set('email', 'lain.baru@ns.gov.my')
                ->call('updateProfileInformation');
            $this->fail('Exception dijangka.');
        } catch (RuntimeException) {
            // Dijangka.
        }

        $fresh = User::query()->find($user->id);
        $this->assertSame('067650000', $fresh->getRawOriginal('phone_office'));
        $this->assertSame('saya@ns.gov.my', $fresh->getRawOriginal('email'));
    }

    // 12. Fungsi lain tidak terjejas
    public function test_profile_link_is_in_user_menu_and_password_form_still_works(): void
    {
        $user = $this->profiled('pentadbir_urus_setia');

        $this->actingAs($user)->get('/dashboard')->assertOk()->assertSee(route('profile'), false)->assertSee('Profil');
        $this->actingAs($user)->get('/profile')->assertOk()->assertSeeVolt('profile.update-password-form');
        Auth::logout();
    }

    // --- Helpers ---

    private int $sequence = 0;

    private function profiled(string $role, array $attributes = []): User
    {
        $this->sequence++;
        $user = User::factory()->create(array_merge([
            'agency_id' => $this->agency->id,
            'no_kad_pengenalan' => sprintf('90010105%04d', $this->sequence),
            'position_id' => $this->position->id,
            'grade_id' => $this->grade9->id,
            'phone_office' => '067650000',
            'phone_mobile' => '0120000000',
        ], $attributes));
        $user->assignRole($role);

        return $user->fresh();
    }

    private function snapshot(string $html): string
    {
        preg_match_all('/wire:snapshot="([^"]+)"/', $html, $matches);

        foreach ($matches[1] as $encoded) {
            $snapshot = html_entity_decode($encoded, ENT_QUOTES);
            if ((json_decode($snapshot, true)['memo']['name'] ?? null) === 'profile.update-profile-information-form') {
                return $snapshot;
            }
        }

        $this->fail('Snapshot Profil tidak dijumpai.');
    }
}
