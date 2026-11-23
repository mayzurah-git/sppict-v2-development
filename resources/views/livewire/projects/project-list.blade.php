<div class="max-w-7xl mx-auto p-6 space-y-6">
    <div class="flex flex-col md:flex-row justify-between md:items-center gap-4 bg-white p-6 rounded-2xl shadow-sm border border-slate-100">
        <div>
            <h2 class="text-xl font-black text-slate-900">Pemantauan Projek</h2>
            <p class="text-xs text-slate-500 mt-0.5">Projek yang telah Lulus JTICT dan dipantau kemajuannya.</p>
            @unless (auth()->user()->hasRole('pengguna_biasa'))
                <p class="text-xs text-slate-500 mt-0.5">Paparan baca sahaja. Kemajuan dikemas kini oleh Pegawai Utama / Pegawai Kedua projek.</p>
            @endunless
        </div>
        <div class="flex flex-wrap gap-2">
            {{-- SP5: eksport A mengikut penapis semasa; eksport C (Urus Setia / Pengurusan) --}}
            <a href="{{ route('projects.export.list', array_filter(['search' => $search, 'status' => $status, 'agency' => $agency])) }}" class="px-4 py-2 bg-white border border-slate-300 hover:bg-slate-50 text-slate-700 text-xs font-bold rounded-xl transition">Eksport Senarai (Excel)</a>
            @can('viewStatistics', \App\Models\Project::class)
                <a href="{{ route('projects.summary.pdf', array_filter(['agency' => $agency])) }}" class="px-4 py-2 bg-white border border-slate-300 hover:bg-slate-50 text-slate-700 text-xs font-bold rounded-xl transition">Ringkasan Status (PDF)</a>
                <a href="{{ route('projects.summary.excel', array_filter(['agency' => $agency])) }}" class="px-4 py-2 bg-white border border-slate-300 hover:bg-slate-50 text-slate-700 text-xs font-bold rounded-xl transition">Ringkasan Status (Excel)</a>
            @endcan
            <a href="{{ route('dashboard') }}" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold rounded-xl transition">← Kembali ke Dashboard</a>
        </div>
    </div>

    @if (session()->has('message'))
        <div class="p-3 bg-emerald-50 border border-emerald-200 text-emerald-700 text-xs font-semibold rounded-xl">{{ session('message') }}</div>
    @endif
    @if (session()->has('error'))
        <div class="p-3 bg-red-50 border border-red-200 text-red-700 text-xs font-semibold rounded-xl">{{ session('error') }}</div>
    @endif

    <div class="bg-white p-4 rounded-2xl shadow-sm border border-slate-100 flex flex-col md:flex-row gap-3">
        <label for="carian-projek" class="sr-only">Cari projek</label>
        <input id="carian-projek" type="search" wire:model.live.debounce.300ms="search" placeholder="Cari No. Rujukan / Nama Projek…"
            class="w-full md:max-w-sm text-xs border-slate-200 rounded-xl shadow-sm focus:border-indigo-700 focus:ring-indigo-700/20">
        <label for="penapis-status" class="sr-only">Status projek</label>
        <select id="penapis-status" wire:model.live="status" class="w-full md:max-w-xs text-xs border-slate-200 rounded-xl shadow-sm focus:border-indigo-700 focus:ring-indigo-700/20">
            <option value="">Semua Status</option>
            @foreach (\App\Enums\ProjectStatus::options() as $value => $label)
                <option value="{{ $value }}">{{ $label }}</option>
            @endforeach
        </select>
        @if ($allAgencies)
            <label for="penapis-agensi" class="sr-only">Jabatan/Agensi</label>
            <select id="penapis-agensi" wire:model.live="agency" class="w-full md:max-w-xs text-xs border-slate-200 rounded-xl shadow-sm focus:border-indigo-700 focus:ring-indigo-700/20">
                <option value="">Semua Jabatan/Agensi</option>
                @foreach ($agencies as $option)
                    <option value="{{ $option->id }}">{{ $option->code }} – {{ $option->name }}</option>
                @endforeach
            </select>
        @endif
    </div>

    @if ($awaiting->isNotEmpty())
        <section aria-labelledby="bahagian-belum-dipantau" class="bg-white rounded-2xl shadow-sm border border-amber-200 overflow-hidden">
            <div class="p-4 border-b border-slate-100">
                <h3 id="bahagian-belum-dipantau" class="text-sm font-bold text-slate-800">Lulus JTICT – Belum Dimulakan Pemantauan ({{ $awaiting->count() }})</h3>
                <p class="text-xs text-slate-500 mt-0.5">Pegawai Utama atau Pegawai Kedua projek perlu memulakan pemantauan dan menetapkan jadual projek.</p>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[760px] text-left text-xs">
                    <thead class="bg-slate-50 text-slate-600 font-bold border-b border-slate-100">
                        <tr>
                            <th scope="col" class="p-3">No. Rujukan</th>
                            <th scope="col" class="p-3">Nama Projek</th>
                            <th scope="col" class="p-3">Jabatan/Agensi</th>
                            <th scope="col" class="p-3">Tarikh Lulus JTICT</th>
                            <th scope="col" class="p-3"><span class="sr-only">Tindakan</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($awaiting as $app)
                            <tr wire:key="belum-{{ $app->id }}" class="hover:bg-slate-50 transition">
                                <td class="p-3 font-bold whitespace-nowrap tabular-nums">{{ $app->reference_number }}</td>
                                <td class="p-3 font-semibold text-slate-900 max-w-xs"><span class="block break-words">{{ $app->title }}</span></td>
                                <td class="p-3 text-slate-600" title="{{ $app->agency?->name }}">{{ $app->agency?->code ?? '-' }}</td>
                                <td class="p-3 whitespace-nowrap tabular-nums">{{ $app->latestJtictDecision?->decision_date?->format('d/m/Y') ?? '-' }}</td>
                                <td class="p-3 text-right whitespace-nowrap">
                                    @can('start', [\App\Models\Project::class, $app])
                                        <a href="{{ route('projects.start', $app->uuid) }}" class="inline-flex items-center min-h-8 px-3 bg-sky-700 hover:bg-sky-800 text-white text-[12px] font-bold rounded-lg transition">Mulakan Pemantauan</a>
                                    @else
                                        <span class="text-[11px] text-slate-500">Menunggu pegawai projek</span>
                                    @endcan
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif

    <section aria-labelledby="bahagian-projek" class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
        <div class="p-4 border-b border-slate-100">
            <h3 id="bahagian-projek" class="text-sm font-bold text-slate-800">Senarai Projek ({{ $projects->total() }})</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full min-w-[1200px] text-left text-xs">
                <thead class="bg-slate-50 text-slate-600 font-bold border-b border-slate-100">
                    <tr>
                        <th scope="col" class="p-3">No. Rujukan</th>
                        <th scope="col" class="p-3">Nama Projek</th>
                        <th scope="col" class="p-3">Jabatan/Agensi</th>
                        <th scope="col" class="p-3">Templat</th>
                        <th scope="col" class="p-3">Fasa</th>
                        <th scope="col" class="p-3">Tempoh Projek</th>
                        <th scope="col" class="p-3 text-right">Sebenar</th>
                        <th scope="col" class="p-3 text-right">Dirancang</th>
                        <th scope="col" class="p-3">Jadual</th>
                        <th scope="col" class="p-3">Status</th>
                        <th scope="col" class="p-3"><span class="sr-only">Tindakan</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($projects as $project)
                        <tr wire:key="projek-{{ $project->id }}" class="hover:bg-slate-50 transition">
                            <td class="p-3 font-bold whitespace-nowrap tabular-nums">{{ $project->application?->reference_number ?? $project->legacy_reference ?? '-' }}</td>
                            <td class="p-3 font-semibold text-slate-900 max-w-xs"><span class="block break-words">{{ $project->application?->title ?? '-' }}</span></td>
                            <td class="p-3 text-slate-600" title="{{ $project->agency?->name }}">{{ $project->agency?->code ?? '-' }}</td>
                            <td class="p-3 text-slate-600">{{ $project->progressTemplate ? 'Templat ' . $project->progressTemplate->code : '-' }}</td>
                            <td class="p-3 text-slate-600 whitespace-nowrap">{{ $project->is_phased ? $project->phases_count . ' fasa' : 'Tidak Berfasa' }}</td>
                            <td class="p-3 whitespace-nowrap tabular-nums">
                                {{ $project->planned_start_date?->format('d/m/Y') ?? '-' }} – {{ $project->planned_end_date?->format('d/m/Y') ?? '-' }}
                            </td>
                            {{-- SP4: nilai cache (dikemas kini setiap perubahan dan setiap hari) --}}
                            <td class="p-3 text-right font-bold tabular-nums">{{ number_format((float) $project->actual_progress_percent, 2) }}%</td>
                            <td class="p-3 text-right tabular-nums">{{ $project->planned_progress_percent === null ? '-' : number_format((float) $project->planned_progress_percent, 2) . '%' }}</td>
                            <td class="p-3 whitespace-nowrap">
                                @if ($project->variance_percent !== null)
                                    @php
                                        $variance = (float) $project->variance_percent;
                                    @endphp
                                    <span class="inline-flex px-2 py-0.5 rounded-md text-[11px] font-bold ring-1 {{ \App\Enums\ScheduleStatus::indicatorClasses(\App\Enums\ScheduleStatus::indicatorFor($variance)) }}">
                                        {{ \App\Enums\ScheduleStatus::fromVariance($variance)->label() }} ({{ sprintf('%+.2f', $variance) }})
                                    </span>
                                @else
                                    <span class="text-[11px] text-slate-500">Tiada data rancangan</span>
                                @endif
                            </td>
                            <td class="p-3">
                                <span class="inline-flex px-2 py-0.5 rounded-md text-[11px] font-bold bg-slate-100 text-slate-700">{{ $project->status->label() }}</span>
                                @if ($project->isAwaitingJpict())
                                    <span class="inline-flex mt-1 px-2 py-0.5 rounded-md text-[11px] font-bold bg-amber-50 text-amber-800 ring-1 ring-amber-200">Menunggu JPICT</span>
                                @endif
                            </td>
                            <td class="p-3 text-right whitespace-nowrap">
                                <a href="{{ route('projects.show', $project->uuid) }}" class="inline-flex items-center min-h-8 text-[13px] font-bold text-sky-800 hover:underline">Papar</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="11" class="p-6 text-center text-sm text-slate-500">Tiada projek dalam pemantauan.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($projects->hasPages())
            <div class="p-4 border-t border-slate-100">{{ $projects->links() }}</div>
        @endif
    </section>
</div>
