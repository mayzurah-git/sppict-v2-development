<?php

namespace App\Livewire;

use App\Enums\ApplicationStatus;
use App\Enums\OfficerEndReason;
use App\Enums\OfficerRole;
use App\Models\Application;
use App\Models\ApplicationReview;
use App\Models\User;
use App\Services\ApplicationReviewService;
use App\Services\OfficerHandoverService;
use Illuminate\Auth\Access\AuthorizationException;
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
