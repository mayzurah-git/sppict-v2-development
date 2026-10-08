<?php

namespace App\Policies;

use App\Models\Application;
use App\Models\User;

class ApplicationPolicy
{
    /**
     * Papar permohonan & dokumen: pemohon, pegawai bertanggungjawab aktif, urus setia,
     * atau pengguna dari jabatan/agensi yang sama dengan permohonan.
     */
    public function view(User $user, Application $application): bool
    {
        return $user->id === $application->applicant_id
            || $user->hasRole('pentadbir_urus_setia')
            || $this->inSameAgency($user, $application)
            || $application->isActiveOfficer($user);
    }

    /**
     * Kemas kini permohonan & padam/ganti dokumen: pemohon sahaja,
     * dan hanya ketika permohonan berstatus Draf / Tidak Lengkap.
     */
    public function update(User $user, Application $application): bool
    {
        return $user->id === $application->applicant_id
            && $application->isEditable();
    }

    /**
     * Tukar Pegawai Bertanggungjawab selepas permohonan dihantar.
     * Urus Setia sentiasa dibenarkan (jalan terakhir jika pegawai sudah bertukar keluar);
     * pemohon atau pegawai aktif dibenarkan selagi masih dalam agensi yang sama.
     */
    public function manageOfficers(User $user, Application $application): bool
    {
        if ($application->isEditable()) {
            return false; // Semasa Draf / Tidak Lengkap, pegawai dikemas kini melalui borang permohonan
        }

        if ($user->hasRole('pentadbir_urus_setia')) {
            return true;
        }

        return $this->inSameAgency($user, $application)
            && ($user->id === $application->applicant_id || $application->isActiveOfficer($user));
    }

    /**
     * Isi laporan kemajuan projek: pegawai bertanggungjawab aktif yang masih dalam agensi yang sama.
     */
    public function updateProgress(User $user, Application $application): bool
    {
        return ! $application->isEditable()
            && $this->inSameAgency($user, $application)
            && $application->isActiveOfficer($user);
    }

    private function inSameAgency(User $user, Application $application): bool
    {
        return $user->agency_id !== null && $user->agency_id === $application->agency_id;
    }
}
