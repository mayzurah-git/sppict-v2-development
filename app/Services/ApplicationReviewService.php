<?php

namespace App\Services;

use App\Enums\ApplicationStatus;
use App\Models\Application;
use App\Models\ApplicationReview;
use App\Models\User;
use App\Notifications\ApplicationMarkedIncomplete;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;

/**
 * PP2: semakan kelengkapan permohonan oleh Urus Setia (Lengkap / Tidak Lengkap).
 */
class ApplicationReviewService
{
    public const REMARKS_MIN = 10;

    public const REMARKS_MAX = 5000;

    /**
     * Keputusan yang boleh dibuat dalam semakan kelengkapan.
     */
    public const DECISIONS = [ApplicationStatus::Complete, ApplicationStatus::NeedsAmendment];

    /**
     * Rekod keputusan semakan. Status permohonan & rekod sejarah dikemas kini dalam satu transaksi
     * (rekod dikunci dan status disemak semula, jadi klik berganda tidak menghasilkan rekod pendua).
     * E-mel Tidak Lengkap dihantar hanya selepas transaksi berjaya.
     *
     * @throws AuthorizationException  pengguna tidak dibenarkan / permohonan tidak lagi menunggu semakan
     * @throws \Illuminate\Validation\ValidationException  ulasan tidak sah (kunci: reviewRemarks)
     */
    public function review(Application $application, ApplicationStatus $decision, ?string $remarks, User $reviewer): ApplicationReview
    {
        if (! in_array($decision, self::DECISIONS, true)) {
            throw new InvalidArgumentException('Keputusan semakan tidak sah.');
        }

        $remarks = $this->normaliseRemarks($remarks);

        Validator::make(['reviewRemarks' => $remarks], [
            'reviewRemarks' => [
                $decision === ApplicationStatus::NeedsAmendment ? 'required' : 'nullable',
                'string',
                'min:' . self::REMARKS_MIN,
                'max:' . self::REMARKS_MAX,
            ],
        ], [
            'reviewRemarks.required' => 'Ulasan semakan wajib diisi bagi keputusan Tidak Lengkap. Nyatakan maklumat, dokumen atau bahagian yang perlu dipinda.',
            'reviewRemarks.min' => 'Ulasan semakan hendaklah sekurang-kurangnya ' . self::REMARKS_MIN . ' aksara.',
            'reviewRemarks.max' => 'Ulasan semakan tidak boleh melebihi ' . self::REMARKS_MAX . ' aksara.',
        ])->validate();

        $review = DB::transaction(function () use ($application, $decision, $remarks, $reviewer) {
            $locked = Application::lockForUpdate()->findOrFail($application->id);

            if ($reviewer->cannot('review', $locked) || ! $locked->statusEnum()?->canTransitionTo($decision)) {
                throw new AuthorizationException('Permohonan ini tidak boleh disemak atau tidak lagi menunggu semakan.');
            }

            $from = $locked->status;

            // review_remarks dikekalkan untuk keserasian: ulasan semakan terkini (sejarah penuh dalam application_reviews).
            $locked->status = $decision->value;
            $locked->review_remarks = $remarks;
            $locked->save();

            return ApplicationReview::create([
                'application_id' => $locked->id,
                'from_status' => $from,
                'to_status' => $decision->value,
                'remarks' => $remarks,
                'user_id' => $reviewer->id,
                'jtict_meeting_id' => $locked->jtict_meeting_id,
            ]);
        });

        $application->refresh();

        if ($decision === ApplicationStatus::NeedsAmendment) {
            $this->notifyIncomplete($application, $review);
        }

        return $review;
    }

    /**
     * Ulasan disimpan sebagai teks biasa (tag HTML dibuang; dipaparkan ter-escape).
     */
    private function normaliseRemarks(?string $remarks): ?string
    {
        $remarks = trim(strip_tags((string) $remarks));

        return $remarks === '' ? null : $remarks;
    }

    /**
     * E-mel kepada Pemohon dan Pegawai Utama. Kegagalan dilog; keputusan semakan tidak terjejas.
     */
    private function notifyIncomplete(Application $application, ApplicationReview $review): void
    {
        $notification = new ApplicationMarkedIncomplete($application, $review);

        foreach ($this->incompleteRecipients($application) as $role => $notifiable) {
            try {
                Notification::send($notifiable, $notification);
            } catch (\Throwable $e) {
                Log::error("Notifikasi Permohonan Tidak Lengkap gagal dihantar kepada {$role} ({$application->reference_number}): " . $e->getMessage());
            }
        }
    }

    /**
     * Penerima: Pemohon (akaun pengguna) dan Pegawai Utama aktif (akaun pengguna; jika akaun tiada,
     * e-mel snapshot pegawai). Alamat yang tiada / tidak sah dilangkau dan dilog. Alamat yang sama
     * (cth. pemohon ialah Pegawai Utama) hanya menerima satu e-mel.
     *
     * @return Collection<string, object>
     */
    public function incompleteRecipients(Application $application): Collection
    {
        $application->loadMissing(['applicant', 'primaryOfficer.user']);
        $officer = $application->primaryOfficer;

        $candidates = [
            'Pemohon' => [$application->applicant, $application->applicant?->email],
            'Pegawai Utama' => $officer?->user
                ? [$officer->user, $officer->user->email]
                : [null, $officer?->email],
        ];

        $recipients = collect();
        $seen = [];

        foreach ($candidates as $role => [$user, $email]) {
            if ($role === 'Pegawai Utama' && ! $officer) {
                Log::warning("Notifikasi Permohonan Tidak Lengkap: tiada Pegawai Utama aktif ({$application->reference_number}).");
                continue;
            }

            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                Log::warning("Notifikasi Permohonan Tidak Lengkap tidak dihantar kepada {$role} ({$application->reference_number}): alamat e-mel tiada atau tidak sah.");
                continue;
            }

            $key = strtolower($email);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;

            $recipients->put($role, $user ?? Notification::route('mail', $email));
        }

        return $recipients;
    }
}
