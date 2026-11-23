<?php

namespace App\Services;

use App\Enums\ProgressReportStatus;
use App\Models\Meeting;
use App\Models\Project;
use App\Models\ProjectProgressActivity;
use App\Models\ProjectProgressReport;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * SP3: laporan kemajuan bulanan (audit SP0 F.4, F.5; keputusan I.10, L.7).
 *
 * - Satu laporan bagi setiap projek setiap bulan; bulan antara bulan mula projek dan bulan semasa.
 * - Tarikh akhir bulan M = progress_update_deadline mesyuarat pertama pada / selepas hari terakhir bulan M
 *   (mesyuarat tidak dipadam), disalin semasa laporan dicipta. Laporan lewat dibenarkan dengan tanda "Lewat".
 * - Semasa dihantar: snapshot aktiviti pada tarikh penilaian (hari terakhir bulan, atau hari ini jika lebih
 *   awal), kemudian laporan dikunci.
 * - Peratus sebenar / dirancang / varians laporan diisi oleh pengiraan SP4 (daripada snapshot).
 */
class ProjectReportService
{
    public const TEXT_FIELDS = ['work_summary', 'issues_and_challenges', 'corrective_action_plan'];

    public const MAX_TEXT = 5000;

    /**
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function create(Project $project, User $user, int $year, int $month): ProjectProgressReport
    {
        $report = DB::transaction(function () use ($project, $user, $year, $month) {
            $locked = Project::lockForUpdate()->findOrFail($project->id);
            $actor = User::findOrFail($user->id);

            if ($actor->cannot('manage', $locked)) {
                throw new AuthorizationException('Anda tidak dibenarkan mencipta laporan bagi projek ini.');
            }

            if (! in_array($month, range(1, 12), true) || ! in_array([$year, $month], $this->availablePeriods($locked), true)) {
                throw ValidationException::withMessages(['period' => 'Bulan laporan tidak sah atau laporan bagi bulan tersebut telah wujud.']);
            }

            $periodEnd = Carbon::create($year, $month, 1)->endOfMonth()->startOfDay();
            $meeting = $this->dueMeeting($periodEnd);

            return ProjectProgressReport::create([
                'project_id' => $locked->id,
                'report_year' => $year,
                'report_month' => $month,
                'status' => ProgressReportStatus::Draft,
                'due_date' => $meeting?->progress_update_deadline,
                'due_meeting_id' => $meeting?->id,
                'created_by' => $actor->id,
            ]);
        });

        Log::info("Laporan kemajuan {$report->periodLabel()} dicipta bagi projek {$project->uuid} oleh ID Pengguna {$user->id}");

        return $report;
    }

    /**
     * Bulan yang boleh dilaporkan: dari bulan mula projek hingga bulan semasa, tidak termasuk bulan yang
     * telah mempunyai laporan (termasuk dipadam).
     *
     * @return list<array{0: int, 1: int}>  [tahun, bulan], terkini dahulu
     */
    public function availablePeriods(Project $project): array
    {
        $start = ($project->planned_start_date ?? $project->started_at)?->copy()->startOfMonth();
        if ($start === null) {
            return [];
        }

        $existing = ProjectProgressReport::withTrashed()->where('project_id', $project->id)
            ->get(['report_year', 'report_month'])
            ->map(fn ($report) => $report->report_year . '-' . $report->report_month)
            ->all();

        $periods = [];
        for ($cursor = today()->startOfMonth(); $cursor->gte($start); $cursor->subMonth()) {
            if (! in_array($cursor->year . '-' . $cursor->month, $existing, true)) {
                $periods[] = [$cursor->year, $cursor->month];
            }
        }

        return $periods;
    }

