<?php

namespace Tests\Feature;

use App\Enums\UserStatus;
use App\Livewire\Admin\UserManagement;
use App\Livewire\Users\ManageUsers;
use App\Models\Agency;
use App\Models\Grade;
use App\Models\Position;
use App\Models\ServiceScheme;
use App\Models\User;
use Database\Seeders\GradeSeeder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use OwenIt\Auditing\Models\Audit;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Fasa UM10: kemas kini tahap capaian & profil pengguna sedia ada.
 * - Superadmin: kemas kini peranan kepada mana-mana 4 peranan (daftar baharu kekal Superadmin/Urus Setia).
 * - Urus Setia: kemas kini profil Pengguna Biasa/Pengurusan/Urus Setia; tukar peranan Pengurusan ↔ Pengguna Biasa sahaja.
 */
class UserRoleManagementTest extends TestCase
{
    use RefreshDatabase;

    private Agency $agency;

    private Position $position;

    private Grade $grade;

    private User $superadmin;

    private User $urusSetia;

    private int $sequence = 0;

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
        $this->position->grades()->sync([$this->grade->id]);

        $this->superadmin = $this->profiled('superadmin');
        $this->urusSetia = $this->profiled('pentadbir_urus_setia');
    }

    // =========================================================
    // Superadmin
    // =========================================================

    public function test_superadmin_new_accounts_remain_limited_to_superadmin_and_urus_setia(): void
    {
        Livewire::actingAs($this->superadmin)->test(UserManagement::class)
            ->call('openModal')
            ->assertViewHas('roleOptions', fn ($options) => array_keys($options) === ['pentadbir_urus_setia', 'superadmin']);
    }

    public static function superadminRoleChangeProvider(): array
    {
        return [
            'Urus Setia → Pengguna Biasa' => ['pentadbir_urus_setia', 'pengguna_biasa'],
            'Urus Setia → Pengurusan' => ['pentadbir_urus_setia', 'pengurusan'],
            'Pengguna Biasa → Urus Setia' => ['pengguna_biasa', 'pentadbir_urus_setia'],
            'Pengurusan → Superadmin' => ['pengurusan', 'superadmin'],
            'Superadmin → Urus Setia' => ['superadmin', 'pentadbir_urus_setia'],
        ];
    }

    #[DataProvider('superadminRoleChangeProvider')]
    public function test_superadmin_can_update_any_existing_user_to_any_role(string $from, string $to): void
    {
        config(['audit.console' => true]);
        $target = $this->profiled($from);

        Livewire::actingAs($this->superadmin)->test(UserManagement::class)
            ->call('openModal', $target->uuid)
            ->assertViewHas('roleOptions', fn ($options) => array_keys($options) === ['superadmin', 'pentadbir_urus_setia', 'pengurusan', 'pengguna_biasa'])
            ->set('role', $to)
            ->call('saveUser')
            ->assertHasNoErrors();

        $this->assertSame([$to], $target->fresh()->getRoleNames()->all());
        $this->assertRoleAudit($target, [$from], [$to], $this->superadmin);
    }

    public function test_demoted_urus_setia_loses_urus_setia_access(): void
    {
        $target = $this->profiled('pentadbir_urus_setia');

        Livewire::actingAs($this->superadmin)->test(UserManagement::class)
            ->call('openModal', $target->uuid)->set('role', 'pengurusan')->call('saveUser')->assertHasNoErrors();

        $this->actingAs($target->fresh())->get('/urus-setia/pengguna/permohonan')->assertForbidden();
        $this->actingAs($target->fresh())->get('/dashboard')->assertOk();
    }

    public function test_last_active_superadmin_cannot_be_demoted(): void
    {
        // Superadmin sahaja dalam sistem; cuba turunkan diri sendiri.
        Livewire::actingAs($this->superadmin)->test(UserManagement::class)
            ->call('openModal', $this->superadmin->uuid)
            ->set('role', 'pentadbir_urus_setia')
            ->call('saveUser')
            ->assertHasErrors(['role']);
        $this->assertSame(['superadmin'], $this->superadmin->fresh()->getRoleNames()->all());

        // Superadmin pending tidak dikira sebagai Superadmin aktif.
        $pendingSuperadmin = $this->profiled('superadmin');
        $pendingSuperadmin->forceFill(['status' => UserStatus::Pending])->save();
        Livewire::actingAs($this->superadmin)->test(UserManagement::class)
            ->call('openModal', $this->superadmin->uuid)->set('role', 'pengurusan')->call('saveUser')
            ->assertHasErrors(['role']);

        // Apabila ada Superadmin aktif lain, penurunan dibenarkan.
        $other = $this->profiled('superadmin');
        Livewire::actingAs($this->superadmin)->test(UserManagement::class)
            ->call('openModal', $other->uuid)->set('role', 'pengguna_biasa')->call('saveUser')
            ->assertHasNoErrors();
        $this->assertSame(['pengguna_biasa'], $other->fresh()->getRoleNames()->all());
    }

    // =========================================================
    // Urus Setia: akses & skop
    // =========================================================

    public function test_only_urus_setia_can_open_user_list(): void
    {
        $this->actingAs($this->urusSetia)->get('/urus-setia/pengguna')->assertOk()->assertSee('Senarai Pengguna');

        foreach (['superadmin', 'pengurusan', 'pengguna_biasa'] as $role) {
            $actor = $this->profiled($role);
            $this->actingAs($actor)->get('/urus-setia/pengguna')->assertForbidden();
            Livewire::actingAs($actor)->test(ManageUsers::class)->assertForbidden();
        }

        $temporary = $this->profiled('pentadbir_urus_setia');
        $temporary->forceFill(['must_change_password' => true])->save();
        Livewire::actingAs($temporary)->test(ManageUsers::class)->assertForbidden();
    }

    public function test_list_shows_only_active_non_superadmin_users_in_scope(): void
    {
        $pengguna = $this->profiled('pengguna_biasa', ['name' => 'Ali Pengguna']);
        $pengurusan = $this->profiled('pengurusan', ['name' => 'Bakar Pengurusan']);
        $otherUrusSetia = $this->profiled('pentadbir_urus_setia', ['name' => 'Chong Urus Setia']);
        $this->profiled('superadmin', ['name' => 'Dina Superadmin']);
        $pending = $this->profiled('pengguna_biasa', ['name' => 'Eka Pending']);
        $pending->forceFill(['status' => UserStatus::Pending])->save();
        $mixed = $this->profiled('pengurusan', ['name' => 'Fadi Campuran']);
        $mixed->assignRole('superadmin');

        Livewire::actingAs($this->urusSetia)->test(ManageUsers::class)
            ->assertSee('Ali Pengguna')
            ->assertSee('Bakar Pengurusan')
            ->assertSee('Chong Urus Setia')
            ->assertDontSee('Dina Superadmin')
            ->assertDontSee('Eka Pending')
            ->assertDontSee('Fadi Campuran')
            ->assertDontSee($this->urusSetia->email)
            ->set('roleFilter', 'pengurusan')
            ->assertSee('Bakar Pengurusan')
            ->assertDontSee('Ali Pengguna')
            ->set('roleFilter', '')
            ->set('search', 'Dina')
            ->assertDontSee('Dina Superadmin');
    }

    public function test_urus_setia_cannot_open_superadmin_self_or_pending_users(): void
    {
        $superadmin = $this->profiled('superadmin');
        $pending = $this->profiled('pengguna_biasa');
        $pending->forceFill(['status' => UserStatus::Pending])->save();

        foreach ([$superadmin, $this->urusSetia, $pending] as $target) {
            try {
                Livewire::actingAs($this->urusSetia)->test(ManageUsers::class)->call('openModal', $target->uuid);
                $this->fail('ModelNotFoundException dijangka bagi ' . $target->email);
            } catch (ModelNotFoundException) {
                // Dijangka.
            }
        }
    }

    public function test_target_uuid_is_locked(): void
    {
        $target = $this->profiled('pengguna_biasa');

        $this->expectException(CannotUpdateLockedPropertyException::class);
        Livewire::actingAs($this->urusSetia)->test(ManageUsers::class)
            ->call('openModal', $target->uuid)
            ->set('userUuid', $this->superadmin->uuid);
    }

    // =========================================================
    // Urus Setia: tukar peranan
    // =========================================================

    public static function switchProvider(): array
    {
        return [
            'Pengurusan → Pengguna Biasa' => ['pengurusan', 'pengguna_biasa'],
            'Pengguna Biasa → Pengurusan' => ['pengguna_biasa', 'pengurusan'],
        ];
    }

    #[DataProvider('switchProvider')]
    public function test_urus_setia_can_switch_between_pengurusan_and_pengguna_biasa(string $from, string $to): void
    {
        config(['audit.console' => true]);
        $target = $this->profiled($from);

        Livewire::actingAs($this->urusSetia)->test(ManageUsers::class)
            ->call('openModal', $target->uuid)
            ->assertSet('role', $from)
            ->assertViewHas('roleOptions', fn ($options) => array_keys($options) === ['pengguna_biasa', 'pengurusan'])
            ->set('role', $to)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame([$to], $target->fresh()->getRoleNames()->all());
        $this->assertRoleAudit($target, [$from], [$to], $this->urusSetia);

        // Authorization mengikut peranan baharu.
        $this->actingAs($target->fresh())->get('/application/new')->assertStatus($to === 'pengguna_biasa' ? 200 : 403);
    }

    public function test_urus_setia_cannot_escalate_roles(): void
    {
        $pengguna = $this->profiled('pengguna_biasa');

        foreach (['pentadbir_urus_setia', 'superadmin', 'tidak_wujud'] as $role) {
            Livewire::actingAs($this->urusSetia)->test(ManageUsers::class)
                ->call('openModal', $pengguna->uuid)
                ->set('role', $role)
                ->call('save')
                ->assertHasErrors(['role']);
        }

        $this->assertSame(['pengguna_biasa'], $pengguna->fresh()->getRoleNames()->all());
    }

    public function test_urus_setia_cannot_change_urus_setia_role_but_can_update_profile(): void
    {
        $other = $this->profiled('pentadbir_urus_setia', ['name' => 'Urus Setia Lain']);

        Livewire::actingAs($this->urusSetia)->test(ManageUsers::class)
            ->call('openModal', $other->uuid)
            ->assertViewHas('roleOptions', fn ($options) => array_keys($options) === ['pentadbir_urus_setia'])
            ->assertSee('Peranan Urus Setia hanya boleh ditukar oleh Superadmin')
            ->set('role', 'pengguna_biasa')
            ->call('save')
            ->assertHasErrors(['role' => 'in']);
        $this->assertSame(['pentadbir_urus_setia'], $other->fresh()->getRoleNames()->all());

        Livewire::actingAs($this->urusSetia)->test(ManageUsers::class)
            ->call('openModal', $other->uuid)
            ->set('name', 'Urus Setia Dikemas Kini')
            ->set('phone_mobile', '0199999999')
            ->call('save')
            ->assertHasNoErrors();

        $other->refresh();
        $this->assertSame('Urus Setia Dikemas Kini', $other->name);
        $this->assertSame('0199999999', $other->phone_mobile);
        $this->assertSame(['pentadbir_urus_setia'], $other->getRoleNames()->all());
    }

    // =========================================================
    // Urus Setia: profil & integriti
    // =========================================================

    public function test_profile_update_uses_shared_validation_and_keeps_protected_fields(): void
    {
        $target = $this->profiled('pengurusan');
        $hash = $target->password;
        $otherGrade = Grade::where('code', '14')->firstOrFail();

        Livewire::actingAs($this->urusSetia)->test(ManageUsers::class)
            ->call('openModal', $target->uuid)
            ->set('grade_id', (string) $otherGrade->id)
            ->call('save')
            ->assertHasErrors(['grade_id']);

        Livewire::actingAs($this->urusSetia)->test(ManageUsers::class)
            ->call('openModal', $target->uuid)
            ->set('email', strtoupper($this->superadmin->email))
            ->call('save')
            ->assertHasErrors(['email']);

        // Nilai unik sendiri diabaikan; kata laluan/status/flag tidak disentuh.
        Livewire::actingAs($this->urusSetia)->test(ManageUsers::class)
            ->call('openModal', $target->uuid)
            ->set('name', 'Nama Baharu')
            ->call('save')
            ->assertHasNoErrors();

        $target->refresh();
        $this->assertSame('Nama Baharu', $target->name);
        $this->assertSame($hash, $target->password);
        $this->assertSame(UserStatus::Active, $target->status);
        $this->assertFalse($target->must_change_password);
        $this->assertSame(['pengurusan'], $target->getRoleNames()->all());
    }

    public function test_actions_recheck_permission_and_target_scope(): void
    {
        $target = $this->profiled('pengguna_biasa');

        // Peranan Urus Setia ditarik semasa sesi.
        $component = Livewire::actingAs($this->urusSetia)->test(ManageUsers::class)->call('openModal', $target->uuid)->set('role', 'pengurusan');
        $this->urusSetia->syncRoles(['pengguna_biasa']);
        $component->call('save')->assertForbidden();
        $this->assertSame(['pengguna_biasa'], $target->fresh()->getRoleNames()->all());

        // Sasaran dinaikkan kepada Superadmin selepas modal dibuka: simpan ditolak.
        $this->urusSetia->syncRoles(['pentadbir_urus_setia']);
        $component = Livewire::actingAs($this->urusSetia->fresh())->test(ManageUsers::class)->call('openModal', $target->uuid)->set('role', 'pengurusan');
        $target->syncRoles(['superadmin']);

        try {
            $component->call('save');
            $this->fail('ModelNotFoundException dijangka.');
        } catch (ModelNotFoundException) {
            // Dijangka: sasaran tidak lagi dalam skop Urus Setia.
        }
        $this->assertSame(['superadmin'], $target->fresh()->getRoleNames()->all());
    }

    public function test_role_change_failure_rolls_back_profile_update(): void
    {
        $target = $this->profiled('pengurusan', ['name' => 'Nama Asal']);

        DB::listen(function ($query): void {
            if (str_contains($query->sql, 'insert into "model_has_roles"')) {
                throw new RuntimeException('Simulasi kegagalan.');
            }
        });

        try {
            Livewire::actingAs($this->urusSetia)->test(ManageUsers::class)
                ->call('openModal', $target->uuid)
                ->set('name', 'Nama Baharu')
                ->set('role', 'pengguna_biasa')
                ->call('save');
            $this->fail('Exception dijangka.');
        } catch (RuntimeException) {
            // Dijangka.
        }

        $this->assertSame('Nama Asal', User::find($target->id)->name);
        $this->assertSame(['pengurusan'], User::find($target->id)->getRoleNames()->all());
    }

    // --- Helpers ---

    private function profiled(string $role, array $attributes = []): User
    {
        $this->sequence++;
        $user = User::factory()->create(array_merge([
            'agency_id' => $this->agency->id,
            'no_kad_pengenalan' => sprintf('80010105%04d', $this->sequence),
            'position_id' => $this->position->id,
            'grade_id' => $this->grade->id,
            'phone_office' => '067650000',
            'phone_mobile' => '0120000000',
        ], $attributes));
        $user->assignRole($role);

        return $user;
    }

    private function assertRoleAudit(User $target, array $old, array $new, User $actor): void
    {
        $audit = Audit::where('auditable_type', User::class)->where('auditable_id', $target->id)->where('event', 'role_updated')->latest('id')->firstOrFail();

        $this->assertSame($old, $audit->old_values['roles']);
        $this->assertSame($new, $audit->new_values['roles']);
        $this->assertSame($actor->id, (int) $audit->user_id);
        $this->assertStringNotContainsString('password', json_encode($audit->getAttributes()));
    }
}
