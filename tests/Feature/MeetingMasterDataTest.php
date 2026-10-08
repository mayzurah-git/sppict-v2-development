<?php

namespace Tests\Feature;

use App\Livewire\Meetings\MeetingNameManagement;
use App\Livewire\Meetings\MeetingNumberManagement;
use App\Livewire\Meetings\MeetingYearManagement;
use App\Models\MeetingName;
use App\Models\MeetingNumber;
use App\Models\MeetingYear;
use App\Models\User;
use Database\Seeders\MeetingMasterDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MeetingMasterDataTest extends TestCase
{
    use RefreshDatabase;

    private User $urusSetia;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'pentadbir_urus_setia']);
        Role::create(['name' => 'superadmin']);
        Role::create(['name' => 'pengguna_biasa']);

        $this->urusSetia = User::factory()->create();
        $this->urusSetia->assignRole('pentadbir_urus_setia');
    }

    public static function routeProvider(): array
    {
        return [
            'nama' => ['meetings.names'],
            'bilangan' => ['meetings.numbers'],
            'tahun' => ['meetings.years'],
        ];
    }

    #[DataProvider('routeProvider')]
    public function test_master_data_pages_are_accessible_to_urus_setia_only(string $route): void
    {
        $superadmin = User::factory()->create();
        $superadmin->assignRole('superadmin');

        $pengguna = User::factory()->create();
        $pengguna->assignRole('pengguna_biasa');

        $this->actingAs($this->urusSetia)->get(route($route))->assertOk();
        $this->actingAs($superadmin)->get(route($route))->assertForbidden();
        $this->actingAs($pengguna)->get(route($route))->assertForbidden();
    }

    public function test_superadmin_cannot_call_actions_directly(): void
    {
        $superadmin = User::factory()->create();
        $superadmin->assignRole('superadmin');

        Livewire::actingAs($superadmin)
            ->test(MeetingYearManagement::class)
            ->set('year', 2030)
            ->call('save')
            ->assertForbidden();

        $this->assertDatabaseMissing('meeting_years', ['year' => 2030]);
    }

    public function test_meeting_name_can_be_created_and_updated(): void
    {
        Livewire::actingAs($this->urusSetia)
            ->test(MeetingNameManagement::class)
            ->call('openModal')
            ->set('code', ' jtictns ')
            ->set('name', 'Jawatankuasa Teknikal ICT Negeri Sembilan')
            ->set('accepts_applications', true)
            ->call('save')
            ->assertHasNoErrors();

        $meetingName = MeetingName::firstWhere('code', 'JTICTNS');
        $this->assertNotNull($meetingName);
        $this->assertTrue($meetingName->accepts_applications);
        $this->assertSame(1, $meetingName->sort_order);

        Livewire::actingAs($this->urusSetia)
            ->test(MeetingNameManagement::class)
            ->call('openModal', $meetingName->uuid)
            ->set('name', 'Nama Dikemaskini')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('Nama Dikemaskini', $meetingName->fresh()->name);
    }

    public function test_meeting_name_code_must_be_unique(): void
    {
        MeetingName::create(['code' => 'JTICTNS', 'name' => 'Sedia Ada']);

        Livewire::actingAs($this->urusSetia)
            ->test(MeetingNameManagement::class)
            ->set('code', 'jtictns')
            ->set('name', 'Pendua')
            ->call('save')
            ->assertHasErrors(['code' => 'unique']);
    }

    public function test_meeting_number_defaults_to_next_number_and_must_be_unique(): void
    {
        MeetingNumber::create(['number' => 1]);
        MeetingNumber::create(['number' => 2]);

        Livewire::actingAs($this->urusSetia)
            ->test(MeetingNumberManagement::class)
            ->call('openModal')
            ->assertSet('number', 3)
            ->set('number', 2)
            ->call('save')
            ->assertHasErrors(['number' => 'unique'])
            ->set('number', 3)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('Bil. 3', MeetingNumber::firstWhere('number', 3)->label());
    }

    public function test_meeting_year_can_be_deactivated_and_soft_deleted(): void
    {
        $meetingYear = MeetingYear::create(['year' => 2026]);

        $component = Livewire::actingAs($this->urusSetia)->test(MeetingYearManagement::class);

        $component->call('setStatus', $meetingYear->uuid, false);
        $this->assertFalse($meetingYear->fresh()->is_active);

        $component->call('delete', $meetingYear->uuid);
        $this->assertSoftDeleted('meeting_years', ['id' => $meetingYear->id]);
    }

    public function test_master_data_seeder_is_idempotent_and_keeps_user_changes(): void
    {
        $this->seed(MeetingMasterDataSeeder::class);

        MeetingName::firstWhere('code', 'JPICTNS')->update(['name' => 'Diubah Urus Setia']);

        $this->seed(MeetingMasterDataSeeder::class);

        $this->assertSame(2, MeetingName::count());
        $this->assertSame(6, MeetingNumber::count());
        $this->assertSame(2, MeetingYear::count());
        $this->assertTrue(MeetingName::firstWhere('code', 'JTICTNS')->accepts_applications);
        $this->assertSame('Diubah Urus Setia', MeetingName::firstWhere('code', 'JPICTNS')->name);
    }
}
