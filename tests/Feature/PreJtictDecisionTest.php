<?php

namespace Tests\Feature;

use App\Enums\ApplicationDecision as Decision;
use App\Enums\ApplicationStatus;
use App\Enums\DecisionStage;
use App\Enums\OfficerRole;
use App\Livewire\ApplicationForm;
use App\Livewire\Forms\ApplicationForm as ApplicationFormObject;
use App\Livewire\PreJtictList;
use App\Livewire\ViewApplication;
use App\Models\Agency;
use App\Models\Application;
use App\Models\ApplicationDecision;
use App\Models\ApplicationOfficer;
use App\Models\ApplicationReview;
use App\Models\Meeting;
use App\Models\ProcurementMethod;
use App\Models\ProcurementType;
use App\Models\ProjectCategory;
use App\Models\User;
use App\Notifications\ApplicationDecisionRecorded;
use App\Services\ApplicationDecisionService;
use App\Services\ApplicationReviewService;
use App\Services\ApplicationSubmissionService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use ReflectionClass;
use ReflectionMethod;
use RuntimeException;
use Spatie\Permission\Models\Role;
use Tests\Concerns\OpensJtictMeeting;
use Tests\Concerns\UsesApplicationMasterData;
use Tests\TestCase;

/**
 * PP4.2: fungsi Keputusan Pra JTICT (senarai menunggu, paparan butiran, borang keputusan,
 * sejarah keputusan, status & kawalan akses) melalui ApplicationDecisionService.
 *
 * Nota: dijalankan pada SQLite in-memory; tidak membuktikan tingkah laku MySQL (cth. kunci baris).
 */
class PreJtictDecisionTest extends TestCase
{
    use RefreshDatabase, OpensJtictMeeting, UsesApplicationMasterData;

    private const REMARKS = 'Sila perincikan justifikasi kos dan skop fasa kedua projek.';

    private Agency $agency;

    private User $urusSetia;

    private User $applicant;

    private User $officer;

    private Meeting $meeting;

    private int $sequence = 0;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Mail::fake();
        Notification::fake();

        foreach (['pengguna_biasa', 'pentadbir_urus_setia', 'pengurusan', 'superadmin'] as $role) {
            Role::findOrCreate($role);
        }

        $this->agency = Agency::create(['code' => 'JKR', 'name' => 'Agensi JKR', 'category' => 'SUK', 'is_active' => true]);

        $this->urusSetia = User::factory()->create(['name' => 'Siti Urus Setia', 'agency_id' => $this->agency->id]);
        $this->urusSetia->assignRole('pentadbir_urus_setia');

        $this->applicant = User::factory()->create(['name' => 'Ali Pemohon', 'agency_id' => $this->agency->id]);
        $this->applicant->assignRole('pengguna_biasa');

        $this->officer = User::factory()->create(['name' => 'Ahmad Pegawai', 'agency_id' => $this->agency->id]);
        $this->officer->assignRole('pengguna_biasa');

