<?php

namespace App\Livewire;

use App\Enums\OfficerEndReason;
use App\Enums\OfficerRole;
use App\Models\Application;
use App\Models\User;
use App\Services\OfficerHandoverService;
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
