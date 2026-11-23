<?php

namespace App\Enums;

/**
 * PP4: kod keputusan Pra JTICT dan JTICT (disimpan dalam `application_decisions.decision`, VARCHAR).
 *
 * Keputusan bukan status proses (lihat ApplicationStatus): setiap keputusan menetapkan status
 * proses seterusnya melalui targetStatus(), manakala keputusan itu sendiri kekal dalam sejarah.
 */
enum ApplicationDecision: string
{
    // --- Pra JTICT ---
    case Accepted = 'ACCEPTED';
    case AmendmentRequired = 'AMENDMENT_REQUIRED';
    case Rejected = 'REJECTED';

    // --- JTICT ---
    case Approved = 'APPROVED';
    case NotApproved = 'NOT_APPROVED';
    case Review = 'REVIEW';

    public function stage(): DecisionStage
    {
        return match ($this) {
            self::Accepted, self::AmendmentRequired, self::Rejected => DecisionStage::PreJtict,
            self::Approved, self::NotApproved, self::Review => DecisionStage::Jtict,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Accepted => 'Diterima',
            self::AmendmentRequired => 'Ditolak dengan Pindaan',
            self::Rejected => 'Ditolak',
            self::Approved => 'Lulus',
            self::NotApproved => 'Tidak Lulus',
            self::Review => 'Kaji Semula',
        };
    }

    /**
     * Status proses permohonan selepas keputusan direkodkan. Semua keputusan JTICT menutup
     * kitaran permohonan asal (keputusan sebenar dibaca daripada rekod keputusan, bukan status).
     */
    public function targetStatus(): ApplicationStatus
    {
        return match ($this) {
            self::Accepted => ApplicationStatus::InJtict,
            self::AmendmentRequired => ApplicationStatus::PreJtictAmendment,
            self::Rejected, self::Approved, self::NotApproved, self::Review => ApplicationStatus::Closed,
        };
    }

    /**
     * Ulasan wajib (keputusan pemilik sistem PP4.1): Ditolak dengan Pindaan dan ketiga-tiga keputusan JTICT.
     */
    public function requiresRemarks(): bool
    {
        return match ($this) {
            self::Accepted, self::Rejected => false,
            self::AmendmentRequired, self::Approved, self::NotApproved, self::Review => true,
        };
    }

    /**
     * @return array<int, self>
     */
    public static function forStage(DecisionStage $stage): array
    {
        return array_values(array_filter(self::cases(), fn (self $decision) => $decision->stage() === $stage));
    }
}
