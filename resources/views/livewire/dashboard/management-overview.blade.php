{{-- SP5: Papan Pemuka Pengurusan (interaktif, reka bentuk diluluskan 23/11/2026). Baca sahaja (I.9).
     Warna carta membawa maksud data dan tidak ikut tema peranan; lampu sentiasa disertai ikon & label. --}}
@php
    $theme = \App\Support\RoleTheme::for(auth()->user());
    $money = fn (float $value) => $value >= 1_000_000 ? 'RM ' . number_format($value / 1_000_000, 2) . ' juta' : 'RM ' . number_format($value, 2);
    $card = 'bg-white border border-slate-200 rounded-2xl';
    $statusColors = ['#2a78d6', '#eb6834', '#1baf7a', '#eda100', '#e87ba4', '#008300'];
    $statuses = collect(\App\Enums\ProjectStatus::cases())->values()->map(fn ($status, $i) => [
        'label' => $status->label(),
        'count' => $projectStatus['statuses'][$status->value] ?? 0,
        'color' => $statusColors[$i % count($statusColors)],
    ]);
    $indicators = $projectStatus['indicators'];
    $activeCount = array_sum($indicators);
    $lateCount = $indicators['AMBER'] + $indicators['RED'];
    $lampDefs = [
        'GREEN' => ['label' => 'Mengikut jadual', 'color' => '#15803d', 'icon' => 'm5 12 5 5 9-10'],
        'AMBER' => ['label' => 'Lewat (< 20)', 'color' => '#b45309', 'icon' => 'M12 7v6M12 17h.01'],
        'RED' => ['label' => 'Lewat teruk (≥ 20)', 'color' => '#b91c1c', 'icon' => 'M12 7v6M12 17h.01'],
    ];
    $maxYear = max(1, (int) $byYear->max('total'));
    $maxAgency = max(1, (float) $costByAgency->max($measure === 'count' ? 'count' : 'cost'));
    $outcomes = \App\Services\DashboardStatisticsService::OUTCOMES;
    $columns = ['label' => ['Jabatan/Agensi', 'left'], 'total' => ['Projek', 'right'], 'active' => ['Aktif', 'right'], 'late' => ['Lewat', 'right'], 'average' => ['Purata kemajuan', 'right']];
@endphp

