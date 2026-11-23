<?php

namespace App\Enums;

/**
 * SP4 (keputusan I.5, audit SP0 L.8): status jadual diterbitkan daripada varians (sebenar − dirancang),
 * dengan lampu RAG untuk papan pemuka.
 */
enum ScheduleStatus: string
{
    case Ahead = 'AHEAD';
    case OnTrack = 'ON_TRACK';
    case Late = 'LATE';

    /** I.5: Merah apabila varians <= −20 mata peratus. */
    public const RED_THRESHOLD = -20.0;

    public static function fromVariance(float $variance): self
    {
        return match (true) {
            $variance > 0 => self::Ahead,
            $variance == 0.0 => self::OnTrack,
            default => self::Late,
        };
    }

    /**
     * Lampu RAG (nilai kolum projects.status_indicator): Hijau varians >= 0, Kuning −0.01 hingga −19.99,
     * Merah <= −20.
     */
    public static function indicatorFor(float $variance): string
    {
        return match (true) {
            $variance >= 0 => 'GREEN',
            $variance > self::RED_THRESHOLD => 'AMBER',
            default => 'RED',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Ahead => 'Mendahului Jadual',
            self::OnTrack => 'Mengikut Jadual',
            self::Late => 'Lewat',
        };
    }

    public static function indicatorLabel(string $indicator): string
    {
        return match ($indicator) {
            'GREEN' => 'Hijau',
            'AMBER' => 'Kuning',
            'RED' => 'Merah',
            default => '-',
        };
    }

    public static function indicatorClasses(string $indicator): string
    {
        return match ($indicator) {
            'GREEN' => 'bg-emerald-50 text-emerald-800 ring-emerald-200',
            'AMBER' => 'bg-amber-50 text-amber-800 ring-amber-200',
            'RED' => 'bg-red-50 text-red-800 ring-red-200',
            default => 'bg-slate-100 text-slate-700 ring-slate-200',
        };
    }
}
