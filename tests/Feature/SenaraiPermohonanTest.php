<?php

namespace Tests\Feature;

use App\Livewire\ApplicationList;
use App\Models\Agency;
use App\Models\Application;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Halaman Senarai Permohonan (Pengguna Biasa) dan menu Permohonan ▾ (Senarai Permohonan | Permohonan Baru).
 */
class SenaraiPermohonanTest extends TestCase
{
    use RefreshDatabase;

    private Agency $agency;

    private User $pemohon;

    private int $seq = 0;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['pengguna_biasa', 'pentadbir_urus_setia', 'pengurusan', 'superadmin'] as $role) {
            Role::create(['name' => $role]);
        }

        $this->agency = Agency::create(['code' => 'JKR', 'name' => 'Jabatan Kerja Raya', 'category' => 'STATE_DEPT', 'is_active' => true]);
        $this->pemohon = User::factory()->create(['agency_id' => $this->agency->id]);
        $this->pemohon->assignRole('pengguna_biasa');
    }

    private function application(string $status, string $title, ?User $applicant = null): Application
    {
        $this->seq++;

        return Application::create([
            'reference_number' => 'SPPICT/2026/' . str_pad((string) $this->seq, 4, '0', STR_PAD_LEFT),
            'agency_id' => $this->agency->id,
            'applicant_id' => ($applicant ?? $this->pemohon)->id,
            'title' => $title,
            'estimated_cost' => 10000,
            'status' => $status,
        ]);
    }

    public function test_page_lists_own_applications_with_new_application_button(): void
    {
        $this->application('DRAFT', 'Draf Saya');
        $other = User::factory()->create(['agency_id' => $this->agency->id]);
        $other->assignRole('pengguna_biasa');
        $this->application('DRAFT', 'Draf Orang Lain', $other);

        $this->actingAs($this->pemohon)->get(route('applications.index'))
            ->assertOk()
            ->assertSee('Senarai Permohonan')
            ->assertSee('Draf Saya')
            ->assertDontSee('Draf Orang Lain')
            ->assertSee(route('application.create'), false)
            ->assertSee('bg-sky-700', false);
    }

    public function test_only_pengguna_biasa_can_open_the_page(): void
    {
        foreach (['pentadbir_urus_setia', 'pengurusan', 'superadmin'] as $role) {
            $user = User::factory()->create(['agency_id' => $this->agency->id]);
            $user->assignRole($role);
            $this->actingAs($user)->get(route('applications.index'))->assertForbidden();
        }
    }

    public function test_group_buttons_filter_and_count_applications(): void
    {
        $this->application('DRAFT', 'Projek Draf');
        $this->application('NEEDS_AMENDMENT', 'Projek Pindaan');
        $this->application('SUBMITTED', 'Projek Dihantar');
        $this->application('IN_JTICT', 'Projek JTICT');
        $this->application('CLOSED', 'Projek Selesai');

        $component = Livewire::actingAs($this->pemohon)->test(ApplicationList::class)
            ->assertViewHas('groupCounts', ['' => 5, 'tindakan' => 2, 'proses' => 2, 'selesai' => 1]);

        $component->call('setGroup', 'tindakan')
            ->assertSee('Projek Draf')->assertSee('Projek Pindaan')
            ->assertDontSee('Projek Dihantar')->assertDontSee('Projek Selesai');

        $component->call('setGroup', 'proses')
            ->assertSee('Projek Dihantar')->assertSee('Projek JTICT')
            ->assertDontSee('Projek Draf');

        $component->call('setGroup', 'selesai')
            ->assertSee('Projek Selesai')->assertDontSee('Projek JTICT');

        // Nilai tidak sah kembali kepada Semua.
        $component->call('setGroup', 'bukan')
            ->assertSet('group', '')
            ->assertSee('Projek Draf')->assertSee('Projek Selesai');
    }

    public function test_menu_groups_list_and_new_application_under_permohonan(): void
    {
        $this->actingAs($this->pemohon)->get(route('applications.index'))
            ->assertOk()
            ->assertSeeInOrder(['Papan Pemuka', 'Permohonan', 'Senarai Permohonan', 'Permohonan Baru', 'Kemajuan Projek'])
            ->assertDontSee('Permohonan Saya');
    }

    public function test_urus_setia_list_keeps_status_dropdown(): void
    {
        $urusSetia = User::factory()->create(['agency_id' => $this->agency->id]);
        $urusSetia->assignRole('pentadbir_urus_setia');

        Livewire::actingAs($urusSetia)->test(ApplicationList::class)
            ->assertSeeHtml('id="tapis-status"')
            ->assertDontSee('Perlu tindakan');
    }
}
