<?php

namespace App\Services;

use App\Models\Project;
use App\Models\ProjectProgressReport;
use App\Support\Progress\ProgressResult;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * SP4: pengiraan kemajuan (audit SP0 L.2, L.3; keputusan I.5). Satu sumber bagi semua skrin, laporan dan
 * papan pemuka.
 *
 * Bagi setiap fasa:
 *   Sebenar   = Σ pemberat aktiviti siap (pada / sebelum tarikh penilaian) ÷ Σ pemberat aktiviti fasa × 100
 *   Dirancang = Σ pemberat aktiviti dengan tarikh sasaran <= tarikh penilaian ÷ Σ pemberat aktiviti fasa × 100
 * Jumlah pemberat fasa selain 100 dikira secara berkadar (Model 2). Kemajuan projek = purata sama rata fasa.
 * Varians = Sebenar − Dirancang (nilai dibundarkan, supaya angka yang dipaparkan konsisten).
 *
 * Kaedah compute() adalah tulen (tiada akses pangkalan data). Aritmetik dalam unit 0.01 pemberat (integer).
 */
class ProgressCalculator
{
    /**
     * @param  iterable<int, iterable<array{weight: mixed, target_date: ?CarbonInterface, is_completed: bool, actual_completion_date: ?CarbonInterface}>>  $phases
     *         aktiviti dikumpulkan mengikut nombor fasa
     */
    public function compute(iterable $phases, CarbonInterface $evaluationDate): ProgressResult
    {
        $date = $evaluationDate->copy()->startOfDay();
        $results = [];

        foreach ($phases as $number => $activities) {
            $total = 0;
            $completed = 0;
            $due = 0;
            $scheduleComplete = true;

            foreach ($activities as $activity) {
                $weight = (int) round(((float) $activity['weight']) * 100);
                $total += $weight;

                $done = $activity['is_completed']
                    && ($activity['actual_completion_date'] === null || $activity['actual_completion_date']->copy()->startOfDay()->lte($date));
                if ($done) {
                    $completed += $weight;
                }

                if ($activity['target_date'] === null) {
                    $scheduleComplete = false;
                } elseif ($activity['target_date']->copy()->startOfDay()->lte($date)) {
                    $due += $weight;
                }
            }

            if ($total === 0) {
                continue; // fasa tanpa aktiviti tidak dikira (tidak sepatutnya berlaku; disekat oleh SP2)
            }

            $results[$number] = [
                'actual' => $completed * 100 / $total,
                'planned' => $scheduleComplete ? $due * 100 / $total : null,
            ];
        }

        if ($results === []) {
            return new ProgressResult(0.0, null);
        }

        $phaseResults = [];
        foreach ($results as $number => $values) {
            $phaseResults[$number] = new ProgressResult(
                round($values['actual'], 2),
                $values['planned'] === null ? null : round($values['planned'], 2),
            );
        }

        // Purata sama rata daripada nilai fasa yang belum dibundarkan.
        $count = count($results);
        $actual = array_sum(array_column($results, 'actual')) / $count;
        $planned = in_array(null, array_column($results, 'planned'), true)
            ? null
            : array_sum(array_column($results, 'planned')) / $count;

        return new ProgressResult(round($actual, 2), $planned === null ? null : round($planned, 2), $phaseResults);
    }

    /**
     * Kemajuan semasa projek (aktiviti aktif) pada tarikh penilaian (lalai hari ini).
     */
    public function forProject(Project $project, ?CarbonInterface $evaluationDate = null): ProgressResult
    {
        $project->loadMissing('phases.activities');

        $phases = [];
        foreach ($project->phases as $phase) {
            $phases[$phase->phase_number] = $phase->activities->map(fn ($activity) => [
                'weight' => $activity->weight,
                'target_date' => $activity->target_date,
                'is_completed' => (bool) $activity->is_completed,
                'actual_completion_date' => $activity->actual_completion_date,
            ])->all();
        }

        return $this->compute($phases, $evaluationDate ?? today());
    }

    /**
     * Kemajuan laporan bulanan daripada snapshot aktivitinya (tidak berubah walaupun aktiviti dikemas kini kemudian).
     */
    public function forReport(ProjectProgressReport $report): ?ProgressResult
    {
        if ($report->evaluation_date === null) {
            return null;
        }

        $phases = [];
        foreach ($report->activitySnapshots()->get() as $row) {
            $phases[$row->phase_number][] = [
                'weight' => $row->weight,
                'target_date' => $row->target_date,
                'is_completed' => (bool) $row->is_completed,
                'actual_completion_date' => $row->actual_completion_date,
            ];
        }

        return $this->compute($phases, $report->evaluation_date);
    }

    /**
     * Simpan nilai cache pada fasa dan projek (untuk senarai dan papan pemuka). Kolum lama
     * physical_progress_percent dan status_indicator diselaraskan untuk papan pemuka sedia ada.
     */
    public function refresh(Project $project, ?CarbonInterface $evaluationDate = null): ProgressResult
    {
        $date = ($evaluationDate ?? today())->copy()->startOfDay();
        $project->unsetRelation('phases');
        $result = $this->forProject($project, $date);

        DB::transaction(function () use ($project, $result, $date): void {
            foreach ($project->phases as $phase) {
                $values = $result->phases[$phase->phase_number] ?? null;
                $phase->forceFill([
                    'actual_progress_percent' => $values?->actual ?? 0,
                    'planned_progress_percent' => $values?->planned,
                ])->saveQuietly();
            }

            $project->forceFill(array_filter([
                'actual_progress_percent' => $result->actual,
                'planned_progress_percent' => $result->planned,
                'variance_percent' => $result->variance(),
                'progress_evaluated_at' => $date,
                'physical_progress_percent' => $result->actual,
                'status_indicator' => $result->indicator(),
            ], fn ($value, $key) => $value !== null || in_array($key, ['planned_progress_percent', 'variance_percent'], true), ARRAY_FILTER_USE_BOTH))->saveQuietly();
        });

        return $result;
    }
}
