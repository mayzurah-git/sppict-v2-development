<?php

namespace App\Livewire\Projects;

use App\Enums\ProjectStatus;
use App\Models\Agency;
use App\Models\Application;
use App\Models\Project;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * SP2: Senarai Projek (audit SP0 G).
 *
 * - Pengguna biasa: projek agensi sendiri, dan permohonan Lulus JTICT agensi sendiri yang belum dipantau.
 * - Urus Setia (pantau) dan Pengurusan (baca sahaja): semua agensi, dengan penapis agensi.
 * - Superadmin tiada akses (I.9).
 */
#[Layout('layouts.app')]
class ProjectList extends Component
{
    use WithPagination;

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $status = '';

    #[Url(except: '')]
    public string $agency = '';

    public function mount(): void
    {
        Gate::authorize('viewAny', Project::class);
    }

    public function updating($property): void
    {
        if (in_array($property, ['search', 'status', 'agency'], true)) {
            $this->resetPage();
        }
    }

    public function render()
    {
        // Kebenaran disemak semula pada setiap permintaan (peranan / status akaun mungkin berubah).
        Gate::authorize('viewAny', Project::class);

        $user = Auth::user();
        $allAgencies = $user->hasAnyRole(['pentadbir_urus_setia', 'pengurusan']);
        $agencyId = $allAgencies ? ($this->agency !== '' ? (int) $this->agency : null) : $user->agency_id;
        $search = trim($this->search);

        $projects = Project::query()
            ->with(['application:id,uuid,reference_number,title,total_cost,estimated_cost', 'agency:id,code,name', 'progressTemplate:id,code,name'])
            ->withCount('phases')
            ->when(! $allAgencies, fn (Builder $q) => $q->where('agency_id', $user->agency_id ?? 0))
            ->when($allAgencies && $agencyId, fn (Builder $q) => $q->where('agency_id', $agencyId))
            ->when(ProjectStatus::tryFrom($this->status), fn (Builder $q, ProjectStatus $status) => $q->where('status', $status->value))
            ->when($search !== '', fn (Builder $q) => $q->where(fn (Builder $q) => $q
                ->where('legacy_reference', 'like', "%{$search}%")
                ->orWhereHas('application', fn (Builder $a) => $a
                    ->where('reference_number', 'like', "%{$search}%")
                    ->orWhere('title', 'like', "%{$search}%"))))
            ->latest('id')
            ->paginate(20);

        // Permohonan Lulus JTICT yang belum mempunyai rekod pemantauan (termasuk projek dipadam).
        $awaiting = Application::approvedByJtict()
            ->with(['agency:id,code,name', 'latestJtictDecision'])
            ->whereNotIn('id', Project::withTrashed()->whereNotNull('application_id')->select('application_id'))
            ->when(! $allAgencies, fn (Builder $q) => $q->where('agency_id', $user->agency_id ?? 0))
            ->when($allAgencies && $agencyId, fn (Builder $q) => $q->where('agency_id', $agencyId))
            ->when($search !== '', fn (Builder $q) => $q->where(fn (Builder $q) => $q
                ->where('reference_number', 'like', "%{$search}%")
                ->orWhere('title', 'like', "%{$search}%")))
            ->orderBy('id')
            ->get();

        $agencies = $allAgencies ? Agency::orderBy('code')->get(['id', 'code', 'name']) : collect();

        return view('livewire.projects.project-list', compact('projects', 'awaiting', 'agencies', 'allAgencies'));
    }
}
