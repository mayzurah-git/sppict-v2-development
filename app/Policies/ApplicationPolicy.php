<?php

namespace App\Policies;

use App\Enums\ApplicationDecision;
use App\Enums\ApplicationStatus;
use App\Models\Application;
use App\Models\User;

class ApplicationPolicy
{
    /**
     * Peranan yang boleh mendaftar permohonan projek baharu (keputusan dasar UM7A).
     * `pengurusan` dan `superadmin` tidak dibenarkan. Sumber peranan: Spatie (`model_has_roles`).
     */
    public const CREATOR_ROLES = ['pengguna_biasa', 'pentadbir_urus_setia'];

    /**
     * Daftar permohonan baharu. Akaun mesti boleh menggunakan sistem (aktif, tiada kata laluan
     * sementara) dan memegang salah satu peranan pencipta, tetapi bukan Superadmin/Pengurusan
     * (dikecualikan secara eksplisit sekiranya pengguna memegang lebih daripada satu peranan).
     */
    public function create(User $user): bool
    {
        return $user->canUseSystem()
            && $user->hasAnyRole(self::CREATOR_ROLES)
            && ! $user->hasAnyRole(['superadmin', 'pengurusan']);
    }

    /**
     * PP3: daftar permohonan baharu bagi pihak agensi sasaran. Urus Setia sahaja (tertakluk pada
     * `create`); agensi sasaran wajib dipilih dan disahkan di pelayan. Pengguna biasa sentiasa
     * menggunakan agensi akaunnya sendiri.
     */
    public function createOnBehalf(User $user): bool
    {
        return $this->create($user) && $user->hasRole('pentadbir_urus_setia');
    }

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
     * Kemas kini permohonan & padam/ganti dokumen: hanya ketika permohonan berstatus Draf / Tidak Lengkap,
     * oleh pemohon (pendaftar rekod) atau pegawai bertanggungjawab aktif (Utama / Kedua) yang masih
     * dalam agensi permohonan (UAT PP3: pegawai agensi sasaran boleh meminda permohonan bagi pihak agensi).
     */
    public function update(User $user, Application $application): bool
    {
        return $application->isEditable()
            && ($user->id === $application->applicant_id
                || ($this->inSameAgency($user, $application) && $application->isActiveOfficer($user)));
    }

    /**
     * PP2: semakan kelengkapan (Lengkap / Tidak Lengkap). Urus Setia sahaja (skop semua agensi,
     * selaras dengan `view`), hanya bagi permohonan berstatus Dihantar. Superadmin & Pengurusan
     * tidak dibenarkan walaupun memegang peranan lain.
     */
    public function review(User $user, Application $application): bool
    {
        return $user->canUseSystem()
            && $user->hasRole('pentadbir_urus_setia')
            && ! $user->hasAnyRole(['superadmin', 'pengurusan'])
            && $application->statusEnum() === ApplicationStatus::Submitted;
    }

    /**
     * PP4: rekod keputusan Pra JTICT (Diterima / Ditolak dengan Pindaan / Ditolak). Urus Setia sahaja,
     * bagi permohonan Lengkap (telah melepasi semakan PP2). Superadmin & Pengurusan tidak dibenarkan.
     */
    public function decidePreJtict(User $user, Application $application): bool
    {
        return $this->canRecordDecision($user)
            && $application->statusEnum() === ApplicationStatus::Complete;
    }

    /**
     * PP4: rekod keputusan Mesyuarat JTICT (Lulus / Tidak Lulus / Kaji Semula). Urus Setia sahaja,
     * bagi permohonan Dalam Proses JTICT yang keputusan Pra JTICT terkininya ialah Diterima.
     */
    public function decideJtict(User $user, Application $application): bool
    {
        return $this->canRecordDecision($user)
            && $application->statusEnum() === ApplicationStatus::InJtict
            && $application->latestPreJtictDecision()->first()?->decision === ApplicationDecision::Accepted;
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

    /**
     * PP4: peranan yang boleh merekod keputusan Pra JTICT / JTICT (corak sama dengan review()).
     */
    private function canRecordDecision(User $user): bool
    {
        return $user->canUseSystem()
            && $user->hasRole('pentadbir_urus_setia')
            && ! $user->hasAnyRole(['superadmin', 'pengurusan']);
    }

    private function inSameAgency(User $user, Application $application): bool
    {
        return $user->agency_id !== null && $user->agency_id === $application->agency_id;
    }
}
