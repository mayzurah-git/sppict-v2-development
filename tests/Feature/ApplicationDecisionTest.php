<?php

namespace Tests\Feature;

use App\Enums\ApplicationDecision as Decision;
use App\Enums\ApplicationStatus;
use App\Enums\DecisionStage;
use App\Enums\OfficerRole;
use App\Livewire\ApplicationForm;
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
use App\Services\ApplicationDecisionService;
use App\Services\ApplicationReviewService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Livewire\Livewire;
use LogicException;
use RuntimeException;
use Spatie\Permission\Models\Role;
use Tests\Concerns\OpensJtictMeeting;
use Tests\Concerns\UsesApplicationMasterData;
use Tests\TestCase;

/**
 * PP4.1: struktur asas keputusan Pra JTICT dan JTICT (application_decisions, status Pindaan Pra JTICT,
 * ApplicationDecisionService, polisi decidePreJtict / decideJtict, copied_from_application_id).
 *
 * Nota: dijalankan pada SQLite in-memory; tidak membuktikan migration / FK pada MySQL.
 */
class ApplicationDecisionTest extends TestCase
{
    use RefreshDatabase, OpensJtictMeeting, UsesApplicationMasterData;

    private const REMARKS = 'Sila perincikan justifikasi kos dan skop fasa kedua projek.';

    private Agency $agency;

    private User $urusSetia;

    private User $applicant;

    private User $officer;

    private Meeting $meeting;

    private ApplicationDecisionService $service;

