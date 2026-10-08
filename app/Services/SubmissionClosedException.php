<?php

namespace App\Services;

use App\Models\Application;
use RuntimeException;

/**
 * Tiada Mesyuarat JTICT yang dibuka semasa permohonan dihantar.
 * Permohonan telah disimpan sebagai draf sebelum pengecualian ini dilontar.
 */
class SubmissionClosedException extends RuntimeException
{
    public function __construct(public readonly Application $application)
    {
        parent::__construct('Tempoh permohonan Mesyuarat JTICT telah ditutup. Permohonan anda disimpan sebagai draf dan boleh dihantar apabila Mesyuarat JTICT seterusnya dibuka.');
    }
}
