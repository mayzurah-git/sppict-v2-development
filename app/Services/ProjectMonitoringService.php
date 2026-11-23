<?php

namespace App\Services;

use App\Enums\ProjectStatus;
use App\Enums\ScheduleField;
use App\Models\Application;
use App\Models\ProgressTemplate;
use App\Models\Project;
use App\Models\ProjectPhase;
use App\Models\ProjectProgressActivity;
use App\Models\ProjectScheduleChange;
use App\Models\ProjectStructureUnlock;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * SP2: Mulakan Pemantauan, struktur fasa/aktiviti, pindaan jadual, buka kunci struktur dan rekod JPICT
 * (audit SP0 D.4, F.2A, F.3, L.2, L.3, L.6).
 *
 * Semua syarat perniagaan dan kebenaran disemak semula di sini dalam transaksi dengan kunci baris,
 * supaya permintaan serentak atau komponen yang tidak dikemas kini tidak memintas peraturan.
 *
 * Format struktur ($input):
 *   [
 *     'is_phased' => bool,
 *     'planned_start_date' => 'Y-m-d', 'planned_end_date' => 'Y-m-d',   // start() sahaja
 *     'phases' => [ ['name' => ?string, 'activities' => [progress_template_activity_id => 'Y-m-d' (tarikh sasaran)]], ... ],
 *   ]
 */
class ProjectMonitoringService
{
    /**
     * Cipta rekod pemantauan bagi permohonan Lulus JTICT (idempoten melalui kunci permohonan dan indeks unik).
     *
     * @throws AuthorizationException  tidak layak / telah dimulakan
     * @throws ValidationException     data struktur atau jadual tidak sah, atau tiada templat
     */
    public function start(Application $application, User $user, array $input): Project
    {
        $project = DB::transaction(function () use ($application, $user, $input) {
            // Kunci permohonan: permintaan serentak menunggu, kemudian ditolak kerana projek telah wujud.
            $locked = Application::withTrashed()->lockForUpdate()->findOrFail($application->id);
            $actor = User::findOrFail($user->id); // peranan, status & agensi terkini

            if ($actor->cannot('start', [Project::class, $locked])) {
                throw new AuthorizationException('Pemantauan projek ini tidak boleh dimulakan atau telah dimulakan.');
            }

            $template = ProgressTemplate::activeFor($locked->procurement_type_id)?->load('activities');

            if ($template === null || $template->activities->isEmpty()) {
                throw ValidationException::withMessages([
                    'template' => 'Tiada templat pemberat aktif bagi Jenis Perolehan permohonan ini. Sila hubungi Urus Setia.',
                ]);
            }

            $start = $this->date($input['planned_start_date'] ?? null);
            $end = $this->date($input['planned_end_date'] ?? null);
            $this->validateProjectDates($start, $end);

            $structure = $this->validatedStructure($input, $template, $start, $end, existing: []);

            $project = Project::create([
                'application_id' => $locked->id,
                'agency_id' => $locked->agency_id,
                'progress_template_id' => $template->id,
                'is_phased' => $structure['is_phased'],
                'status' => ProjectStatus::InProgress,
                'planned_start_date' => $start,
                'planned_end_date' => $end,
                'started_by' => $actor->id,
                'started_at' => now(),
            ]);

            $this->syncStructure($project, $template, $structure);

            return $project;
        });

        Log::info("Pemantauan projek dimulakan: permohonan {$application->reference_number} (projek {$project->uuid}) oleh ID Pengguna {$user->id}");

        return $project;
    }

    /**
     * Pinda struktur fasa / aktiviti sebelum struktur dikunci (L.3). Tarikh sasaran aktiviti sedia ada
     * tidak diubah di sini (pindaan tarikh melalui amendSchedule() dengan justifikasi, I.3); aktiviti
     * baharu wajib diberi tarikh sasaran.
     *
     * @throws AuthorizationException  struktur dikunci / bukan pegawai aktif
     * @throws ValidationException
     */
    public function updateStructure(Project $project, User $user, array $input): Project
    {
        DB::transaction(function () use ($project, $user, $input): void {
            $locked = Project::lockForUpdate()->findOrFail($project->id);
            $actor = User::findOrFail($user->id);

            if ($actor->cannot('updateStructure', $locked)) {
                throw new AuthorizationException('Struktur projek ini telah dikunci atau anda tidak dibenarkan mengemas kininya.');
            }

            $template = $locked->progressTemplate()->with('activities')->firstOrFail();

            $existing = $locked->activities()->with('phase:id,phase_number')->get()
                ->mapWithKeys(fn (ProjectProgressActivity $activity) => [
                    $activity->phase->phase_number . ':' . $activity->progress_template_activity_id => $activity,
                ])->all();

            $structure = $this->validatedStructure($input, $template, $locked->planned_start_date, $locked->planned_end_date, $existing);

            $locked->update(['is_phased' => $structure['is_phased']]);
            $this->syncStructure($locked, $template, $structure);

            // Struktur dibuka semula oleh Urus Setia: kunci semula jika masih ada aktiviti yang ditanda siap.
            if ($locked->activities()->where('is_completed', true)->exists()) {
                $locked->update(['structure_locked_at' => now()]);
            }
        });

        Log::info("Struktur projek {$project->uuid} dikemas kini oleh ID Pengguna {$user->id}");

        return $project->fresh();
    }

