<?php

namespace App\Enums;

/**
 * SP1: sumber rekod projek (disimpan dalam `projects.source`).
 */
enum ProjectSource: string
{
    /** Projek dimulakan dalam SPPICT v2 daripada permohonan Lulus JTICT. */
    case V2 = 'V2';

    /** Projek dipindahkan daripada SPPICT v1 (Bank Projek); tiada permohonan v2. */
    case V1 = 'V1';

    public function label(): string
    {
        return match ($this) {
            self::V2 => 'SPPICT v2',
            self::V1 => 'SPPICT v1 (Bank Projek)',
        };
    }
}
