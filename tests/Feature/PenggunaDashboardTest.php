<?php

namespace Tests\Feature;

use App\Enums\OfficerRole;
use App\Models\Agency;
use App\Models\Application;
use App\Models\ApplicationDecision;
use App\Models\ApplicationOfficer;
use App\Models\Project;
use App\Models\ProjectProgressReport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\Concerns\OpensJtictMeeting;
use Tests\TestCase;

/**
 * Papan Pemuka Pengguna Biasa: kad ringkasan permohonan pemohon, perlu tindakan, makluman
 * mesyuarat, senarai permohonan & status kemajuan projek; bar menu biru langit.
 */
class PenggunaDashboardTest extends TestCase
{
    use RefreshDatabase, OpensJtictMeeting;

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

        $this->pemohon = User::factory()->create(['name' => 'Ahmad Pemohon', 'agency_id' => $this->agency->id]);
        $this->pemohon->assignRole('pengguna_biasa');
    }

    private function application(string $status, string $title, ?User $applicant = null): Application
    {
        $this->seq++;

        $application = Application::create([
            'reference_number' => 'SPPICT/2026/' . str_pad((string) $this->seq, 4, '0', STR_PAD_LEFT),
            'agency_id' => $this->agency->id,
            'applicant_id' => ($applicant ?? $this->pemohon)->id,
            'title' => $title,
            'estimated_cost' => 10000,
            'status' => $status,
        ]);

        // Permohonan yang pernah dihantar sentiasa mempunyai Pegawai Utama aktif (pemohon secara lalai);
        // selepas dihantar, hanya pegawai aktif boleh meminda.
        if ($status !== 'DRAFT') {
            $application->officers()->create(ApplicationOfficer::snapshotFrom($applicant ?? $this->pemohon) + [
                'role' => OfficerRole::Primary,
                'started_at' => today()->subMonth(),
            ]);
        }

        return $application;
    }

    private function cardValue(string $html, string $label): ?string
    {
        return preg_match('/>' . preg_quote($label, '/') . '<\/span>.*?text-\[40px\][^>]*>([\d,]+)<\/span>/s', $html, $m) ? $m[1] : null;
    }

    public function test_dashboard_shows_greeting_sky_theme_and_no_meeting_message(): void
    {
        $this->actingAs($this->pemohon)->get('/dashboard')
            ->assertOk()
            ->assertSee('Selamat datang, Ahmad Pemohon')
            ->assertSee('Jabatan Kerja Raya')
            ->assertSee('bg-sky-700', false)
            ->assertSee('Pengguna Biasa · JKR')
            ->assertSee('Tiada Mesyuarat JTICT yang dibuka buat masa ini')
            ->assertSee('Tiada projek dalam pelaksanaan.');
    }

    public function test_summary_cards_count_only_the_applicants_applications(): void
    {
        $draft = $this->application('DRAFT', 'Draf Saya');
        $this->application('NEEDS_AMENDMENT', 'Perlu Pindaan Saya');
        $this->application('SUBMITTED', 'Dihantar Saya');
        $approved = $this->application('CLOSED', 'Lulus Saya');
        $rejected = $this->application('CLOSED', 'Tidak Lulus Saya');

        foreach ([[$approved, 'APPROVED'], [$rejected, 'NOT_APPROVED']] as [$app, $decision]) {
            ApplicationDecision::create([
                'application_id' => $app->id, 'stage' => 'JTICT', 'decision' => $decision,
                'from_status' => 'IN_JTICT', 'to_status' => 'CLOSED', 'decision_date' => today(),
                'decided_by' => $this->pemohon->id,
            ]);
        }

        // Permohonan pengguna lain dalam agensi yang sama tidak dikira.
        $other = User::factory()->create(['agency_id' => $this->agency->id]);
        $other->assignRole('pengguna_biasa');
        $this->application('DRAFT', 'Draf Orang Lain', $other);

        $html = $this->actingAs($this->pemohon)->get('/dashboard')->assertOk()->getContent();

        $this->assertSame('5', $this->cardValue($html, 'Jumlah Permohonan Projek Pemohon'));
        $this->assertSame('1', $this->cardValue($html, 'Permohonan Projek Lulus'));
        $this->assertSame('2', $this->cardValue($html, 'Perlu Tindakan'));
        $this->assertStringNotContainsString('Draf Orang Lain', $html);
        $this->assertStringContainsString(route('application.edit', $draft->uuid), $html);
        $this->assertStringContainsString('JTICT: Lulus', $html);
        $this->assertStringContainsString('JTICT: Tidak Lulus', $html);
    }

    public function test_action_card_shows_urus_setia_remarks_for_incomplete_application(): void
    {
        $app = $this->application('NEEDS_AMENDMENT', 'Projek Tidak Lengkap');
        $app->reviews()->create([
            'from_status' => 'SUBMITTED', 'to_status' => 'NEEDS_AMENDMENT',
            'remarks' => 'Sila lampirkan tiga sebut harga.', 'user_id' => $this->pemohon->id,
        ]);

        $this->actingAs($this->pemohon)->get('/dashboard')
            ->assertOk()
            ->assertSee('Ulasan Urus Setia: Sila lampirkan tiga sebut harga.')
            ->assertSee('Pinda &amp; hantar semula', false);
    }

    public function test_open_jtict_meeting_and_progress_deadline_are_announced(): void
    {
        $this->openJtictMeeting(['series' => 'Bil. 4/2026', 'progress_update_deadline' => today()->addDays(12)]);

        $this->actingAs($this->pemohon)->get('/dashboard')
            ->assertOk()
            ->assertSee('Permohonan dibuka untuk JTICTNS Bil. 4/2026')
            ->assertSee('Kemas kini kemajuan projek untuk JTICTNS Bil. 4/2026')
            ->assertSee(today()->addDays(12)->format('d/m/Y'));
    }

    public function test_project_progress_lists_latest_report_and_indicator(): void
    {
        $app = $this->application('CLOSED', 'Pusat Data Mini');
        // SP5: nilai cache kemajuan SP4 dan status kitaran projek; laporan terkini = laporan Dihantar terkini.
        $project = Project::forceCreate([
            'application_id' => $app->id, 'agency_id' => $this->agency->id,
            'status' => 'IN_PROGRESS',
            'actual_progress_percent' => 35, 'planned_progress_percent' => 60, 'variance_percent' => -25,
            'status_indicator' => 'RED',
        ]);
        foreach ([[8, 'SUBMITTED'], [9, 'SUBMITTED'], [10, 'DRAFT']] as [$month, $status]) {
            ProjectProgressReport::forceCreate([
                'project_id' => $project->id, 'report_year' => 2026, 'report_month' => $month,
                'status' => $status, 'submitted_by' => $this->pemohon->id,
            ]);
        }

        $html = $this->actingAs($this->pemohon)->get('/dashboard')->assertOk()->getContent();

        $this->assertStringContainsString('Pusat Data Mini', $html);
        $this->assertStringContainsString('Lewat · 25 mata', $html);
        $this->assertStringContainsString('dirancang 60%', $html);
        $this->assertStringContainsString('September 2026', $html);   // Draf Oktober tidak dikira
        $this->assertStringNotContainsString('Oktober 2026', $html);
        $this->assertSame('1', $this->cardValue($html, 'Projek Dalam Pelaksanaan'));

        $project->forceFill(['status' => 'COMPLETED'])->save();
        $html = $this->actingAs($this->pemohon)->get('/dashboard')->getContent();
        $this->assertSame('0', $this->cardValue($html, 'Projek Dalam Pelaksanaan'));
    }

    public function test_other_roles_keep_their_own_dashboard_and_nav_colour(): void
    {
        $urusSetia = User::factory()->create(['agency_id' => $this->agency->id]);
        $urusSetia->assignRole('pentadbir_urus_setia');
        $this->actingAs($urusSetia)->get('/dashboard')
            ->assertOk()
            ->assertSee('bg-brand-dark', false)
            ->assertDontSee('Jumlah Permohonan Projek Pemohon');

        $superadmin = User::factory()->create(['agency_id' => $this->agency->id]);
        $superadmin->assignRole('superadmin');
        $this->actingAs($superadmin)->get('/dashboard')
            ->assertOk()
            ->assertSee('bg-indigo-950', false)
            ->assertDontSee('Jumlah Permohonan Projek Pemohon');
    }
}
