<?php

namespace Tests\Feature;

use App\Enums\ApplicationDecision as Decision;
use App\Enums\ApplicationStatus;
use App\Enums\DecisionStage;
use App\Enums\OfficerRole;
use App\Enums\UserStatus;
use App\Livewire\ApplicationForm;
use App\Livewire\ViewApplication;
use App\Models\Agency;
use App\Models\Application;
use App\Models\ApplicationCategory;
use App\Models\ApplicationDetail;
use App\Models\ApplicationDocument;
use App\Models\ApplicationOfficer;
use App\Models\ProcurementMethod;
use App\Models\ProcurementType;
use App\Models\ProjectCategory;
use App\Models\User;
use App\Services\ApplicationCopyService;
use App\Services\ApplicationDecisionService;
use App\Services\ApplicationReviewService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use RuntimeException;
use Spatie\Permission\Models\Role;
use Tests\Concerns\OpensJtictMeeting;
use Tests\Concerns\UsesApplicationMasterData;
use Tests\TestCase;

/**
 * PP4.3C: Salin Permohonan bagi keputusan JTICT Kaji Semula (keputusan pemilik 17/11/2026: O7, O8, O10).
 *
 * Nota: dijalankan pada SQLite in-memory. Kunci baris (lockForUpdate) dan permintaan serentak sebenar
 * tidak dapat dibuktikan pada SQLite; ujian serentak disimulasikan secara berurutan.
 */
class ApplicationCopyTest extends TestCase
{
    use RefreshDatabase, OpensJtictMeeting, UsesApplicationMasterData;

    private const JTICT_REMARKS = 'Mesyuarat JTICT meminta skop dan kos projek dikaji semula.';

    private Agency $agency;

    private User $urusSetia;

    private User $registrar;

    private User $primary;

    private User $secondary;

    private int $sequence = 0;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        Notification::fake();

        foreach (['pengguna_biasa', 'pentadbir_urus_setia', 'pengurusan', 'superadmin'] as $role) {
            Role::findOrCreate($role);
        }

        $this->agency = Agency::create(['code' => 'JKR', 'name' => 'Agensi JKR', 'category' => 'SUK', 'is_active' => true]);
        $btm = Agency::create(['code' => 'BTM', 'name' => 'Bahagian Teknologi Maklumat', 'category' => 'SUK', 'is_active' => true]);