        $this->meeting = $this->openJtictMeeting(['series' => 'Bil. 1/2026']);
    }

    // --- A. DITERIMA ---

    public function test_accepted_decision_moves_application_to_in_jtict_with_optional_remarks(): void
    {
        $application = $this->completeApplication();

        $this->decideViaUi($application, Decision::Accepted, '', today()->toDateString())
            ->assertHasNoErrors()
            ->assertSet('showPreJtictModal', false);

        $application->refresh();
        $record = $application->decisions()->sole();

        $this->assertSame(ApplicationStatus::InJtict->value, $application->status);
        $this->assertSame(DecisionStage::PreJtict, $record->stage);
        $this->assertSame(Decision::Accepted, $record->decision);
        $this->assertSame('COMPLETE', $record->from_status);
        $this->assertSame('IN_JTICT', $record->to_status);
        $this->assertNull($record->remarks, 'Ulasan kosong dibenarkan bagi Diterima');
        $this->assertSame(today()->toDateString(), $record->decision_date->toDateString());
        $this->assertSame($this->urusSetia->id, (int) $record->decided_by);
        $this->assertSame($this->meeting->id, $application->jtict_meeting_id, 'Mesyuarat JTICT tidak berubah');
        $this->assertSame($this->meeting->id, (int) $record->meeting_id);
        $this->assertSame('Diterima – Menunggu Mesyuarat JTICT', $application->statusDisplayLabel());
    }

    // --- B. DITOLAK DENGAN PINDAAN ---

    public function test_amendment_decision_requires_remarks(): void
    {
        $application = $this->completeApplication();

        $this->decideViaUi($application, Decision::AmendmentRequired, '   ', today()->toDateString())
            ->assertHasErrors(['preJtictRemarks'])
            ->assertSet('showPreJtictModal', true);

        $this->assertSame(0, ApplicationDecision::count());
        $this->assertSame('COMPLETE', $application->fresh()->status);
    }

    public function test_full_amendment_cycle_through_ui_keeps_identity_and_history(): void
    {
        $application = $this->completeApplication();
        [$id, $uuid, $reference] = [$application->id, $application->uuid, $application->reference_number];

        $this->decideViaUi($application, Decision::AmendmentRequired, self::REMARKS, today()->toDateString())
            ->assertHasNoErrors();

        $application->refresh();
        $this->assertSame(ApplicationStatus::PreJtictAmendment->value, $application->status);
        $this->assertSame('Ditolak dengan Pindaan – Menunggu Tindakan Pemohon', $application->statusDisplayLabel());
        $this->assertSame([$id, $uuid, $reference], [$application->id, $application->uuid, $application->reference_number]);
        $this->assertSame(1, Application::count(), 'Tiada rekod permohonan baharu');
        $this->assertSame($this->meeting->id, $application->jtict_meeting_id, 'Mesyuarat tidak dipindahkan');

        // Pegawai Utama aktif melihat ulasan Pra JTICT dan boleh meminda permohonan yang sama
        $this->assertTrue($this->officer->can('update', $application));
        Livewire::actingAs($this->officer)->test(ViewApplication::class, ['uuid' => $uuid])
            ->assertSee('Ditolak dengan Pindaan')
            ->assertSee(self::REMARKS)
            ->assertSee('Kemas Kini &amp; Hantar Semula', false);

        // Pendaftar yang bukan pegawai aktif hanya boleh melihat
        $this->assertFalse($this->applicant->can('update', $application));
        Livewire::actingAs($this->applicant)->test(ViewApplication::class, ['uuid' => $uuid])
            ->assertSee(self::REMARKS)
            ->assertDontSee('Kemas Kini &amp; Hantar Semula', false);

        $form = Livewire::actingAs($this->officer)->test(ApplicationForm::class, ['uuid' => $uuid])
            ->assertSee('Keputusan Pra JTICT: Ditolak dengan Pindaan')
            ->assertSee(self::REMARKS);
        $this->fillValidForm($form);
        $form->call('submitApplication')->assertHasNoErrors();

        // Aliran PP2 sedia ada: Dihantar Semula direkod dalam application_reviews
        $application->refresh();
        $this->assertSame('SUBMITTED', $application->status);
        $resubmission = ApplicationReview::where('application_id', $id)->latest('id')->first();
        $this->assertSame('PRE_JTICT_AMENDMENT', $resubmission->from_status);
        $this->assertSame('Dihantar Semula', $resubmission->actionLabel());

        // Semakan PP2 semula -> kembali ke senarai menunggu keputusan Pra JTICT
        app(ApplicationReviewService::class)->review($application, ApplicationStatus::Complete, null, $this->urusSetia);
        Livewire::actingAs($this->urusSetia)->test(PreJtictList::class)
            ->assertSee($reference)
            ->assertSee('Dihantar semula selepas Pindaan Pra JTICT');

        $this->decideViaUi($application->fresh(), Decision::Accepted, null, today()->toDateString())->assertHasNoErrors();

        $decisions = $application->decisions()->orderBy('id')->get();
        $this->assertSame([Decision::AmendmentRequired, Decision::Accepted], $decisions->pluck('decision')->all(), 'Keputusan terdahulu kekal');
        $this->assertSame('IN_JTICT', $application->fresh()->status);
        $this->assertSame($id, $application->fresh()->id);
    }

    // --- C. DITOLAK ---

    public function test_rejected_decision_closes_application_and_blocks_amendment(): void
    {
        $application = $this->completeApplication();

        $this->decideViaUi($application, Decision::Rejected, null, today()->toDateString())->assertHasNoErrors();

        $application->refresh();
        $this->assertSame(ApplicationStatus::Closed->value, $application->status);
        $this->assertSame(Decision::Rejected, $application->decisions()->sole()->decision);
        $this->assertSame('Selesai – Ditolak (Pra JTICT)', $application->statusDisplayLabel());

        // Tidak boleh dipinda atau dihantar semula
        $this->assertFalse($this->applicant->can('update', $application));
        $this->assertFalse($this->officer->can('update', $application));

        try {
            app(ApplicationSubmissionService::class)->submit(new ApplicationFormObject(new ApplicationForm(), 'form'), $this->applicant, $application->id);
            $this->fail('Permohonan Ditolak tidak boleh dihantar semula');
        } catch (AuthorizationException) {
            $this->assertSame('CLOSED', $application->fresh()->status);
        }

        // Kekal dalam sejarah permohonan agensi
        $this->assertTrue(Application::forApplicant($this->applicant)->whereKey($application->id)->exists());
    }

    // --- D. VALIDASI TARIKH ---

    public function test_decision_date_validation(): void
    {
        $application = $this->completeApplication(completedAt: today()->subDays(3));

        $this->decideViaUi($application, Decision::Accepted, null, today()->addDay()->toDateString())
            ->assertHasErrors(['preJtictDate']);

        $this->decideViaUi($application, Decision::Accepted, null, today()->subDays(4)->toDateString())
            ->assertHasErrors(['preJtictDate']);

        $this->decideViaUi($application, Decision::Accepted, null, '')
            ->assertHasErrors(['preJtictDate']);

        $this->assertSame(0, ApplicationDecision::count());
        $this->assertSame('COMPLETE', $application->fresh()->status);

        // Tarikh sebenar dimasukkan oleh Urus Setia (tiada nilai lalai) dan tarikh sah diterima
        Livewire::actingAs($this->urusSetia)->test(ViewApplication::class, ['uuid' => $application->uuid])
            ->call('openPreJtictModal', Decision::Accepted->value)
            ->assertSet('preJtictDate', '');

        $this->decideViaUi($application, Decision::Accepted, null, today()->subDays(3)->toDateString())->assertHasNoErrors();
        $this->assertSame(today()->subDays(3)->toDateString(), $application->decisions()->sole()->decision_date->toDateString());
    }

    // --- E. KAWALAN AKSES ---

    public function test_only_active_urus_setia_can_record_pre_jtict_decision(): void
    {
        $application = $this->completeApplication();
        $service = app(ApplicationDecisionService::class);

        $superadmin = User::factory()->create(['agency_id' => $this->agency->id]);
        $superadmin->assignRole('superadmin');
        $pengurusan = User::factory()->create(['agency_id' => $this->agency->id]);
        $pengurusan->assignRole('pengurusan');
        $dualRole = User::factory()->create(['agency_id' => $this->agency->id]);
        $dualRole->assignRole(['pentadbir_urus_setia', 'superadmin']);
        $inactive = User::factory()->create(['agency_id' => $this->agency->id, 'status' => 'inactive']);
        $inactive->assignRole('pentadbir_urus_setia');

        // Panggilan terus kepada service ditolak (bukan bergantung kepada UI)
        foreach ([$this->applicant, $this->officer, $superadmin, $pengurusan, $dualRole, $inactive] as $user) {
            try {
                $service->decide($application, DecisionStage::PreJtict, Decision::Accepted, null, today(), $user);
                $this->fail("Pengguna {$user->id} tidak sepatutnya boleh merekod keputusan");
            } catch (AuthorizationException) {
                $this->assertSame('COMPLETE', $application->fresh()->status);
            }
        }

        // Pengguna yang boleh melihat permohonan tidak boleh membuka / menghantar borang keputusan melalui payload Livewire
        foreach ([$this->applicant, $inactive] as $user) {
            Livewire::actingAs($user)->test(ViewApplication::class, ['uuid' => $application->uuid])
                ->assertDontSee('Rekod Keputusan Pra JTICT')
                ->call('openPreJtictModal', Decision::Accepted->value)
                ->assertForbidden();

            Livewire::actingAs($user)->test(ViewApplication::class, ['uuid' => $application->uuid])
                ->set('preJtictDecision', Decision::Accepted->value)
                ->set('preJtictDate', today()->toDateString())
                ->call('submitPreJtictDecision')
                ->assertForbidden();
        }

        $this->assertSame(0, ApplicationDecision::count());
        $this->assertSame('COMPLETE', $application->fresh()->status);
    }

    public function test_pre_jtict_list_is_restricted_to_active_urus_setia(): void
    {
        $this->actingAs($this->urusSetia)->get(route('pre-jtict.index'))->assertOk()->assertSee('Keputusan Pra JTICT');

        $superadmin = User::factory()->create();
        $superadmin->assignRole('superadmin');
        $pengurusan = User::factory()->create();
        $pengurusan->assignRole('pengurusan');
        $dualRole = User::factory()->create();
        $dualRole->assignRole(['pentadbir_urus_setia', 'superadmin']);

        foreach ([$this->applicant, $superadmin, $pengurusan, $dualRole] as $user) {
            $this->actingAs($user)->get(route('pre-jtict.index'))->assertForbidden();
        }

        $inactive = User::factory()->create(['status' => 'inactive']);
        $inactive->assignRole('pentadbir_urus_setia');
        $this->assertNotSame(200, $this->actingAs($inactive)->get(route('pre-jtict.index'))->status());

        Livewire::actingAs($this->applicant)->test(PreJtictList::class)->assertForbidden();
    }

    // --- F. INTEGRITI & KESELAMATAN ---

    public function test_only_complete_applications_can_be_decided(): void
    {
        foreach (['DRAFT', 'SUBMITTED', 'NEEDS_AMENDMENT', 'PRE_JTICT_AMENDMENT', 'IN_JTICT', 'CLOSED'] as $status) {
            $application = $this->makeApplication($status);

            Livewire::actingAs($this->urusSetia)->test(ViewApplication::class, ['uuid' => $application->uuid])
                ->assertDontSee('Rekod Keputusan Pra JTICT')
                ->call('openPreJtictModal', Decision::Accepted->value)
                ->assertForbidden();

            $this->decideViaUi($application, Decision::Accepted, null, today()->toDateString())
                ->assertHasNoErrors()
                ->assertSee('tidak lagi menunggu keputusan');

            $this->assertSame($status, $application->fresh()->status);
        }

        $this->assertSame(0, ApplicationDecision::count());
    }

    public function test_concurrent_decisions_record_only_once(): void
    {
        $application = $this->completeApplication();

        // Dua Urus Setia membuka borang yang sama sebelum mana-mana keputusan direkodkan
        $first = $this->openDecisionForm($application, Decision::Accepted, null, today()->toDateString());
        $second = $this->openDecisionForm($application, Decision::Rejected, null, today()->toDateString());

        $first->call('submitPreJtictDecision')->assertHasNoErrors();
        $second->call('submitPreJtictDecision')->assertHasNoErrors()->assertSee('telah direkodkan');

        $this->assertSame(1, ApplicationDecision::count());
        $this->assertSame(Decision::Accepted, ApplicationDecision::sole()->decision);
        $this->assertSame('IN_JTICT', $application->fresh()->status);
    }

    public function test_failed_save_rolls_back_decision_and_status(): void
    {
        $application = $this->completeApplication();

        Application::updating(function (Application $model) {
            if ($model->status === ApplicationStatus::InJtict->value) {
                throw new RuntimeException('Simulasi kegagalan pangkalan data');
            }
        });

        try {
            $this->decideViaUi($application, Decision::Accepted, null, today()->toDateString());
            $this->fail('Kegagalan simpan sepatutnya dilaporkan');
        } catch (RuntimeException) {
        }

        $this->assertSame(0, ApplicationDecision::count(), 'Rekod keputusan dibatalkan');
        $this->assertSame('COMPLETE', $application->fresh()->status, 'Status permohonan tidak berubah');
    }

    public function test_no_edit_or_delete_and_no_jtict_decision_through_pp42(): void
    {
        $application = $this->completeApplication();

        // Tiada tindakan awam untuk mengubah / memadam keputusan
        // (PP4.3A menambah preJtictAcceptanceBlocker [paparan PP42B-01] dan submitJtictDecision [rekod JTICT sahaja])
        $actions = collect((new ReflectionClass(ViewApplication::class))->getMethods(ReflectionMethod::IS_PUBLIC))
            ->map(fn (ReflectionMethod $method) => $method->getName())
            ->filter(fn (string $name) => str_contains(strtolower($name), 'prejtict') || str_contains(strtolower($name), 'decision'))
            ->values()
            ->all();
        $this->assertEqualsCanonicalizing(
            ['preJtictDecisions', 'preJtictAmendmentDecision', 'preJtictEarliestDate', 'openPreJtictModal', 'closePreJtictModal', 'submitPreJtictDecision',
                'preJtictAcceptanceBlocker', 'submitJtictDecision', 'decisionHistory' /* PP4.3B: paparan sejarah, baca sahaja */],
            $actions
        );

        // Keputusan JTICT tidak boleh dihantar melalui borang Pra JTICT
        foreach ([Decision::Approved, Decision::NotApproved, Decision::Review] as $jtict) {
            Livewire::actingAs($this->urusSetia)->test(ViewApplication::class, ['uuid' => $application->uuid])
                ->call('openPreJtictModal', $jtict->value)
                ->assertStatus(422);

            Livewire::actingAs($this->urusSetia)->test(ViewApplication::class, ['uuid' => $application->uuid])
                ->set('preJtictDecision', $jtict->value)
                ->set('preJtictDate', today()->toDateString())
                ->set('preJtictRemarks', self::REMARKS)
                ->call('submitPreJtictDecision')
                ->assertStatus(422);
        }

        $this->assertSame(0, ApplicationDecision::count());
    }

    public function test_decision_does_not_touch_legacy_fields_other_applications_or_send_email(): void
    {
        $application = $this->completeApplication();
        $other = $this->completeApplication();
        $otherSnapshot = $other->fresh()->getAttributes();

        $this->decideViaUi($application, Decision::AmendmentRequired, self::REMARKS, today()->toDateString())->assertHasNoErrors();

        $application->refresh();
        $this->assertSame('PENDING', $application->jtict_decision, 'Lajur warisan jtict_decision tidak disentuh');
        $this->assertNull($application->jtict_remarks);
        $this->assertSame($otherSnapshot, $other->fresh()->getAttributes(), 'Permohonan lain tidak berubah');
        $this->assertSame(0, ApplicationReview::where('to_status', 'PRE_JTICT_AMENDMENT')->count(), 'Keputusan tidak disimpan dalam application_reviews');

        // PP4.3D: keputusan kini memaklumkan Pendaftar & Pegawai Utama melalui ApplicationDecisionRecorded sahaja;
        // tiada notifikasi atau e-mel lain (asal: assertNothingSent sebelum PP4.3D).
        $expected = app(ApplicationDecisionService::class)->decisionRecipients($application->fresh())->count();
        $this->assertGreaterThan(0, $expected);
        Notification::assertSentTimes(ApplicationDecisionRecorded::class, $expected);
        Notification::assertCount($expected);
        Mail::assertNothingSent();
    }

    // --- SENARAI, BUTIRAN & SEJARAH ---

    public function test_list_shows_only_applications_awaiting_pre_jtict_decision(): void
    {
        $pending = $this->completeApplication(title: 'Projek Menunggu');
        $this->makeApplication('SUBMITTED', 'Projek Dihantar');
        $decided = $this->completeApplication(title: 'Projek Sudah Diterima');
        app(ApplicationDecisionService::class)->decide($decided, DecisionStage::PreJtict, Decision::Accepted, null, today(), $this->urusSetia);
        $deleted = $this->completeApplication(title: 'Projek Dipadam');
        $deleted->delete();
        $legacy = $this->makeApplication('COMPLETE', 'Projek Lama Tanpa Mesyuarat', ['jtict_meeting_id' => null]);

        Livewire::actingAs($this->urusSetia)->test(PreJtictList::class)
            ->assertSee('Projek Menunggu')
            ->assertSee($pending->reference_number)
            ->assertSee('Agensi JKR', false)
            ->assertSee('Ali Pemohon')
            ->assertSee($this->meeting->label())
            ->assertSee('Lengkap – Menunggu Keputusan Pra JTICT')
            ->assertSee(route('application.view', $pending->uuid))
            ->assertDontSee('Projek Dihantar')
            ->assertDontSee('Projek Sudah Diterima')
            ->assertDontSee('Projek Dipadam')
            ->assertSee('Perlu Semakan Manual (1)')
            ->assertSee('Projek Lama Tanpa Mesyuarat');

        // Rekod lama tanpa Mesyuarat JTICT tidak diproses secara automatik
        $this->assertFalse($this->urusSetia->can('decidePreJtict', $legacy));
        $this->assertSame(['COMPLETE'], Application::needsManualPreJtictReview()->pluck('status')->all());

        // Papan pemuka Urus Setia memaparkan bilangan menunggu keputusan
        $this->actingAs($this->urusSetia)->get('/dashboard')
            ->assertOk()
            ->assertSee('Keputusan Pra JTICT')
            ->assertSee('menunggu keputusan Pra JTICT direkodkan')
            ->assertSee(route('pre-jtict.index'));
    }

    public function test_detail_page_is_read_only_and_history_hides_names_from_applicant(): void
    {
        $application = $this->completeApplication();
        $before = $application->fresh()->getAttributes();

        Livewire::actingAs($this->urusSetia)->test(ViewApplication::class, ['uuid' => $application->uuid])
            ->assertSee('Keputusan Pra JTICT')
            ->assertSee('Maklumat Am Projek')
            ->assertSee('Dokumen Sokongan')
            ->assertSee('Pegawai Bertanggungjawab')
            ->assertSee('Sejarah Semakan')
            ->assertSee($this->meeting->label())
            ->assertDontSee('Sejarah Keputusan Permohonan'); // PP4.3B: tajuk bahagian sejarah bersatu

        $this->assertSame($before, $application->fresh()->getAttributes(), 'Paparan tidak mengubah permohonan');

        $this->decideViaUi($application, Decision::AmendmentRequired, self::REMARKS, today()->toDateString())->assertHasNoErrors();

        Livewire::actingAs($this->urusSetia)->test(ViewApplication::class, ['uuid' => $application->uuid])
            ->assertSee('Sejarah Keputusan Permohonan')
            ->assertSee('Siti Urus Setia');

        Livewire::actingAs($this->applicant)->test(ViewApplication::class, ['uuid' => $application->uuid])
            ->assertSee('Sejarah Keputusan Permohonan')
            ->assertSee('Ditolak dengan Pindaan')
            ->assertSee(today()->format('d/m/Y'))
            ->assertSee(self::REMARKS)
            ->assertDontSee('Siti Urus Setia');

        // Pengguna tanpa kebenaran view tidak boleh melihat sejarah
        $outsider = User::factory()->create(['agency_id' => Agency::create(['code' => 'JPN', 'name' => 'Agensi Lain', 'category' => 'SUK', 'is_active' => true])->id]);
        $outsider->assignRole('pengguna_biasa');
        Livewire::actingAs($outsider)->test(ViewApplication::class, ['uuid' => $application->uuid])->assertForbidden();
    }

    public function test_closed_status_label_distinguishes_decision_stage(): void
    {
        $this->assertSame('Selesai', $this->makeApplication('CLOSED')->statusDisplayLabel(), 'Tiada andaian tanpa rekod keputusan');
        $this->assertSame('Lengkap – Menunggu Keputusan Pra JTICT', $this->makeApplication('COMPLETE')->statusDisplayLabel());

        // Keputusan JTICT (peringkat lain) tidak dipaparkan sebagai Ditolak Pra JTICT
        // (PP4.3A / PP42B-01: Pra JTICT Diterima pada tarikh mesyuarat; keputusan JTICT selepas mesyuarat berlangsung)
        $pastMeeting = $this->openJtictMeeting(['series' => 'Bil. 2/2026', 'meeting_date' => today()->subDay()]);
        $service = app(ApplicationDecisionService::class);
        $this->travelTo(today()->subDay()->setTime(9, 0));
        $application = $this->completeApplication(attributes: ['jtict_meeting_id' => $pastMeeting->id]);
        $service->decide($application, DecisionStage::PreJtict, Decision::Accepted, null, today(), $this->urusSetia);
        $this->travelBack();
        $service->decide($application, DecisionStage::Jtict, Decision::NotApproved, self::REMARKS, today(), $this->urusSetia);

        $this->assertSame('Selesai – Tidak Lulus (JTICT)', $application->fresh()->statusDisplayLabel());
    }

    // --- PEMBANTU ---

    private function decideViaUi(Application $application, Decision $decision, ?string $remarks, string $date)
    {
        return $this->openDecisionForm($application, $decision, $remarks, $date)->call('submitPreJtictDecision');
    }

    private function openDecisionForm(Application $application, Decision $decision, ?string $remarks, string $date)
    {
        return Livewire::actingAs($this->urusSetia)->test(ViewApplication::class, ['uuid' => $application->uuid])
            ->set('showPreJtictModal', true)
            ->set('preJtictDecision', $decision->value)
            ->set('preJtictDate', $date)
            ->set('preJtictRemarks', (string) $remarks);
    }

    private function makeApplication(string $status, ?string $title = null, array $attributes = []): Application
    {
        $this->sequence++;

        $application = Application::create($attributes + [
            'reference_number' => 'SPPICT/2026/' . str_pad((string) $this->sequence, 4, '0', STR_PAD_LEFT),
            'agency_id' => $this->agency->id,
            'applicant_id' => $this->applicant->id,
            'title' => $title ?? "Projek Pra JTICT {$this->sequence}",
            'estimated_cost' => 10000,
            'status' => $status,
            'jtict_meeting_id' => $this->meeting->id,
        ]);

        $application->officers()->create(ApplicationOfficer::snapshotFrom($this->officer) + [
            'role' => OfficerRole::Primary,
            'started_at' => today()->subMonth(),
        ]);

        return $application;
    }

    /**
     * Permohonan Dihantar yang disahkan Lengkap melalui semakan PP2 sebenar (pada tarikh $completedAt).
     */
    private function completeApplication(?string $title = null, array $attributes = [], $completedAt = null): Application
    {
        $application = $this->makeApplication('SUBMITTED', $title, $attributes);

        if ($completedAt) {
            $this->travelTo($completedAt->copy()->setTime(10, 0));
        }

        app(ApplicationReviewService::class)->review($application, ApplicationStatus::Complete, null, $this->urusSetia);

        if ($completedAt) {
            $this->travelBack();
        }

        return $application->fresh();
    }

    private function fillValidForm($component): void
    {
        $component
            ->set('form.title', 'Projek Pindaan Pra JTICT')
            ->set('form.application_category', 'pembangunan_sistem')
            ->set('form.objectives', 'Objektif projek ujian yang lengkap.')
            ->set('form.project_scope', 'Skop projek ujian yang lengkap.')
            ->set('form.procurement_type_id', $this->masterId(ProcurementType::class, 'pembekalan_perkhidmatan_one_off'))
            ->set('form.procurement_method_id', $this->masterId(ProcurementMethod::class, 'sebut_harga'))
            ->set('form.ceiling_cost', 20000)
            ->set('form.estimated_cost', 10000)
            ->set('form.expected_duration_months', 12)
            ->set('form.outcome_code', 'Bajet Mengurus')
            ->set('form.details', [[
                'project_category_id' => $this->masterId(ProjectCategory::class, 'projek_baharu'),
                'items' => [['technical_specifications' => 'Pelayan aplikasi', 'unit_quantity' => 1, 'unit_cost' => 10000]],
            ]])
            ->set('form.proposal_paper', UploadedFile::fake()->createWithContent('kertas.pdf', "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF"));
    }
}
