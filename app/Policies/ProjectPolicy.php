<?php

namespace App\Policies;

use App\Enums\ProjectStatus;
use App\Models\Application;
use App\Models\Project;
use App\Models\User;

/**
 * SP2: akses Modul Status Kemajuan Projek (keputusan pemilik I.9, I.11; audit SP0 L.6).
 *
 * - Lihat: pengguna dalam agensi projek, pegawai bertanggungjawab aktif, Urus Setia (pantau) dan
 *   Pengurusan (baca sahaja). Superadmin tiada akses walaupun memegang peranan lain.
 * - Kemas kini: Pegawai Utama / Pegawai Kedua aktif yang masih dalam agensi projek. Projek baca sahaja
 *   (rekod sejarah v1, Selesai, Ditutup) tidak boleh dikemas kini oleh sesiapa.
 * - Agensi projek diambil daripada projects.agency_id (projek v1 tiada permohonan v2).
 */
class ProjectPolicy
{
    /** Peranan yang boleh membuka modul projek (I.9). */
    public const VIEWER_ROLES = ['pengguna_biasa', 'pentadbir_urus_setia', 'pengurusan'];

    public function viewAny(User $user): bool
    {
        return $user->canUseSystem()
            && $user->hasAnyRole(self::VIEWER_ROLES)
            && ! $user->hasRole('superadmin');
    }

    public function view(User $user, Project $project): bool
    {
        if (! $this->viewAny($user)) {
            return false;
        }

        return $user->hasAnyRole(['pentadbir_urus_setia', 'pengurusan'])
            || $this->inProjectAgency($user, $project)
            || ($project->application !== null && $project->application->isActiveOfficer($user));
    }

    /**
     * Mulakan Pemantauan: permohonan Lulus JTICT (keputusan JTICT terkini) yang belum mempunyai projek,
     * oleh Pegawai Utama / Kedua aktif dalam agensi permohonan (I.1, I.11).
     */
    public function start(User $user, Application $application): bool
    {
        return $this->canAct($user)
            && ! $application->trashed()
            && $application->isApprovedByJtict()
            && ! Project::withTrashed()->where('application_id', $application->id)->exists()
            && $user->agency_id !== null
            && $user->agency_id === $application->agency_id
            && $application->isActiveOfficer($user);
    }

    /**
     * Pindaan jadual (I.3), struktur dan kemas kini aktiviti / dokumen aktiviti (SP3): Pegawai Utama / Kedua
     * aktif (I.11). SP4: disekat semasa Menunggu Pengesahan Penutupan.
     */
    public function manage(User $user, Project $project): bool
    {
        return $this->isOfficer($user, $project)
            && ! $project->isReadOnly()
            && $project->status !== ProjectStatus::PendingClosure;
    }

    /**
     * Laporan kemajuan bulanan dan dokumennya: pegawai aktif; masih dibenarkan semasa Menunggu Pengesahan
     * Penutupan supaya laporan tertunggak boleh dihantar.
     */
    public function manageReports(User $user, Project $project): bool
    {
        return $this->isOfficer($user, $project) && ! $project->isReadOnly();
    }

    /**
     * SP4 (I.6): mohon penutupan (Dalam Pelaksanaan). Syarat kemajuan disemak oleh ProjectClosureService.
     */
    public function requestClosure(User $user, Project $project): bool
    {
        return $this->isOfficer($user, $project) && $project->status === ProjectStatus::InProgress;
    }

    /** SP4: tarik balik permohonan penutupan. */
    public function withdrawClosure(User $user, Project $project): bool
    {
        return $this->isOfficer($user, $project) && $project->status === ProjectStatus::PendingClosure;
    }

    /** SP4: sahkan / tolak permohonan penutupan: Urus Setia sahaja. */
    public function decideClosure(User $user, Project $project): bool
    {
        return $this->isUrusSetia($user) && $project->status === ProjectStatus::PendingClosure;
    }

    /** SP4: tutup projek tanpa siap (dihentikan / dibatalkan): Urus Setia sahaja, dengan ulasan wajib. */
    public function terminate(User $user, Project $project): bool
    {
        return $this->isUrusSetia($user)
            && ! $project->isLegacy()
            && in_array($project->status, [ProjectStatus::InProgress, ProjectStatus::PendingClosure], true);
    }

    private function isOfficer(User $user, Project $project): bool
    {
        return $this->canAct($user)
            && $project->application !== null
            && $this->inProjectAgency($user, $project)
            && $project->application->isActiveOfficer($user);
    }

    /**
     * Pinda struktur fasa / aktiviti: hanya sebelum struktur dikunci (L.3).
     */
    public function updateStructure(User $user, Project $project): bool
    {
        return $this->manage($user, $project) && ! $project->isStructureLocked();
    }

    /**
     * Buka semula struktur yang dikunci: Urus Setia sahaja, dengan catatan (L.3).
     */
    public function unlockStructure(User $user, Project $project): bool
    {
        return $this->isUrusSetia($user)
            && ! $project->isReadOnly()
            && $project->status !== ProjectStatus::PendingClosure
            && $project->isStructureLocked();
    }

    /**
     * Rekod kelulusan JPICT (pilihan, I.1): Urus Setia sahaja, bagi projek v2 yang memerlukan JPICT.
     */
    public function recordJpict(User $user, Project $project): bool
    {
        return $this->isUrusSetia($user)
            && ! $project->isLegacy()
            && $project->requiresJpict();
    }

    private function canAct(User $user): bool
    {
        return $user->canUseSystem() && ! $user->hasAnyRole(['superadmin', 'pengurusan']);
    }

    private function isUrusSetia(User $user): bool
    {
        return $user->canUseSystem()
            && $user->hasRole('pentadbir_urus_setia')
            && ! $user->hasAnyRole(['superadmin', 'pengurusan']);
    }

    private function inProjectAgency(User $user, Project $project): bool
    {
        return $user->agency_id !== null && $user->agency_id === $project->agency_id;
    }
}
