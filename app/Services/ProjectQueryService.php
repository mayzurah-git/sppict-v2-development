<?php

namespace App\Services;

use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * SP5: pertanyaan Senarai Projek yang dikongsi oleh skrin dan eksport (eksport A), supaya skop akses dan
 * penapis sentiasa sama.
 *
 * Skop: Urus Setia dan Pengurusan melihat semua agensi; pengguna lain hanya agensi sendiri (ProjectPolicy@view).
 */
class ProjectQueryService
{
    public function seesAllAgencies(User $user): bool
    {
        return $user->hasAnyRole(['pentadbir_urus_setia', 'pengurusan']);
    }

    /**
     * @param  array{search?: ?string, status?: ?string, agency?: int|string|null}  $filters
     */
    public function list(User $user, array $filters = []): Builder
    {
        $allAgencies = $this->seesAllAgencies($user);
        $agencyId = $allAgencies ? ((int) ($filters['agency'] ?? 0) ?: null) : null;
        $search = trim((string) ($filters['search'] ?? ''));

        return Project::query()
            ->with(['application:id,uuid,reference_number,title,total_cost,estimated_cost', 'agency:id,code,name', 'progressTemplate:id,code,name'])
            ->withCount('phases')
            ->when(! $allAgencies, fn (Builder $q) => $q->where('agency_id', $user->agency_id ?? 0))
            ->when($agencyId, fn (Builder $q) => $q->where('agency_id', $agencyId))
            ->when(ProjectStatus::tryFrom((string) ($filters['status'] ?? '')), fn (Builder $q, ProjectStatus $status) => $q->where('status', $status->value))
            ->when($search !== '', fn (Builder $q) => $q->where(fn (Builder $q) => $q
                ->where('legacy_reference', 'like', "%{$search}%")
                ->orWhereHas('application', fn (Builder $a) => $a
                    ->where('reference_number', 'like', "%{$search}%")
                    ->orWhere('title', 'like', "%{$search}%"))))
            ->latest('id');
    }
}
