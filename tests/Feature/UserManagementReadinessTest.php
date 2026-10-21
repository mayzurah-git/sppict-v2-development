<?php

namespace Tests\Feature;

use App\Enums\UserStatus;
use App\Livewire\Admin\GradeManagement;
use App\Livewire\Admin\PositionManagement;
use App\Models\ApplicationOfficer;
use App\Models\Grade;
use App\Models\Position;
use App\Models\ServiceScheme;
use App\Models\User;
use Database\Seeders\GradeSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use OwenIt\Auditing\Models\Audit;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Fasa UM1: kesediaan database & model bagi Modul Pengurusan Pengguna.
 */
class UserManagementReadinessTest extends TestCase
{
    use RefreshDatabase;

    private User $superadmin;

    private ServiceScheme $scheme;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'superadmin']);
        $this->superadmin = User::factory()->create();
        $this->superadmin->assignRole('superadmin');

        $this->seed(GradeSeeder::class);
        $this->scheme = ServiceScheme::create(['code' => 'F', 'name' => 'Teknologi Maklumat', 'is_active' => true]);

        // Jawatan id=1 ialah rekod legacy (read-only) seperti dalam data sebenar.
        Position::create(['title' => 'Jawatan Legacy', 'is_active' => false]);
    }

    // --- Users ---

    public function test_new_users_default_to_active_without_forced_password_change(): void
    {
        $user = User::factory()->create()->fresh();

        $this->assertSame(UserStatus::Active, $user->status);
        $this->assertTrue($user->isActive());
        $this->assertFalse($user->must_change_password);
    }

    public function test_pending_state_and_status_scopes(): void
    {
        $pending = User::factory()->pending()->create();

        $this->assertTrue($pending->fresh()->isPending());
        $this->assertTrue(User::pending()->whereKey($pending->id)->exists());
        $this->assertFalse(User::active()->whereKey($pending->id)->exists());
        $this->assertTrue(User::active()->whereKey($this->superadmin->id)->exists());
    }

    public function test_status_and_approval_fields_are_not_mass_assignable(): void
    {
        $user = new User([
            'status' => UserStatus::Pending->value,
            'approved_by' => $this->superadmin->id,
            'must_change_password' => true,
        ]);

        $this->assertSame(UserStatus::Active, $user->status);
        $this->assertNull($user->approved_by);
        $this->assertFalse($user->must_change_password);
    }

    public function test_ic_and_phone_numbers_keep_leading_zeros(): void
    {
        $user = User::factory()->create([
            'no_kad_pengenalan' => '050101051234',
            'phone_office' => '067659999',
            'phone_mobile' => '0123456789',
        ])->fresh();

        $this->assertSame('050101051234', $user->no_kad_pengenalan);
        $this->assertSame('067659999', $user->phone_office);
        $this->assertSame('0123456789', $user->phone_mobile);
    }

    public function test_ic_number_is_unique(): void
    {
        User::factory()->create(['no_kad_pengenalan' => '900101051234']);

        $this->expectException(QueryException::class);
        User::factory()->create(['no_kad_pengenalan' => '900101051234']);
    }

    public function test_user_grade_and_approver_relationships(): void
    {
        $grade = Grade::where('code', '9')->first();
        $user = User::factory()->create(['grade_id' => $grade->id]);
        $user->forceFill(['approved_by' => $this->superadmin->id, 'approved_at' => now()])->save();

        $user = $user->fresh();
        $this->assertTrue($user->grade->is($grade));
        $this->assertTrue($user->approver->is($this->superadmin));
        $this->assertTrue($grade->users()->whereKey($user->id)->exists());
    }

    public function test_user_changes_are_audited_without_password(): void
    {
        config(['audit.console' => true]);

        $user = User::factory()->create();
        $user->update(['name' => 'Nama Baharu', 'password' => 'rahsia-baharu']);

        $audit = Audit::where('auditable_type', User::class)
            ->where('auditable_id', $user->id)
            ->where('event', 'updated')
            ->latest('id')
            ->first();

        $this->assertNotNull($audit);
        $this->assertSame('Nama Baharu', $audit->new_values['name']);
        $this->assertArrayNotHasKey('password', $audit->new_values);
    }

    public function test_officer_snapshot_prefers_user_grade_and_mobile_phone(): void
    {
        $grade = Grade::where('code', '10')->first();
        $user = User::factory()->create([
            'grade_id' => $grade->id,
            'phone_number' => '06-7657777',
            'phone_mobile' => '0123456789',
        ]);

        $snapshot = ApplicationOfficer::snapshotFrom($user);

        $this->assertSame('10', $snapshot['grade']);
        $this->assertSame('0123456789', $snapshot['phone']);
    }

    // --- Position ↔ Grade (pivot) ---

    public function test_superadmin_can_create_position_with_multiple_grades(): void
    {
        $grades = Grade::whereIn('code', ['9', '10', '12'])->pluck('id')->all();

        Livewire::actingAs($this->superadmin)
            ->test(PositionManagement::class)
            ->set('title', 'Pegawai Teknologi Maklumat')
            ->set('service_scheme_id', $this->scheme->id)
            ->set('grade_ids', array_map('strval', $grades))
            ->call('save')
            ->assertHasNoErrors();

        $position = Position::where('title', 'Pegawai Teknologi Maklumat')->first();
        $this->assertEqualsCanonicalizing($grades, $position->grades()->pluck('grades.id')->all());
        $this->assertNull($position->grade_id);
    }

    public function test_editing_position_syncs_grades(): void
    {
        $position = $this->makePosition(['9', '10']);
        $grade12 = Grade::where('code', '12')->value('id');

        Livewire::actingAs($this->superadmin)
            ->test(PositionManagement::class)
            ->call('openModal', $position->uuid)
            ->assertSet('grade_ids', array_map('strval', $position->grades()->pluck('grades.id')->all()))
            ->set('grade_ids', [(string) $grade12])
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame([$grade12], $position->grades()->pluck('grades.id')->all());
    }

    public function test_position_requires_at_least_one_active_grade(): void
    {
        $inactive = Grade::where('code', '15')->first();
        $inactive->update(['is_active' => false]);

        Livewire::actingAs($this->superadmin)
            ->test(PositionManagement::class)
            ->set('title', 'Jawatan Ujian')
            ->set('service_scheme_id', $this->scheme->id)
            ->set('grade_ids', [])
            ->call('save')
            ->assertHasErrors(['grade_ids' => 'required'])
            ->set('grade_ids', [(string) $inactive->id])
            ->call('save')
            ->assertHasErrors(['grade_ids.0']);

        $this->assertFalse(Position::where('title', 'Jawatan Ujian')->exists());
    }

    public function test_position_cannot_be_activated_without_an_active_grade(): void
    {
        $position = $this->makePosition(['14'], active: false);
        Grade::where('code', '14')->update(['is_active' => false]);

        Livewire::actingAs($this->superadmin)
            ->test(PositionManagement::class)
            ->call('setStatus', $position->uuid, true);

        $this->assertFalse($position->fresh()->is_active);
    }

    public function test_grade_linked_to_position_or_user_cannot_be_deleted(): void
    {
        $this->makePosition(['9']);
        $usedByPosition = Grade::where('code', '9')->first();
        $usedByUser = Grade::where('code', '11')->first();
        User::factory()->create(['grade_id' => $usedByUser->id]);

        Livewire::actingAs($this->superadmin)
            ->test(GradeManagement::class)
            ->call('delete', $usedByPosition->uuid)
            ->call('delete', $usedByUser->uuid);

        $this->assertNotSoftDeleted($usedByPosition);
        $this->assertNotSoftDeleted($usedByUser);
    }

    // --- Data migration ---

    public function test_pivot_migration_copies_legacy_position_grade(): void
    {
        $migration = require database_path('migrations/2026_10_08_000001_create_grade_position_table.php');
        $migration->down();

        $grade = Grade::where('code', '5')->first();
        $positionId = DB::table('positions')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'title' => 'Penolong Pegawai Tadbir',
            'service_scheme_id' => $this->scheme->id,
            'grade_id' => $grade->id,
            'is_active' => true,
        ]);

        $migration->up();

        $this->assertDatabaseHas('grade_position', ['position_id' => $positionId, 'grade_id' => $grade->id]);
    }

    private function makePosition(array $gradeCodes, bool $active = true): Position
    {
        $position = Position::create([
            'title' => 'Jawatan ' . Str::random(6),
            'service_scheme_id' => $this->scheme->id,
            'is_active' => $active,
        ]);
        $position->grades()->sync(Grade::whereIn('code', $gradeCodes)->pluck('id'));

        return $position;
    }
}
