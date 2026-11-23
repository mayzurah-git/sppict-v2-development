<?php

namespace App\Livewire;

use App\Models\Application;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * PP4.2: senarai permohonan Lengkap yang menunggu keputusan Pra JTICT (Urus Setia sahaja).
 * Keputusan direkodkan di halaman butiran permohonan (ViewApplication) melalui ApplicationDecisionService.
 */
#[Layout('layouts.app')]
class PreJtictList extends Component
{
    use WithPagination;

    #[Url(except: '')]
    public string $search = '';

    public function mount(): void
    {
        Gate::authorize('viewPreJtictQueue', Application::class);
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        // Kebenaran disemak semula pada setiap permintaan (peranan / status akaun mungkin berubah).
        Gate::authorize('viewPreJtictQueue', Application::class);

        $applications = Application::awaitingPreJtictDecision()
            // PP4.3A: relation mesyuarat untuk label & semakan PP42B-01 (Application::preJtictAcceptanceBlocker)
            ->with(['agency:id,code,name', 'applicant:id,name', 'jtictMeeting.meetingName', 'jtictMeeting.meetingNumber', 'jtictMeeting.meetingYear', 'latestCompleteReview', 'latestPreJtictDecision'])
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('reference_number', 'like', '%' . $this->search . '%')
                        ->orWhere('title', 'like', '%' . $this->search . '%');
                });
            })
            ->orderBy('created_at')
            ->orderBy('id')
            ->paginate(15);

        $manual = Application::needsManualPreJtictReview()
            ->with(['agency:id,code,name', 'jtictMeeting'])
            ->orderBy('created_at')
            ->get();

        return view('livewire.pre-jtict-list', [
            'applications' => $applications,
            'manual' => $manual,
        ]);
    }
}
