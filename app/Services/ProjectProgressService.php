<?php

namespace App\Services;

use App\Enums\ProgressUpdateType;
use App\Models\Project;
use App\Models\ProjectProgressActivity;
use App\Models\ProjectProgressAttachment;
use App\Models\ProjectProgressReport;
use App\Models\ProjectProgressUpdate;
use App\Models\User;
use App\Rules\DocumentSignature;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * SP3: tanda siap aktiviti + tarikh sebenar siap, batal tanda, dan dokumen sokongan kemajuan
 * (audit SP0 F.6, F.7; keputusan L.1, L.3, I.8).
 *
 * - Binari: aktiviti siap (100%) atau belum siap (0%); tiada peratus separa (L.1).
 * - Dokumen wajib (matriks §5.5) mesti dimuat naik sebelum aktiviti ditanda siap (I.8).
 * - Tanda siap pertama mengunci struktur fasa / aktiviti (L.3).
 * - Setiap tanda / batal tanda direkodkan dalam project_progress_updates (append-only).
 */
class ProjectProgressService
{
    public const PDF_MESSAGE = 'Dokumen sokongan hendaklah dalam format PDF sahaja.';

    /**
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function completeActivity(ProjectProgressActivity $activity, User $user, ?string $completionDate, ?string $remarks): void
    {
        DB::transaction(function () use ($activity, $user, $completionDate, $remarks): void {
            [$project, $locked, $actor] = $this->lockForManage($activity, $user);

            if ($locked->is_completed) {
                throw ValidationException::withMessages(['activity' => 'Aktiviti ini telah ditanda siap.']);
            }

            $date = $this->date($completionDate);
            if ($date === null) {
                throw ValidationException::withMessages(['completionDate' => 'Tarikh sebenar siap wajib diisi.']);
            }
            if ($date->gt(today())) {
                throw ValidationException::withMessages(['completionDate' => 'Tarikh sebenar siap tidak boleh melebihi tarikh hari ini.']);
            }

            $remarks = $this->remarks($remarks, required: false);

            if ($locked->requires_document && ! $locked->attachments()->exists()) {
                $label = $locked->document_label ?: 'Dokumen sokongan';
                throw ValidationException::withMessages(['document' => "{$label} wajib dimuat naik sebelum aktiviti ini ditanda siap."]);
            }

            $locked->update([
                'is_completed' => true,
                'actual_completion_date' => $date,
                'completed_by' => $actor->id,
                'completed_at' => now(),
            ]);

            ProjectProgressUpdate::create([
                'project_id' => $project->id,
                'project_progress_activity_id' => $locked->id,
                'change_type' => ProgressUpdateType::Completed,
                'completion_date' => $date,
                'remarks' => $remarks,
                'updated_by' => $actor->id,
            ]);

            // L.3: struktur dikunci selepas aktiviti pertama ditanda siap.
            if ($project->structure_locked_at === null) {
                $project->update(['structure_locked_at' => now()]);
            }
        });

        Log::info("Aktiviti projek {$activity->uuid} ditanda siap oleh ID Pengguna {$user->id}");
    }

    /**
     * Batal tanda siap: catatan wajib. Struktur kekal dikunci (hanya Urus Setia membuka semula).
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function uncompleteActivity(ProjectProgressActivity $activity, User $user, ?string $remarks): void
    {
        DB::transaction(function () use ($activity, $user, $remarks): void {
            [$project, $locked, $actor] = $this->lockForManage($activity, $user);

            if (! $locked->is_completed) {
                throw ValidationException::withMessages(['activity' => 'Aktiviti ini belum ditanda siap.']);
            }

            $remarks = $this->remarks($remarks, required: true);
            $previousDate = $locked->actual_completion_date;

            $locked->update([
                'is_completed' => false,
                'actual_completion_date' => null,
                'completed_by' => null,
                'completed_at' => null,
            ]);

            ProjectProgressUpdate::create([
                'project_id' => $project->id,
                'project_progress_activity_id' => $locked->id,
                'change_type' => ProgressUpdateType::Uncompleted,
                'completion_date' => $previousDate,
                'remarks' => $remarks,
                'updated_by' => $actor->id,
            ]);
        });

        Log::info("Tanda siap aktiviti projek {$activity->uuid} dibatalkan oleh ID Pengguna {$user->id}");
    }

    /**
     * Muat naik dokumen sokongan (PDF sahaja, maksimum 25 MB) bagi aktiviti atau laporan bulanan (Draf).
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function uploadAttachment(Project $project, User $user, mixed $file, ?ProjectProgressActivity $activity = null, ?ProjectProgressReport $report = null): ProjectProgressAttachment
    {
        if (($activity === null) === ($report === null)) {
            throw new \InvalidArgumentException('Dokumen mesti dipautkan kepada satu aktiviti atau satu laporan.');
        }

        $actor = User::findOrFail($user->id);
        $project = Project::findOrFail($project->id);

        if ($actor->cannot('manage', $project)) {
            throw new AuthorizationException('Anda tidak dibenarkan memuat naik dokumen bagi projek ini.');
        }

        if ($activity && (int) $activity->project_id !== $project->id) {
            throw new AuthorizationException('Aktiviti tidak berkaitan dengan projek ini.');
        }

        if ($report) {
            $report = ProjectProgressReport::findOrFail($report->id);
            if ((int) $report->project_id !== $project->id) {
                throw new AuthorizationException('Laporan tidak berkaitan dengan projek ini.');
            }
            if ($report->isSubmitted()) {
                throw ValidationException::withMessages(['upload' => 'Laporan yang telah dihantar tidak boleh diubah.']);
            }
        }

        Validator::make(['upload' => $file], [
            'upload' => [
                'required',
                'file',
                'extensions:pdf',
                'mimes:pdf',
                'bail',
                new DocumentSignature(['pdf'], self::PDF_MESSAGE),
                'max:' . ProjectProgressAttachment::MAX_FILE_KB,
            ],
        ], [
            'upload.required' => 'Sila pilih dokumen untuk dimuat naik.',
            'upload.extensions' => self::PDF_MESSAGE,
            'upload.mimes' => self::PDF_MESSAGE,
            'upload.max' => 'Saiz dokumen tidak boleh melebihi 25 MB.',
        ])->validate();

        /** @var UploadedFile $file */
        $path = $file->store("projects/{$project->uuid}/progress", ProjectProgressAttachment::DISK);