<div class="space-y-5">
    <!-- Kepala: tajuk, status JTICT, penapis tahun & eksport -->
    <section class="flex flex-wrap items-end justify-between gap-x-6 gap-y-3.5">
        <div class="flex flex-col gap-1.5">
            <span class="text-xs font-extrabold uppercase tracking-[0.14em] {{ $theme['eyebrow'] }}">Papan Pemuka Pengurusan</span>
            <h1 class="text-[26px] font-black tracking-tight text-slate-900">Gambaran Keseluruhan Projek ICT</h1>
            @if ($meeting)
                <span class="self-start inline-flex items-center gap-2 pl-2.5 pr-3 py-1 rounded-full bg-green-100 text-green-900 text-[13px] font-bold">
                    <span aria-hidden="true" class="w-2 h-2 rounded-full bg-green-600"></span>
                    Permohonan dibuka untuk {{ $meeting->label() }} · tutup {{ $meeting->submission_closes_at->format('d/m/Y') }}
                </span>
            @else
                <span class="self-start inline-flex items-center gap-2 pl-2.5 pr-3 py-1 rounded-full bg-amber-100 text-amber-900 text-[13px] font-bold">
                    <span aria-hidden="true" class="w-2 h-2 rounded-full bg-amber-600"></span>
                    Tiada Mesyuarat JTICT dibuka
                </span>
            @endif
        </div>
        <div class="flex flex-wrap items-center gap-2.5">
            <div role="group" aria-label="Penapis tahun" class="flex p-[3px] rounded-xl bg-slate-200/80">
                @foreach (['' => 'Semua tahun'] + collect($years)->mapWithKeys(fn ($y) => [(string) $y => (string) $y])->all() as $value => $label)
                    <button type="button" wire:click="setYear('{{ $value }}')" aria-pressed="{{ (string) $year === (string) $value ? 'true' : 'false' }}"
                        @class(['min-h-9 px-3.5 rounded-[9px] text-[13px] font-extrabold transition focus:outline-none focus-visible:ring-2', $theme['focus'],
                            'bg-white text-slate-900 shadow-sm' => (string) $year === (string) $value,
                            'text-slate-600 hover:text-slate-900' => (string) $year !== (string) $value])>{{ $label }}</button>
                @endforeach
            </div>
            @foreach (['PDF' => route('projects.summary.pdf'), 'Excel' => route('projects.summary.excel')] as $label => $href)
                <a href="{{ $href }}" class="inline-flex items-center gap-1.5 h-[42px] px-3.5 rounded-[10px] border border-slate-300 bg-white text-[13px] font-bold text-slate-700 hover:bg-slate-50 transition">
                    <svg aria-hidden="true" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 4v11M7 10l5 5 5-5M5 20h14" /></svg>
                    <span><span class="sr-only">Muat turun Ringkasan Status Kemajuan </span>{{ $label }}</span>
                </a>
            @endforeach
        </div>
    </section>

    <!-- Angka utama -->
    <section aria-label="Angka utama" class="grid grid-cols-[repeat(auto-fit,minmax(220px,1fr))] gap-3.5">
        @foreach ([
            ['Permohonan diterima', number_format($kpi['applications']), $selectedYear ? 'Tahun ' . $selectedYear : 'Semua tahun', 'text-slate-500'],
            ['Lulus JTICT', number_format($kpi['approved']), 'Kadar kelulusan ' . ($kpi['applications'] ? round($kpi['approved'] / $kpi['applications'] * 100) : 0) . '%', 'text-green-700'],
            ['Kos diluluskan', $money($kpi['approvedCost']), $kpi['approved'] ? 'Purata ' . $money($kpi['approvedCost'] / $kpi['approved']) . ' / projek' : 'Tiada projek Lulus JTICT', 'text-slate-500'],
            ['Projek aktif', number_format($activeCount), $lateCount . ' lewat daripada jadual', $lateCount ? 'text-red-700' : 'text-slate-500'],
        ] as [$label, $value, $sub, $subClass])
            <div class="{{ $card }} px-5 py-[18px] flex flex-col gap-1.5">
                <span class="text-[13px] font-bold text-slate-600">{{ $label }}</span>
                <span class="text-[30px] leading-tight font-black tracking-tight tabular-nums text-slate-900">{{ $value }}</span>
                <span class="text-xs font-bold {{ $subClass }}">{{ $sub }}</span>
            </div>
        @endforeach
    </section>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
        <!-- (a)(i) Permohonan mengikut tahun -->
        <section aria-labelledby="stat-permohonan" class="{{ $card }} px-5 py-[18px] flex flex-col gap-3" x-data="{ readout: '' }">
            <div class="flex items-baseline justify-between gap-3">
                <h2 id="stat-permohonan" class="text-base font-extrabold text-slate-900">Statistik Permohonan Projek</h2>
                <span aria-live="polite" class="text-[13px] font-bold text-slate-700" x-text="readout || 'Halakan pada bar'">Halakan pada bar</span>
            </div>
            @if ($byYear->isEmpty())
                <p class="py-10 text-center text-sm text-slate-500">Tiada permohonan.</p>
            @else
                <div class="flex items-end gap-4 h-[180px] px-1.5 border-b border-slate-200">
                    @foreach ($byYear as $row)
                        @php($dim = $selectedYear && (string) $selectedYear !== $row['label'])
                        @php($tip = $row['label'] . ' · ' . $row['total'] . ' permohonan · ' . $row['counts']['approved'] . ' lulus')
                        <button type="button" wire:click="setYear('{{ $row['label'] }}')" aria-label="{{ $tip }}"
                            x-on:mouseenter="readout = @js($tip)" x-on:focus="readout = @js($tip)" x-on:mouseleave="readout = ''" x-on:blur="readout = ''"
                            class="flex-1 h-full flex flex-col justify-end items-center gap-1 focus:outline-none focus-visible:ring-2 rounded {{ $theme['focus'] }}">
                            <span class="text-xs font-extrabold text-slate-700 tabular-nums">{{ $row['total'] }}</span>
                            <span class="w-full max-w-16 flex flex-col gap-0.5" style="height: {{ round($row['total'] / $maxYear * 140) }}px">
                                <span @class(['rounded-t', 'bg-slate-300' => ! $dim, 'bg-slate-200' => $dim]) style="flex: {{ max(0, $row['total'] - $row['counts']['approved']) }}"></span>
                                <span @class(['bg-green-700' => ! $dim, 'bg-green-200' => $dim]) style="flex: {{ $row['counts']['approved'] }}"></span>
                            </span>
                        </button>
                    @endforeach
                </div>
                <div class="flex justify-around text-xs font-bold text-slate-600">
                    @foreach ($byYear as $row)<span class="flex-1 text-center">{{ $row['label'] }}</span>@endforeach
                </div>
                <div class="flex gap-4 text-xs text-slate-600">
                    <span class="inline-flex items-center gap-1.5"><span aria-hidden="true" class="w-2.5 h-2.5 rounded-sm bg-green-700"></span>Lulus JTICT</span>
                    <span class="inline-flex items-center gap-1.5"><span aria-hidden="true" class="w-2.5 h-2.5 rounded-sm bg-slate-300"></span>Lain-lain</span>
                </div>
            @endif
        </section>

        <!-- (b) Projek mengikut status kemajuan & lampu -->
        <section aria-labelledby="stat-status-projek" class="{{ $card }} px-5 py-[18px] flex flex-col gap-3.5" x-data="{ hover: null }">
            <div class="flex items-baseline justify-between gap-3">
                <h2 id="stat-status-projek" class="text-base font-extrabold text-slate-900">Bilangan Projek mengikut Status Kemajuan</h2>
                <span class="text-[13px] font-bold text-slate-700">{{ $projectStatus['total'] }} projek</span>
            </div>
            @if ($projectStatus['total'] > 0)
                <div class="flex gap-0.5 h-7 rounded-md overflow-hidden" aria-hidden="true">
                    @foreach ($statuses as $i => $status)
                        @if ($status['count'] > 0)
                            <span class="transition-opacity" style="flex: {{ $status['count'] }}; background: {{ $status['color'] }}" x-on:mouseenter="hover = {{ $i }}" x-on:mouseleave="hover = null" :style="hover !== null && hover !== {{ $i }} ? 'opacity: .35' : ''"></span>
                        @endif
                    @endforeach
                </div>
            @endif
            <ul class="grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-1.5">
                @foreach ($statuses as $i => $status)
                    <li class="flex items-center gap-2.5 px-2.5 py-1.5 rounded-[10px]" :class="hover === {{ $i }} ? 'bg-slate-100' : ''" x-on:mouseenter="hover = {{ $i }}" x-on:mouseleave="hover = null">
                        <span aria-hidden="true" class="w-3 h-3 rounded" style="background: {{ $status['color'] }}"></span>
                        <span class="flex-1 text-sm font-semibold text-slate-800">{{ $status['label'] }}</span>
                        <span class="text-[15px] font-extrabold tabular-nums">{{ $status['count'] }}</span>
                        <span class="w-9 text-right text-xs text-slate-500">{{ $projectStatus['total'] ? round($status['count'] / $projectStatus['total'] * 100) : 0 }}%</span>
                    </li>
                @endforeach
            </ul>

            <div class="pt-3 border-t border-slate-100 flex flex-col gap-2">
                <p class="text-[13px] font-extrabold text-slate-900">Projek aktif mengikut lampu <span class="font-semibold text-slate-500">· klik Kuning/Merah untuk tapis senarai lewat</span></p>
                <div class="grid grid-cols-3 gap-2">
                    @foreach ($lampDefs as $key => $def)
                        @php($selected = $lamp === $key)
                        @if ($key === 'GREEN')
                            <div class="flex items-center gap-2 min-h-12 px-2.5 py-1.5 rounded-xl border border-slate-200">
                        @else
                            <button type="button" wire:click="setLamp('{{ $key }}')" aria-pressed="{{ $selected ? 'true' : 'false' }}"
                                @class(['flex items-center gap-2 min-h-12 px-2.5 py-1.5 rounded-xl text-left transition focus:outline-none focus-visible:ring-2', $theme['focus'], 'border-2 bg-slate-50' => $selected, 'border border-slate-200 hover:bg-slate-50' => ! $selected])
                                @if ($selected) style="border-color: {{ $def['color'] }}" @endif>
                        @endif
                            <span aria-hidden="true" class="w-[26px] h-[26px] shrink-0 rounded-full text-white flex items-center justify-center" style="background: {{ $def['color'] }}">
                                <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="{{ $def['icon'] }}" /></svg>
                            </span>
                            <span class="flex flex-col leading-tight">
                                <span class="text-lg font-black tabular-nums">{{ $indicators[$key] }}</span>
                                <span class="text-xs font-bold text-slate-600">{{ \App\Enums\ScheduleStatus::indicatorLabel($key) }} · {{ $def['label'] }}</span>
                            </span>
                        @if ($key === 'GREEN')
                            </div>
                        @else
                            </button>
                        @endif
                    @endforeach
                </div>
                @if ($indicators['NONE'] > 0)
                    <p class="text-xs text-slate-500">{{ $indicators['NONE'] }} projek aktif belum mempunyai data rancangan.</p>
                @endif
            </div>
        </section>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
        <!-- (d) Projek Lulus JTICT mengikut agensi -->
        <section aria-labelledby="stat-kos" class="{{ $card }} px-5 py-[18px] flex flex-col gap-3.5">
            <div class="flex flex-wrap items-center justify-between gap-2.5">
                <h2 id="stat-kos" class="text-base font-extrabold text-slate-900">Statistik Projek mengikut Kos Jabatan/Agensi</h2>
                <div role="group" aria-label="Ukuran" class="flex p-[3px] rounded-[10px] bg-slate-100">
                    @foreach (['cost' => 'Kos (RM)', 'count' => 'Bilangan'] as $value => $label)
                        <button type="button" wire:click="setMeasure('{{ $value }}')" aria-pressed="{{ $measure === $value ? 'true' : 'false' }}"
                            @class(['min-h-8 px-3 rounded-lg text-xs font-extrabold transition focus:outline-none focus-visible:ring-2', $theme['focus'], 'bg-white text-slate-900 shadow-sm' => $measure === $value, 'text-slate-600' => $measure !== $value])>{{ $label }}</button>
                    @endforeach
                </div>
            </div>
            @forelse ($costByAgency as $row)
                @php($value = $measure === 'count' ? $row['count'] : $row['cost'])
                <div class="grid grid-cols-[72px_1fr_auto] items-center gap-2.5" title="{{ $row['name'] }}">
                    <span class="text-[13px] font-extrabold text-slate-700">{{ $row['label'] }}</span>
                    <span class="h-4 rounded-r bg-slate-100 overflow-hidden" aria-hidden="true"><span class="block h-4 rounded-r bg-slate-700" style="width: {{ round($value / $maxAgency * 100, 1) }}%"></span></span>
                    <span class="text-[13px] font-bold tabular-nums text-right">{{ $measure === 'count' ? $row['count'] . ' projek' : $money($row['cost']) }}</span>
                </div>
            @empty
                <p class="py-8 text-center text-sm text-slate-500">Tiada projek Lulus JTICT.</p>
            @endforelse
            @if ($costByAgency->isNotEmpty())
                <p class="pt-2 border-t border-slate-100 flex justify-between text-[13px] font-bold">
                    <span>Jumlah · {{ $costByAgency->sum('count') }} projek</span>
                    <span class="tabular-nums">{{ $money((float) $costByAgency->sum('cost')) }}</span>
                </p>
            @endif
        </section>

        <!-- (e) Projek lewat -->
        <section aria-labelledby="stat-lewat" class="{{ $card }} px-5 py-[18px] flex flex-col gap-3">
            <div class="flex items-baseline justify-between gap-3">
                <h2 id="stat-lewat" class="text-base font-extrabold text-slate-900">Senarai Projek Lewat ({{ $lateTotal }})
                    <span class="font-semibold text-slate-500">· {{ $lamp === '' ? 'paling lewat dahulu' : 'lampu ' . \App\Enums\ScheduleStatus::indicatorLabel($lamp) }}</span></h2>
                <a href="{{ route('projects.index') }}" class="shrink-0 text-[13px] font-bold hover:underline {{ $theme['link'] }}">Semua projek →</a>
            </div>
            <ul class="flex flex-col">
                @forelse ($lateProjects->take(8) as $project)
                    @php($indicator = \App\Enums\ScheduleStatus::indicatorFor((float) $project->variance_percent))
                    @php($color = $lampDefs[$indicator]['color'] ?? '#64748b')
                    <li class="flex items-center gap-3 py-2.5 border-t border-slate-100" wire:key="lewat-{{ $project->id }}">
                        <span aria-hidden="true" class="w-2.5 h-2.5 shrink-0 rounded-full" style="background: {{ $color }}"></span>
                        <span class="flex-1 min-w-0 flex flex-col gap-1">
                            <a href="{{ route('projects.show', $project->uuid) }}" class="text-sm font-bold text-slate-900 hover:underline break-words">
                                {{ $project->application?->title ?? ($project->application?->reference_number ?? $project->legacy_reference ?? '-') }}
                                <span class="text-xs font-semibold text-slate-500" title="{{ $project->agency?->name }}">· {{ $project->agency?->code ?? '-' }}</span>
                            </a>
                            <span class="relative h-1.5 rounded-full bg-slate-100" aria-hidden="true">
                                <span class="absolute inset-y-0 left-0 rounded-full" style="width: {{ min(100, (float) $project->actual_progress_percent) }}%; background: {{ $color }}"></span>
                                <span class="absolute -top-[3px] w-0.5 h-3 bg-slate-700" style="left: {{ min(100, (float) $project->planned_progress_percent) }}%"></span>
                            </span>
                        </span>
                        <span class="shrink-0 flex flex-col items-end leading-tight">
                            <span class="text-sm font-black text-red-700 tabular-nums">{{ sprintf('%+.2f', (float) $project->variance_percent) }}</span>
                            <span class="text-[11px] text-slate-500 tabular-nums">{{ number_format((float) $project->actual_progress_percent, 0) }}% / {{ number_format((float) $project->planned_progress_percent, 0) }}%</span>
                            <span class="sr-only">Lampu {{ \App\Enums\ScheduleStatus::indicatorLabel($indicator) }}</span>
                        </span>
                    </li>
                @empty
                    <li class="py-8 text-center text-sm text-slate-500">{{ $lateTotal ? 'Tiada projek lewat bagi lampu ini.' : 'Tiada projek lewat.' }}</li>
                @endforelse
            </ul>
            <p class="text-xs text-slate-500">Bar: kemajuan sebenar · garis: dirancang · angka: varians.</p>
        </section>
    </div>

    <!-- (c) Ringkasan mengikut Jabatan/Agensi -->
    <section aria-labelledby="stat-ringkasan-agensi" class="{{ $card }} overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-100 flex flex-wrap items-baseline justify-between gap-2">
            <h2 id="stat-ringkasan-agensi" class="text-base font-extrabold text-slate-900">Ringkasan Projek mengikut Jabatan/Agensi</h2>
            <span class="text-xs text-slate-500">Klik tajuk lajur untuk susun · lewat & purata bagi projek aktif</span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full min-w-[720px] text-sm">
                <thead class="bg-slate-50 text-xs text-slate-600">
                    <tr>
                        @foreach ($columns as $key => [$label, $align])
                            <th scope="col" class="px-4 {{ $align === 'right' ? 'text-right' : 'text-left' }}" aria-sort="{{ $sort === $key ? ($direction === 'asc' ? 'ascending' : 'descending') : 'none' }}">
                                <button type="button" wire:click="sortBy('{{ $key }}')" class="min-h-10 font-extrabold hover:text-slate-900">{{ $label }}{{ $sort === $key ? ($direction === 'asc' ? ' ↑' : ' ↓') : '' }}</button>
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @forelse ($agencySummary as $row)
                        <tr class="border-t border-slate-100">
                            <th scope="row" class="px-4 py-3 text-left font-bold text-slate-900">{{ $row['name'] ?: $row['label'] }} <span class="text-xs font-semibold text-slate-500">({{ $row['label'] }})</span></th>
                            <td class="px-4 py-3 text-right tabular-nums">{{ $row['total'] }}</td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ $row['active'] }}</td>
                            <td @class(['px-4 py-3 text-right tabular-nums font-bold', 'text-red-700' => $row['late'] > 0, 'text-slate-500' => $row['late'] === 0])>{{ $row['late'] }}</td>
                            <td class="px-4 py-3">
                                @if ($row['average'] === null)
                                    <span class="block text-right text-slate-500">-</span>
                                @else
                                    <span class="flex items-center justify-end gap-2">
                                        <span class="w-24 h-1.5 rounded-full bg-slate-100 overflow-hidden" aria-hidden="true"><span class="block h-full bg-slate-700" style="width: {{ min(100, $row['average']) }}%"></span></span>
                                        <span class="w-14 text-right font-bold tabular-nums">{{ number_format($row['average'], 2) }}%</span>
                                    </span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-8 text-center text-slate-500">Tiada projek dalam pemantauan.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <!-- (a)(ii)(iii) Butiran permohonan mengikut agensi / kategori -->
    <section aria-labelledby="stat-butiran" class="{{ $card }} overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-100 flex flex-wrap items-center justify-between gap-2.5">
            <h2 id="stat-butiran" class="text-base font-extrabold text-slate-900">Butiran keputusan permohonan{{ $selectedYear ? " ({$selectedYear})" : '' }}</h2>
            <div role="group" aria-label="Pecahan" class="flex p-[3px] rounded-[10px] bg-slate-100">
                @foreach (['agency' => 'Mengikut Jabatan/Agensi', 'category' => 'Mengikut Kategori Permohonan'] as $value => $label)
                    <button type="button" wire:click="setBreakdown('{{ $value }}')" aria-pressed="{{ $breakdown === $value ? 'true' : 'false' }}"
                        @class(['min-h-8 px-3 rounded-lg text-xs font-extrabold transition focus:outline-none focus-visible:ring-2', $theme['focus'], 'bg-white text-slate-900 shadow-sm' => $breakdown === $value, 'text-slate-600' => $breakdown !== $value])>{{ $label }}</button>
                @endforeach
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full min-w-[720px] text-sm">
                <thead class="bg-slate-50 text-xs text-slate-600">
                    <tr>
                        <th scope="col" class="px-4 py-2.5 text-left font-extrabold">{{ $breakdown === 'category' ? 'Kategori' : 'Jabatan/Agensi' }}</th>
                        @foreach ($outcomes as $label)
                            <th scope="col" class="px-4 py-2.5 text-right font-extrabold">{{ $label }}</th>
                        @endforeach
                        <th scope="col" class="px-4 py-2.5 text-right font-extrabold">Jumlah</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($breakdownRows as $row)
                        <tr class="border-t border-slate-100">
                            <th scope="row" class="px-4 py-2.5 text-left font-bold text-slate-900">{{ $row['label'] }}</th>
                            @foreach (array_keys($outcomes) as $key)
                                <td @class(['px-4 py-2.5 text-right tabular-nums', 'text-slate-400' => $row['counts'][$key] === 0])>{{ $row['counts'][$key] }}</td>
                            @endforeach
                            <td class="px-4 py-2.5 text-right tabular-nums font-extrabold">{{ $row['total'] }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="{{ count($outcomes) + 2 }}" class="px-4 py-8 text-center text-slate-500">Tiada permohonan.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
