<?php

namespace Tests\Feature;

use App\Enums\ApplicationStatus;
use App\Livewire\ApplicationForm;
use App\Livewire\ApplicationList;
use App\Livewire\Forms\ApplicationForm as ApplicationFormObject;
use App\Models\Agency;
use App\Models\Application;
use App\Models\ApplicationOfficer;
use App\Models\ProcurementMethod;
use App\Models\ProcurementType;
use App\Models\ProjectCategory;
use App\Models\User;
use App\Enums\OfficerRole;
use App\Services\ApplicationSubmissionService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use OwenIt\Auditing\Models\Audit;
use Spatie\Permission\Models\Role;
use Tests\Concerns\OpensJtictMeeting;
use Tests\Concerns\UsesApplicationMasterData;
use Tests\TestCase;

/**
 * PP1: status proses permohonan diseragamkan melalui App\Enums\ApplicationStatus dan
 * diasingkan daripada keputusan Pra JTICT / JTICT.
 */
class ApplicationStatusTest extends TestCase
{
    use RefreshDatabase, OpensJtictMeeting, UsesApplicationMasterData;

    private Agency $agency;

    private User $applicant;

    private int $sequence = 0;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->agency = Agency::create(['code' => 'JKR', 'name' => 'Agensi JKR', 'category' => 'SUK', 'is_active' => true]);
        $this->applicant = User::factory()->create(['agency_id' => $this->agency->id]);
        $this->applicant->assignRole(Role::findOrCreate('pengguna_biasa'));
    }

    // --- SUMBER RUJUKAN STATUS ---

    public function test_status_values_and_malay_labels(): void
    {
        $this->assertSame([
            'DRAFT' => 'Draf',
            'SUBMITTED' => 'Dihantar',
            'NEEDS_AMENDMENT' => 'Tidak Lengkap',
            'COMPLETE' => 'Lengkap',
            'PRE_JTICT_AMENDMENT' => 'Pindaan Pra JTICT', // PP4.1: Pra JTICT "Ditolak dengan Pindaan"
            'IN_JTICT' => 'Dalam Proses JTICT',
            'CLOSED' => 'Selesai',
        ], ApplicationStatus::options());
    }

    public function test_process_status_does_not_contain_meeting_decisions(): void
    {
        // Keputusan Pra JTICT (Diterima/Ditolak) dan JTICT (Lulus/Tidak Lulus/Kaji Semula) bukan status proses.
        foreach (['APPROVED', 'REJECTED', 'REVIEW', 'ACCEPTED', 'PENDING', 'NOT_APPROVED'] as $decision) {
            $this->assertNull(ApplicationStatus::tryFrom($decision), $decision);
        }

        foreach (['Lulus', 'Tidak Lulus', 'Kaji Semula', 'Diterima', 'Ditolak'] as $label) {
            $this->assertNotContains($label, ApplicationStatus::options());
        }
    }

    public function test_allowed_transitions_follow_workflow(): void
    {
        $allowed = [
            'DRAFT' => ['SUBMITTED'],
            'SUBMITTED' => ['NEEDS_AMENDMENT', 'COMPLETE'],
            'NEEDS_AMENDMENT' => ['SUBMITTED'],
            // PP4.1: Pra JTICT Ditolak dengan Pindaan -> pindaan -> hantar semula (melalui PP2)
            'COMPLETE' => ['IN_JTICT', 'PRE_JTICT_AMENDMENT', 'CLOSED'],
            'PRE_JTICT_AMENDMENT' => ['SUBMITTED'],
            'IN_JTICT' => ['CLOSED'],
            'CLOSED' => [],
        ];

        foreach (ApplicationStatus::cases() as $from) {
            foreach (ApplicationStatus::cases() as $to) {
                $this->assertSame(
                    in_array($to->value, $allowed[$from->value], true),
                    $from->canTransitionTo($to),
                    "{$from->value} -> {$to->value}"
                );
            }
        }

        // Pemohon tidak boleh melompat terus ke Lengkap; penghantaran semula kembali kepada Dihantar.
        $this->assertFalse(ApplicationStatus::Draft->canTransitionTo(ApplicationStatus::Complete));
        $this->assertFalse(ApplicationStatus::NeedsAmendment->canTransitionTo(ApplicationStatus::Complete));
    }

    public function test_editable_statuses_come_from_enum(): void
    {
        $this->assertSame(
            array_map(fn (ApplicationStatus $status) => $status->value, ApplicationStatus::editableCases()),
            Application::EDITABLE_STATUSES
        );

        foreach (ApplicationStatus::cases() as $status) {
            $application = $this->makeApplication($status->value);
            $this->assertSame($status->isEditableByApplicant(), $application->isEditable(), $status->value);
        }
    }

    public function test_status_column_accepts_new_workflow_statuses(): void
    {
        foreach (['COMPLETE', 'IN_JTICT', 'CLOSED'] as $status) {
            $this->assertSame($status, $this->makeApplication($status)->fresh()->status);
        }
    }

    // --- REKOD LAMA / NILAI TIDAK DIKENALI ---

    public function test_legacy_or_unknown_status_is_displayed_safely(): void
    {
        $legacy = $this->makeApplication('UNDER_REVIEW', 'Projek Status Lama');

        $this->assertNull($legacy->statusEnum());
        $this->assertSame('Tidak Diketahui (UNDER_REVIEW)', $legacy->statusLabel());
        $this->assertFalse($legacy->isEditable());

        $this->actingAs($this->applicant)->get('/dashboard')
            ->assertOk()
            ->assertSee('Projek Status Lama')
            ->assertSee('Tidak Diketahui (UNDER_REVIEW)');

        $this->actingAs($this->applicant)->get(route('application.view', $legacy->uuid))
            ->assertOk()
            ->assertSee('Tidak Diketahui (UNDER_REVIEW)');
    }

    // --- PAPARAN & PENAPIS ---

    public function test_list_filter_uses_internal_values_and_ignores_invalid_values(): void
    {
        $this->makeApplication('DRAFT', 'Projek Draf');
        $this->makeApplication('NEEDS_AMENDMENT', 'Projek Pindaan');
        $this->makeApplication('SUBMITTED', 'Projek Dihantar');

        $component = Livewire::actingAs($this->applicant)->test(ApplicationList::class)
            ->assertSeeHtml('<option value="NEEDS_AMENDMENT">Tidak Lengkap</option>')
            ->assertDontSeeHtml('<option value="APPROVED">')
            ->assertSee('Perlu pindaan &amp; hantar semula', false);

        $component->set('statusFilter', 'NEEDS_AMENDMENT')
            ->assertSee('Projek Pindaan')
            ->assertDontSee('Projek Draf')
            ->assertDontSee('Projek Dihantar');

        // Nilai luar enum (cth. label lama "APPROVED") diabaikan, bukan menapis kepada senarai kosong
        $component->set('statusFilter', 'APPROVED')
            ->assertSee('Projek Pindaan')
            ->assertSee('Projek Draf')
            ->assertSee('Projek Dihantar');
    }

    public function test_view_page_shows_process_status_label(): void
    {
        $application = $this->makeApplication('SUBMITTED');

        $this->actingAs($this->applicant)->get(route('application.view', $application->uuid))
            ->assertOk()
            ->assertSee('Dihantar');
    }

    // --- ALIRAN TIDAK LENGKAP -> DIHANTAR SEMULA ---

    public function test_resubmission_after_amendment_returns_to_submitted_and_keeps_remarks_and_decision(): void
    {
        config(['audit.console' => true]); // rekod audit sedia ada (owen-it) sebagai jejak tindakan

        $meeting = $this->openJtictMeeting();
        $application = $this->makeApplication('NEEDS_AMENDMENT', 'Projek Pindaan', [
            'jtict_meeting_id' => $meeting->id,
            'review_remarks' => 'Sila lampirkan Slaid Pembentangan.',
        ]);
        $application->officers()->create(ApplicationOfficer::snapshotFrom($this->applicant) + [
            'role' => OfficerRole::Primary,
            'started_at' => today()->subMonth(),
        ]);

        $component = Livewire::actingAs($this->applicant)->test(ApplicationForm::class, ['uuid' => $application->uuid]);
        $this->fillValidForm($component);
        $component->call('submitApplication')->assertHasNoErrors()->assertRedirect('/dashboard');

        $application->refresh();
        $this->assertSame(ApplicationStatus::Submitted->value, $application->status, 'Kembali kepada semakan Urus Setia, bukan Lengkap');
        $this->assertSame('Sila lampirkan Slaid Pembentangan.', $application->review_remarks, 'Ulasan terdahulu tidak dipadam');
        $this->assertSame('PENDING', $application->jtict_decision, 'Keputusan JTICT tidak disentuh oleh status proses');

        $audit = Audit::where('auditable_type', Application::class)
            ->where('auditable_id', $application->id)
            ->where('event', 'updated')
            ->get()
            ->first(fn (Audit $audit) => ($audit->old_values['status'] ?? null) === 'NEEDS_AMENDMENT');

        $this->assertNotNull($audit, 'Penghantaran semula direkodkan dalam log audit');
        $this->assertSame('SUBMITTED', $audit->new_values['status']);
        $this->assertSame($this->applicant->id, (int) $audit->user_id);
    }

    public function test_application_beyond_submission_cannot_be_submitted_again(): void
    {
        $this->openJtictMeeting();
        $form = new ApplicationFormObject(new ApplicationForm(), 'form');

        foreach (['SUBMITTED', 'COMPLETE', 'IN_JTICT', 'CLOSED'] as $status) {
            $application = $this->makeApplication($status);

            try {
                app(ApplicationSubmissionService::class)->submit($form, $this->applicant, $application->id);
                $this->fail("Status {$status} tidak sepatutnya boleh dihantar");
            } catch (AuthorizationException) {
                $this->assertSame($status, $application->fresh()->status);
            }
        }
    }

    // --- PEMBANTU ---

    private function makeApplication(string $status, ?string $title = null, array $attributes = []): Application
    {
        $this->sequence++;

        return Application::create($attributes + [
            'reference_number' => 'SPPICT/2026/' . str_pad((string) $this->sequence, 4, '0', STR_PAD_LEFT),
            'agency_id' => $this->agency->id,
            'applicant_id' => $this->applicant->id,
            'title' => $title ?? "Projek {$this->sequence}",
            'estimated_cost' => 10000,
            'status' => $status,
        ]);
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
