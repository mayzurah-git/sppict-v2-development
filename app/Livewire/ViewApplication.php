<?php

namespace App\Livewire;

use App\Enums\ApplicationDecision;
use App\Enums\ApplicationStatus;
use App\Enums\DecisionStage;
use App\Enums\OfficerEndReason;
use App\Enums\OfficerRole;
use App\Models\Application;
use App\Models\ApplicationDecision as ApplicationDecisionRecord;
use App\Models\ApplicationReview;
use App\Models\User;
use App\Services\ApplicationDecisionService;
use App\Services\ApplicationReviewService;
use App\Services\OfficerHandoverService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;

class ViewApplication extends Component
{
    public Application $application;

    // --- MODAL TUKAR PEGAWAI BERTANGGUNGJAWAB ---
    public bool $showHandoverModal = false;
    public string $handoverRole = '';
    public $newOfficerId = '';
    public string $effectiveDate = '';
    public string $endReason = '';
    public string $handoverRemarks = '';

    // --- PP2: SEMAKAN KELENGKAPAN (URUS SETIA) ---
    public bool $showReviewModal = false;
    public string $reviewDecision = '';
    public string $reviewRemarks = '';

    // --- PP4.2: KEPUTUSAN PRA JTICT (URUS SETIA) ---
    public bool $showPreJtictModal = false;
    public string $preJtictDecision = '';
    public string $preJtictDate = '';
    public string $preJtictRemarks = '';

    // --- PP4.3A: KEPUTUSAN MESYUARAT JTICT (URUS SETIA) ---
    public bool $showJtictModal = false;
    public string $jtictDecision = '';
    public string $jtictDate = '';
    public string $jtictRemarks = '';

    public function mount(string $uuid): void
    {
        $this->application = Application::with(['details.projectCategory', 'documents', 'applicant', 'agency', 'applicationCategoryMaster', 'procurementType', 'procurementMethod'])
            ->where('uuid', $uuid)
            ->firstOrFail();

        // Pemohon, pegawai bertanggungjawab, urus setia, atau pengguna dari jabatan/agensi yang sama
        $this->authorize('view', $this->application);
    }

