<?php

namespace Tests\Feature;

use App\Enums\ApplicationStatus;
use App\Enums\OfficerRole;
use App\Livewire\ApplicationForm;
use App\Livewire\ApplicationList;
use App\Livewire\ViewApplication;
use App\Models\Agency;
use App\Models\Application;
use App\Models\ApplicationOfficer;
use App\Models\ApplicationReview;
use App\Models\Meeting;
use App\Models\ProcurementMethod;
use App\Models\ProcurementType;
use App\Models\ProjectCategory;
use App\Models\User;
use App\Notifications\ApplicationMarkedIncomplete;
use App\Services\ApplicationReviewService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use LogicException;
use Spatie\Permission\Models\Role;
use Tests\Concerns\OpensJtictMeeting;
use Tests\Concerns\UsesApplicationMasterData;
use Tests\TestCase;

/**
 * PP2: semakan kelengkapan oleh Urus Setia, sejarah application_reviews, e-mel Tidak Lengkap,
 * pindaan dan penghantaran semula (termasuk peraturan mesyuarat).
 */
class ApplicationReviewTest extends TestCase
{
    use RefreshDatabase, OpensJtictMeeting, UsesApplicationMasterData;

    private const REMARKS = 'Sila lampirkan Slaid Pembentangan dan perincikan skop projek.';

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

    // --- 11.1 SEMAKAN LENGKAP ---

    public function test_urus_setia_can_mark_submitted_application_complete_without_email(): void
    {
        Notification::fake();
        $application = $this->submittedApplication();

        Livewire::actingAs($this->urusSetia)->test(ViewApplication::class, ['uuid' => $application->uuid])
            ->assertSee('Tandakan Lengkap')
            ->call('openReviewModal', 'COMPLETE')
            ->call('submitReview')
            ->assertHasNoErrors()
            ->assertSee('Permohonan ditetapkan sebagai Lengkap.')
            ->assertSee('Ditetapkan Lengkap')
            ->assertDontSee('Tandakan Tidak Lengkap');

        $application->refresh();
        $this->assertSame(ApplicationStatus::Complete->value, $application->status);

        $review = ApplicationReview::sole();
        $this->assertSame($application->id, $review->application_id);
        $this->assertSame('SUBMITTED', $review->from_status);
        $this->assertSame('COMPLETE', $review->to_status);
        $this->assertSame($this->urusSetia->id, $review->user_id);
        $this->assertSame($this->meeting->id, $review->jtict_meeting_id);
        $this->assertNull($review->remarks, 'Ulasan tidak diwajibkan bagi Lengkap');
        $this->assertNotNull($review->created_at);
        $this->assertSame('PENDING', $application->jtict_decision, 'Lengkap bukan keputusan JTICT');

        Notification::assertNothingSent();
    }

    // --- 11.2 SEMAKAN TIDAK LENGKAP ---

    public function test_incomplete_requires_meaningful_remarks(): void
    {
        Notification::fake();
        $application = $this->submittedApplication();

        $component = Livewire::actingAs($this->urusSetia)->test(ViewApplication::class, ['uuid' => $application->uuid])
            ->call('openReviewModal', 'NEEDS_AMENDMENT');

        foreach (['', '     ', "<p>  </p>", 'Pendek', str_repeat('a', ApplicationReviewService::REMARKS_MAX + 1)] as $remarks) {
            $component->set('reviewRemarks', $remarks)->call('submitReview')->assertHasErrors('reviewRemarks');
        }

        $this->assertSame('SUBMITTED', $application->fresh()->status);
        $this->assertSame(0, ApplicationReview::count());
        Notification::assertNothingSent();
    }