    /**
     * Simpan draf (ringkasan kemajuan, isu dan kekangan, tindakan susulan).
     *
     * @param  array<string, ?string>  $fields
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function saveDraft(ProjectProgressReport $report, User $user, array $fields): void
    {
        DB::transaction(function () use ($report, $user, $fields): void {
            $locked = $this->lockDraft($report, $user);
            $locked->update($this->validatedText($fields));
        });
    }

    /**
     * Hantar laporan: snapshot aktiviti pada tarikh penilaian, tanda Lewat, kemudian dikunci.
     *
     * @param  array<string, ?string>  $fields
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function submit(ProjectProgressReport $report, User $user, array $fields): void
    {
        DB::transaction(function () use ($report, $user, $fields): void {
            $locked = $this->lockDraft($report, $user);
            $text = $this->validatedText($fields);

            if (($text['work_summary'] ?? null) === null) {
                throw ValidationException::withMessages(['work_summary' => 'Ringkasan kemajuan wajib diisi sebelum laporan dihantar.']);
            }

            $project = $locked->project;
            $evaluationDate = $locked->periodEnd()->min(today());

            // Tarikh akhir yang belum ditentukan semasa laporan dicipta (tiada mesyuarat ketika itu).
            $dueDate = $locked->due_date;
            $dueMeetingId = $locked->due_meeting_id;
            if ($dueDate === null && ($meeting = $this->dueMeeting($locked->periodEnd()))) {
                $dueDate = $meeting->progress_update_deadline;
                $dueMeetingId = $meeting->id;
            }

            $activities = ProjectProgressActivity::where('project_id', $project->id)
                ->with('phase:id,phase_number')
                ->get();

            foreach ($activities as $activity) {
                $completed = $activity->is_completed
                    && $activity->actual_completion_date !== null
                    && $activity->actual_completion_date->lte($evaluationDate);

                $locked->activitySnapshots()->create([
                    'project_progress_activity_id' => $activity->id,
                    'phase_number' => $activity->phase->phase_number,
                    'sequence' => $activity->sequence,
                    'name' => $activity->name,
                    'weight' => $activity->weight,
                    'target_date' => $activity->target_date,
                    'is_planned_due' => $activity->target_date !== null && $activity->target_date->lte($evaluationDate),
                    'is_completed' => $completed,
                    'actual_completion_date' => $completed ? $activity->actual_completion_date : null,
                ]);
            }

            $officerId = $project->application?->officers()->active()->where('user_id', $user->id)->value('id');

            $locked->update($text + [
                'status' => ProgressReportStatus::Submitted,
                'evaluation_date' => $evaluationDate,
                'due_date' => $dueDate,
                'due_meeting_id' => $dueMeetingId,
                'is_late' => $dueDate !== null && today()->gt($dueDate),
                'submitted_by' => $user->id,
                'submitted_at' => now(),
                'application_officer_id' => $officerId,
            ]);
        });

        Log::info("Laporan kemajuan {$report->uuid} dihantar oleh ID Pengguna {$user->id}");
    }

    /**
     * Mesyuarat (tidak dipadam) dengan Tarikh Status Kemaskini Kemajuan Projek yang pertama pada / selepas
     * hari terakhir bulan laporan (keputusan I.10, L.7). Status Aktif mesyuarat tidak digunakan kerana
     * mesyuarat menjadi Tidak Aktif selepas tempoh permohonan ditutup.
     */
    public function dueMeeting(Carbon $periodEnd): ?Meeting
    {
        return Meeting::query()
            ->whereNotNull('progress_update_deadline')
            ->whereDate('progress_update_deadline', '>=', $periodEnd->toDateString())
            ->orderBy('progress_update_deadline')
            ->orderBy('id')
            ->first();
    }

    private function lockDraft(ProjectProgressReport $report, User $user): ProjectProgressReport
    {
        $locked = ProjectProgressReport::lockForUpdate()->findOrFail($report->id);
        $project = Project::findOrFail($locked->project_id);
        $actor = User::findOrFail($user->id);

        if ($actor->cannot('manage', $project)) {
            throw new AuthorizationException('Anda tidak dibenarkan mengemas kini laporan projek ini.');
        }

        if ($locked->isSubmitted()) {
            throw ValidationException::withMessages(['report' => 'Laporan ini telah dihantar dan tidak boleh diubah.']);
        }

        return $locked;
    }

    /**
     * @param  array<string, ?string>  $fields
     * @return array<string, ?string>
     */
    private function validatedText(array $fields): array
    {
        $values = [];
        $errors = [];

        foreach (self::TEXT_FIELDS as $field) {
            $value = trim((string) ($fields[$field] ?? ''));
            if (mb_strlen($value) > self::MAX_TEXT) {
                $errors[$field] = 'Teks tidak boleh melebihi ' . self::MAX_TEXT . ' aksara.';
            }
            $values[$field] = $value !== '' ? $value : null;
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $values;
    }
}
