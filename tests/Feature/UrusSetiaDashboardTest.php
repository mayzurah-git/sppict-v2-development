<?php

namespace Tests\Feature;

use App\Enums\UserStatus;
use App\Models\Agency;
use App\Models\Application;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\Concerns\OpensJtictMeeting;
use Tests\TestCase;

/**
 * Papan Pemuka Urus Setia: status JTICT, kad Perlu Tindakan (boleh diklik), senarai ikut ?status=,
 * mesyuarat akan datang & bar menu satu baris dipaparkan daripada data sebenar.
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

    private function cardValue(string $html, string $title): ?string
    {
        return preg_match('/>' . preg_quote($title, '/') . '<\/span>.*?text-4xl[^>]*>([\d,]+)<\/span>/s', $html, $m) ? $m[1] : null;
    }

    public function test_action_cards_count_only_work_awaiting_urus_setia_and_link_to_lists(): void
    {
        $applicant = User::factory()->create(['agency_id' => $this->agency->id]);

        // Tidak Lengkap, Pindaan Pra JTICT, Selesai & Draf menunggu pemohon / telah selesai: tiada kad.
        foreach (['SUBMITTED', 'SUBMITTED', 'IN_JTICT', 'NEEDS_AMENDMENT', 'PRE_JTICT_AMENDMENT', 'CLOSED', 'DRAFT'] as $i => $status) {
            Application::create([
                'reference_number' => 'SPPICT/2026/' . str_pad((string) ($i + 1), 4, '0', STR_PAD_LEFT),
                'agency_id' => $this->agency->id,
                'applicant_id' => $applicant->id,
                'title' => 'Projek Ujian ' . $i,
                'estimated_cost' => 10000,
                'status' => $status,
            ]);
        }
        User::factory()->create(['name' => 'Pemohon Menunggu', 'agency_id' => $this->agency->id, 'status' => UserStatus::Pending]);
        User::factory()->create(['name' => 'Pemohon Ditolak', 'agency_id' => $this->agency->id, 'status' => UserStatus::Rejected]);

        $html = $this->actingAs($this->urusSetia)->get('/dashboard')->assertOk()->getContent();

        $this->assertStringContainsString('Perlu Tindakan Anda', $html);
        $this->assertSame('2', $this->cardValue($html, 'Semakan Permohonan'));
        $this->assertSame('0', $this->cardValue($html, 'Keputusan Pra JTICT'));
        $this->assertSame('1', $this->cardValue($html, 'Keputusan JTICT'));
        $this->assertSame('1', $this->cardValue($html, 'Pendaftaran Pengguna'));
        $this->assertStringNotContainsString('>Tidak Lengkap</span>', $html);
        $this->assertStringNotContainsString('>Selesai</span>', $html);

        foreach ([
            route('dashboard', ['status' => 'SUBMITTED']) . '#senarai-permohonan',
            route('pre-jtict.index'),
            route('jtict.index'), // PP4.3A: kad Keputusan JTICT ke senarai keputusan JTICT

            route('users.requests'),
        ] as $href) {
            $this->assertStringContainsString('href="' . e($href) . '"', $html);
        }
    }

    public function test_status_query_filters_the_review_list(): void
    {
        $applicant = User::factory()->create(['agency_id' => $this->agency->id]);
        foreach (['SUBMITTED' => 'Projek Dihantar', 'IN_JTICT' => 'Projek Dalam JTICT'] as $status => $title) {
            Application::create([
                'reference_number' => 'SPPICT/2026/' . $status, 'agency_id' => $this->agency->id, 'applicant_id' => $applicant->id,
                'title' => $title, 'estimated_cost' => 10000, 'status' => $status,
            ]);
        }

        // Hanya jadual senarai disemak (amaran "Tanpa Pegawai Utama" di lajur sisi turut menyenaraikan tajuk).
        // SP5: betweenFirst kerana papan pemuka kini mempunyai jadual statistik projek selepas senarai permohonan.
        $table = fn (string $query) => Str::betweenFirst($this->actingAs($this->urusSetia)->get('/dashboard' . $query)->assertOk()->getContent(), '<tbody', '</tbody>');

        $this->assertStringContainsString('Projek Dihantar', $table(''));
        $this->assertStringNotContainsString('Projek Dalam JTICT', $table(''));

        $this->assertStringContainsString('Projek Dalam JTICT', $table('?status=IN_JTICT'));
        $this->assertStringNotContainsString('Projek Dihantar', $table('?status=IN_JTICT'));
        $this->actingAs($this->urusSetia)->get('/dashboard?status=IN_JTICT')->assertSee('Permohonan Dalam Proses JTICT');

        // Nilai tidak sah kembali kepada lalai Dihantar.
        $this->assertStringContainsString('Projek Dihantar', $table('?status=BUKAN'));
    }

    public function test_menu_bar_groups_urus_setia_items_into_dropdowns(): void
    {
        $this->actingAs($this->urusSetia)->get('/dashboard')
            ->assertOk()
            ->assertSeeInOrder(['Papan Pemuka', 'Permohonan', 'Semakan Permohonan', 'Keputusan Pra JTICT', 'Keputusan JTICT', 'Mesyuarat', 'Pengguna', 'Tetapan', 'Pengurusan Agensi', 'Kategori Permohonan'])
            ->assertSee('Pautan Pantas');
    }

    public function test_review_table_has_no_kemaskini_column_for_urus_setia_only(): void
    {
        $this->actingAs($this->urusSetia)->get('/dashboard')
            ->assertOk()
            ->assertDontSee('<th scope="col" class="px-4 py-3">Kemaskini</th>', false);

        $user = User::factory()->create(['agency_id' => $this->agency->id]);
        $user->assignRole('pengguna_biasa');

        $this->actingAs($user)->get('/dashboard')
            ->assertOk()
            ->assertSee('<th scope="col" class="px-4 py-3">Kemaskini</th>', false);
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