    public function test_urus_setia_marks_incomplete_and_applicant_and_primary_officer_are_notified(): void
    {
        Notification::fake();
        $application = $this->submittedApplication();

        Livewire::actingAs($this->urusSetia)->test(ViewApplication::class, ['uuid' => $application->uuid])
            ->call('openReviewModal', 'NEEDS_AMENDMENT')
            ->set('reviewRemarks', '  <b>' . self::REMARKS . '</b>  ')
            ->call('submitReview')
            ->assertHasNoErrors()
            ->assertSee('Pemohon dan Pegawai Utama akan dimaklumkan');

        $application->refresh();
        $this->assertSame('NEEDS_AMENDMENT', $application->status);
        $this->assertSame(self::REMARKS, $application->review_remarks, 'Tag HTML & ruang dibuang; review_remarks = ulasan terkini');

        $review = ApplicationReview::sole();
        $this->assertSame('NEEDS_AMENDMENT', $review->to_status);
        $this->assertSame(self::REMARKS, $review->remarks);
        $this->assertSame($this->urusSetia->id, $review->user_id);

        Notification::assertSentTo($this->applicant, ApplicationMarkedIncomplete::class);
        Notification::assertSentTo($this->officer, ApplicationMarkedIncomplete::class);
        Notification::assertNotSentTo($this->urusSetia, ApplicationMarkedIncomplete::class);
        Notification::assertCount(2);
    }

    public function test_incomplete_email_content(): void
    {
        $application = $this->submittedApplication();
        $review = app(ApplicationReviewService::class)->review($application, ApplicationStatus::NeedsAmendment, self::REMARKS, $this->urusSetia);

        $mail = (new ApplicationMarkedIncomplete($application, $review))->toMail($this->applicant);
        $lines = implode("\n", $mail->introLines);

        $this->assertStringContainsString($application->reference_number, $mail->subject);
        $this->assertStringContainsString('Tidak Lengkap', $mail->subject);
        $this->assertStringContainsString('SPPICT', $lines);
        $this->assertStringContainsString($application->title, $lines);
        $this->assertStringContainsString(self::REMARKS, $lines);
        $this->assertStringContainsString('hantar semula', $lines);
        $this->assertSame(route('application.view', $application->uuid), $mail->actionUrl);
    }

    public function test_applicant_who_is_also_primary_officer_receives_one_email(): void
    {
        Notification::fake();
        $application = $this->submittedApplication(officer: $this->applicant);

        app(ApplicationReviewService::class)->review($application, ApplicationStatus::NeedsAmendment, self::REMARKS, $this->urusSetia);

        Notification::assertSentToTimes($this->applicant, ApplicationMarkedIncomplete::class, 1);
        Notification::assertCount(1);
    }

    public function test_officer_without_account_uses_stored_email_and_invalid_email_is_skipped(): void
    {
        Notification::fake();
        Log::spy();

        $application = $this->submittedApplication();
        $application->primaryOfficer->forceFill(['user_id' => null, 'email' => 'pegawai.lama@ns.gov.my'])->save();

        app(ApplicationReviewService::class)->review($application, ApplicationStatus::NeedsAmendment, self::REMARKS, $this->urusSetia);

        Notification::assertSentTo($this->applicant, ApplicationMarkedIncomplete::class);
        Notification::assertSentOnDemand(ApplicationMarkedIncomplete::class, fn ($notification, $channels, AnonymousNotifiable $notifiable) => $notifiable->routes['mail'] === 'pegawai.lama@ns.gov.my');

        // E-mel snapshot tidak sah: dilangkau dan dilog, keputusan tetap disimpan
        $second = $this->submittedApplication();
        $second->primaryOfficer->forceFill(['user_id' => null, 'email' => 'bukan-emel'])->save();

        app(ApplicationReviewService::class)->review($second, ApplicationStatus::NeedsAmendment, self::REMARKS, $this->urusSetia);

        $this->assertSame('NEEDS_AMENDMENT', $second->fresh()->status);
        Log::shouldHaveReceived('warning')->withArgs(fn ($message) => str_contains($message, 'Pegawai Utama') && str_contains($message, 'tidak sah'));
    }

