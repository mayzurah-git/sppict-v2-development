<?php

namespace App\Services;

use App\Enums\ApplicationStatus;
use App\Models\Application;
use RuntimeException;

/**
 * Tiada Mesyuarat JTICT yang dibuka semasa permohonan dihantar.
 * Perubahan telah disimpan (tanpa dihantar) sebelum pengecualian ini dilontar.
 */
class SubmissionClosedException extends RuntimeException
{
    public function __construct(public readonly Application $application)
    {
        $status = $application->statusEnum();

        // PP4: Pindaan Pra JTICT menggunakan mesej penghantaran semula yang sama dengan label statusnya.
        parent::__construct($status?->isAmendment()
            ? "Tiada Mesyuarat JTICT aktif yang layak untuk penghantaran semula. Pindaan anda telah disimpan; permohonan kekal {$status->label()} dan boleh dihantar semula apabila Mesyuarat JTICT dibuka."
            : 'Tempoh permohonan Mesyuarat JTICT telah ditutup. Permohonan anda disimpan sebagai draf dan boleh dihantar apabila Mesyuarat JTICT seterusnya dibuka.');
    }
}
