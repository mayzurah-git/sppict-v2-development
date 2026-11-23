<?php

namespace App\Support\Progress;

use App\Enums\ScheduleStatus;

/**
 * SP4: hasil pengiraan kemajuan (projek atau fasa) pada satu tarikh penilaian. Nilai dalam peratus,
 * dibundarkan 2 tempat perpuluhan. planned / variance null apabila jadual tidak lengkap (contoh rekod
 * sejarah v1 tanpa tarikh sasaran).
 */
final class ProgressResult
{
    /**
     * @param  array<int, ProgressResult>  $phases  hasil setiap fasa, kunci nombor fasa (kosong bagi hasil fasa)
     */
    public function __construct(
        public readonly float $actual,
        public readonly ?float $planned,
        public readonly array $phases = [],
    ) {
    }

    public function variance(): ?float
    {
        return $this->planned === null ? null : round($this->actual - $this->planned, 2);
    }

    public function status(): ?ScheduleStatus
    {
        $variance = $this->variance();

        return $variance === null ? null : ScheduleStatus::fromVariance($variance);
    }

    public function indicator(): ?string
    {
        $variance = $this->variance();

        return $variance === null ? null : ScheduleStatus::indicatorFor($variance);
    }
}
