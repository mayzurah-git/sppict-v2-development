@php
    $outcomes = \App\Services\DashboardStatisticsService::OUTCOMES;
    $th = 'p-3 text-left font-bold';
    $thNum = 'p-3 text-right font-bold';
    $td = 'p-3';
    $tdNum = 'p-3 text-right tabular-nums';
    $card = 'bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden';
    $money = fn (float $value) => 'RM ' . number_format($value, 2);
@endphp

<div class="space-y-6">
    @if ($full)
        {{-- (a) Statistik permohonan projek --}}
        <section class="{{ $card }}" aria-labelledby="stat-permohonan">
            <div class="p-4 border-b border-slate-100 flex flex-col md:flex-row md:items-end justify-between gap-3">
                <div>
                    <h3 id="stat-permohonan" class="text-sm font-bold text-slate-800">Statistik Permohonan Projek</h3>
                    <p class="text-[11px] text-slate-500 mt-0.5">Permohonan yang telah dihantar (tidak termasuk Draf). Tahun mengikut tahun permohonan dihantar.</p>
                </div>
                <div>
                    <label for="tahun-statistik" class="sr-only">Tahun</label>
                    <select id="tahun-statistik" wire:model.live="year" class="text-xs border-slate-200 rounded-lg shadow-sm focus:border-sky-700 focus:ring-sky-700/20">
                        <option value="">Semua tahun</option>
                        @foreach ($years as $option)
                            <option value="{{ $option }}">{{ $option }}</option>
                        @endforeach
                    </select>
                    <p class="text-[11px] text-slate-500 mt-1">Penapis tahun: mengikut Jabatan/Agensi, Kategori dan Kos.</p>
                </div>
            </div>

            @foreach ([
                ['Mengikut Tahun', $byYear, 'Tahun'],
                ['Mengikut Jabatan/Agensi' . ($selectedYear ? " ({$selectedYear})" : ''), $byAgency, 'Jabatan/Agensi'],
                ['Mengikut Kategori Permohonan' . ($selectedYear ? " ({$selectedYear})" : ''), $byCategory, 'Kategori'],
            ] as [$title, $rows, $column])
                <div class="border-b border-slate-100 last:border-b-0">
                    <h4 class="px-4 pt-4 text-xs font-bold text-slate-700">{{ $title }}</h4>
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[720px] text-xs">
                            <thead class="text-slate-600 border-b border-slate-100">
                                <tr>
                                    <th scope="col" class="{{ $th }}">{{ $column }}</th>
                                    @foreach ($outcomes as $label)
                                        <th scope="col" class="{{ $thNum }}">{{ $label }}</th>
                                    @endforeach
                                    <th scope="col" class="{{ $thNum }}">Jumlah</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @forelse ($rows as $row)
                                    <tr>
                                        <th scope="row" class="{{ $td }} text-left font-semibold text-slate-800">{{ $row['label'] }}</th>
                                        @foreach (array_keys($outcomes) as $key)
                                            <td class="{{ $tdNum }} {{ $row['counts'][$key] === 0 ? 'text-slate-400' : '' }}">{{ $row['counts'][$key] }}</td>
                                        @endforeach
                                        <td class="{{ $tdNum }} font-bold">{{ $row['total'] }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="{{ count($outcomes) + 2 }}" class="p-4 text-center text-slate-500">Tiada permohonan.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            @endforeach
        </section>
    @endif

    {{-- (b) Projek mengikut status kemajuan --}}
    <section class="{{ $card }} p-4 space-y-4" aria-labelledby="stat-status-projek">
        <div>
            <h3 id="stat-status-projek" class="text-sm font-bold text-slate-800">Bilangan Projek mengikut Status Kemajuan</h3>
            <p class="text-[11px] text-slate-500 mt-0.5">Jumlah projek dalam pemantauan: {{ $projectStatus['total'] }}</p>
        </div>
        <dl class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-6 gap-3">
            @foreach (\App\Enums\ProjectStatus::cases() as $status)
                <div class="p-3 rounded-xl bg-slate-50">
                    <dt class="text-[11px] font-semibold text-slate-500">{{ $status->label() }}</dt>
                    <dd class="text-2xl font-black text-slate-900 tabular-nums">{{ $projectStatus['statuses'][$status->value] }}</dd>
                </div>
            @endforeach
        </dl>
        <div>
            <h4 class="text-xs font-bold text-slate-700">Projek aktif mengikut lampu</h4>
            <dl class="mt-2 grid grid-cols-2 md:grid-cols-4 gap-3">
                @foreach (['GREEN' => 'Hijau (tidak lewat)', 'AMBER' => 'Kuning (lewat < 20)', 'RED' => 'Merah (lewat ≥ 20)', 'NONE' => 'Tiada data rancangan'] as $key => $label)
                    <div class="p-3 rounded-xl ring-1 {{ \App\Enums\ScheduleStatus::indicatorClasses($key) }}">
                        <dt class="text-[11px] font-semibold">{{ $label }}</dt>
                        <dd class="text-2xl font-black tabular-nums">{{ $projectStatus['indicators'][$key] }}</dd>
                    </div>
                @endforeach
            </dl>
        </div>
    </section>

    {{-- (c) Ringkasan mengikut Jabatan/Agensi --}}
    <section class="{{ $card }}" aria-labelledby="stat-ringkasan-agensi">
        <div class="p-4 border-b border-slate-100">
            <h3 id="stat-ringkasan-agensi" class="text-sm font-bold text-slate-800">Ringkasan Projek mengikut Jabatan/Agensi</h3>
            <p class="text-[11px] text-slate-500 mt-0.5">Purata kemajuan dan bilangan lewat dikira bagi projek aktif (Dalam Pelaksanaan dan Menunggu Pengesahan Penutupan).</p>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full min-w-[720px] text-xs">
                <thead class="text-slate-600 border-b border-slate-100">
                    <tr>
                        <th scope="col" class="{{ $th }}">Jabatan/Agensi</th>
                        <th scope="col" class="{{ $thNum }}">Jumlah Projek</th>
                        <th scope="col" class="{{ $thNum }}">Aktif</th>
                        <th scope="col" class="{{ $thNum }}">Selesai</th>
                        <th scope="col" class="{{ $thNum }}">Ditutup</th>
                        <th scope="col" class="{{ $thNum }}">Lewat</th>
                        <th scope="col" class="{{ $thNum }}">Purata Kemajuan Sebenar</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($agencySummary as $row)
                        <tr>
                            <th scope="row" class="{{ $td }} text-left font-semibold text-slate-800" title="{{ $row['name'] }}">{{ $row['label'] }}</th>
                            <td class="{{ $tdNum }}">{{ $row['total'] }}</td>
                            <td class="{{ $tdNum }}">{{ $row['active'] }}</td>
                            <td class="{{ $tdNum }}">{{ $row['completed'] }}</td>
                            <td class="{{ $tdNum }}">{{ $row['closed'] }}</td>
                            <td class="{{ $tdNum }} {{ $row['late'] > 0 ? 'font-bold text-red-700' : '' }}">{{ $row['late'] }}</td>
                            <td class="{{ $tdNum }}">{{ $row['average'] === null ? '-' : number_format($row['average'], 2) . '%' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="p-4 text-center text-slate-500">Tiada projek dalam pemantauan.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    @if ($full)
        {{-- (d) Kos projek mengikut Jabatan --}}
        <section class="{{ $card }}" aria-labelledby="stat-kos">
            <div class="p-4 border-b border-slate-100">
                <h3 id="stat-kos" class="text-sm font-bold text-slate-800">Statistik Projek mengikut Kos Jabatan/Agensi{{ $selectedYear ? " ({$selectedYear})" : '' }}</h3>
                <p class="text-[11px] text-slate-500 mt-0.5">Projek Lulus JTICT. Kos = Jumlah Keseluruhan permohonan yang diluluskan (termasuk SST).</p>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[600px] text-xs">
                    <thead class="text-slate-600 border-b border-slate-100">
                        <tr>
                            <th scope="col" class="{{ $th }}">Jabatan/Agensi</th>
                            <th scope="col" class="{{ $thNum }}">Bilangan Projek</th>
                            <th scope="col" class="{{ $thNum }}">Jumlah Kos</th>
                            <th scope="col" class="{{ $th }} w-1/3"><span class="sr-only">Perbandingan</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @php
                            $maxCost = max(1, (float) $costByAgency->max('cost'));
                        @endphp
                        @forelse ($costByAgency as $row)
                            <tr>
                                <th scope="row" class="{{ $td }} text-left font-semibold text-slate-800" title="{{ $row['name'] }}">{{ $row['label'] }}</th>
                                <td class="{{ $tdNum }}">{{ $row['count'] }}</td>
                                <td class="{{ $tdNum }} font-bold">{{ $money($row['cost']) }}</td>
                                <td class="{{ $td }}"><span class="block h-2 rounded-full bg-sky-700" style="width: {{ round($row['cost'] / $maxCost * 100, 1) }}%" aria-hidden="true"></span></td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="p-4 text-center text-slate-500">Tiada projek Lulus JTICT.</td></tr>
                        @endforelse
                    </tbody>
                    @if ($costByAgency->isNotEmpty())
                        <tfoot class="border-t border-slate-200">
                            <tr>
                                <th scope="row" class="{{ $td }} text-left font-bold">Jumlah</th>
                                <td class="{{ $tdNum }} font-bold">{{ $costByAgency->sum('count') }}</td>
                                <td class="{{ $tdNum }} font-black">{{ $money((float) $costByAgency->sum('cost')) }}</td>
                                <td></td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>
        </section>
    @endif

    {{-- (e) Senarai projek lewat --}}
    <section class="{{ $card }}" aria-labelledby="stat-lewat">
        <div class="p-4 border-b border-slate-100 flex flex-col md:flex-row md:items-end justify-between gap-2">
            <div>
                <h3 id="stat-lewat" class="text-sm font-bold text-slate-800">Senarai Projek Lewat ({{ $lateProjects->count() }})</h3>
                <p class="text-[11px] text-slate-500 mt-0.5">Projek aktif dengan kemajuan sebenar di belakang jadual, paling lewat dahulu.</p>
            </div>
            <a href="{{ route('projects.summary.pdf') }}" class="text-xs font-bold text-sky-800 hover:underline">Muat turun Ringkasan Status Kemajuan (PDF)</a>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full min-w-[760px] text-xs">
                <thead class="text-slate-600 border-b border-slate-100">
                    <tr>
                        <th scope="col" class="{{ $th }}">No. Rujukan</th>
                        <th scope="col" class="{{ $th }}">Nama Projek</th>
                        <th scope="col" class="{{ $th }}">Jabatan/Agensi</th>
                        <th scope="col" class="{{ $thNum }}">Sebenar</th>
                        <th scope="col" class="{{ $thNum }}">Dirancang</th>
                        <th scope="col" class="{{ $thNum }}">Varians</th>
                        <th scope="col" class="{{ $th }}">Lampu</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($lateProjects as $project)
                        @php
                            $indicator = \App\Enums\ScheduleStatus::indicatorFor((float) $project->variance_percent);
                        @endphp
                        <tr wire:key="lewat-{{ $project->id }}">
                            <td class="{{ $td }} font-bold whitespace-nowrap tabular-nums"><a href="{{ route('projects.show', $project->uuid) }}" class="text-sky-800 hover:underline">{{ $project->application?->reference_number ?? $project->legacy_reference ?? '-' }}</a></td>
                            <td class="{{ $td }} max-w-xs"><span class="block break-words">{{ $project->application?->title ?? '-' }}</span></td>
                            <td class="{{ $td }}" title="{{ $project->agency?->name }}">{{ $project->agency?->code ?? '-' }}</td>
                            <td class="{{ $tdNum }}">{{ number_format((float) $project->actual_progress_percent, 2) }}%</td>
                            <td class="{{ $tdNum }}">{{ number_format((float) $project->planned_progress_percent, 2) }}%</td>
                            <td class="{{ $tdNum }} font-bold text-red-700">{{ sprintf('%+.2f', (float) $project->variance_percent) }}</td>
                            <td class="{{ $td }}"><span class="inline-flex px-2 py-0.5 rounded-md text-[11px] font-bold ring-1 {{ \App\Enums\ScheduleStatus::indicatorClasses($indicator) }}">{{ \App\Enums\ScheduleStatus::indicatorLabel($indicator) }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="p-4 text-center text-slate-500">Tiada projek lewat.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
