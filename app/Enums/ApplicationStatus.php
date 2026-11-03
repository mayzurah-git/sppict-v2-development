<?php

namespace App\Enums;

/**
 * PP1: status PROSES permohonan projek (kedudukan dalam aliran kerja sistem).
 *
 * Status ini tidak mewakili keputusan mesyuarat. PP4: keputusan Pra JTICT dan keputusan JTICT
 * direkodkan secara berasingan dalam `application_decisions` (App\Models\ApplicationDecision);
 * lajur warisan `applications.jtict_decision` tidak digunakan. Perubahan status proses tidak
 * menimpa keputusan atau sejarahnya. "Lengkap" hanya bermaksud semakan kelengkapan oleh
 * Urus Setia telah lulus; ia bukan keputusan "Lulus".
 *
 * Nilai disimpan dalam `applications.status` (VARCHAR). Nilai lama yang tiada di sini
 * (UNDER_REVIEW, APPROVED_URUS_SETIA, REJECTED_URUS_SETIA) dipaparkan melalui labelFor().
 */
enum ApplicationStatus: string
{
    /** Disediakan oleh pemohon; boleh disunting. */
    case Draft = 'DRAFT';

    /** Dihantar (kali pertama atau selepas pindaan); menunggu semakan Urus Setia. */
    case Submitted = 'SUBMITTED';

    /** Tidak Lengkap: dikembalikan oleh Urus Setia dengan ulasan; pemohon perlu meminda dan menghantar semula. */
    case NeedsAmendment = 'NEEDS_AMENDMENT';

    /** Lengkap: semakan kelengkapan lulus; menunggu pembentangan / keputusan Pra JTICT. */
    case Complete = 'COMPLETE';

    /** PP4: Pra JTICT "Ditolak dengan Pindaan"; pemohon meminda permohonan asal dan menghantar semula (melalui PP2). */
    case PreJtictAmendment = 'PRE_JTICT_AMENDMENT';

    /** Diterima pada peringkat Pra JTICT; menunggu keputusan Mesyuarat JTICT. */
    case InJtict = 'IN_JTICT';

    /** Selesai diproses (keputusan muktamad Pra JTICT atau JTICT telah direkodkan). */
    case Closed = 'CLOSED';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draf',
            self::Submitted => 'Dihantar',
            self::NeedsAmendment => 'Tidak Lengkap',
            self::Complete => 'Lengkap',
            self::PreJtictAmendment => 'Pindaan Pra JTICT',
            self::InJtict => 'Dalam Proses JTICT',
            self::Closed => 'Selesai',
        };
    }

    /**
     * Maksud ringkas status (untuk papan pemuka / bantuan paparan).
     */
    public function description(): string
    {
        return match ($this) {
            self::Draft => 'Sedang disediakan oleh pemohon',
            self::Submitted => 'Menunggu semakan Urus Setia',
            self::NeedsAmendment => 'Dikembalikan untuk pindaan pemohon',
            self::Complete => 'Lengkap, menunggu keputusan Pra JTICT',
            self::PreJtictAmendment => 'Dikembalikan selepas Pra JTICT untuk pindaan pemohon',
            self::InJtict => 'Menunggu keputusan Mesyuarat JTICT',
            self::Closed => 'Proses permohonan telah selesai',
        };
    }

    /**
     * Pemohon boleh menyunting permohonan (dan dokumennya) pada status ini.
     */
    public function isEditableByApplicant(): bool
    {
        return in_array($this, self::editableCases(), true);
    }

    /**
     * Peralihan status yang dibenarkan oleh aliran kerja. Kebenaran PERANAN untuk setiap
     * tindakan disemak berasingan melalui ApplicationPolicy.
     *
     * - Draf / Tidak Lengkap / Pindaan Pra JTICT -> Dihantar (pemohon; tertakluk Mesyuarat JTICT aktif)
     * - Dihantar -> Tidak Lengkap (Urus Setia; ulasan wajib) | Lengkap (Urus Setia)
     * - Lengkap -> Dalam Proses JTICT (Pra JTICT Diterima) | Pindaan Pra JTICT (Ditolak dengan Pindaan)
     *   | Selesai (Pra JTICT Ditolak)
     * - Dalam Proses JTICT -> Selesai (keputusan JTICT Lulus / Tidak Lulus / Kaji Semula direkodkan)
     *
     * @return array<int, self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft, self::NeedsAmendment, self::PreJtictAmendment => [self::Submitted],
            self::Submitted => [self::NeedsAmendment, self::Complete],
            self::Complete => [self::InJtict, self::PreJtictAmendment, self::Closed],
            self::InJtict => [self::Closed],
            self::Closed => [],
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, $this->allowedTransitions(), true);
    }

    /**
     * @return array<int, self>
     */
    public static function editableCases(): array
    {
        return [self::Draft, self::NeedsAmendment, self::PreJtictAmendment];
    }

    /**
     * Status pindaan selepas permohonan dihantar: Tidak Lengkap (PP2) atau Pindaan Pra JTICT (PP4).
     * Kedua-duanya mengikut peraturan mesyuarat PP2 semasa hantar semula dan direkodkan sebagai
     * "Dihantar Semula" dalam sejarah semakan.
     */
    public function isAmendment(): bool
    {
        return $this === self::NeedsAmendment || $this === self::PreJtictAmendment;
    }

    /**
     * Label selamat bagi sebarang nilai tersimpan, termasuk nilai lama / tidak dikenali.
     */
    public static function labelFor(self|string|null $value): string
    {
        if ($value instanceof self) {
            return $value->label();
        }

        if (blank($value)) {
            return '-';
        }

        return self::tryFrom($value)?->label() ?? "Tidak Diketahui ({$value})";
    }

    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case) => [$case->value => $case->label()])
            ->all();
    }
}