    public function test_email_failure_does_not_undo_review(): void
    {
        Log::spy();
        Notification::shouldReceive('send')->andThrow(new \RuntimeException('SMTP tidak tersedia'));

        $application = $this->submittedApplication();
        app(ApplicationReviewService::class)->review($application, ApplicationStatus::NeedsAmendment, self::REMARKS, $this->urusSetia);

        $this->assertSame('NEEDS_AMENDMENT', $application->fresh()->status);
        $this->assertSame(1, ApplicationReview::count());
        Log::shouldHaveReceived('error')->withArgs(fn ($message) => str_contains($message, 'gagal dihantar'));
    }

    public function test_repeated_review_request_does_not_create_duplicate_record_or_email(): void
    {
        Notification::fake();
        $application = $this->submittedApplication();

        $component = Livewire::actingAs($this->urusSetia)->test(ViewApplication::class, ['uuid' => $application->uuid])
            ->call('openReviewModal', 'NEEDS_AMENDMENT')
            ->set('reviewRemarks', self::REMARKS)
            ->call('submitReview');

        // Permintaan berganda (payload sama dihantar semula)
        $component->set('reviewDecision', 'NEEDS_AMENDMENT')
            ->set('reviewRemarks', self::REMARKS)
            ->call('submitReview')
            ->assertSee('Permohonan ini telah disemak');

        $this->assertSame(1, ApplicationReview::count());
        Notification::assertSentToTimes($this->applicant, ApplicationMarkedIncomplete::class, 1);

        // Panggilan terus kepada service juga ditolak
        $this->expectException(AuthorizationException::class);
        app(ApplicationReviewService::class)->review($application, ApplicationStatus::Complete, null, $this->urusSetia);
    }

    // --- 11.3 PINDAAN & PENGHANTARAN SEMULA ---

    public function test_full_amendment_cycle_keeps_history(): void
    {
        Notification::fake();
        $application = $this->submittedApplication();
        $service = app(ApplicationReviewService::class);

        $service->review($application, ApplicationStatus::NeedsAmendment, self::REMARKS, $this->urusSetia);

        // Pemohon melihat ulasan dan pautan pindaan; nama penyemak tidak didedahkan
        $this->actingAs($this->applicant)->get(route('application.view', $application->uuid))
            ->assertOk()
            ->assertSee(self::REMARKS)
            ->assertSee('Kemas Kini &amp; Hantar Semula', false)
            ->assertSee('Ditetapkan Tidak Lengkap')
            ->assertDontSee('Siti Urus Setia')
            ->assertDontSee('Tandakan Lengkap');

        // Borang pindaan memaparkan ulasan; mesyuarat asal masih dibuka -> dikekalkan
        $component = Livewire::actingAs($this->applicant)->test(ApplicationForm::class, ['uuid' => $application->uuid])
            ->assertSee(self::REMARKS)
            ->set('currentStep', 5)
            ->assertSee('Penghantaran semula pindaan ke mesyuarat asal');
        $this->fillValidForm($component);
        $component->call('submitApplication')->assertHasNoErrors()->assertRedirect('/dashboard');

        $application->refresh();
        $this->assertSame('SUBMITTED', $application->status, 'Kembali kepada semakan, bukan Lengkap');
        $this->assertSame($this->meeting->id, $application->jtict_meeting_id);

        $resubmission = ApplicationReview::latest('id')->first();
        $this->assertTrue($resubmission->isResubmission());
        $this->assertSame($this->applicant->id, $resubmission->user_id);

        // Semakan kedua: masih Tidak Lengkap (rekod baharu), kemudian dihantar semula & Lengkap
        $service->review($application, ApplicationStatus::NeedsAmendment, 'Kos seunit item kedua perlu disahkan semula.', $this->urusSetia);

        $component = Livewire::actingAs($this->applicant)->test(ApplicationForm::class, ['uuid' => $application->uuid])
            ->assertSee('Kos seunit item kedua perlu disahkan semula.');
        $this->fillValidForm($component);
        $component->call('submitApplication')->assertHasNoErrors();

        $service->review($application->fresh(), ApplicationStatus::Complete, null, $this->urusSetia);

        $this->assertSame(
            ['NEEDS_AMENDMENT', 'SUBMITTED', 'NEEDS_AMENDMENT', 'SUBMITTED', 'COMPLETE'],
            $application->reviews()->orderBy('id')->pluck('to_status')->all()
        );
        $this->assertSame(self::REMARKS, $application->reviews()->orderBy('id')->first()->remarks, 'Ulasan terdahulu kekal');
        $this->assertSame('COMPLETE', $application->fresh()->status);

        $this->actingAs($this->applicant)->get(route('application.view', $application->uuid))
            ->assertSee(self::REMARKS)
            ->assertSee('Kos seunit item kedua perlu disahkan semula.')
            ->assertSee('Dihantar Semula');
    }

