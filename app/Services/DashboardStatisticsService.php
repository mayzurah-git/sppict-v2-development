<?php

namespace App\Services;

use App\Enums\ApplicationDecision;
use App\Enums\ApplicationStatus;
use App\Enums\ProjectStatus;
use App\Models\Agency;
use App\Models\Application;
use App\Models\ApplicationCategory;
use App\Models\Project;
use Illuminate\Support\Collection;

/**
 * SP5: statistik papan pemuka Pengurusan dan Urus Setia (keputusan pemilik 23/11/2026, audit SP0 L.9).
 *
 * (a) Statistik permohonan projek mengikut tahun dihantar, Jabatan/Agensi dan Kategori Permohonan, dengan
 *     pecahan keputusan. Tahun dihantar diambil daripada nombor rujukan rasmi (SPPICT/TTTT/NNNN), yang
 *     diberikan semasa permohonan dihantar; Draf tidak dikira.
 * (b) Bilangan projek mengikut status kemajuan dan lampu.
 * (c) Ringkasan projek mengikut Jabatan/Agensi.
 * (d) Statistik projek (Lulus JTICT) mengikut kos Jabatan: Jumlah Keseluruhan permohonan termasuk SST.
 * (e) Senarai projek lewat.
 */
class DashboardStatisticsService
{
    /** Kategori keputusan permohonan, mengikut susunan paparan. */
    public const OUTCOMES = [
        'in_process' => 'Dalam Proses',
        'approved' => 'Lulus',
        'not_approved' => 'Tidak Lulus',
        'review' => 'Kaji Semula',
        'rejected' => 'Ditolak (Pra JTICT)',
    ];

    /** Status projek aktif (dalam pemantauan). */
    public const ACTIVE_STATUSES = [ProjectStatus::InProgress, ProjectStatus::PendingClosure];

    private ?Collection $applications = null;

    /**
     * Permohonan yang telah dihantar (bukan Draf, tidak dipadam), dengan tahun dan keputusan.
     *
     * @return Collection<int, array{year: int, agency_id: ?int, category: ?string, outcome: string, total: float}>
     */
    public function applications(): Collection
    {
        return $this->applications ??= Application::query()
            ->where('status', '!=', ApplicationStatus::Draft->value)
            ->with('latestJtictDecision')
            ->get(['id', 'reference_number', 'agency_id', 'application_category', 'status', 'total_cost', 'estimated_cost', 'created_at'])
            ->map(fn (Application $application) => [
                'year' => $this->submissionYear($application),
                'agency_id' => $application->agency_id,
                'category' => $application->application_category,
                'outcome' => $this->outcome($application),
                'total' => (float) ($application->total_cost ?? $application->estimated_cost ?? 0),
            ]);
    }

    /** @return list<int> tahun yang mempunyai permohonan, terkini dahulu */
    public function years(): array
    {
        return $this->applications()->pluck('year')->unique()->sortDesc()->values()->all();
    }

    /**
     * (a)(i) Permohonan mengikut tahun dihantar.
     *
     * @return Collection<int, array{label: string, counts: array<string, int>, total: int}>
     */
    public function applicationsByYear(): Collection
    {
        return $this->breakdown($this->applications(), fn (array $row) => $row['year'], fn ($year) => (string) $year)
            ->sortKeysDesc();
    }

    /** (a)(ii) Permohonan mengikut Jabatan/Agensi (pilihan: satu tahun). */
    public function applicationsByAgency(?int $year = null): Collection
    {
        $agencies = Agency::withTrashed()->pluck('code', 'id');

        return $this->breakdown($this->forYear($year), fn (array $row) => $row['agency_id'] ?? 0, fn ($id) => $agencies[$id] ?? 'Tidak dinyatakan')
            ->sortBy('label');
    }

    /** (a)(iii) Permohonan mengikut Kategori Permohonan (pilihan: satu tahun). */
    public function applicationsByCategory(?int $year = null): Collection
    {
        $categories = ApplicationCategory::withTrashed()->pluck('name', 'code');

        return $this->breakdown($this->forYear($year), fn (array $row) => $row['category'] ?? '', fn ($code) => $categories[$code] ?? ($code !== '' ? $code : 'Tidak dinyatakan'))
            ->sortByDesc('total');
    }

    /**
     * (d) Projek Lulus JTICT mengikut Jabatan/Agensi: bilangan dan jumlah kos (Jumlah Keseluruhan permohonan).
     *
     * @return Collection<int, array{label: string, name: string, count: int, cost: float}>
     */
    public function approvedCostByAgency(?int $year = null): Collection
    {
        $agencies = Agency::withTrashed()->get(['id', 'code', 'name'])->keyBy('id');

        return $this->forYear($year)
            ->where('outcome', 'approved')
            ->groupBy(fn (array $row) => $row['agency_id'] ?? 0)
            ->map(fn (Collection $rows, $id) => [
                'label' => $agencies[$id]?->code ?? 'Tidak dinyatakan',
                'name' => $agencies[$id]?->name ?? '',
                'count' => $rows->count(),
                'cost' => round($rows->sum('total'), 2),
            ])
            ->sortByDesc('cost')
            ->values();
    }

