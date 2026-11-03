<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\WithPagination;
use App\Enums\ApplicationStatus;
use App\Models\Application;
use Illuminate\Support\Facades\Auth;

class ApplicationList extends Component
{
    use WithPagination;

    public string $search = '';
    public string $statusFilter = '';

    public function mount(): void
    {
        if (Auth::user()?->hasRole('pentadbir_urus_setia')) {
            $this->statusFilter = ApplicationStatus::Submitted->value;
        }
    }

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function updatingStatusFilter()
    {
        $this->resetPage();
    }

    public function render()
    {
        $user = Auth::user();
        $isUrusSetia = $user->hasRole('pentadbir_urus_setia');

        // Penapis menggunakan nilai dalaman enum; nilai tidak sah (cth. label lama) diabaikan.
        $statusFilter = ApplicationStatus::tryFrom($this->statusFilter);

        // PP2: Urus Setia melihat satu status pada satu masa (lalai: Dihantar = menunggu semakan).
        // Draf milik pemohon lain tidak dipaparkan kepada Urus Setia; PP3: pilihan Draf hanya
        // memaparkan draf yang didaftarkan oleh Urus Setia itu sendiri (cth. bagi pihak agensi).
        $urusSetiaOptions = ApplicationStatus::cases();
        $urusSetiaStatus = in_array($statusFilter, $urusSetiaOptions, true) ? $statusFilter : ApplicationStatus::Submitted;

        $applications = Application::query()
            ->with(['applicationCategoryMaster', 'agency:id,code,name'])
            ->when($isUrusSetia, function ($query) use ($urusSetiaStatus, $user) {
                $query->where('status', $urusSetiaStatus->value)
                    ->when($urusSetiaStatus === ApplicationStatus::Draft, fn ($drafts) => $drafts->where('applicant_id', $user->id))
                    // "Dihantar semula": pernah ditetapkan Tidak Lengkap sebelum ini
                    ->withExists(['reviews as was_returned' => fn ($review) => $review->where('to_status', ApplicationStatus::NeedsAmendment->value)]);
            }, function ($query) use ($user) {
                // Permohonan sendiri, atau projek yang dipertanggungjawabkan (termasuk yang diambil alih)
                $query->where(function ($q) use ($user) {
                    $q->where('applicant_id', $user->id)
                      ->orWhereHas('officers', fn ($officer) => $officer->active()->where('user_id', $user->id));
                });
            })
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('reference_number', 'like', '%' . $this->search . '%')
                      ->orWhere('title', 'like', '%' . $this->search . '%');
                });
            })
            ->when($statusFilter && ! $isUrusSetia, function ($query) use ($statusFilter) {
                $query->where('status', $statusFilter->value);
            })
            ->latest()
            ->paginate(10);

        return view('livewire.application-list', [
            'applications' => $applications,
            'isUrusSetia' => $isUrusSetia,
            'statusOptions' => $isUrusSetia ? $urusSetiaOptions : ApplicationStatus::cases(),
        ]);
    }
}