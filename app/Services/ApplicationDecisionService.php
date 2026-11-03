<?php

namespace App\Services;

use App\Enums\ApplicationDecision as Decision;
use App\Enums\ApplicationStatus;
use App\Enums\DecisionStage;
use App\Models\Application;
use App\Models\ApplicationDecision;
use App\Models\Meeting;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * PP4: rekod keputusan Pra JTICT dan Mesyuarat JTICT oleh Urus Setia.
 *
 * PP4.1: belum dipanggil oleh mana-mana route / UI (diaktifkan dalam fasa antara muka).
 * Tiada e-mel dihantar, tiada rekod projek dicipta, dan lajur warisan jtict_decision tidak disentuh.
 */
class ApplicationDecisionService
{
    public const REMARKS_MIN = 10;

    public const REMARKS_MAX = 5000;

    /**
     * Rekod satu keputusan. Rekod keputusan & status permohonan dikemas kini dalam satu transaksi
     * (rekod permohonan dikunci; kebenaran, status dan tarikh disemak semula di dalam transaksi),
     * jadi klik berganda / keputusan serentak tidak menghasilkan rekod pendua.
     *
     * Peringkat, status asal & sasaran dan perekod ditentukan oleh pelayan; $stage hanya menyatakan
     * peringkat yang dijangka oleh pemanggil dan mesti sepadan dengan keputusan.
     *
     * @param  int|null  $meetingId  pilihan; jika diberi, mesti sama dengan mesyuarat JTICT permohonan
     *
     * @throws AuthorizationException  pengguna tidak dibenarkan / permohonan tidak berada pada peringkat ini
     * @throws ValidationException  keputusan, ulasan, tarikh atau mesyuarat tidak sah
     *                              (kunci: decision, remarks, decisionDate, meetingId)
     */
    public function decide(
        Application $application,
        DecisionStage $stage,
        Decision|string|null $decision,
        ?string $remarks,
        CarbonInterface|string|null $decisionDate,
        User $user,
        ?int $meetingId = null,
    ): ApplicationDecision {
        $decision = $this->resolveDecision($stage, $decision);
        $remarks = $this->normaliseRemarks($remarks);
        $decisionDate = $decisionDate instanceof CarbonInterface ? $decisionDate->toDateString() : $decisionDate;

        Validator::make(['remarks' => $remarks, 'decisionDate' => $decisionDate], [
            'remarks' => [
                $decision->requiresRemarks() ? 'required' : 'nullable',
                'string',
                'min:' . self::REMARKS_MIN,
                'max:' . self::REMARKS_MAX,
            ],
            'decisionDate' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
        ], [
            'remarks.required' => "Ulasan wajib diisi bagi keputusan {$decision->label()} ({$stage->label()}).",
            'remarks.min' => 'Ulasan hendaklah sekurang-kurangnya ' . self::REMARKS_MIN . ' aksara.',
            'remarks.max' => 'Ulasan tidak boleh melebihi ' . self::REMARKS_MAX . ' aksara.',
            'decisionDate.required' => 'Tarikh keputusan wajib diisi.',
            'decisionDate.date_format' => 'Tarikh keputusan tidak sah.',
            'decisionDate.before_or_equal' => 'Tarikh keputusan tidak boleh melebihi tarikh semasa.',
        ])->validate();

        $record = DB::transaction(function () use ($application, $stage, $decision, $remarks, $decisionDate, $user, $meetingId) {
            $locked = Application::lockForUpdate()->findOrFail($application->id);
            $actor = User::findOrFail($user->id); // peranan & status akaun terkini

            $ability = $stage === DecisionStage::PreJtict ? 'decidePreJtict' : 'decideJtict';
            $target = $decision->targetStatus();

            if ($actor->cannot($ability, $locked)
                || $locked->statusEnum() !== $stage->requiredStatus()
                || ! $locked->statusEnum()->canTransitionTo($target)) {
                throw new AuthorizationException("Keputusan {$stage->label()} tidak boleh direkodkan bagi permohonan ini pada status semasa.");
            }

            $meeting = $stage === DecisionStage::PreJtict
                ? $this->validatePreJtict($locked, $decisionDate, $meetingId)
                : $this->validateJtict($locked, $decisionDate, $meetingId);

            $record = ApplicationDecision::create([
                'application_id' => $locked->id,
                'stage' => $decision->stage(),
                'decision' => $decision,
                'from_status' => $locked->status,
                'to_status' => $target->value,
                'remarks' => $remarks,
                'decision_date' => $decisionDate,
                'meeting_id' => $meeting?->id,
                'decided_by' => $actor->id,
            ]);

            // Hanya status berubah; mesyuarat, ulasan semakan PP2 dan jtict_decision tidak disentuh.
            $locked->status = $target->value;
            $locked->save();

            return $record;
        });

        $application->refresh();

        Log::info("Keputusan {$stage->label()} direkodkan: {$application->reference_number} {$decision->value} oleh ID Pengguna {$user->id}");

        return $record;
    }