        $this->urusSetia = $this->user('pentadbir_urus_setia', ['name' => 'Siti Urus Setia', 'agency_id' => $btm->id]);
        $this->registrar = $this->user('pengguna_biasa', ['name' => 'Ali Pendaftar']);
        $this->primary = $this->user('pengguna_biasa', ['name' => 'Ahmad Pegawai Utama']);
        $this->secondary = $this->user('pengguna_biasa', ['name' => 'Bakar Pegawai Kedua']);
    }

    // --- 1 - 3: KELAYAKAN (DIBENARKAN) ---

    public function test_primary_secondary_officer_and_registrar_in_same_agency_can_copy(): void
    {
        foreach (['Pegawai Utama' => $this->primary, 'Pegawai Kedua' => $this->secondary, 'Pendaftar asal' => $this->registrar] as $label => $user) {
            $source = $this->closedApplication(title: "Projek {$label}");

            $this->assertTrue($user->can('copy', $source), $label);
            $copy = $this->service()->copy($source, $user);

            $this->assertSame($source->id, $copy->copied_from_application_id, $label);
            $this->assertSame($user->id, $copy->applicant_id, $label);
        }
    }

    // --- 4 - 8: KELAYAKAN (DITOLAK) ---

    public function test_registrar_who_moved_agency_cannot_copy(): void
    {
        $source = $this->closedApplication();
        $this->registrar->update(['agency_id' => Agency::create(['code' => 'JPN', 'name' => 'Agensi Lain', 'category' => 'SUK', 'is_active' => true])->id]);

        $this->assertCopyRefused($source, $this->registrar->fresh());
    }

    public function test_urus_setia_who_registered_on_behalf_cannot_copy(): void
    {
        $source = $this->closedApplication(registrar: $this->urusSetia);

        $this->assertSame($this->urusSetia->id, $source->applicant_id);
        $this->assertCopyRefused($source, $this->urusSetia);

        // Pegawai agensi sasaran masih boleh menyalin
        $this->assertTrue($this->primary->can('copy', $source));
    }

    public function test_inactive_user_cannot_copy(): void
    {
        $source = $this->closedApplication();
        $this->primary->forceFill(['status' => UserStatus::Inactive])->save();

        $this->assertCopyRefused($source, $this->primary->fresh());
    }

    public function test_other_agency_user_cannot_copy_or_view(): void
    {
        $source = $this->closedApplication();
        $outsider = $this->user('pengguna_biasa', ['agency_id' => Agency::create(['code' => 'JPN', 'name' => 'Agensi Lain', 'category' => 'SUK', 'is_active' => true])->id]);

        $this->assertCopyRefused($source, $outsider);
        Livewire::actingAs($outsider)->test(ViewApplication::class, ['uuid' => $source->uuid])->assertForbidden();
    }

    public function test_unauthorised_users_cannot_bypass_through_livewire_action_or_service(): void
    {
        $source = $this->closedApplication();
        $colleague = $this->user('pengguna_biasa'); // agensi sama (boleh melihat), bukan pegawai / pendaftar
        $manager = $this->user('pengurusan');
        $source->officers()->where('role', OfficerRole::Secondary->value)->update(['user_id' => $manager->id]);

        foreach (['rakan seagensi' => $colleague, 'pengurusan (pegawai)' => $manager] as $label => $user) {
            Livewire::actingAs($user)->test(ViewApplication::class, ['uuid' => $source->uuid])
                ->assertDontSeeHtml('wire:click="copyApplication"')
                ->assertSee('Anda tidak mempunyai kebenaran membuat salinan permohonan ini.')
                ->call('copyApplication')
                ->assertForbidden();
            $this->assertCopyRefused($source, $user, $label);
        }
    }

    // --- 9 - 12: KELAYAKAN KEPUTUSAN ---

    public function test_only_jtict_review_decision_can_be_copied(): void
    {
        $this->assertTrue($this->primary->can('copy', $this->closedApplication(Decision::Review)));

        foreach ([Decision::Approved, Decision::NotApproved] as $decision) {
            $this->assertCopyRefused($this->closedApplication($decision, "Projek {$decision->value}"), $this->primary, $decision->value);
        }

        // Belum ada keputusan JTICT (Dalam Proses JTICT)
        $this->assertCopyRefused($this->closedApplication(null, 'Projek Dalam JTICT'), $this->primary, 'IN_JTICT');

        // Ditutup oleh Pra JTICT Ditolak (bukan keputusan JTICT)
        $rejected = $this->makeSubmitted('Projek Ditolak Pra JTICT', $this->meeting(today()->addDays(5)));
        app(ApplicationReviewService::class)->review($rejected, ApplicationStatus::Complete, null, $this->urusSetia);
        app(ApplicationDecisionService::class)->decide($rejected->fresh(), DecisionStage::PreJtict, Decision::Rejected, null, today(), $this->urusSetia);
        $this->assertCopyRefused($rejected->fresh(), $this->primary, 'Pra JTICT Ditolak');
    }

    // --- 13 - 16: HAD SATU SALINAN ---

    public function test_only_one_copy_is_allowed_even_after_soft_delete(): void
    {
        $source = $this->closedApplication();

        $copy = $this->service()->copy($source, $this->primary); // 13
        $this->assertCopyRefused($source, $this->secondary, 'salinan kedua'); // 14

        $copy->delete(); // 15: salinan dipadam (soft delete) tetap dikira
        $this->assertSoftDeleted($copy);
        $this->assertCopyRefused($source, $this->primary, 'selepas salinan dipadam');

        $this->assertSame(1, Application::withTrashed()->where('copied_from_application_id', $source->id)->count());
    }

    public function test_repeated_request_with_stale_page_does_not_create_second_copy(): void
    {
        $source = $this->closedApplication();
        $stale = $source->fresh(); // halaman yang dibuka sebelum salinan pertama

        $this->service()->copy($source, $this->primary);

        try {
            $this->service()->copy($stale, $this->secondary);
            $this->fail('Permintaan kedua tidak sepatutnya menghasilkan salinan');
        } catch (AuthorizationException) {
        }

        // Klik berganda pada halaman butiran
        $component = Livewire::actingAs($this->registrar)->test(ViewApplication::class, ['uuid' => $source->uuid]);
        $component->call('copyApplication')->assertForbidden();

        $this->assertSame(1, Application::withTrashed()->where('copied_from_application_id', $source->id)->count());
    }

    public function test_failure_during_copy_leaves_no_partial_records(): void
    {
        $source = $this->closedApplication();
        $before = [Application::withTrashed()->count(), ApplicationDetail::withTrashed()->count(), ApplicationOfficer::withTrashed()->count()];

        ApplicationDetail::creating(function (ApplicationDetail $detail) use ($source) {
            if ($detail->application_id !== $source->id) {
                throw new RuntimeException('Simulasi kegagalan semasa menyalin perincian');
            }
        });

        try {
            $this->service()->copy($source, $this->primary);
            $this->fail('Kegagalan sepatutnya dilontar');
        } catch (RuntimeException) {
        }

        $this->assertSame($before, [Application::withTrashed()->count(), ApplicationDetail::withTrashed()->count(), ApplicationOfficer::withTrashed()->count()]);
        $this->assertTrue($this->primary->can('copy', $source->fresh()), 'Masih boleh disalin selepas kegagalan');
    }

    // --- 17 - 26: INTEGRITI DATA ---

    public function test_copy_contains_f1_to_f3_only_and_original_is_unchanged(): void
    {
        $source = $this->closedApplication();
        $snapshot = $this->snapshot($source);

        $copy = $this->service()->copy($source, $this->primary)->fresh();

        // 17: ID, UUID & nombor rujukan baharu (Draf)
        $this->assertNotSame($source->id, $copy->id);
        $this->assertNotSame($source->uuid, $copy->uuid);
        $this->assertNotSame($source->reference_number, $copy->reference_number);
        $this->assertStringStartsWith('SPPICT/' . date('Y') . '/DRAFT-', $copy->reference_number);

        // 18, 19: hubungan asal; pendaftar = penyalin; agensi kekal
        $this->assertSame($source->id, $copy->copied_from_application_id);
        $this->assertTrue($copy->copiedFromApplication->is($source));
        $this->assertTrue($source->copies()->sole()->is($copy));
        $this->assertSame($this->primary->id, $copy->applicant_id);
        $this->assertSame($source->agency_id, $copy->agency_id);

        // 20: F1 & F2
        foreach (['title', 'application_category', 'objectives', 'project_scope', 'procurement_type_id', 'procurement_type', 'procurement_method_id', 'procurement_method',
            'ceiling_cost', 'estimated_cost', 'subtotal_cost', 'sst_rate', 'sst_amount', 'total_cost', 'expected_duration_months', 'outcome_code'] as $field) {
            $this->assertEquals($source->{$field}, $copy->{$field}, $field);
        }

        // 20, 21: F3 disalin sebagai rekod baharu
        $fields = ['item_category', 'project_category_id', 'technical_specifications', 'unit_quantity', 'unit_cost', 'total_cost'];
        $this->assertSame(
            $source->details()->orderBy('id')->get()->map->only($fields)->all(),
            $copy->details()->orderBy('id')->get()->map->only($fields)->all()
        );
        $this->assertEmpty(array_intersect($source->details()->pluck('id')->all(), $copy->details()->pluck('id')->all()));

        // 22, 23: tiada dokumen, keputusan, semakan, mesyuarat atau ulasan
        $this->assertSame(0, $copy->documents()->withTrashed()->count());
        $this->assertSame(0, $copy->decisions()->count());
        $this->assertSame(0, $copy->reviews()->count());
        $this->assertNull($copy->jtict_meeting_id);
        $this->assertNull($copy->review_remarks);
        $this->assertSame('PENDING', $copy->jtict_decision);
        $this->assertNull($copy->jtict_remarks);

        // Pegawai aktif yang layak disalin (tarikh mula ditetapkan semasa dihantar)
        $this->assertSame([$this->primary->id, $this->secondary->id], [$copy->primaryOfficer->user_id, $copy->secondaryOfficer->user_id]);
        $this->assertNull($copy->primaryOfficer->started_at);

        // 24: permohonan asal tidak berubah
        $this->assertSame($snapshot, $this->snapshot($source));

        // 25: status permulaan Draf; boleh disunting oleh penyalin melalui borang sedia ada
        $this->assertSame(ApplicationStatus::Draft->value, $copy->status);
        $this->assertTrue($this->primary->can('update', $copy));
        Livewire::actingAs($this->primary)->test(ApplicationForm::class, ['uuid' => $copy->uuid])
            ->assertOk()
            ->assertSee('Permohonan ini disalin daripada ' . $source->reference_number)
            ->assertSet('form.title', $source->title);

        // 26: tiada rekod Projek
        $this->assertSame(0, DB::table('projects')->count());
    }

    public function test_inactive_master_data_and_ineligible_officers_are_not_copied(): void
    {
        $source = $this->closedApplication();
        ProcurementMethod::whereKey($source->procurement_method_id)->update(['is_active' => false]);
        ProjectCategory::where('code', 'peningkatan_sistem')->update(['is_active' => false]);
        ApplicationCategory::where('code', $source->application_category)->update(['is_active' => false]);
        $this->secondary->forceFill(['status' => UserStatus::Inactive])->save();

        $copy = $this->service()->copy($source, $this->primary)->fresh();

        $this->assertNull($copy->application_category);
        $this->assertNull($copy->procurement_method_id);
        $this->assertNull($copy->procurement_method);
        $this->assertSame($source->procurement_type_id, $copy->procurement_type_id, 'Master aktif kekal');

        $details = $copy->details()->orderBy('id')->get();
        $this->assertSame($this->masterId(ProjectCategory::class, 'projek_baharu'), $details[0]->project_category_id);
        $this->assertNull($details[1]->project_category_id, 'Kategori tidak aktif mesti dipilih semula');
        $this->assertSame('Peningkatan Sistem', $details[1]->item_category, 'Snapshot teks dikekalkan (lajur NOT NULL)');

        $this->assertSame($this->primary->id, $copy->primaryOfficer->user_id);
        $this->assertNull($copy->secondaryOfficer, 'Pegawai tidak aktif tidak disalin');
    }

    // --- PP43C-01 & 04: PAPARAN ---

    public function test_detail_pages_show_copy_action_and_relationship(): void
    {
        $source = $this->closedApplication();

        Livewire::actingAs($this->primary)->test(ViewApplication::class, ['uuid' => $source->uuid])
            ->assertSee('Keputusan JTICT: Kaji Semula')
            ->assertSee('Permohonan ini boleh disalin')
            ->assertSeeHtml('wire:click="copyApplication"')
            ->call('copyApplication')
            ->assertRedirect(route('application.edit', Application::where('copied_from_application_id', $source->id)->value('uuid')));

        $copy = $source->copies()->sole();

        // Permohonan asal: telah disalin (pautan ke salinan); tiada butang
        Livewire::actingAs($this->secondary)->test(ViewApplication::class, ['uuid' => $source->uuid])
            ->assertSee('Permohonan ini telah mempunyai salinan')
            ->assertSee($copy->reference_number)
            ->assertDontSeeHtml('wire:click="copyApplication"')
            ->assertSee('Selesai – Kaji Semula (JTICT)');

        // Salinan: berasal daripada permohonan asal
        Livewire::actingAs($this->primary)->test(ViewApplication::class, ['uuid' => $copy->uuid])
            ->assertSee('Permohonan ini disalin daripada permohonan')
            ->assertSee($source->reference_number);

        // Lulus: tiada bahagian salin
        $approved = $this->closedApplication(Decision::Approved, 'Projek Lulus');
        Livewire::actingAs($this->primary)->test(ViewApplication::class, ['uuid' => $approved->uuid])
            ->assertDontSee('Keputusan JTICT: Kaji Semula')
            ->assertDontSeeHtml('wire:click="copyApplication"');
    }

    // --- PEMBANTU ---

    private function service(): ApplicationCopyService
    {
        return app(ApplicationCopyService::class);
    }

    private function assertCopyRefused(Application $source, User $user, string $label = ''): void
    {
        $before = Application::withTrashed()->count();

        $this->assertFalse($user->can('copy', $source->fresh()), $label);

        try {
            $this->service()->copy($source, $user);
            $this->fail("Salinan sepatutnya ditolak {$label}");
        } catch (AuthorizationException) {
        }

        $this->assertSame($before, Application::withTrashed()->count(), $label);
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(Application $application): array
    {
        $application = $application->fresh();

        return [
            'application' => $application->getAttributes(),
            'details' => $application->details()->withTrashed()->orderBy('id')->get()->toArray(),
            'documents' => $application->documents()->withTrashed()->orderBy('id')->get()->toArray(),
            'decisions' => $application->decisions()->orderBy('id')->get()->toArray(),
            'reviews' => $application->reviews()->orderBy('id')->get()->toArray(),
            'officers' => $application->officers()->withTrashed()->orderBy('id')->get()->toArray(),
        ];
    }

    private function user(string $role, array $attributes = []): User
    {
        $user = User::factory()->create($attributes + ['agency_id' => $this->agency->id]);
        $user->assignRole($role);

        return $user;
    }

    private function meeting($date)
    {
        return $this->openJtictMeeting(['series' => 'Bil. ' . ($this->sequence + 1) . '/2026', 'meeting_date' => $date]);
    }

    private function makeSubmitted(string $title, $meeting, ?User $registrar = null): Application
    {
        $this->sequence++;

        $application = Application::create([
            'reference_number' => 'SPPICT/2026/' . str_pad((string) $this->sequence, 4, '0', STR_PAD_LEFT),
            'agency_id' => $this->agency->id,
            'applicant_id' => ($registrar ?? $this->registrar)->id,
            'title' => $title,
            'application_category' => 'pembangunan_sistem',
            'objectives' => '<div>Objektif projek salinan.</div>',
            'project_scope' => '<div>Skop projek salinan.</div>',
            'procurement_type_id' => $this->masterId(ProcurementType::class, 'pembekalan_perkhidmatan_one_off'),
            'procurement_type' => 'Pembekalan / Perkhidmatan Tidak Bermasa (One-Off)',
            'procurement_method_id' => $this->masterId(ProcurementMethod::class, 'sebut_harga'),
            'procurement_method' => 'Sebut Harga',
            'ceiling_cost' => 200000,
            'estimated_cost' => 150000,
            'subtotal_cost' => 150000,
            'sst_rate' => 8,
            'sst_amount' => 12000,
            'total_cost' => 162000,
            'expected_duration_months' => 12,
            'outcome_code' => 'Bajet Mengurus',
            'status' => 'SUBMITTED',
            'jtict_meeting_id' => $meeting->id,
        ]);

        foreach ([['projek_baharu', 'Projek Baharu', 'Pelayan aplikasi', 2, 50000], ['peningkatan_sistem', 'Peningkatan Sistem', 'Lesen perisian', 5, 10000]] as [$code, $name, $spec, $qty, $cost]) {
            $application->details()->create([
                'project_category_id' => $this->masterId(ProjectCategory::class, $code),
                'item_category' => $name,
                'technical_specifications' => $spec,
                'unit_quantity' => $qty,
                'unit_cost' => $cost,
                'total_cost' => $qty * $cost,
            ]);
        }

        ApplicationDocument::create([
            'application_id' => $application->id, 'document_type' => 'PROPOSAL_PAPER', 'file_name' => 'kertas.pdf',
            'file_path' => "applications/{$application->uuid}/documents/kertas.pdf", 'mime_type' => 'application/pdf', 'file_size_kb' => 1,
        ]);

        foreach ([OfficerRole::Primary->value => $this->primary, OfficerRole::Secondary->value => $this->secondary] as $role => $user) {
            $application->officers()->create(ApplicationOfficer::snapshotFrom($user) + ['role' => $role, 'started_at' => today()->subMonth()]);
        }

        return $application;
    }

    /**
     * Permohonan melalui aliran sebenar: PP2 Lengkap & Pra JTICT Diterima pada tarikh mesyuarat (semalam),
     * kemudian keputusan JTICT hari ini ($decision null = kekal Dalam Proses JTICT).
     */
    private function closedApplication(?Decision $decision = Decision::Review, string $title = 'Projek Kaji Semula', ?User $registrar = null): Application
    {
        $meeting = $this->meeting(today()->subDay());

        $this->travelTo(today()->subDay()->setTime(9, 0));
        $application = $this->makeSubmitted($title, $meeting, $registrar);
        app(ApplicationReviewService::class)->review($application, ApplicationStatus::Complete, null, $this->urusSetia);
        app(ApplicationDecisionService::class)->decide($application->fresh(), DecisionStage::PreJtict, Decision::Accepted, null, today(), $this->urusSetia);
        $this->travelBack();

        if ($decision) {
            app(ApplicationDecisionService::class)->decide($application->fresh(), DecisionStage::Jtict, $decision, self::JTICT_REMARKS, today(), $this->urusSetia);
        }

        return $application->fresh();
    }
}