    /**
     * Sejarah pegawai: aktif dahulu, kemudian terkini ke terdahulu.
     */
    #[Computed]
    public function officerHistory(): Collection
    {
        return $this->application->officers()
            ->with(['assigner', 'ender'])
            ->orderByRaw('ended_at IS NOT NULL')
            ->orderByDesc('started_at')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * PP2: sejarah semakan & penghantaran semula, terkini dahulu.
     */
    #[Computed]
    public function reviews(): Collection
    {
        return $this->application->reviews()
            ->with('user:id,name')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * Ulasan Tidak Lengkap terkini (rekod lama tanpa sejarah: review_remarks).
     */
    #[Computed]
    public function amendmentRemarks(): ?string
    {
        if ($this->application->statusEnum() !== ApplicationStatus::NeedsAmendment) {
            return null;
        }

        return $this->reviews->first(fn (ApplicationReview $review) => $review->toStatus() === ApplicationStatus::NeedsAmendment)?->remarks
            ?? $this->application->review_remarks;
    }

    public function openReviewModal(string $decision): void
    {
        $this->authorize('review', $this->application);

        $this->reviewDecision = $this->decisionFrom($decision)->value;
        $this->reviewRemarks = '';
        $this->resetErrorBag();
        $this->showReviewModal = true;
    }

    public function closeReviewModal(): void
    {
        $this->showReviewModal = false;
        $this->resetErrorBag();
    }

    public function submitReview(ApplicationReviewService $service): void
    {
        $decision = $this->decisionFrom($this->reviewDecision);

        // Status mungkin telah berubah (klik berganda / Urus Setia lain): kemas kini paparan, tiada rekod pendua.
        $this->application->refresh();
        if (Auth::user()->hasRole('pentadbir_urus_setia') && $this->application->statusEnum() !== ApplicationStatus::Submitted) {
            $this->finishReview(error: 'Permohonan ini telah disemak dan tidak lagi menunggu semakan.');

            return;
        }

        $this->authorize('review', $this->application);

        try {
            $service->review($this->application, $decision, $this->reviewRemarks, Auth::user());
        } catch (AuthorizationException) {
            $this->finishReview(error: 'Permohonan ini tidak lagi menunggu semakan.');

            return;
        }

        $this->finishReview(message: $decision === ApplicationStatus::NeedsAmendment
            ? 'Permohonan ditetapkan sebagai Tidak Lengkap. Pemohon dan Pegawai Utama akan dimaklumkan melalui e-mel.'
            : 'Permohonan ditetapkan sebagai Lengkap.');
    }

    private function decisionFrom(string $value): ApplicationStatus
    {
        $decision = ApplicationStatus::tryFrom($value);
        abort_unless(in_array($decision, ApplicationReviewService::DECISIONS, true), 422);

        return $decision;
    }

    private function finishReview(?string $message = null, ?string $error = null): void
    {
        $this->application->refresh();
        unset($this->reviews, $this->amendmentRemarks);
        $this->showReviewModal = false;
        $this->reviewDecision = '';
        $this->reviewRemarks = '';

        if ($message) {
            session()->flash('review_message', $message);
        }
        if ($error) {
            session()->flash('review_error', $error);
        }
    }

    /**
     * PP4.2: sejarah keputusan Pra JTICT (application_decisions, peringkat PRE_JTICT), terkini dahulu.
     */
    #[Computed]
    public function preJtictDecisions(): Collection
    {
        return $this->application->decisions()
            ->where('stage', DecisionStage::PreJtict->value)
            ->with('decidedBy:id,name')
            ->orderByDesc('decision_date')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * PP4.2: ulasan Pra JTICT "Ditolak dengan Pindaan" terkini untuk pemohon.
     */
    #[Computed]
    public function preJtictAmendmentDecision(): ?ApplicationDecisionRecord
    {
        if ($this->application->statusEnum() !== ApplicationStatus::PreJtictAmendment) {
            return null;
        }

        return $this->preJtictDecisions->first(fn (ApplicationDecisionRecord $record) => $record->decision === ApplicationDecision::AmendmentRequired);
    }

    /**
     * PP4.2: tarikh terawal yang dibenarkan bagi keputusan Pra JTICT (tarikh PP2 terakhir mengesahkan Lengkap).
     * Paparan sahaja; had sebenar disemak oleh ApplicationDecisionService.
     */
    #[Computed]
    public function preJtictEarliestDate(): ?string
    {
        return $this->application->latestCompleteReview()->first()?->created_at?->toDateString();
    }

    public function openPreJtictModal(string $decision): void
    {
        $this->authorize('decidePreJtict', $this->application);

        $this->preJtictDecision = $this->preJtictDecisionFrom($decision)->value;
        $this->preJtictDate = ''; // Tarikh keputusan sebenar dimasukkan oleh Urus Setia (tiada nilai lalai)
        $this->preJtictRemarks = '';
        $this->resetErrorBag();
        $this->showPreJtictModal = true;
    }

    public function closePreJtictModal(): void
    {
        $this->showPreJtictModal = false;
        $this->resetErrorBag();
    }

    /**
     * Semua validasi (keputusan, ulasan, tarikh), kebenaran dan peralihan status dibuat oleh
     * ApplicationDecisionService dalam satu transaksi; komponen ini hanya memetakan ralat ke medan borang.
     */
    public function submitPreJtictDecision(ApplicationDecisionService $service): void
    {
        $decision = $this->preJtictDecisionFrom($this->preJtictDecision);

        // Keputusan mungkin telah direkodkan (klik berganda / Urus Setia lain): kemas kini paparan, tiada rekod pendua.
        $this->application->refresh();
        if (Auth::user()->hasRole('pentadbir_urus_setia') && $this->application->statusEnum() !== ApplicationStatus::Complete) {
            $this->finishPreJtict(error: 'Keputusan Pra JTICT bagi permohonan ini telah direkodkan atau permohonan tidak lagi menunggu keputusan.');

            return;
        }

        $this->authorize('decidePreJtict', $this->application);

        try {
            $service->decide(
                $this->application,
                DecisionStage::PreJtict,
                $decision,
                $this->preJtictRemarks,
                $this->preJtictDate !== '' ? $this->preJtictDate : null,
                Auth::user(),
            );
        } catch (ValidationException $e) {
            $fields = ['decision' => 'preJtictDecision', 'remarks' => 'preJtictRemarks', 'decisionDate' => 'preJtictDate'];

            throw ValidationException::withMessages(collect($e->errors())
                ->mapWithKeys(fn (array $messages, string $key) => [$fields[$key] ?? 'preJtictDecision' => $messages])
                ->all());
        } catch (AuthorizationException) {
            $this->finishPreJtict(error: 'Keputusan Pra JTICT tidak boleh direkodkan bagi permohonan ini pada status semasa.');

            return;
        }

        $this->finishPreJtict(message: match ($decision) {
            ApplicationDecision::Accepted => 'Keputusan Pra JTICT direkodkan: Diterima. Permohonan dibawa ke peringkat Mesyuarat JTICT.',
            ApplicationDecision::AmendmentRequired => 'Keputusan Pra JTICT direkodkan: Ditolak dengan Pindaan. Permohonan dikembalikan kepada pemohon untuk pindaan.',
            default => 'Keputusan Pra JTICT direkodkan: Ditolak. Permohonan ditutup.',
        });
    }

    private function preJtictDecisionFrom(string $value): ApplicationDecision
    {
        $decision = ApplicationDecision::tryFrom($value);
        abort_unless($decision?->stage() === DecisionStage::PreJtict, 422);

        return $decision;
    }

    private function finishPreJtict(?string $message = null, ?string $error = null): void
    {
        $this->application->refresh();
        unset($this->preJtictDecisions, $this->preJtictAmendmentDecision, $this->preJtictEarliestDate, $this->preJtictAcceptanceBlocker);
        $this->showPreJtictModal = false;
        $this->preJtictDecision = '';
        $this->preJtictDate = '';
        $this->preJtictRemarks = '';

        if ($message) {
            session()->flash('review_message', $message);
        }
        if ($error) {
            session()->flash('review_error', $error);
        }
    }

    /**
     * PP4.3A (PP42B-01): sebab butang Pra JTICT "Diterima" dinyahdayakan; null jika mesyuarat sah.
     * Paparan sahaja; sekatan sebenar dibuat oleh ApplicationDecisionService.
     */
    #[Computed]
    public function preJtictAcceptanceBlocker(): ?string
    {
        return $this->application->statusEnum() === ApplicationStatus::Complete
            ? $this->application->preJtictAcceptanceBlocker()
            : null;
    }

    /**
     * PP4.3A: sebab keputusan JTICT belum boleh direkodkan (dipaparkan kepada Urus Setia bagi permohonan
     * Dalam Proses JTICT); null jika sedia diputuskan atau tidak berkenaan.
     */
    #[Computed]
    public function jtictPendingReason(): ?string
    {
        if ($this->application->statusEnum() !== ApplicationStatus::InJtict
            || ! Auth::user()->can('viewJtictQueue', Application::class)) {
            return null;
        }

        return $this->application->jtictMeetingBlocker();
    }

    public function openJtictModal(string $decision): void
    {
        $this->authorize('decideJtict', $this->application);

        $this->jtictDecision = $this->jtictDecisionFrom($decision)->value;
        $this->jtictDate = ''; // Tarikh keputusan sebenar dimasukkan oleh Urus Setia (tiada nilai lalai)
        $this->jtictRemarks = '';
        $this->resetErrorBag();
        $this->showJtictModal = true;
    }

    public function closeJtictModal(): void
    {
        $this->showJtictModal = false;
        $this->resetErrorBag();
    }

    /**
     * Semua validasi (keputusan, ulasan, tarikh, mesyuarat), kebenaran dan peralihan status dibuat oleh
     * ApplicationDecisionService dalam satu transaksi; komponen ini hanya memetakan ralat ke medan borang.
     */
    public function submitJtictDecision(ApplicationDecisionService $service): void
    {
        $decision = $this->jtictDecisionFrom($this->jtictDecision);

        // Keputusan mungkin telah direkodkan (klik berganda / Urus Setia lain): kemas kini paparan, tiada rekod pendua.
        $this->application->refresh();
        if (Auth::user()->hasRole('pentadbir_urus_setia') && $this->application->statusEnum() !== ApplicationStatus::InJtict) {
            $this->finishJtict(error: 'Keputusan Mesyuarat JTICT bagi permohonan ini telah direkodkan atau permohonan tidak lagi menunggu keputusan.');

            return;
        }

        $this->authorize('decideJtict', $this->application);

        try {
            $service->decide(
                $this->application,
                DecisionStage::Jtict,
                $decision,
                $this->jtictRemarks,
                $this->jtictDate !== '' ? $this->jtictDate : null,
                Auth::user(),
            );
        } catch (ValidationException $e) {
            $fields = ['decision' => 'jtictDecision', 'remarks' => 'jtictRemarks', 'decisionDate' => 'jtictDate'];

            throw ValidationException::withMessages(collect($e->errors())
                ->mapWithKeys(fn (array $messages, string $key) => [$fields[$key] ?? 'jtictDecision' => $messages])
                ->all());
        } catch (AuthorizationException) {
            $this->finishJtict(error: 'Keputusan Mesyuarat JTICT tidak boleh direkodkan bagi permohonan ini pada status semasa.');

            return;
        }

        $this->finishJtict(message: "Keputusan Mesyuarat JTICT direkodkan: {$decision->label()}. Permohonan ditutup dengan keputusan tersebut.");
    }

    private function jtictDecisionFrom(string $value): ApplicationDecision
    {
        $decision = ApplicationDecision::tryFrom($value);
        abort_unless($decision?->stage() === DecisionStage::Jtict, 422);

        return $decision;
    }

    private function finishJtict(?string $message = null, ?string $error = null): void
    {
        $this->application->refresh();
        unset($this->jtictPendingReason);
        $this->showJtictModal = false;
        $this->jtictDecision = '';
        $this->jtictDate = '';
        $this->jtictRemarks = '';

        if ($message) {
            session()->flash('review_message', $message);
        }
        if ($error) {
            session()->flash('review_error', $error);
        }
    }

    #[Computed]
    public function officerCandidates(): Collection
    {
        return User::eligibleOfficersFor($this->application->agency_id);
    }

    public function openHandoverModal(string $role): void
    {
        $this->authorize('manageOfficers', $this->application);

        $this->resetErrorBag();
        $this->handoverRole = OfficerRole::from($role)->value;
        $this->newOfficerId = '';
        $this->effectiveDate = today()->toDateString();
        $this->endReason = OfficerEndReason::TransferOut->value;
        $this->handoverRemarks = '';
        $this->showHandoverModal = true;
    }

    public function closeHandoverModal(): void
    {
        $this->showHandoverModal = false;
        $this->resetErrorBag();
    }

    public function reassignOfficer(OfficerHandoverService $service): void
    {
        $this->authorize('manageOfficers', $this->application);

        $role = OfficerRole::from($this->handoverRole);
        $hasCurrent = $this->application->officers()->active()->role($role)->exists();

        $this->validate([
            'newOfficerId' => [
                $role === OfficerRole::Primary ? 'required' : 'nullable',
                Rule::in($this->officerCandidates->modelKeys()),
            ],
            'effectiveDate' => ['required', 'date', 'before_or_equal:today'],
            'endReason' => [$hasCurrent ? 'required' : 'nullable', Rule::enum(OfficerEndReason::class)],
            'handoverRemarks' => ['nullable', 'string', 'max:2000'],
        ], [
            'newOfficerId.required' => 'Sila pilih pegawai baharu.',
            'newOfficerId.in' => 'Pegawai baharu mestilah pengguna berdaftar dalam agensi yang sama.',
            'effectiveDate.required' => 'Tarikh berkuat kuasa wajib diisi.',
            'effectiveDate.before_or_equal' => 'Tarikh berkuat kuasa tidak boleh melebihi tarikh hari ini.',
            'endReason.required' => 'Sila pilih sebab pertukaran.',
        ]);

        $service->reassign(
            $this->application,
            $role,
            $this->newOfficerId ? $this->officerCandidates->find($this->newOfficerId) : null,
            Carbon::parse($this->effectiveDate),
            OfficerEndReason::tryFrom($this->endReason) ?? OfficerEndReason::Other,
            $this->handoverRemarks ?: null,
            Auth::user(),
        );

        unset($this->officerHistory);
        $this->showHandoverModal = false;

        session()->flash('message', "{$role->label()} berjaya dikemas kini. Urus Setia telah dimaklumkan.");
    }

    public function render()
    {
        return view('livewire.view-application')
            ->layout('layouts.app');
    }
}
