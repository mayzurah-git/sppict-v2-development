{{-- PP1: paparan status PROSES permohonan (label daripada App\Enums\ApplicationStatus).
     Bukan keputusan mesyuarat: "Lengkap" sengaja tidak berwarna hijau "Lulus".
     variant="dot" = titik warna sahaja (kad papan pemuka). Nilai lama / tidak dikenali dipaparkan kelabu. --}}
@props(['status', 'variant' => 'badge'])

@php
    use App\Enums\ApplicationStatus;

    $case = $status instanceof ApplicationStatus ? $status : ApplicationStatus::tryFrom((string) $status);

    $tones = [
        'DRAFT' => ['badge' => 'bg-amber-100 text-amber-800', 'dot' => 'bg-amber-700'],
        'SUBMITTED' => ['badge' => 'bg-blue-100 text-blue-800', 'dot' => 'bg-blue-800'],
        'NEEDS_AMENDMENT' => ['badge' => 'bg-purple-100 text-purple-800', 'dot' => 'bg-purple-800'],
        'COMPLETE' => ['badge' => 'bg-cyan-100 text-cyan-800', 'dot' => 'bg-cyan-800'],
        'IN_JTICT' => ['badge' => 'bg-indigo-100 text-indigo-800', 'dot' => 'bg-indigo-800'],
        'CLOSED' => ['badge' => 'bg-slate-200 text-slate-700', 'dot' => 'bg-slate-600'],
    ];
    $tone = $tones[$case?->value] ?? ['badge' => 'bg-gray-100 text-gray-800', 'dot' => 'bg-gray-500'];
@endphp

@if ($variant === 'dot')
    <span {{ $attributes->merge(['class' => 'w-2.5 h-2.5 rounded-full ' . $tone['dot']]) }} aria-hidden="true"></span>
@else
    <span {{ $attributes->merge(['class' => 'inline-flex px-2.5 py-1 rounded-full text-xs font-bold whitespace-nowrap ' . $tone['badge']]) }}>{{ ApplicationStatus::labelFor($case ?? $status) }}</span>
@endif