        try {
            $attachment = ProjectProgressAttachment::create([
                'project_id' => $project->id,
                'project_progress_activity_id' => $activity?->id,
                'project_progress_report_id' => $report?->id,
                'document_label' => $activity
                    ? ($activity->document_label ?: 'Dokumen Sokongan Aktiviti')
                    : 'Dokumen Sokongan Laporan',
                'file_name' => mb_substr($file->getClientOriginalName(), 0, 255),
                'file_path' => $path,
                'mime_type' => 'application/pdf',
                'file_size_kb' => (int) max(1, ceil($file->getSize() / 1024)),
                'uploaded_by' => $actor->id,
            ]);
        } catch (\Throwable $e) {
            Storage::disk(ProjectProgressAttachment::DISK)->delete($path);

            throw $e;
        }

        Log::info("Dokumen kemajuan dimuat naik bagi projek {$project->uuid} ({$attachment->uuid}) oleh ID Pengguna {$user->id}");

        return $attachment;
    }

    /**
     * Padam dokumen (soft delete; fail kekal dalam storan untuk audit).
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function deleteAttachment(ProjectProgressAttachment $attachment, User $user): void
    {
        DB::transaction(function () use ($attachment, $user): void {
            $project = Project::lockForUpdate()->findOrFail($attachment->project_id);
            $actor = User::findOrFail($user->id);

            if ($actor->cannot('manage', $project)) {
                throw new AuthorizationException('Anda tidak dibenarkan memadam dokumen projek ini.');
            }

            $attachment = ProjectProgressAttachment::findOrFail($attachment->id);

            if ($attachment->report?->isSubmitted()) {
                throw ValidationException::withMessages(['attachment' => 'Dokumen laporan yang telah dihantar tidak boleh dipadam.']);
            }

            $activity = $attachment->activity;
            if ($activity && $activity->is_completed && $activity->requires_document
                && $activity->attachments()->whereKeyNot($attachment->id)->doesntExist()) {
                throw ValidationException::withMessages(['attachment' => 'Dokumen wajib bagi aktiviti yang telah ditanda siap tidak boleh dipadam. Batalkan tanda siap dahulu atau muat naik dokumen pengganti.']);
            }

            $attachment->delete();
        });

        Log::info("Dokumen kemajuan {$attachment->uuid} dipadam oleh ID Pengguna {$user->id}");
    }

    /**
     * @return array{0: Project, 1: ProjectProgressActivity, 2: User}
     */
    private function lockForManage(ProjectProgressActivity $activity, User $user): array
    {
        $project = Project::lockForUpdate()->findOrFail($activity->project_id);
        $actor = User::findOrFail($user->id);

        if ($actor->cannot('manage', $project)) {
            throw new AuthorizationException('Anda tidak dibenarkan mengemas kini kemajuan projek ini.');
        }

        $locked = ProjectProgressActivity::where('project_id', $project->id)->lockForUpdate()->findOrFail($activity->id);

        return [$project, $locked, $actor];
    }

    private function remarks(?string $remarks, bool $required): ?string
    {
        $remarks = trim((string) $remarks);

        if ($required && $remarks === '') {
            throw ValidationException::withMessages(['remarks' => 'Catatan wajib diisi apabila tanda siap dibatalkan.']);
        }
        if (mb_strlen($remarks) > 2000) {
            throw ValidationException::withMessages(['remarks' => 'Catatan tidak boleh melebihi 2000 aksara.']);
        }

        return $remarks !== '' ? $remarks : null;
    }

    private function date(?string $value): ?Carbon
    {
        if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        $date = Carbon::createFromFormat('!Y-m-d', $value);

        return $date && $date->format('Y-m-d') === $value ? $date : null;
    }
}