    public function test_resubmission_is_blocked_without_open_meeting(): void
    {
        $application = $this->amendmentApplication();
        $this->meeting->update(['submission_closes_at' => today()->subDay()]);

        $component = Livewire::actingAs($this->applicant)->test(ApplicationForm::class, ['uuid' => $application->uuid])
            ->set('currentStep', 5)
            ->assertSee('Tiada Mesyuarat JTICT aktif untuk penghantaran semula');
        $this->fillValidForm($component);
        $component->call('submitApplication')->assertSee('Tiada Mesyuarat JTICT aktif yang layak untuk penghantaran semula');

        $application->refresh();
        $this->assertSame('NEEDS_AMENDMENT', $application->status);
        $this->assertSame($this->meeting->id, $application->jtict_meeting_id, 'Mesyuarat tidak diubah / diaktifkan semula');
        $this->assertSame(0, $application->reviews()->where('to_status', 'SUBMITTED')->count());
        $this->assertFalse($this->meeting->fresh()->isOpenForSubmission());
    }

    public function test_tampered_meeting_selection_is_rejected(): void
    {
        $application = $this->amendmentApplication();
        $this->meeting->update(['submission_closes_at' => today()->subDay()]);
        $open = $this->openJtictMeeting(['series' => 'Bil. 2/2026']);
        $inactive = $this->openJtictMeeting(['series' => 'Bil. 3/2026', 'is_active' => false]);
        $jpict = $this->openJtictMeeting(['code' => 'JPICTNS', 'series' => 'Bil. 4/2026']);

        $component = Livewire::actingAs($this->applicant)->test(ApplicationForm::class, ['uuid' => $application->uuid]);
        $this->fillValidForm($component);

        foreach ([$this->meeting->id, $inactive->id, $jpict->id, 999999] as $meetingId) {
            $component->set('resubmissionMeetingId', $meetingId)->call('submitApplication')->assertHasErrors('resubmissionMeetingId');
            $this->assertSame('NEEDS_AMENDMENT', $application->fresh()->status);
        }

        $component->set('resubmissionMeetingId', $open->id)->call('submitApplication')->assertHasNoErrors();
        $this->assertSame($open->id, $application->fresh()->jtict_meeting_id);
        $this->assertSame($open->id, ApplicationReview::latest('id')->first()->jtict_meeting_id);
    }

    public function test_only_owner_can_amend_incomplete_application(): void
    {
        $application = $this->amendmentApplication();

        $colleague = User::factory()->create(['agency_id' => $this->agency->id]);
        $colleague->assignRole('pengguna_biasa');
        $otherAgency = Agency::create(['code' => 'JPN', 'name' => 'Agensi Lain', 'category' => 'SUK', 'is_active' => true]);
        $outsider = User::factory()->create(['agency_id' => $otherAgency->id]);
        $outsider->assignRole('pengguna_biasa');

        $this->actingAs($this->applicant)->get(route('application.edit', $application->uuid))->assertOk();
        $this->actingAs($colleague)->get(route('application.edit', $application->uuid))->assertForbidden();
        $this->actingAs($outsider)->get(route('application.edit', $application->uuid))->assertForbidden();
        $this->actingAs($this->urusSetia)->get(route('application.edit', $application->uuid))->assertForbidden();
    }

    // --- 11.4 KAWALAN AKSES ---

