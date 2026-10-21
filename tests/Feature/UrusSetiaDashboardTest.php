<?php

namespace Tests\Feature;

use App\Enums\UserStatus;
use App\Models\Agency;
use App\Models\Application;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\Concerns\OpensJtictMeeting;
use Tests\TestCase;

/**
 * Papan Pemuka Urus Setia: status JTICT, ringkasan status permohonan, mesyuarat akan datang
 * dan senarai Perlu Tindakan dipaparkan daripada data sebenar.
 */
class UrusSetiaDashboardTest extends TestCase
{
    use RefreshDatabase, OpensJtictMeeting;

    private Agency $agency;

    private User $urusSetia;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['pengguna_biasa', 'pentadbir_urus_setia', 'pengurusan', 'superadmin'] as $role) {
            Role::create(['name' => $role]);
        }

        $this->agency = Agency::create(['code' => 'JKR', 'name' => 'Jabatan Kerja Raya', 'category' => 'STATE_DEPT', 'is_active' => true]);

        $this->urusSetia = User::factory()->create(['name' => 'Siti Urus Setia', 'agency_id' => $this->agency->id]);
        $this->urusSetia->assignRole('pentadbir_urus_setia');
    }

    public function test_dashboard_shows_greeting_and_no_open_jtict_message(): void
    {
        $this->actingAs($this->urusSetia)->get('/dashboard')
            ->assertOk()
            ->assertSee('Papan Pemuka Urus Setia')
            ->assertSee('Selamat datang, Siti Urus Setia')
            ->assertSee('Tiada Mesyuarat JTICT yang dibuka buat masa ini')
            ->assertSee('Tiada mesyuarat aktif yang dijadualkan.');
    }

    public function test_dashboard_shows_open_jtict_meeting_and_upcoming_meeting(): void
    {
        $meeting = $this->openJtictMeeting(['series' => 'Bil. 4/2026']);

        $this->actingAs($this->urusSetia)->get('/dashboard')
            ->assertOk()
            ->assertSee('Permohonan dibuka untuk JTICTNS Bil. 4/2026')
            ->assertSee($meeting->submission_closes_at->format('d/m/Y'))
            ->assertSee('7 hari')
            ->assertSee('Bilik Mesyuarat Ujian');
    }

    public function test_status_cards_count_applications_by_status_excluding_drafts(): void
    {
        $applicant = User::factory()->create(['agency_id' => $this->agency->id]);

        foreach (['SUBMITTED', 'SUBMITTED', 'APPROVED', 'DRAFT'] as $i => $status) {
            Application::create([
                'reference_number' => 'SPPICT/2026/' . str_pad((string) ($i + 1), 4, '0', STR_PAD_LEFT),
                'agency_id' => $this->agency->id,
                'applicant_id' => $applicant->id,
                'title' => 'Projek Ujian ' . $i,
                'estimated_cost' => 10000,
                'status' => $status,
            ]);
        }

        $response = $this->actingAs($this->urusSetia)->get('/dashboard')->assertOk();

        $html = $response->getContent();
        $this->assertMatchesRegularExpression('/>Dihantar<\/span>.*?>2<\/span>/s', $html);
        $this->assertMatchesRegularExpression('/>Lulus<\/span>.*?>1<\/span>/s', $html);
        $this->assertMatchesRegularExpression('/>Perlu Pembetulan<\/span>.*?>0<\/span>/s', $html);
    }

    public function test_pending_registrations_are_listed_with_link_to_review_page(): void
    {
        User::factory()->create(['name' => 'Pemohon Menunggu', 'agency_id' => $this->agency->id, 'status' => UserStatus::Pending]);
        User::factory()->create(['name' => 'Pemohon Ditolak', 'agency_id' => $this->agency->id, 'status' => UserStatus::Rejected]);

        $this->actingAs($this->urusSetia)->get('/dashboard')
            ->assertOk()
            ->assertSee('Pemohon Menunggu')
            ->assertDontSee('Pemohon Ditolak')
            ->assertSee(route('users.requests'));
    }

    public function test_other_roles_do_not_see_urus_setia_dashboard(): void
    {
        $user = User::factory()->create(['agency_id' => $this->agency->id]);
        $user->assignRole('pengguna_biasa');

        $this->actingAs($user)->get('/dashboard')
            ->assertOk()
            ->assertDontSee('Papan Pemuka Urus Setia');
    }
}