    /**
     * Pinda jadual (I.3): tarikh mula / tamat projek dan tarikh sasaran aktiviti. Justifikasi wajib;
     * setiap tarikh yang berubah direkodkan dalam project_schedule_changes (append-only).
     *
     * @param  array{planned_start_date?: ?string, planned_end_date?: ?string, activities?: array<string, ?string>}  $input
     *         activities: [uuid aktiviti => 'Y-m-d']
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function amendSchedule(Project $project, User $user, array $input, ?string $justification): int
    {
        $count = DB::transaction(function () use ($project, $user, $input, $justification): int {
            $locked = Project::lockForUpdate()->findOrFail($project->id);
            $actor = User::findOrFail($user->id);

            if ($actor->cannot('manage', $locked)) {
                throw new AuthorizationException('Anda tidak dibenarkan meminda jadual projek ini.');
            }

            $justification = trim((string) $justification);
            if ($justification === '') {
                throw ValidationException::withMessages(['justification' => 'Justifikasi pindaan jadual wajib diisi.']);
            }
            if (mb_strlen($justification) > 2000) {
                throw ValidationException::withMessages(['justification' => 'Justifikasi tidak boleh melebihi 2000 aksara.']);
            }

            $start = $this->date($input['planned_start_date'] ?? null);
            $end = $this->date($input['planned_end_date'] ?? null);
            $this->validateProjectDates($start, $end);

            $activities = $locked->activities()->get();
            $targets = $input['activities'] ?? [];
            $errors = [];
            $newTargets = [];

            foreach ($activities as $activity) {
                $value = array_key_exists($activity->uuid, $targets) ? $targets[$activity->uuid] : $activity->target_date?->toDateString();
                $date = $this->date($value);
                $key = "activities.{$activity->uuid}";

                if ($date === null) {
                    $errors[$key] = "Tarikh sasaran bagi \"{$activity->name}\" wajib diisi.";
                } elseif ($date->lt($start) || $date->gt($end)) {
                    $errors[$key] = "Tarikh sasaran bagi \"{$activity->name}\" mesti dalam tempoh projek.";
                }

                $newTargets[$activity->id] = $date;
            }

            if ($errors !== []) {
                throw ValidationException::withMessages($errors);
            }

            $changes = [];
            foreach ([
                [ScheduleField::PlannedStartDate, 'planned_start_date', $start],
                [ScheduleField::PlannedEndDate, 'planned_end_date', $end],
            ] as [$field, $column, $date]) {
                if (! $this->sameDate($locked->{$column}, $date)) {
                    $changes[] = [$field, null, $locked->{$column}, $date];
                }
            }

            foreach ($activities as $activity) {
                if (! $this->sameDate($activity->target_date, $newTargets[$activity->id])) {
                    $changes[] = [ScheduleField::TargetDate, $activity, $activity->target_date, $newTargets[$activity->id]];
                }
            }

            if ($changes === []) {
                throw ValidationException::withMessages(['schedule' => 'Tiada perubahan tarikh untuk disimpan.']);
            }

            $batch = (string) Str::uuid();

            foreach ($changes as [$field, $activity, $old, $new]) {
                ProjectScheduleChange::create([
                    'batch_uuid' => $batch,
                    'project_id' => $locked->id,
                    'project_progress_activity_id' => $activity?->id,
                    'field' => $field,
                    'old_value' => $old,
                    'new_value' => $new,
                    'justification' => $justification,
                    'changed_by' => $actor->id,
                ]);

                $activity
                    ? $activity->update(['target_date' => $new])
                    : $locked->update([$field === ScheduleField::PlannedStartDate ? 'planned_start_date' : 'planned_end_date' => $new]);
            }

            return count($changes);
        });

        Log::info("Jadual projek {$project->uuid} dipinda ({$count} perubahan) oleh ID Pengguna {$user->id}");

        return $count;
    }

    /**
     * Urus Setia membuka semula struktur yang dikunci (L.3). Catatan wajib; direkodkan (append-only).
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function unlockStructure(Project $project, User $user, ?string $remarks): void
    {
        DB::transaction(function () use ($project, $user, $remarks): void {
            $locked = Project::lockForUpdate()->findOrFail($project->id);
            $actor = User::findOrFail($user->id);

            if ($actor->cannot('unlockStructure', $locked)) {
                throw new AuthorizationException('Struktur projek ini tidak boleh dibuka semula.');
            }

            $remarks = trim((string) $remarks);
            if ($remarks === '') {
                throw ValidationException::withMessages(['unlockRemarks' => 'Catatan buka kunci wajib diisi.']);
            }
            if (mb_strlen($remarks) > 2000) {
                throw ValidationException::withMessages(['unlockRemarks' => 'Catatan tidak boleh melebihi 2000 aksara.']);
            }

            ProjectStructureUnlock::create([
                'project_id' => $locked->id,
                'locked_at' => $locked->structure_locked_at,
                'remarks' => $remarks,
                'unlocked_by' => $actor->id,
            ]);

            $locked->update(['structure_locked_at' => null]);
        });

        Log::info("Struktur projek {$project->uuid} dibuka semula oleh ID Pengguna {$user->id}");
    }

    /**
     * Rekod kelulusan JPICT (pilihan, I.1) oleh Urus Setia. Perubahan direkodkan dalam audit trail projek.
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function recordJpict(Project $project, User $user, ?string $approvedAt, ?string $reference): void
    {
        DB::transaction(function () use ($project, $user, $approvedAt, $reference): void {
            $locked = Project::lockForUpdate()->findOrFail($project->id);
            $actor = User::findOrFail($user->id);

            if ($actor->cannot('recordJpict', $locked)) {
                throw new AuthorizationException('Kelulusan JPICT tidak boleh direkodkan bagi projek ini.');
            }

            $date = $this->date($approvedAt);
            $reference = trim((string) $reference);

            if ($date === null) {
                throw ValidationException::withMessages(['jpictDate' => 'Tarikh kelulusan JPICT wajib diisi.']);
            }
            if ($date->gt(today())) {
                throw ValidationException::withMessages(['jpictDate' => 'Tarikh kelulusan JPICT tidak boleh melebihi tarikh hari ini.']);
            }
            if (mb_strlen($reference) > 100) {
                throw ValidationException::withMessages(['jpictReference' => 'Rujukan tidak boleh melebihi 100 aksara.']);
            }

            $locked->update([
                'jpict_approved_at' => $date,
                'jpict_reference' => $reference !== '' ? $reference : null,
                'jpict_recorded_by' => $actor->id,
                'jpict_recorded_at' => now(),
            ]);
        });

        Log::info("Kelulusan JPICT direkodkan bagi projek {$project->uuid} oleh ID Pengguna {$user->id}");
    }

    // --- STRUKTUR ---

    /**
     * Sahkan struktur fasa/aktiviti dan kembalikan bentuk ternormal:
     * ['is_phased' => bool, 'phases' => [['name' => ?string, 'activities' => [template_activity_id => ?Carbon]]]].
     *
     * @param  array<string, ProjectProgressActivity>  $existing  aktiviti sedia ada, kunci "nombor_fasa:id_aktiviti_templat"
     */
    private function validatedStructure(array $input, ProgressTemplate $template, Carbon $start, Carbon $end, array $existing): array
    {
        $isPhased = (bool) ($input['is_phased'] ?? false);
        $phasesInput = array_values($input['phases'] ?? []);
        $templateActivities = $template->activities->keyBy('id');

        if ($isPhased && ! $template->allows_phases) {
            throw ValidationException::withMessages(['is_phased' => 'Projek Berfasa hanya dibenarkan bagi Pembangunan Sistem / Aplikasi.']);
        }

        if (! $isPhased) {
            // Tidak Berfasa: satu fasa mengandungi semua aktiviti templat (L.2).
            $dates = $phasesInput[0]['activities'] ?? [];
            $phasesInput = [[
                'name' => null,
                'activities' => $templateActivities->mapWithKeys(fn ($activity, $id) => [$id => $dates[$id] ?? null])->all(),
            ]];
        } elseif (count($phasesInput) < 2 || count($phasesInput) > Project::MAX_PHASES) {
            throw ValidationException::withMessages(['phases' => 'Projek Berfasa mesti mempunyai antara 2 hingga ' . Project::MAX_PHASES . ' fasa.']);
        }

        $errors = [];
        $phases = [];

        foreach ($phasesInput as $index => $phaseInput) {
            $number = $index + 1;
            $name = trim((string) ($phaseInput['name'] ?? ''));
            $activities = [];

            if (mb_strlen($name) > 100) {
                $errors["phases.{$index}.name"] = "Nama Fasa {$number} tidak boleh melebihi 100 aksara.";
            }

            foreach (($phaseInput['activities'] ?? []) as $templateActivityId => $value) {
                $source = $templateActivities->get((int) $templateActivityId);

                if ($source === null) {
                    $errors["phases.{$index}.activities"] = "Fasa {$number} mengandungi aktiviti yang tiada dalam templat projek.";

                    continue;
                }

                $current = $existing[$number . ':' . $source->id] ?? null;
                // Aktiviti sedia ada mengekalkan tarikh sasarannya (pindaan melalui Pinda Jadual).
                $date = $current ? $current->target_date : $this->date($value);
                $key = "phases.{$index}.activities.{$source->id}";

                if ($date === null) {
                    $errors[$key] = "Tarikh sasaran \"{$source->name}\" (Fasa {$number}) wajib diisi.";
                } elseif ($date->lt($start) || $date->gt($end)) {
                    $errors[$key] = "Tarikh sasaran \"{$source->name}\" (Fasa {$number}) mesti dalam tempoh projek.";
                }

                $activities[$source->id] = $date;
            }

            if ($activities === [] && ! isset($errors["phases.{$index}.activities"])) {
                $errors["phases.{$index}.activities"] = "Fasa {$number} mesti mempunyai sekurang-kurangnya satu aktiviti.";
            }

            $phases[] = ['name' => $isPhased && $name !== '' ? $name : null, 'activities' => $activities];
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return ['is_phased' => $isPhased, 'phases' => $phases];
    }

    /**
     * Selaraskan fasa dan aktiviti projek dengan struktur yang disahkan. Rekod yang dikeluarkan di-soft delete
     * (dipulihkan jika dipilih semula); aktiviti yang telah ditanda siap tidak boleh dikeluarkan.
     */
    private function syncStructure(Project $project, ProgressTemplate $template, array $structure): void
    {
        $templateActivities = $template->activities->keyBy('id');
        $keptPhaseIds = [];

        foreach ($structure['phases'] as $index => $phaseData) {
            $phase = ProjectPhase::withTrashed()->firstOrNew(['project_id' => $project->id, 'phase_number' => $index + 1]);
            $phase->name = $phaseData['name'];
            $phase->deleted_at = null;
            $phase->save();
            $keptPhaseIds[] = $phase->id;

            $keptActivityIds = [];

            foreach ($phaseData['activities'] as $templateActivityId => $targetDate) {
                $source = $templateActivities->get($templateActivityId);
                $activity = ProjectProgressActivity::withTrashed()->firstOrNew([
                    'project_phase_id' => $phase->id,
                    'progress_template_activity_id' => $source->id,
                ]);

                if (! $activity->exists || $activity->trashed()) {
                    // Snapshot templat (pemberat projek tidak berubah walaupun templat berubah).
                    $activity->fill([
                        'project_id' => $project->id,
                        'sequence' => $source->sequence,
                        'name' => $source->name,
                        'weight' => $source->weight,
                        'requires_document' => $source->requires_document,
                        'document_label' => $source->document_label,
                        'target_date' => $targetDate,
                    ]);
                    $activity->deleted_at = null;
                    $activity->save();
                }

                $keptActivityIds[] = $activity->id;
            }

            $this->removeActivities($phase->activities()->whereNotIn('id', $keptActivityIds)->get());
        }

        foreach (ProjectPhase::where('project_id', $project->id)->whereNotIn('id', $keptPhaseIds)->get() as $phase) {
            $this->removeActivities($phase->activities()->get());
            $phase->delete();
        }
    }

    private function removeActivities($activities): void
    {
        foreach ($activities as $activity) {
            if ($activity->is_completed) {
                throw ValidationException::withMessages([
                    'phases' => "Aktiviti \"{$activity->name}\" telah ditanda siap dan tidak boleh dikeluarkan daripada struktur projek.",
                ]);
            }

            $activity->delete();
        }
    }

    // --- TARIKH ---

    private function validateProjectDates(?Carbon $start, ?Carbon $end): void
    {
        $errors = [];

        if ($start === null) {
            $errors['planned_start_date'] = 'Tarikh mula projek wajib diisi.';
        }
        if ($end === null) {
            $errors['planned_end_date'] = 'Tarikh tamat projek wajib diisi.';
        }
        if ($start && $end && $end->lt($start)) {
            $errors['planned_end_date'] = 'Tarikh tamat projek tidak boleh lebih awal daripada tarikh mula.';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * Tarikh dalam format Y-m-d sahaja; nilai lain dianggap kosong (dan ditolak oleh validasi wajib).
     */
    private function date(mixed $value): ?Carbon
    {
        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance($value)->startOfDay();
        }

        if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        $date = Carbon::createFromFormat('!Y-m-d', $value);

        return $date && $date->format('Y-m-d') === $value ? $date : null;
    }

    private function sameDate(?\DateTimeInterface $a, ?\DateTimeInterface $b): bool
    {
        return $a?->format('Y-m-d') === $b?->format('Y-m-d');
    }
}
