<?php

namespace App\Livewire;

use App\Enums\ApplicationStatus;
use App\Models\Application;
use App\Models\Meeting;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * PP4.3A: senarai permohonan Dalam Proses JTICT (Urus Setia sahaja), dibahagikan kepada
 * Sedia Diputuskan, Menunggu Mesyuarat dan Perlu Semakan Manual. Keputusan direkodkan di halaman
 * butiran permohonan (ViewApplication) melalui ApplicationDecisionService; tiada tindakan pukal.
 */
#[Layout('layouts.app')]
class JtictList extends Component
{
    #[Url(except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $meeting = '';

    public function mount(): void
    {
        Gate::authorize('viewJtictQueue', Application::class);
    }

    public function render()
    {
        // Kebenaran disemak semula pada setiap permintaan (peranan / status akaun mungkin berubah).
        Gate::authorize('viewJtictQueue', Application::class);

        $relations = ['agency:id,code,name', 'applicant:id,name', 'jtictMeeting.meetingName', 'jtictMeeting.meetingNumber', 'jtictMeeting.meetingYear', 'latestPreJtictDecision'];
        $filter = function (Builder $query): void {
            $query->when($this->search, fn (Builder $q) => $q->where(fn (Builder $q) => $q
                ->where('reference_number', 'like', '%' . $this->search . '%')
                ->orWhere('title', 'like', '%' . $this->search . '%')))
                ->when($this->meeting !== '', fn (Builder $q) => $q->where('jtict_meeting_id', (int) $this->meeting));
        };

        $ready = Application::awaitingJtictDecision()->with($relations)->tap($filter)->orderBy('created_at')->orderBy('id')->get();
        $scheduled = Application::scheduledForJtict()->with($relations)->tap($filter)->orderBy('created_at')->orderBy('id')->get();
        $manual = Application::needsManualJtictReview()->with($relations)->tap($filter)->orderBy('created_at')->orderBy('id')->get();

        // Penapis: mesyuarat yang dirujuk oleh permohonan Dalam Proses JTICT (termasuk mesyuarat tidak aktif).
        $meetings = Meeting::query()
            ->with(['meetingName', 'meetingNumber', 'meetingYear'])
            ->whereIn('id', Application::where('status', ApplicationStatus::InJtict->value)->whereNotNull('jtict_meeting_id')->select('jtict_meeting_id'))
            ->orderByDesc('meeting_date')
            ->get();

        return view('livewire.jtict-list', compact('ready', 'scheduled', 'manual', 'meetings'));
    }
}
