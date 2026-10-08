<?php

namespace App\Services;

use App\Enums\OfficerRole;
use App\Models\Application;
use App\Models\ApplicationCategory;
use App\Models\ApplicationDetail;
use App\Models\ApplicationDocument;
use App\Models\ApplicationOfficer;
use App\Models\Meeting;
use App\Models\ProcurementMethod;
use App\Models\ProcurementType;
use App\Models\ProjectCategory;
use App\Models\User;
use App\Livewire\Forms\ApplicationForm;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use InvalidArgumentException;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ApplicationSubmissionService
{
    /**
     * Disk private: dokumen hanya boleh dibuka melalui route muat turun yang menyemak kebenaran.
     */
    public const DOCUMENT_DISK = 'local';

    private const DOCUMENT_TYPES = [
        'proposal_paper' => 'PROPOSAL_PAPER',
        'presentation_slide' => 'PRESENTATION_SLIDE_JTICT',
    ];

    /**
     * Hantar permohonan kepada Urus Setia. Jika permohonan ini berasal daripada draf
     * (atau berstatus Tidak Lengkap), rekod yang sama dikemas kini.
     *
     * Permohonan hanya boleh dihantar ketika ada Mesyuarat JTICT yang dibuka (kecuali
     * Perlu Pindaan, yang kekal pada mesyuarat asal). Jika tempoh telah ditutup,
     * permohonan disimpan sebagai draf supaya tiada data hilang.
     *
     * @throws SubmissionClosedException  tiada Mesyuarat JTICT aktif; permohonan telah disimpan sebagai draf
     * @throws \Exception
     */
    public function submit(ApplicationForm $form, $user, ?int $applicationId = null): Application
    {
        $closed = false;

        $application = DB::transaction(function () use ($form, $user, $applicationId, &$closed) {
            $application = $this->resolveApplication($user, $applicationId);

            // Semak semula di pelayan; kunci rekod mesyuarat supaya tidak ditutup serentak
            $meeting = $application->targetJtictMeeting();
            $meeting = $meeting ? Meeting::lockForUpdate()->find($meeting->id) : null;

            if (! $meeting) {
                $closed = true;

                return $this->persistDraft($application, $form, $user);
            }

            // Draf menerima nombor rujukan rasmi semasa dihantar
            if (! $application->exists || str_contains($application->reference_number, '/DRAFT-')) {
                $application->reference_number = $this->nextReferenceNumber(isDraft: false);
            }

            $this->fillApplication($application, $form);
            $application->status = 'SUBMITTED';
            $application->jtict_meeting_id = $meeting->id;
            $application->save();

            $this->syncOfficers($application, $form, $user, submitting: true);
            $this->syncDetails($application, $form);
            $this->storeDocuments($application, $form);

            Log::info("Permohonan berjaya dihantar: {$application->reference_number} ke {$meeting->label()} oleh ID Pemohon {$user->id}");

            return $application;
        });

        if ($closed) {
            throw new SubmissionClosedException($application);
        }

        return $application;
    }

    /**
     * Menyimpan atau mengemas kini permohonan sebagai DRAF.
     * Permohonan berstatus Tidak Lengkap kekal dengan statusnya.
     */
    public function saveDraft(ApplicationForm $form, $user, ?int $applicationId = null): Application
    {
        return DB::transaction(fn () => $this->persistDraft($this->resolveApplication($user, $applicationId), $form, $user));
    }

    private function persistDraft(Application $application, ApplicationForm $form, $user): Application
    {
        if (! $application->exists) {
            $application->reference_number = $this->nextReferenceNumber(isDraft: true);
            $application->status = 'DRAFT';
        }

        $this->fillApplication($application, $form);
        $application->save();

        $this->syncOfficers($application, $form, $user, submitting: false);
        $this->syncDetails($application, $form);
        $this->storeDocuments($application, $form);

        return $application;
    }

    /**
     * Dapatkan permohonan sedia ada (dengan semakan kebenaran) atau sediakan rekod baharu.
     */
    private function resolveApplication($user, ?int $applicationId): Application
    {
        if (! $applicationId) {
            return new Application([
                'uuid' => (string) Str::uuid(),
                'agency_id' => $user->agency_id ?? 1,
                'applicant_id' => $user->id,
            ]);
        }

        $application = Application::lockForUpdate()->findOrFail($applicationId);

        if ($user->cannot('update', $application)) {
            throw new AuthorizationException('Permohonan ini tidak boleh dikemas kini.');
        }

        return $application;
    }

    /**
     * Jana nombor rujukan seterusnya berdasarkan nombor tertinggi tahun semasa
     * (termasuk rekod yang dipadam) supaya tidak bertindih.
     */
    private function nextReferenceNumber(bool $isDraft): string
    {
        $prefix = sprintf('SPPICT/%s/%s', date('Y'), $isDraft ? 'DRAFT-' : '');

        $references = Application::withTrashed()
            ->where('reference_number', 'like', $prefix . '%')
            ->lockForUpdate()
            ->pluck('reference_number');

        $lastNumber = $references
            ->map(fn ($ref) => Str::after($ref, $prefix))
            ->filter(fn ($number) => ctype_digit($number))
            ->map(fn ($number) => (int) $number)
            ->max() ?? 0;

        return sprintf('%s%04d', $prefix, $lastNumber + 1);
    }

    private function fillApplication(Application $application, ApplicationForm $form): void
    {
        $summary = $form->costSummary();

        $procurementType = $this->findMaster(ProcurementType::class, $form->procurement_type_id, 'Jenis Perolehan');
        $procurementMethod = $this->findMaster(ProcurementMethod::class, $form->procurement_method_id, 'Kaedah Perolehan');

        if (filled($form->application_category)
            && ! ApplicationCategory::withTrashed()->where('code', $form->application_category)->exists()) {
            throw new InvalidArgumentException('Kategori Permohonan yang dipilih tidak sah.');
        }

        // Kemaskini medan (boleh jadi kosong semasa draf). Nama master disimpan sebagai snapshot.
        $application->fill([
            'title' => $form->title ?: 'Permohonan Tanpa Tajuk (Draf)',
            'application_category' => $form->application_category ?: null,
            'objectives' => $form->objectives,
            'project_scope' => $form->project_scope,
            'procurement_type_id' => $procurementType?->id,
            'procurement_type' => $procurementType?->name,
            'procurement_method_id' => $procurementMethod?->id,
            'procurement_method' => $procurementMethod?->name,
            'ceiling_cost' => $form->ceiling_cost ?: 0,
            'estimated_cost' => $form->estimated_cost ?: 0,
            'subtotal_cost' => $summary['subtotal'],
            'sst_rate' => $summary['sst_rate'],
            'sst_amount' => $summary['sst_amount'],
            'total_cost' => $summary['total'],
            'expected_duration_months' => $form->expected_duration_months ?: 1,
            'outcome_code' => $form->outcome_code ?? '',
        ]);
    }

    /**
     * Cari master data mengikut ID (termasuk yang telah dipadam, untuk rekod lama).
     * Nilai kosong (draf) dikembalikan sebagai null; ID yang tidak wujud ditolak.
     *
     * @template T of \Illuminate\Database\Eloquent\Model
     * @param  class-string<T>  $model
     * @return T|null
     */
    private function findMaster(string $model, $id, string $label)
    {
        if (blank($id)) {
            return null;
        }

        return $model::withTrashed()->find($id)
            ?? throw new InvalidArgumentException("{$label} yang dipilih tidak sah.");
    }

    /**
     * Simpan Pegawai Bertanggungjawab (Fasa 5) semasa permohonan masih boleh disunting.
     * Pada peringkat ini rekod aktif dikemas kini terus (tiada sejarah) kerana permohonan
     * belum rasmi; selepas dihantar, pertukaran pegawai melalui OfficerHandoverService.
     */
    private function syncOfficers(Application $application, ApplicationForm $form, $user, bool $submitting): void
    {
        $assignments = [
            OfficerRole::Primary->value => $form->primary_officer_id ?: null,
            OfficerRole::Secondary->value => $form->has_secondary_officer ? ($form->secondary_officer_id ?: null) : null,
        ];

        $eligible = User::eligibleOfficersFor($application->agency_id, $user)->keyBy('id');

        foreach ($assignments as $role => $userId) {
            $current = $application->officers()->active()->where('role', $role)->first();
            $officer = $userId ? $eligible->get((int) $userId) : null;

            if (! $officer) {
                $current?->delete();
                continue;
            }

            $attributes = ApplicationOfficer::snapshotFrom($officer);

            // Pegawai bertukar semasa draf/pindaan: tarikh mula ditetapkan semula ketika dihantar
            if ($current && (int) $current->user_id !== $officer->id) {
                $attributes['started_at'] = null;
            }

            if ($current) {
                $current->update($attributes);
            } else {
                $current = $application->officers()->create($attributes + [
                    'role' => $role,
                    'assigned_by' => $user->id,
                ]);
            }

            if ($submitting && $current->started_at === null) {
                $current->update(['started_at' => now()->toDateString()]);
            }
        }
    }

    /**
     * Simpan Perincian Projek (Fasa 3): perincian lama diganti dengan senarai semasa.
     */
    private function syncDetails(Application $application, ApplicationForm $form): void
    {
        if (empty($form->details)) {
            return;
        }

        $application->details()->delete();

        foreach ($form->details as $group) {
            $category = $this->findMaster(ProjectCategory::class, $group['project_category_id'] ?? null, 'Kategori Projek');

            foreach ($group['items'] ?? [] as $item) {
                if (empty($item['technical_specifications'])) {
                    continue;
                }

                if (! $category) {
                    throw new InvalidArgumentException('Kategori Projek wajib dipilih bagi setiap kumpulan item.');
                }

                $qty = (int) ($item['unit_quantity'] ?? 1);
                $unitCost = (float) ($item['unit_cost'] ?? 0);

                ApplicationDetail::create([
                    'uuid' => (string) Str::uuid(),
                    'application_id' => $application->id,
                    'project_category_id' => $category->id,
                    'item_category' => $category->name,
                    'technical_specifications' => strip_tags($item['technical_specifications']),
                    'unit_quantity' => $qty,
                    'unit_cost' => $unitCost,
                    'total_cost' => $qty * $unitCost,
                ]);
            }
        }
    }

    /**
     * Simpan dokumen baharu (Fasa 4). Dokumen lama jenis yang sama di-soft delete
     * dan failnya dikekalkan untuk tujuan audit.
     */
    private function storeDocuments(Application $application, ApplicationForm $form): void
    {
        foreach (self::DOCUMENT_TYPES as $field => $documentType) {
            $file = $form->{$field};

            if (! $file instanceof UploadedFile) {
                continue;
            }

            $application->documents()->where('document_type', $documentType)->get()->each->delete();

            ApplicationDocument::create([
                'uuid' => (string) Str::uuid(),
                'application_id' => $application->id,
                'document_type' => $documentType,
                'file_name' => $file->getClientOriginalName(),
                'file_path' => $file->store("applications/{$application->uuid}/documents", self::DOCUMENT_DISK),
                'mime_type' => $file->getMimeType(),
                'file_size_kb' => max(1, (int) round($file->getSize() / 1024)),
            ]);
        }
    }
}