    /**
     * @throws ValidationException
     */
    private function resolveDecision(DecisionStage $stage, Decision|string|null $decision): Decision
    {
        $decision = is_string($decision) ? Decision::tryFrom($decision) : $decision;

        if (! $decision || $decision->stage() !== $stage) {
            throw ValidationException::withMessages([
                'decision' => "Keputusan tidak sah bagi peringkat {$stage->label()}.",
            ]);
        }

        return $decision;
    }

    /**
     * Pra JTICT: tarikh keputusan tidak lebih awal daripada tarikh terakhir PP2 mengesahkan Lengkap.
     * Mesyuarat yang direkodkan = Mesyuarat JTICT permohonan pada masa keputusan (jika ada; tidak diubah).
     *
     * @throws ValidationException
     */
    private function validatePreJtict(Application $application, string $decisionDate, ?int $meetingId): ?Meeting
    {
        $completedAt = $application->reviews()
            ->where('to_status', ApplicationStatus::Complete->value)
            ->latest('id')
            ->value('created_at');

        if (! $completedAt) {
            throw ValidationException::withMessages([
                'decisionDate' => 'Tiada rekod semakan PP2 yang mengesahkan permohonan ini Lengkap.',
            ]);
        }

        $completedDate = Carbon::parse($completedAt);

        if ($decisionDate < $completedDate->toDateString()) {
            throw ValidationException::withMessages([
                'decisionDate' => 'Tarikh keputusan Pra JTICT tidak boleh lebih awal daripada tarikh permohonan disahkan Lengkap (' . $completedDate->format('d/m/Y') . ').',
            ]);
        }

        if ($meetingId !== null && $meetingId !== $application->jtict_meeting_id) {
            throw ValidationException::withMessages([
                'meetingId' => 'Mesyuarat tidak sepadan dengan Mesyuarat JTICT permohonan.',
            ]);
        }

        return $application->jtict_meeting_id ? Meeting::withTrashed()->find($application->jtict_meeting_id) : null;
    }

    /**
     * JTICT: Mesyuarat JTICT permohonan mesti wujud (tidak dipadam) dan tarikh keputusan tidak lebih
     * awal daripada tarikh mesyuarat.
     *
     * @throws ValidationException
     */
    private function validateJtict(Application $application, string $decisionDate, ?int $meetingId): Meeting
    {
        $meeting = $application->jtict_meeting_id
            ? Meeting::jtict()->find($application->jtict_meeting_id)
            : null;

        if (! $meeting) {
            throw ValidationException::withMessages([
                'meetingId' => 'Mesyuarat JTICT permohonan ini tidak wujud atau telah dipadam.',
            ]);
        }

        if ($meetingId !== null && $meetingId !== $meeting->id) {
            throw ValidationException::withMessages([
                'meetingId' => 'Mesyuarat tidak sepadan dengan Mesyuarat JTICT permohonan.',
            ]);
        }

        if ($decisionDate < $meeting->meeting_date->toDateString()) {
            throw ValidationException::withMessages([
                'decisionDate' => 'Tarikh keputusan JTICT tidak boleh lebih awal daripada tarikh mesyuarat (' . $meeting->meeting_date->format('d/m/Y') . ').',
            ]);
        }

        return $meeting;
    }

    /**
     * Ulasan disimpan sebagai teks biasa (tag HTML dibuang; dipaparkan ter-escape).
     */
    private function normaliseRemarks(?string $remarks): ?string
    {
        $remarks = trim(strip_tags((string) $remarks));

        return $remarks === '' ? null : $remarks;
    }
}