    private int $sequence = 0;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

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
        $this->service = app(ApplicationDecisionService::class);
    }

    // --- I1. MIGRATION DAN STRUKTUR ---

    public function test_decision_table_and_copy_column_structure(): void
    {
        $this->assertTrue(Schema::hasColumns('application_decisions', [
            'id', 'uuid', 'application_id', 'stage', 'decision', 'from_status', 'to_status',
            'remarks', 'decision_date', 'meeting_id', 'decided_by', 'created_at',
        ]));
        $this->assertFalse(Schema::hasColumn('application_decisions', 'updated_at'), 'Rekod tidak boleh dikemas kini');

        $foreignKeys = collect(Schema::getForeignKeys('application_decisions'))
            ->mapWithKeys(fn (array $fk) => [$fk['columns'][0] => $fk]);
        $this->assertSame('applications', $foreignKeys['application_id']['foreign_table']);
        $this->assertSame('meetings', $foreignKeys['meeting_id']['foreign_table']);
        $this->assertSame('users', $foreignKeys['decided_by']['foreign_table']);
        foreach ($foreignKeys as $column => $fk) {
            $this->assertNotContains(strtolower((string) $fk['on_delete']), ['cascade', 'set null'], $column);
        }

        $this->assertTrue(Schema::hasColumn('applications', 'copied_from_application_id'));
        $copyFk = collect(Schema::getForeignKeys('applications'))->first(fn (array $fk) => $fk['columns'] === ['copied_from_application_id']);
        $this->assertSame('applications', $copyFk['foreign_table']);
        $this->assertNotContains(strtolower((string) $copyFk['on_delete']), ['cascade', 'set null']);

        // Lajur warisan & struktur PP2 dikekalkan
        $this->assertTrue(Schema::hasColumns('applications', ['jtict_decision', 'jtict_remarks', 'status']));
        $this->assertSame(
            ['id', 'uuid', 'application_id', 'from_status', 'to_status', 'remarks', 'user_id', 'jtict_meeting_id', 'created_at'],
            Schema::getColumnListing('application_reviews'),
        );
    }

    public function test_copy_column_is_nullable_and_not_mass_assignable(): void
    {
        $source = $this->submittedApplication();
        $application = $this->submittedApplication(attributes: ['copied_from_application_id' => $source->id]);

        $this->assertNull($application->fresh()->copied_from_application_id, 'Tidak boleh diisi melalui mass assignment');
        $this->assertNotContains('copied_from_application_id', (new Application())->getFillable());

        // Hubungan disediakan untuk fungsi Salin Permohonan (fasa akan datang)
        $application->forceFill(['copied_from_application_id' => $source->id])->save();
        $this->assertTrue($application->copiedFromApplication->is($source));
        $this->assertTrue($source->copies->first()->is($application));
    }

    public function test_uuid_is_generated_and_relationships_work(): void
    {
        $application = $this->completeApplication();
        $first = $this->decide($application, Decision::AmendmentRequired, self::REMARKS);
        $this->resubmitAndComplete($application);
        $second = $this->decide($application, Decision::Accepted);

        $this->assertTrue(Str::isUuid($first->uuid));
        $this->assertNotSame($first->uuid, $second->uuid);

        $this->assertTrue($second->application->is($application));
        $this->assertTrue($second->decidedBy->is($this->urusSetia));
        $this->assertTrue($second->meeting->is($this->meeting));
        $this->assertSame(DecisionStage::PreJtict, $second->stage);
        $this->assertSame(Decision::Accepted, $second->decision);

        $this->assertSame([$first->id, $second->id], $application->decisions()->orderBy('id')->pluck('id')->all());
        $this->assertTrue($application->latestPreJtictDecision->is($second));
        $this->assertNull($application->latestJtictDecision);
    }

    public function test_migrations_refuse_to_drop_existing_history(): void
    {
        $application = $this->completeApplication();
        $this->decide($application, Decision::Accepted);

        $decisions = require database_path('migrations/2026_11_03_000001_create_application_decisions_table.php');

        try {
            $decisions->down();
            $this->fail('Jadual sejarah keputusan tidak sepatutnya boleh dibuang');
        } catch (RuntimeException) {
            $this->assertTrue(Schema::hasTable('application_decisions'));
        }

        $this->submittedApplication()->forceFill(['copied_from_application_id' => $application->id])->save();
        $copies = require database_path('migrations/2026_11_03_000002_add_copied_from_application_id_to_applications_table.php');

        $this->expectException(RuntimeException::class);
        $copies->down();
    }

    // --- I2. STATUS DAN KITARAN PINDAAN PRA JTICT ---

    public function test_pre_jtict_amendment_status_is_editable_by_applicant_and_officer(): void
    {
        $application = $this->completeApplication();
        $this->decide($application, Decision::AmendmentRequired, self::REMARKS);

        $this->assertSame(ApplicationStatus::PreJtictAmendment->value, $application->status);
        $this->assertTrue($application->isEditable());
        $this->assertTrue($this->applicant->can('update', $application));
        $this->assertTrue($this->officer->can('update', $application));
        $this->assertFalse($this->urusSetia->can('update', $application), 'Urus Setia bukan pendaftar permohonan ini');
        $this->assertSame('Pindaan Pra JTICT', $application->statusLabel());
    }

    public function test_full_pre_jtict_amendment_cycle_keeps_identity_and_history(): void
    {
        $application = $this->completeApplication();
        $identity = $application->only(['id', 'uuid', 'reference_number']);

        $this->decide($application, Decision::AmendmentRequired, self::REMARKS);
        $this->assertSame($this->meeting->id, $application->jtict_meeting_id, 'Mesyuarat tidak diubah oleh keputusan');

        // Mesyuarat asal masih dibuka -> dikekalkan (peraturan PP2)
        $this->assertFalse($application->requiresMeetingSelection());
        $this->assertTrue($application->targetJtictMeeting()->is($this->meeting));

        $component = Livewire::actingAs($this->applicant)->test(ApplicationForm::class, ['uuid' => $application->uuid]);
        $this->fillValidForm($component);
        $component->call('submitApplication')->assertHasNoErrors()->assertRedirect('/dashboard');

        $application->refresh();
        $this->assertSame(ApplicationStatus::Submitted->value, $application->status, 'Kembali kepada semakan PP2');
        $this->assertSame($identity, $application->only(['id', 'uuid', 'reference_number']));
        $this->assertSame($this->meeting->id, $application->jtict_meeting_id);
        $this->assertSame(1, Application::count(), 'Tiada rekod permohonan baharu');

        $resubmission = ApplicationReview::latest('id')->first();
        $this->assertSame(ApplicationStatus::PreJtictAmendment->value, $resubmission->from_status);
        $this->assertTrue($resubmission->isResubmission());
        $this->assertSame('Dihantar Semula', $resubmission->actionLabel());
        $this->assertSame($this->applicant->id, $resubmission->user_id);

        // PP2 masih menyemak seperti biasa: Tidak Lengkap, pinda, kemudian Lengkap
        $review = app(ApplicationReviewService::class);
        $review->review($application, ApplicationStatus::NeedsAmendment, 'Lampirkan slaid pembentangan terkini.', $this->urusSetia);
        $component = Livewire::actingAs($this->applicant)->test(ApplicationForm::class, ['uuid' => $application->uuid]);
        $this->fillValidForm($component);
        $component->call('submitApplication')->assertHasNoErrors();
        $review->review($application->fresh(), ApplicationStatus::Complete, null, $this->urusSetia);

        $this->assertSame(
            ['COMPLETE', 'SUBMITTED', 'NEEDS_AMENDMENT', 'SUBMITTED', 'COMPLETE'],
            $application->reviews()->orderBy('id')->pluck('to_status')->all(),
            'Sejarah PP2 terdahulu kekal; rekod baharu ditambah sahaja'
        );

        // Pra JTICT semula: keputusan terdahulu kekal
        $this->decide($application->fresh(), Decision::Accepted);

        $decisions = $application->decisions()->orderBy('id')->get();
        $this->assertSame([Decision::AmendmentRequired, Decision::Accepted], $decisions->pluck('decision')->all());
        $this->assertSame(self::REMARKS, $decisions->first()->remarks);
        $this->assertSame(ApplicationStatus::InJtict->value, $application->fresh()->status);
    }

    public function test_pre_jtict_amendment_with_closed_meeting_requires_open_meeting_selection(): void
    {
        $application = $this->completeApplication();
        $this->decide($application, Decision::AmendmentRequired, self::REMARKS);

        $this->meeting->update(['submission_closes_at' => today()->subDay()]);
        $open = $this->openJtictMeeting(['series' => 'Bil. 2/2026']);
        $application->refresh();

        $this->assertTrue($application->requiresMeetingSelection());
        $this->assertNull($application->targetJtictMeeting(), 'Tidak dipindahkan secara automatik ke mesyuarat semasa');

        $component = Livewire::actingAs($this->applicant)->test(ApplicationForm::class, ['uuid' => $application->uuid]);
        $this->fillValidForm($component);
        $component->call('submitApplication')->assertHasErrors('resubmissionMeetingId');
        $this->assertSame(ApplicationStatus::PreJtictAmendment->value, $application->fresh()->status);
        $this->assertSame($this->meeting->id, $application->fresh()->jtict_meeting_id);

        $component->set('resubmissionMeetingId', $open->id)->call('submitApplication')->assertHasNoErrors();
        $this->assertSame(ApplicationStatus::Submitted->value, $application->fresh()->status);
        $this->assertSame($open->id, $application->fresh()->jtict_meeting_id);
        $this->assertSame($open->id, ApplicationReview::latest('id')->first()->jtict_meeting_id);
    }

    public function test_pre_jtict_amendment_without_open_meeting_keeps_status(): void
    {
        $application = $this->completeApplication();
        $this->decide($application, Decision::AmendmentRequired, self::REMARKS);
        $this->meeting->update(['submission_closes_at' => today()->subDay()]);

        $component = Livewire::actingAs($this->applicant)->test(ApplicationForm::class, ['uuid' => $application->uuid]);
        $this->fillValidForm($component);
        $component->call('submitApplication')->assertSee('permohonan kekal Pindaan Pra JTICT');

        $this->assertSame(ApplicationStatus::PreJtictAmendment->value, $application->fresh()->status);
        $this->assertSame(0, $application->reviews()->where('to_status', 'SUBMITTED')->count());
    }

    // --- I3. APPLICATIONDECISIONSERVICE ---

    public function test_decision_must_match_stage(): void
    {
        $application = $this->completeApplication();

        foreach ([Decision::Approved, Decision::Review, 'APPROVED', 'BOGUS', null] as $decision) {
            try {
                $this->service->decide($application, DecisionStage::PreJtict, $decision, self::REMARKS, today(), $this->urusSetia);
                $this->fail('Keputusan tidak sepadan tidak sepatutnya diterima');
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('decision', $e->errors());
            }
        }

        $this->assertSame(0, ApplicationDecision::count());
        $this->assertSame(ApplicationStatus::Complete->value, $application->fresh()->status);

        $this->assertSame([Decision::Accepted, Decision::AmendmentRequired, Decision::Rejected], DecisionStage::PreJtict->decisions());
        $this->assertSame([Decision::Approved, Decision::NotApproved, Decision::Review], DecisionStage::Jtict->decisions());
    }

    public function test_model_rejects_mismatched_stage_and_decision(): void
    {
        $application = $this->completeApplication();

        $this->expectException(InvalidArgumentException::class);
        ApplicationDecision::create([
            'application_id' => $application->id,
            'stage' => 'PRE_JTICT',
            'decision' => 'APPROVED',
            'from_status' => 'COMPLETE',
            'to_status' => 'CLOSED',
            'decision_date' => today(),
            'decided_by' => $this->urusSetia->id,
        ]);
    }

    public function test_pre_jtict_decision_only_for_complete_status(): void
    {
        foreach (['DRAFT', 'SUBMITTED', 'NEEDS_AMENDMENT', 'PRE_JTICT_AMENDMENT', 'IN_JTICT', 'CLOSED'] as $status) {
            $application = $this->submittedApplication($status);
            $this->assertFalse($this->urusSetia->can('decidePreJtict', $application), $status);
            $this->assertDecisionRefused($application, DecisionStage::PreJtict, Decision::Accepted, null);
        }
    }

    public function test_jtict_decision_requires_in_jtict_and_accepted_pre_jtict_decision(): void
    {
        $this->meeting->update(['meeting_date' => today()->subDay()]);

        // Lengkap (belum Pra JTICT) -> tidak boleh
        $complete = $this->completeApplication();
        $this->assertDecisionRefused($complete, DecisionStage::Jtict, Decision::Approved, self::REMARKS);

        // Status IN_JTICT tanpa keputusan Pra JTICT Diterima -> tidak boleh melangkau peringkat
        $skipped = $this->submittedApplication('IN_JTICT');
        $this->assertFalse($this->urusSetia->can('decideJtict', $skipped));
        $this->assertDecisionRefused($skipped, DecisionStage::Jtict, Decision::Approved, self::REMARKS);

        // Diterima di Pra JTICT -> boleh
        $accepted = $this->acceptedApplication();
        $this->assertTrue($this->urusSetia->can('decideJtict', $accepted));
        $record = $this->decide($accepted, Decision::Approved, self::REMARKS);

        $this->assertSame(DecisionStage::Jtict, $record->stage);
        $this->assertSame(ApplicationStatus::InJtict->value, $record->from_status);
        $this->assertSame(ApplicationStatus::Closed->value, $accepted->status);

        // Selepas ditutup, tiada keputusan JTICT kedua
        $this->assertDecisionRefused($accepted, DecisionStage::Jtict, Decision::Review, self::REMARKS);
    }

    public function test_each_decision_sets_expected_status(): void
    {
        $this->meeting->update(['meeting_date' => today()->subDay()]);

        $expected = [
            [Decision::Accepted, null, 'IN_JTICT'],
            [Decision::AmendmentRequired, self::REMARKS, 'PRE_JTICT_AMENDMENT'],
            [Decision::Rejected, null, 'CLOSED'],
        ];
        foreach ($expected as [$decision, $remarks, $status]) {
            $application = $this->completeApplication();
            $record = $this->decide($application, $decision, $remarks);
            $this->assertSame($status, $application->status, $decision->value);
            $this->assertSame(['COMPLETE', $status], [$record->from_status, $record->to_status]);
        }

        foreach ([Decision::Approved, Decision::NotApproved, Decision::Review] as $decision) {
            $application = $this->acceptedApplication();
            $this->decide($application, $decision, self::REMARKS);
            $this->assertSame('CLOSED', $application->status, $decision->value);
            $this->assertSame($decision, $application->latestJtictDecision->decision);
        }

        // Pra JTICT Ditolak: tidak boleh dipinda / dihantar semula
        $rejected = Application::whereHas('decisions', fn ($q) => $q->where('decision', 'REJECTED'))->first();
        $this->assertFalse($this->applicant->can('update', $rejected));
    }

    public function test_remarks_are_required_according_to_owner_decision(): void
    {
        $this->meeting->update(['meeting_date' => today()->subDay()]);

        foreach ([Decision::AmendmentRequired] as $decision) {
            $this->assertRemarksRequired($this->completeApplication(), DecisionStage::PreJtict, $decision);
        }
        foreach ([Decision::Approved, Decision::NotApproved, Decision::Review] as $decision) {
            $this->assertRemarksRequired($this->acceptedApplication(), DecisionStage::Jtict, $decision);
        }

        // Ulasan pendek / HTML sahaja ditolak
        try {
            $this->service->decide($this->completeApplication(), DecisionStage::PreJtict, Decision::AmendmentRequired, '<b>Pinda</b>', today(), $this->urusSetia);
            $this->fail('Ulasan terlalu pendek');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('remarks', $e->errors());
        }

        // Diterima & Ditolak Pra JTICT: ulasan tidak wajib
        $this->assertNull($this->decide($this->completeApplication(), Decision::Accepted)->remarks);
        $this->assertNull($this->decide($this->completeApplication(), Decision::Rejected)->remarks);
    }

    public function test_decision_date_cannot_be_in_the_future(): void
    {
        $this->meeting->update(['meeting_date' => today()->subDay()]);
        $complete = $this->completeApplication();
        $accepted = $this->acceptedApplication();

        foreach ([[$complete, DecisionStage::PreJtict, Decision::Accepted], [$accepted, DecisionStage::Jtict, Decision::Approved]] as [$application, $stage, $decision]) {
            try {
                $this->service->decide($application, $stage, $decision, self::REMARKS, today()->addDay(), $this->urusSetia);
                $this->fail('Tarikh masa hadapan tidak sepatutnya diterima');
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('decisionDate', $e->errors());
            }
        }

        $this->assertSame(1, ApplicationDecision::count(), 'Hanya keputusan Diterima bagi persediaan');
    }

    public function test_pre_jtict_date_not_before_latest_pp2_complete_date(): void
    {
        $this->travelTo(now()->subDays(5));
        $application = $this->completeApplication(); // Lengkap 5 hari lalu
        $this->travelBack();

        // Kitaran kedua: Lengkap semula 2 hari lalu -> tarikh terakhir digunakan
        $this->decide($application, Decision::AmendmentRequired, self::REMARKS, today()->subDays(4));
        $application->forceFill(['status' => 'SUBMITTED'])->save();
        $this->travelTo(now()->subDays(2));
        app(ApplicationReviewService::class)->review($application->fresh(), ApplicationStatus::Complete, null, $this->urusSetia);
        $this->travelBack();

        try {
            $this->service->decide($application->fresh(), DecisionStage::PreJtict, Decision::Accepted, null, today()->subDays(3), $this->urusSetia);
            $this->fail('Tarikh sebelum Lengkap terakhir tidak sepatutnya diterima');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('decisionDate', $e->errors());
        }

        $this->assertSame(Decision::Accepted, $this->decide($application->fresh(), Decision::Accepted, null, today()->subDays(2))->decision);

        // Status Lengkap tanpa rekod PP2 Lengkap (data lama) -> tidak boleh disahkan
        $legacy = $this->submittedApplication('COMPLETE');
        $this->expectException(ValidationException::class);
        $this->service->decide($legacy, DecisionStage::PreJtict, Decision::Accepted, null, today(), $this->urusSetia);
    }

    public function test_jtict_date_not_before_meeting_date_and_meeting_must_match(): void
    {
        $this->meeting->update(['meeting_date' => today()->subDays(2)]);
        $application = $this->acceptedApplication();
        $other = $this->openJtictMeeting(['series' => 'Bil. 2/2026', 'meeting_date' => today()->subDays(5)]);

        $cases = [
            [today()->subDays(3), null, 'decisionDate'],
            [today(), $other->id, 'meetingId'],
        ];
        foreach ($cases as [$date, $meetingId, $key]) {
            try {
                $this->service->decide($application, DecisionStage::Jtict, Decision::Approved, self::REMARKS, $date, $this->urusSetia, $meetingId);
                $this->fail("Sepatutnya ditolak: {$key}");
            } catch (ValidationException $e) {
                $this->assertArrayHasKey($key, $e->errors());
            }
        }

        $record = $this->service->decide($application, DecisionStage::Jtict, Decision::Approved, self::REMARKS, today()->subDays(2), $this->urusSetia, $this->meeting->id);
        $this->assertTrue($record->meeting->is($this->meeting));
        $this->assertSame(today()->subDays(2)->toDateString(), $record->decision_date->toDateString());
    }

    public function test_jtict_decision_refused_when_meeting_is_deleted(): void
    {
        $this->meeting->update(['meeting_date' => today()->subDay()]);
        $application = $this->acceptedApplication();
        $this->meeting->delete();

        $this->expectException(ValidationException::class);
        $this->decide($application, Decision::Approved, self::REMARKS);
    }

    public function test_failed_transaction_leaves_no_partial_decision(): void
    {
        $application = $this->completeApplication();

        Application::updating(function (Application $model) {
            if ($model->status === ApplicationStatus::InJtict->value) {
                throw new RuntimeException('Simulasi kegagalan simpan status');
            }
        });

        try {
            $this->decide($application, Decision::Accepted);
            $this->fail('Kegagalan simulasi tidak dilontar');
        } catch (RuntimeException $e) {
            $this->assertSame('Simulasi kegagalan simpan status', $e->getMessage());
        }

        $this->assertSame(0, ApplicationDecision::count());
        $this->assertSame(ApplicationStatus::Complete->value, $application->fresh()->status);
    }

    public function test_repeated_decision_does_not_create_duplicate(): void
    {
        $application = $this->completeApplication();
        $stale = Application::find($application->id); // salinan lama (cth. tab kedua / klik berganda)

        $this->decide($application, Decision::Accepted);
        $this->assertDecisionRefused($stale, DecisionStage::PreJtict, Decision::Rejected, null);

        $this->assertSame(1, ApplicationDecision::count());
        $this->assertSame(ApplicationStatus::InJtict->value, $application->fresh()->status);
    }

    // --- I4. KAWALAN AKSES ---

    public function test_only_active_urus_setia_may_record_decisions(): void
    {
        $superadmin = User::factory()->create(['agency_id' => $this->agency->id]);
        $superadmin->assignRole('superadmin');
        $pengurusan = User::factory()->create(['agency_id' => $this->agency->id]);
        $pengurusan->assignRole('pengurusan');
        $dualRole = User::factory()->create(['agency_id' => $this->agency->id]);
        $dualRole->assignRole(['pentadbir_urus_setia', 'superadmin']);
        $mustChangePassword = User::factory()->create(['agency_id' => $this->agency->id, 'must_change_password' => true]);
        $mustChangePassword->assignRole('pentadbir_urus_setia');

        $application = $this->completeApplication();

        foreach ([$superadmin, $pengurusan, $this->applicant, $this->officer, $dualRole, $mustChangePassword] as $user) {
            $this->assertFalse($user->can('decidePreJtict', $application), $user->getRoleNames()->implode(','));
            $this->assertDecisionRefused($application, DecisionStage::PreJtict, Decision::Accepted, null, $user);
        }

        $this->assertTrue($this->urusSetia->can('decidePreJtict', $application));
        $record = $this->decide($application, Decision::Accepted);
        $this->assertSame($this->urusSetia->id, $record->decided_by, 'Perekod ditentukan oleh pelayan');
    }

    // --- I5. PEMELIHARAAN DATA ---

    public function test_decision_records_cannot_be_updated_or_deleted(): void
    {
        $record = $this->decide($this->completeApplication(), Decision::AmendmentRequired, self::REMARKS);

        foreach ([fn () => $record->update(['remarks' => 'Ulasan ditulis semula oleh pengguna.']), fn () => $record->delete()] as $operation) {
            try {
                $operation();
                $this->fail('Rekod sejarah keputusan tidak sepatutnya boleh diubah / dipadam');
            } catch (LogicException) {
            }
        }

        $this->assertSame(self::REMARKS, $record->fresh()->remarks);
        $this->assertSame(1, ApplicationDecision::count());
    }

    public function test_decisions_do_not_touch_legacy_fields_other_records_email_or_copies(): void
    {
        Notification::fake();
        Mail::fake();
        $this->meeting->update(['meeting_date' => today()->subDay()]);

        $bystander = $this->completeApplication();
        $bystanderBefore = $bystander->fresh()->getAttributes();
        $meetingBefore = $this->meeting->fresh()->getAttributes();

        $application = $this->completeApplication();
        $application->forceFill(['review_remarks' => 'Ulasan semakan PP2 asal.'])->save();
        $reviewsBefore = ApplicationReview::orderBy('id')->get()->toArray();

        $this->decide($application, Decision::Accepted);
        $this->decide($application, Decision::Review, self::REMARKS);

        $application->refresh();
        $this->assertSame('PENDING', $application->jtict_decision, 'Lajur warisan tidak digunakan');
        $this->assertNull($application->jtict_remarks);
        $this->assertSame('Ulasan semakan PP2 asal.', $application->review_remarks);
        $this->assertSame($this->meeting->id, $application->jtict_meeting_id);

        $this->assertSame($reviewsBefore, ApplicationReview::orderBy('id')->get()->toArray(), 'application_reviews tidak berubah');
        $this->assertSame($bystanderBefore, $bystander->fresh()->getAttributes(), 'Permohonan lain tidak berubah');
        $this->assertSame($meetingBefore, $this->meeting->fresh()->getAttributes(), 'Rekod mesyuarat tidak berubah');

        $this->assertSame(2, Application::count(), 'Tiada salinan permohonan dicipta');
        $this->assertSame(0, Application::whereNotNull('copied_from_application_id')->count());
        $this->assertSame(0, DB::table('projects')->count(), 'Tiada rekod projek dicipta');

        Notification::assertNothingSent();
        Mail::assertNothingSent();
    }

    public function test_no_decision_route_or_ui_is_exposed(): void
    {
        foreach (Route::getRoutes() as $route) {
            $this->assertDoesNotMatchRegularExpression('/keputusan|decision|pra-?jtict/i', $route->uri(), $route->uri());
        }

        foreach (array_merge(glob(app_path('Livewire/*.php')), glob(app_path('Livewire/*/*.php')), glob(app_path('Http/Controllers/*.php'))) as $file) {
            $this->assertStringNotContainsString('ApplicationDecisionService', file_get_contents($file), $file);
        }
    }

    // --- PEMBANTU ---

    private function decide(Application $application, Decision $decision, ?string $remarks = null, $date = null): ApplicationDecision
    {
        return $this->service->decide($application, $decision->stage(), $decision, $remarks, $date ?? today(), $this->urusSetia);
    }

    private function assertDecisionRefused(Application $application, DecisionStage $stage, Decision $decision, ?string $remarks, ?User $user = null): void
    {
        $before = ApplicationDecision::count();
        $status = $application->fresh()->status;

        try {
            $this->service->decide($application, $stage, $decision, $remarks, today(), $user ?? $this->urusSetia);
            $this->fail("Keputusan {$decision->value} tidak sepatutnya diterima (status {$status})");
        } catch (AuthorizationException) {
        }

        $this->assertSame($before, ApplicationDecision::count());
        $this->assertSame($status, $application->fresh()->status);
    }

    private function assertRemarksRequired(Application $application, DecisionStage $stage, Decision $decision): void
    {
        try {
            $this->service->decide($application, $stage, $decision, '   ', today(), $this->urusSetia);
            $this->fail("Ulasan wajib bagi {$decision->value}");
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('remarks', $e->errors());
        }

        $this->assertSame(0, $application->decisions()->where('stage', $stage->value)->count());
    }

    private function submittedApplication(string $status = 'SUBMITTED', array $attributes = []): Application
    {
        $this->sequence++;

        $application = Application::create($attributes + [
            'reference_number' => 'SPPICT/2026/' . str_pad((string) $this->sequence, 4, '0', STR_PAD_LEFT),
            'agency_id' => $this->agency->id,
            'applicant_id' => $this->applicant->id,
            'title' => "Projek Keputusan {$this->sequence}",
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

    private function completeApplication(): Application
    {
        $application = $this->submittedApplication();
        app(ApplicationReviewService::class)->review($application, ApplicationStatus::Complete, null, $this->urusSetia);

        return $application->fresh();
    }

    private function acceptedApplication(): Application
    {
        $application = $this->completeApplication();
        $this->decide($application, Decision::Accepted);

        return $application;
    }

    private function resubmitAndComplete(Application $application): void
    {
        $component = Livewire::actingAs($this->applicant)->test(ApplicationForm::class, ['uuid' => $application->uuid]);
        $this->fillValidForm($component);
        $component->call('submitApplication')->assertHasNoErrors();

        app(ApplicationReviewService::class)->review($application->fresh(), ApplicationStatus::Complete, null, $this->urusSetia);
        $application->refresh();
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
