<?php

namespace App\Enums;

/**
 * PP4: peringkat keputusan yang direkodkan dalam `application_decisions`.
 * Peringkat ditentukan oleh pelayan (daripada kod keputusan), bukan input pengguna.
 */
enum DecisionStage: string
{
    /** Keputusan Pra JTICT oleh Urus Setia selepas semakan kelengkapan (PP2) Lengkap. */
    case PreJtict = 'PRE_JTICT';

    /** Keputusan Mesyuarat JTICT, direkodkan oleh Urus Setia selepas mesyuarat. */
    case Jtict = 'JTICT';

    public function label(): string
    {
        return match ($this) {
            self::PreJtict => 'Pra JTICT',
            self::Jtict => 'JTICT',
        };
    }

    /**
     * Keputusan yang sah bagi peringkat ini.
     *
     * @return array<int, ApplicationDecision>
     */
    public function decisions(): array
    {
        return ApplicationDecision::forStage($this);
    }

    /**
     * Status proses yang mesti dimiliki permohonan sebelum keputusan peringkat ini boleh direkodkan.
     */
    public function requiredStatus(): ApplicationStatus
    {
        return match ($this) {
            self::PreJtict => ApplicationStatus::Complete,
            self::Jtict => ApplicationStatus::InJtict,
        };
    }
}
