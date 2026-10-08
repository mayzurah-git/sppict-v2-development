<?php

namespace App\Services;

use App\Enums\OfficerEndReason;
use App\Enums\OfficerRole;
use App\Models\Application;
use App\Models\ApplicationOfficer;
use App\Models\User;
use App\Notifications\ResponsibleOfficerChanged;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

/**
 * Pertukaran Pegawai Bertanggungjawab selepas permohonan dihantar.
 * Rekod pegawai lama ditutup (bukan dipadam) supaya sejarah projek kekal.
 * Tiada kelulusan diperlukan; Urus Setia dimaklumkan melalui notifikasi.
 */
class OfficerHandoverService
{
    /**
     * @param  User|null  $newOfficer  null hanya dibenarkan bagi Pegawai Kedua (tamatkan tanpa pengganti)
     */
    public function reassign(
        Application $application,
        OfficerRole $role,
        ?User $newOfficer,
        CarbonInterface $effectiveDate,
        OfficerEndReason $reason,
        ?string $remarks,
        User $actor,
    ): ?ApplicationOfficer {
        [$previous, $assigned] = DB::transaction(function () use ($application, $role, $newOfficer, $effectiveDate, $reason, $remarks, $actor) {
            $application = Application::lockForUpdate()->findOrFail($application->id);

            $current = $application->officers()->active()->role($role)->first();

            $this->guard($application, $role, $current, $newOfficer, $effectiveDate);

            $closing = [
                'ended_at' => $effectiveDate->toDateString(),
                'end_reason' => $reason,
                'handover_remarks' => $remarks ? strip_tags($remarks) : null,
                'ended_by' => $actor->id,
            ];

            $current?->update($closing);

            $assigned = null;

            if ($newOfficer) {
                // Pegawai Kedua dinaikkan menjadi Pegawai Utama: tamatkan tugasan Pegawai Kedua
                if ($role === OfficerRole::Primary) {
                    $application->officers()->active()->role(OfficerRole::Secondary)
                        ->where('user_id', $newOfficer->id)
                        ->first()
                        ?->update(['end_reason' => OfficerEndReason::Reassignment, 'handover_remarks' => 'Dilantik sebagai Pegawai Utama.'] + $closing);
                }

                $assigned = $application->officers()->create(ApplicationOfficer::snapshotFrom($newOfficer) + [
                    'role' => $role,
                    'started_at' => $effectiveDate->toDateString(),
                    'assigned_by' => $actor->id,
                ]);
            }

            return [$current, $assigned];
        });

        $this->notify($application, $role, $previous, $assigned, $actor);

        return $assigned;
    }

    private function guard(Application $application, OfficerRole $role, ?ApplicationOfficer $current, ?User $newOfficer, CarbonInterface $effectiveDate): void
    {
        $errors = [];

        if ($application->isEditable()) {
            $errors['newOfficerId'] = 'Permohonan masih boleh disunting; kemas kini pegawai melalui borang permohonan.';
        } elseif (! $newOfficer && $role === OfficerRole::Primary) {
            $errors['newOfficerId'] = 'Sila pilih Pegawai Utama baharu.';
        } elseif (! $newOfficer && ! $current) {
            $errors['newOfficerId'] = 'Tiada Pegawai Kedua aktif untuk ditamatkan.';
        } elseif ($newOfficer && (int) $newOfficer->agency_id !== (int) $application->agency_id) {
            $errors['newOfficerId'] = 'Pegawai baharu mestilah pengguna berdaftar dalam agensi yang sama.';
        } elseif ($newOfficer && $current && (int) $current->user_id === $newOfficer->id) {
            $errors['newOfficerId'] = 'Pegawai ini sudah pun menjadi ' . $role->label() . '.';
        } elseif ($newOfficer && $role === OfficerRole::Secondary
            && $application->officers()->active()->role(OfficerRole::Primary)->where('user_id', $newOfficer->id)->exists()) {
            $errors['newOfficerId'] = 'Pegawai Utama tidak boleh dilantik sebagai Pegawai Kedua.';
        }

        if ($effectiveDate->isAfter(today())) {
            $errors['effectiveDate'] = 'Tarikh berkuat kuasa tidak boleh melebihi tarikh hari ini.';
        } elseif ($current?->started_at && $effectiveDate->lt($current->started_at)) {
            $errors['effectiveDate'] = 'Tarikh berkuat kuasa tidak boleh sebelum tarikh mula pegawai semasa (' . $current->started_at->format('d/m/Y') . ').';
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * Pemakluman kepada Urus Setia, pegawai baharu dan pegawai terdahulu.
     * Kegagalan e-mel tidak membatalkan pertukaran yang telah disimpan.
     */
    private function notify(Application $application, OfficerRole $role, ?ApplicationOfficer $previous, ?ApplicationOfficer $assigned, User $actor): void
    {
        $recipients = User::whereHas('roles', fn ($query) => $query->where('name', 'pentadbir_urus_setia'))->get()
            ->push($assigned?->user, $previous?->user)
            ->filter()
            ->reject(fn (User $user) => $user->is($actor))
            ->unique('id');

        try {
            Notification::send($recipients, new ResponsibleOfficerChanged($application, $role, $previous, $assigned, $actor));
        } catch (\Throwable $e) {
            Log::warning("Notifikasi pertukaran pegawai gagal dihantar ({$application->reference_number}): " . $e->getMessage());
        }
    }
}