    /**
     * (b) Bilangan projek mengikut status kitaran dan, bagi projek aktif, mengikut lampu.
     *
     * @return array{statuses: array<string, int>, indicators: array<string, int>, total: int}
     */
    public function projectsByStatus(): array
    {
        $projects = Project::query()->get(['id', 'status', 'variance_percent']);

        $statuses = [];
        foreach (ProjectStatus::cases() as $status) {
            $statuses[$status->value] = $projects->where('status', $status)->count();
        }

        $active = $projects->filter(fn (Project $project) => in_array($project->status, self::ACTIVE_STATUSES, true));
        $indicators = ['GREEN' => 0, 'AMBER' => 0, 'RED' => 0, 'NONE' => 0];
        foreach ($active as $project) {
            $indicators[$project->variance_percent === null ? 'NONE' : \App\Enums\ScheduleStatus::indicatorFor((float) $project->variance_percent)]++;
        }

        return ['statuses' => $statuses, 'indicators' => $indicators, 'total' => $projects->count()];
    }

    /**
     * (c) Ringkasan projek mengikut Jabatan/Agensi.
     *
     * @return Collection<int, array{label: string, name: string, total: int, active: int, completed: int, closed: int, late: int, average: ?float}>
     */
    public function projectSummaryByAgency(): Collection
    {
        $agencies = Agency::withTrashed()->get(['id', 'code', 'name'])->keyBy('id');

        return Project::query()
            ->where('status', '!=', ProjectStatus::Historical->value)
            ->get(['id', 'agency_id', 'status', 'actual_progress_percent', 'variance_percent'])
            ->groupBy('agency_id')
            ->map(function (Collection $projects, $id) use ($agencies) {
                $active = $projects->filter(fn (Project $project) => in_array($project->status, self::ACTIVE_STATUSES, true));

                return [
                    'label' => $agencies[$id]?->code ?? 'Tidak dinyatakan',
                    'name' => $agencies[$id]?->name ?? '',
                    'total' => $projects->count(),
                    'active' => $active->count(),
                    'completed' => $projects->where('status', ProjectStatus::Completed)->count(),
                    'closed' => $projects->where('status', ProjectStatus::Closed)->count(),
                    'late' => $active->filter(fn (Project $project) => $project->variance_percent !== null && (float) $project->variance_percent < 0)->count(),
                    'average' => $active->isEmpty() ? null : round($active->avg(fn (Project $project) => (float) $project->actual_progress_percent), 2),
                ];
            })
            ->sortBy('label')
            ->values();
    }

    /**
     * (e) Projek aktif yang lewat (varians negatif), paling lewat dahulu.
     *
     * @return Collection<int, Project>
     */
    public function lateProjects(int $limit = 50): Collection
    {
        return Project::query()
            ->whereIn('status', array_map(fn (ProjectStatus $status) => $status->value, self::ACTIVE_STATUSES))
            ->whereNotNull('variance_percent')
            ->where('variance_percent', '<', 0)
            ->with(['application:id,uuid,reference_number,title', 'agency:id,code,name'])
            ->orderBy('variance_percent')
            ->limit($limit)
            ->get();
    }

    public function submissionYear(Application $application): int
    {
        if (preg_match('#^SPPICT/(\d{4})/\d+$#', (string) $application->reference_number, $match)) {
            return (int) $match[1];
        }

        return (int) ($application->created_at?->year ?? now()->year);
    }

    public function outcome(Application $application): string
    {
        return match ($application->latestJtictDecision?->decision) {
            ApplicationDecision::Approved => 'approved',
            ApplicationDecision::NotApproved => 'not_approved',
            ApplicationDecision::Review => 'review',
            default => $application->status === ApplicationStatus::Closed->value ? 'rejected' : 'in_process',
        };
    }

    private function forYear(?int $year): Collection
    {
        return $year === null ? $this->applications() : $this->applications()->where('year', $year);
    }

    /**
     * @return Collection<int|string, array{label: string, counts: array<string, int>, total: int}>
     */
    private function breakdown(Collection $rows, callable $groupBy, callable $label): Collection
    {
        return $rows->groupBy($groupBy)->map(function (Collection $group, $key) use ($label) {
            $counts = array_fill_keys(array_keys(self::OUTCOMES), 0);
            foreach ($group as $row) {
                $counts[$row['outcome']]++;
            }

            return ['label' => $label($key), 'counts' => $counts, 'total' => $group->count()];
        });
    }
}