    public function test_only_urus_setia_can_review_submitted_applications(): void
    {
        $application = $this->submittedApplication();
        $service = app(ApplicationReviewService::class);

        $superadmin = User::factory()->create(['agency_id' => $this->agency->id]);
        $superadmin->assignRole('superadmin');
        $pengurusan = User::factory()->create(['agency_id' => $this->agency->id]);
        $pengurusan->assignRole('pengurusan');
        $dualRole = User::factory()->create(['agency_id' => $this->agency->id]);
        $dualRole->assignRole(['pentadbir_urus_setia', 'superadmin']);

        foreach ([$this->applicant, $this->officer, $superadmin, $pengurusan, $dualRole] as $user) {
            try {
                $service->review($application, ApplicationStatus::Complete, null, $user);
                $this->fail("Pengguna {$user->id} tidak sepatutnya boleh menyemak");
            } catch (AuthorizationException) {
                $this->assertSame('SUBMITTED', $application->fresh()->status);
            }
        }

        // Pemohon tidak boleh membuka tindakan semakan melalui payload Livewire
        Livewire::actingAs($this->applicant)->test(ViewApplication::class, ['uuid' => $application->uuid])
            ->assertDontSee('Tandakan Lengkap')
            ->call('openReviewModal', 'COMPLETE')
            ->assertForbidden();

        Livewire::actingAs($this->applicant)->test(ViewApplication::class, ['uuid' => $application->uuid])
            ->set('reviewDecision', 'COMPLETE')
            ->call('submitReview')
            ->assertForbidden();

        // Keputusan selain Lengkap / Tidak Lengkap ditolak
        Livewire::actingAs($this->urusSetia)->test(ViewApplication::class, ['uuid' => $application->uuid])
            ->call('openReviewModal', 'CLOSED')
            ->assertStatus(422);

        $this->assertSame('SUBMITTED', $application->fresh()->status);
        $this->assertSame(0, ApplicationReview::count());
    }

    public function test_review_is_only_available_for_submitted_status(): void
    {
        foreach (['DRAFT', 'NEEDS_AMENDMENT', 'COMPLETE', 'IN_JTICT', 'CLOSED'] as $status) {
            $application = $this->submittedApplication(status: $status);

            $this->assertFalse($this->urusSetia->can('review', $application), $status);
            Livewire::actingAs($this->urusSetia)->test(ViewApplication::class, ['uuid' => $application->uuid])
                ->assertDontSee('Tandakan Lengkap');
        }
    }

    public function test_review_history_cannot_be_modified_or_deleted(): void
    {
        $application = $this->submittedApplication();
        $review = app(ApplicationReviewService::class)->review($application, ApplicationStatus::NeedsAmendment, self::REMARKS, $this->urusSetia);

        try {
            $review->update(['remarks' => 'Diubah']);
            $this->fail('Rekod sejarah tidak sepatutnya boleh diubah');
        } catch (LogicException) {
        }

        try {
            $review->delete();
            $this->fail('Rekod sejarah tidak sepatutnya boleh dipadam');
        } catch (LogicException) {
        }

        $this->assertSame(self::REMARKS, $review->fresh()->remarks);
    }

    public function test_review_remarks_are_not_exposed_to_unauthorised_users(): void
    {
        $application = $this->submittedApplication();
        app(ApplicationReviewService::class)->review($application, ApplicationStatus::NeedsAmendment, self::REMARKS, $this->urusSetia);

        $otherAgency = Agency::create(['code' => 'JPN', 'name' => 'Agensi Lain', 'category' => 'SUK', 'is_active' => true]);
        $outsider = User::factory()->create(['agency_id' => $otherAgency->id]);
        $outsider->assignRole('pengguna_biasa');

        $this->actingAs($outsider)->get(route('application.view', $application->uuid))->assertForbidden();

        // Urus Setia melihat nama penyemak dalam sejarah
        $this->actingAs($this->urusSetia)->get(route('application.view', $application->uuid))
            ->assertOk()
            ->assertSee('Urus Setia: Siti Urus Setia');
    }

    // --- 6.1 SENARAI URUS SETIA ---

