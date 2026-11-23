<?php

namespace App\Services;

use App\Enums\ClosureEventType;
use App\Enums\ProgressReportStatus;
use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Models\ProjectClosureEvent;
use App\Models\ProjectProgressActivity;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * SP4: penutupan projek (keputusan I.6, audit SP0 L.8; butiran disahkan pemilik 23/11/2026).
 *
 * - Pegawai aktif memohon penutupan apabila semua aktiviti siap (kemajuan sebenar 100%). Projek
 *   Penyelenggaraan: juga selepas Tarikh Tamat Projek (tarikh tamat kontrak).
 * - Tarikh siap projek = tarikh siap aktiviti terakhir. Laporan bulanan tertunggak tidak menyekat.
 * - Menunggu Pengesahan Penutupan: aktiviti tidak boleh dikemas kini; pegawai boleh menarik balik.
 * - Urus Setia mengesahkan (Selesai, baca sahaja) atau menolak (ulasan wajib; kembali Dalam Pelaksanaan).
 * - Urus Setia boleh menutup projek tanpa siap (Ditutup, ulasan wajib).
 * - Tiada buka semula projek Selesai / Ditutup.
 * - Setiap peristiwa direkodkan dalam project_closure_events (append-only).
 */
class ProjectClosureService
{
    public const MAX_REMARKS = 2000;

    /**
     * Sebab permohonan penutupan belum boleh dibuat (null jika boleh).
     */
    public function closureBlocker(Project $project): ?string
    {
        $activities = ProjectProgressActivity::where('project_id', $project->id)->get(['is_completed']);

        if ($activities->isEmpty() || $activities->contains('is_completed', false)) {
            return 'Semua aktiviti mesti ditanda siap (kemajuan sebenar 100%) sebelum penutupan boleh dimohon.';
        }

        $project->loadMissing('progressTemplate.procurementType');
        if ($project->isMaintenance() && $project->planned_end_date && today()->lt($project->planned_end_date)) {
            return 'Projek Penyelenggaraan hanya boleh dimohon tutup selepas tarikh tamat kontrak ('
                . $project->planned_end_date->format('d/m/Y') . ').';
        }

        return null;
    }

    /**
     * Bilangan bulan (dari bulan mula projek hingga bulan siap / bulan semasa) yang belum mempunyai laporan dihantar.
     */
    public function missingReportCount(Project $project): int
    {
        $start = ($project->planned_start_date ?? $project->started_at)?->copy()->startOfMonth();
        if ($start === null) {
            return 0;
        }

        $end = ($project->actual_completion_date ?? today())->copy()->startOfMonth()->min(today()->startOfMonth());
        $submitted = $project->progressReports()->where('status', ProgressReportStatus::Submitted->value)
            ->get(['report_year', 'report_month'])
            ->map(fn ($report) => $report->report_year . '-' . $report->report_month)
            ->all();

        $missing = 0;
        for ($cursor = $start->copy(); $cursor->lte($end); $cursor->addMonth()) {
            if (! in_array($cursor->year . '-' . $cursor->month, $submitted, true)) {
                $missing++;
            }
        }

        return $missing;
    }

    /**
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function request(Project $project, User $user, ?string $remarks): void
    {
        $this->transition($project, $user, 'requestClosure', ClosureEventType::Requested, $remarks, required: false, apply: function (Project $locked): void {
            if ($blocker = $this->closureBlocker($locked)) {
                throw ValidationException::withMessages(['closure' => $blocker]);
            }

            $locked->update([
                'status' => ProjectStatus::PendingClosure,
                'actual_completion_date' => ProjectProgressActivity::where('project_id', $locked->id)->max('actual_completion_date'),
            ]);
        });
    }

    public function withdraw(Project $project, User $user, ?string $remarks): void
    {
        $this->transition($project, $user, 'withdrawClosure', ClosureEventType::Withdrawn, $remarks, required: false, apply: function (Project $locked): void {
            $locked->update(['status' => ProjectStatus::InProgress, 'actual_completion_date' => null]);
        });
    }

    public function approve(Project $project, User $user, ?string $remarks): void
    {
        $this->transition($project, $user, 'decideClosure', ClosureEventType::Approved, $remarks, required: false, apply: function (Project $locked, User $actor, ?string $remarks): void {
            $locked->update([
                'status' => ProjectStatus::Completed,
                'is_completed' => true,
                'closed_by' => $actor->id,
                'closed_at' => now(),
                'closure_remarks' => $remarks,
            ]);
        });
    }

    public function reject(Project $project, User $user, ?string $remarks): void
    {
        $this->transition($project, $user, 'decideClosure', ClosureEventType::Rejected, $remarks, required: true, apply: function (Project $locked): void {
            $locked->update(['status' => ProjectStatus::InProgress, 'actual_completion_date' => null]);
        });
    }

    public function terminate(Project $project, User $user, ?string $remarks): void
    {
        $this->transition($project, $user, 'terminate', ClosureEventType::Terminated, $remarks, required: true, apply: function (Project $locked, User $actor, ?string $remarks): void {
            $locked->update([
                'status' => ProjectStatus::Closed,
                'actual_completion_date' => null,
                'closed_by' => $actor->id,
                'closed_at' => now(),
                'closure_remarks' => $remarks,
            ]);
        });
    }

    /**
     * Peralihan status dalam transaksi dengan kunci baris projek, semakan kebenaran terkini dan rekod sejarah.
     *
     * @param  callable(Project, User, ?string): void  $apply
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    private function transition(Project $project, User $user, string $ability, ClosureEventType $event, ?string $remarks, bool $required, callable $apply): void
    {
        DB::transaction(function () use ($project, $user, $ability, $event, $remarks, $required, $apply): void {
            $locked = Project::lockForUpdate()->findOrFail($project->id);
            $actor = User::findOrFail($user->id);

            if ($actor->cannot($ability, $locked)) {
                throw new AuthorizationException('Tindakan penutupan ini tidak dibenarkan bagi status projek semasa.');
            }

            $remarks = trim((string) $remarks);
            if ($required && $remarks === '') {
                throw ValidationException::withMessages(['closureRemarks' => 'Ulasan wajib diisi.']);
            }
            if (mb_strlen($remarks) > self::MAX_REMARKS) {
                throw ValidationException::withMessages(['closureRemarks' => 'Ulasan tidak boleh melebihi ' . self::MAX_REMARKS . ' aksara.']);
            }
            $remarks = $remarks !== '' ? $remarks : null;

            $apply($locked, $actor, $remarks);

            ProjectClosureEvent::create([
                'project_id' => $locked->id,
                'event_type' => $event,
                'remarks' => $remarks,
                'actor_id' => $actor->id,
            ]);
        });

        Log::info("Penutupan projek {$project->uuid}: {$event->value} oleh ID Pengguna {$user->id}");
    }
}