    public function test_urus_setia_list_defaults_to_submitted_and_filters_by_status(): void
    {
        $this->submittedApplication(title: 'Projek Menunggu Semakan');
        $this->submittedApplication(status: 'NEEDS_AMENDMENT', title: 'Projek Tidak Lengkap');
        $this->submittedApplication(status: 'COMPLETE', title: 'Projek Lengkap');
        $this->submittedApplication(status: 'DRAFT', title: 'Projek Draf');

        $returned = $this->submittedApplication(title: 'Projek Dihantar Semula');
        ApplicationReview::create(['application_id' => $returned->id, 'from_status' => 'SUBMITTED', 'to_status' => 'NEEDS_AMENDMENT', 'remarks' => self::REMARKS, 'user_id' => $this->urusSetia->id]);
        ApplicationReview::create(['application_id' => $returned->id, 'from_status' => 'NEEDS_AMENDMENT', 'to_status' => 'SUBMITTED', 'user_id' => $this->applicant->id]);

        $component = Livewire::actingAs($this->urusSetia)->test(ApplicationList::class)
            ->assertSet('statusFilter', 'SUBMITTED')
            ->assertSee('Projek Menunggu Semakan')
            ->assertSee('Projek Dihantar Semula')
            ->assertSee('Dihantar semula selepas pindaan')
            ->assertDontSee('Projek Tidak Lengkap')
            ->assertDontSee('Projek Lengkap')
            ->assertDontSee('Projek Draf')
            // PP3: pilihan Draf wujud tetapi hanya untuk draf Urus Setia sendiri
            ->assertSeeHtml('<option value="DRAFT">Draf Saya</option>');

        $component->set('statusFilter', 'COMPLETE')
            ->assertSee('Projek Lengkap')
            ->assertDontSee('Projek Menunggu Semakan');

        $component->set('statusFilter', 'NEEDS_AMENDMENT')
            ->assertSee('Projek Tidak Lengkap')
            ->assertDontSee('Projek Lengkap');

        // Draf pemohon lain tidak didedahkan (PP3: penapis Draf = draf Urus Setia sendiri sahaja)
        $component->set('statusFilter', 'DRAFT')
            ->assertDontSee('Projek Draf')
            ->assertDontSee('Projek Menunggu Semakan');

        // Nilai tidak sah: kembali kepada Dihantar
        $component->set('statusFilter', 'BUKAN_STATUS')
            ->assertDontSee('Projek Draf')
            ->assertSee('Projek Menunggu Semakan');
    }

    // --- MIGRASI ---

    public function test_migration_down_refuses_to_drop_existing_history(): void
    {
        $application = $this->submittedApplication();
        app(ApplicationReviewService::class)->review($application, ApplicationStatus::Complete, null, $this->urusSetia);

        $migration = require database_path('migrations/2026_10_28_000002_create_application_reviews_table.php');

        $this->expectException(\RuntimeException::class);
        $migration->down();
    }

    // --- PEMBANTU ---

    private function submittedApplication(string $status = 'SUBMITTED', ?User $officer = null, ?string $title = null): Application
    {
        $this->sequence++;

        $application = Application::create([
            'reference_number' => 'SPPICT/2026/' . str_pad((string) $this->sequence, 4, '0', STR_PAD_LEFT),
            'agency_id' => $this->agency->id,
            'applicant_id' => $this->applicant->id,
            'title' => $title ?? "Projek Semakan {$this->sequence}",
            'estimated_cost' => 10000,
            'status' => $status,
            'jtict_meeting_id' => $this->meeting->id,
        ]);

        $application->officers()->create(ApplicationOfficer::snapshotFrom($officer ?? $this->officer) + [
            'role' => OfficerRole::Primary,
            'started_at' => today()->subMonth(),
        ]);

        return $application;
    }

    private function amendmentApplication(): Application
    {
        $application = $this->submittedApplication();
        app(ApplicationReviewService::class)->review($application, ApplicationStatus::NeedsAmendment, self::REMARKS, $this->urusSetia);

        return $application->fresh();
    }

    private function fillValidForm($component): void
    {
        $component
            ->set('form.title', 'Projek Pindaan')
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
