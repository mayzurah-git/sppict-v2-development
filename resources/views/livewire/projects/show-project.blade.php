@php
    $project = $this->project;
    $application = $project->application;
    $user = auth()->user();
    $canManage = $user->can('manage', $project);
    $canManageReports = $user->can('manageReports', $project);
    $canEditStructure = $user->can('updateStructure', $project);
    $canUnlock = $user->can('unlockStructure', $project);
    $canRecordJpict = $user->can('recordJpict', $project);
    $inputClass = 'block w-full text-xs border-slate-200 rounded-lg shadow-sm focus:border-sky-700 focus:ring-sky-700/20';
@endphp

<div class="max-w-6xl mx-auto p-6 space-y-6">
    {{-- Header --}}
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 bg-white p-6 rounded-2xl shadow-sm border border-slate-100">
        <div>
            <div class="flex flex-wrap items-center gap-2">
                <span class="text-xs font-bold text-sky-700 bg-sky-50 px-2.5 py-1 rounded-md">{{ $application?->reference_number ?? $project->legacy_reference ?? '-' }}</span>
                <span class="text-xs font-bold text-slate-700 bg-slate-100 px-2.5 py-1 rounded-md">{{ $project->status->label() }}</span>
                @if ($project->isLegacy())
                    <span class="text-xs font-bold text-purple-700 bg-purple-50 px-2.5 py-1 rounded-md">SPPICT v1</span>
                @endif
                @if ($project->isAwaitingJpict())
                    <span class="text-xs font-bold text-amber-800 bg-amber-50 ring-1 ring-amber-200 px-2.5 py-1 rounded-md">Menunggu JPICT</span>
                @endif
                @if ($project->isStructureLocked())
                    <span class="text-xs font-bold text-slate-600 bg-slate-50 ring-1 ring-slate-200 px-2.5 py-1 rounded-md">Struktur Dikunci</span>
                @endif
            </div>
            <h2 class="text-xl font-black text-slate-800 mt-2">{{ $application?->title ?? '-' }}</h2>
            <dl class="mt-1.5 flex flex-wrap gap-x-5 gap-y-1 text-xs">
                <div class="flex gap-1"><dt class="text-slate-400">Jabatan/Agensi:</dt><dd class="font-semibold text-slate-700">{{ $project->agency ? $project->agency->name . ' (' . $project->agency->code . ')' : '-' }}</dd></div>
                <div class="flex gap-1"><dt class="text-slate-400">Pegawai Utama:</dt><dd class="font-semibold text-slate-700">{{ $application?->primaryOfficer?->name ?? '-' }}</dd></div>
                <div class="flex gap-1"><dt class="text-slate-400">Pegawai Kedua:</dt><dd class="font-semibold text-slate-700">{{ $application?->secondaryOfficer?->name ?? '-' }}</dd></div>
            </dl>
        </div>
        <a href="{{ route('projects.index') }}" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold rounded-xl transition">← Senarai Projek</a>
    </div>

    @if (session()->has('message'))
        <div class="p-3 bg-emerald-50 border border-emerald-200 text-emerald-700 text-xs font-semibold rounded-xl">{{ session('message') }}</div>
    @endif
    @if (session()->has('error'))
        <div class="p-3 bg-red-50 border border-red-200 text-red-700 text-xs font-semibold rounded-xl">{{ session('error') }}</div>
    @endif

    @if (! $canManageReports && ! $project->isReadOnly())
        <div class="p-3 bg-slate-50 border border-slate-200 text-slate-600 text-xs rounded-xl">Paparan baca sahaja. Jadual dan kemajuan dikemas kini oleh Pegawai Utama / Pegawai Kedua projek yang aktif.</div>
    @endif

    {{-- Ringkasan --}}
    <section class="bg-white p-6 rounded-2xl shadow-sm border border-slate-100">
        <div class="flex flex-col md:flex-row md:items-start justify-between gap-4">
            <h3 class="text-sm font-bold text-slate-800">Ringkasan Projek</h3>
            <div class="flex flex-wrap gap-2">
                @if ($canManage)
                    <button type="button" wire:click="openScheduleModal" class="px-3 py-1.5 bg-sky-700 hover:bg-sky-800 text-white text-xs font-bold rounded-lg transition">Pinda Jadual</button>
                @endif
                @if ($canEditStructure)
                    <a href="{{ route('projects.structure', $project->uuid) }}" class="px-3 py-1.5 bg-white border border-sky-200 text-sky-800 hover:bg-sky-50 text-xs font-bold rounded-lg transition">Pinda Struktur</a>
                @endif
                @if ($canUnlock)
                    <button type="button" wire:click="openUnlockModal" class="px-3 py-1.5 bg-white border border-emerald-300 text-emerald-800 hover:bg-emerald-50 text-xs font-bold rounded-lg transition">Buka Kunci Struktur</button>
                @endif
                @if ($canRecordJpict)
                    <button type="button" wire:click="openJpictModal" class="px-3 py-1.5 bg-white border border-amber-300 text-amber-800 hover:bg-amber-50 text-xs font-bold rounded-lg transition">{{ $project->jpict_approved_at ? 'Kemas Kini Rekod JPICT' : 'Rekod Kelulusan JPICT' }}</button>
                @endif
            </div>
        </div>

        <dl class="mt-4 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-x-6 gap-y-3 text-xs">
            <div><dt class="text-slate-400">Templat Pemberat</dt><dd class="font-semibold text-slate-800">{{ $project->progressTemplate ? 'Templat ' . $project->progressTemplate->code . ' – ' . $project->progressTemplate->name . ' (v' . $project->progressTemplate->version . ')' : '-' }}</dd></div>
            <div><dt class="text-slate-400">Jenis Projek</dt><dd class="font-semibold text-slate-800">{{ $project->is_phased ? 'Berfasa (' . $project->phases->count() . ' fasa)' : 'Tidak Berfasa' }}</dd></div>
            <div><dt class="text-slate-400">Tempoh Projek</dt><dd class="font-semibold text-slate-800 tabular-nums">{{ $project->planned_start_date?->format('d/m/Y') ?? '-' }} – {{ $project->planned_end_date?->format('d/m/Y') ?? '-' }}</dd></div>
            <div><dt class="text-slate-400">Lulus JTICT</dt><dd class="font-semibold text-slate-800 tabular-nums">{{ $application?->latestJtictDecision?->decision_date?->format('d/m/Y') ?? '-' }}</dd></div>
            <div>
                <dt class="text-slate-400">Kelulusan JPICT</dt>
                <dd class="font-semibold text-slate-800">
                    @if ($project->jpict_approved_at)
                        <span class="tabular-nums">{{ $project->jpict_approved_at->format('d/m/Y') }}</span>{{ $project->jpict_reference ? ' · ' . $project->jpict_reference : '' }}
                        <span class="block text-[11px] font-normal text-slate-500">Direkod oleh {{ $project->jpictRecorder?->name ?? '-' }}, {{ $project->jpict_recorded_at?->format('d/m/Y h:i A') }}</span>
                    @elseif ($project->requiresJpict())
                        <span class="text-amber-800">Menunggu JPICT</span>
                        <span class="block text-[11px] font-normal text-slate-500">Projek RM500,000 dan ke atas. Pemantauan diteruskan.</span>
                    @else
                        Tidak berkenaan
                    @endif
                </dd>
            </div>
            <div><dt class="text-slate-400">Dimulakan oleh</dt><dd class="font-semibold text-slate-800">{{ $project->starter?->name ?? '-' }}{{ $project->started_at ? ', ' . $project->started_at->format('d/m/Y') : '' }}</dd></div>
        </dl>

        @if ($application && $user->can('view', $application))
            <a href="{{ route('application.view', $application->uuid) }}" class="inline-flex mt-4 text-xs font-bold text-sky-800 hover:underline">Lihat permohonan projek →</a>
        @endif
    </section>

    {{-- SP4: Kemajuan projek (dikira secara langsung pada hari ini) --}}
    @php
        $progress = $this->progress;
    @endphp
    <section class="bg-white p-6 rounded-2xl shadow-sm border border-slate-100 space-y-4" aria-labelledby="tajuk-kemajuan">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-2">
            <h3 id="tajuk-kemajuan" class="text-sm font-bold text-slate-800">Kemajuan Projek</h3>
            <p class="text-[11px] text-slate-500">Tarikh penilaian: {{ today()->format('d/m/Y') }}</p>
        </div>
        <dl class="grid grid-cols-2 lg:grid-cols-4 gap-3">
            <div class="p-3 rounded-xl bg-slate-50">
                <dt class="text-[11px] font-semibold text-slate-500">Kemajuan Sebenar</dt>
                <dd class="text-2xl font-black text-slate-900 tabular-nums">{{ number_format($progress->actual, 2) }}%</dd>
            </div>
            <div class="p-3 rounded-xl bg-slate-50">
                <dt class="text-[11px] font-semibold text-slate-500">Kemajuan Dirancang</dt>
                <dd class="text-2xl font-black text-slate-900 tabular-nums">{{ $progress->planned === null ? '-' : number_format($progress->planned, 2) . '%' }}</dd>
            </div>
            <div class="p-3 rounded-xl bg-slate-50">
                <dt class="text-[11px] font-semibold text-slate-500">Varians</dt>
                <dd class="text-2xl font-black tabular-nums {{ ($progress->variance() ?? 0) < 0 ? 'text-red-700' : 'text-slate-900' }}">{{ $progress->variance() === null ? '-' : sprintf('%+.2f', $progress->variance()) }}</dd>
            </div>
            <div class="p-3 rounded-xl bg-slate-50">
                <dt class="text-[11px] font-semibold text-slate-500">Status Jadual</dt>
                <dd class="mt-1">
                    @if ($progress->status())
                        <span class="inline-flex px-2.5 py-1 rounded-md text-xs font-bold ring-1 {{ \App\Enums\ScheduleStatus::indicatorClasses($progress->indicator()) }}">{{ $progress->status()->label() }} · {{ \App\Enums\ScheduleStatus::indicatorLabel($progress->indicator()) }}</span>
                    @else
                        <span class="text-xs text-slate-500">Tiada data rancangan</span>
                    @endif
                </dd>
            </div>
        </dl>
        @if ($project->is_phased)
            <p class="text-[11px] text-slate-500">Kemajuan projek ialah purata sama rata kemajuan {{ count($progress->phases) }} fasa. Setiap fasa dikira 100% berdasarkan aktiviti yang dipilih.</p>
        @endif
        <p class="text-[11px] text-slate-500">Lampu: Hijau = tidak lewat; Kuning = lewat kurang daripada 20 mata peratus; Merah = lewat 20 mata peratus atau lebih.</p>
        @if ($project->isMaintenance() && $progress->actual >= 100 && $project->status === \App\Enums\ProjectStatus::InProgress && $project->planned_end_date && today()->lt($project->planned_end_date))
            <p class="p-3 rounded-xl bg-sky-50 border border-sky-200 text-xs text-sky-900"><span class="font-bold">100% – Dalam Pelaksanaan Kontrak.</span> Projek Penyelenggaraan selesai pada tarikh tamat kontrak ({{ $project->planned_end_date->format('d/m/Y') }}).</p>
        @endif
    </section>

    {{-- SP4: Penutupan projek (I.6) --}}
    @php
        $canRequestClosure = $user->can('requestClosure', $project);
        $canWithdrawClosure = $user->can('withdrawClosure', $project);
        $canDecideClosure = $user->can('decideClosure', $project);
        $canTerminate = $user->can('terminate', $project);
    @endphp
    @if ($canRequestClosure || $canWithdrawClosure || $canDecideClosure || $canTerminate || in_array($project->status, [\App\Enums\ProjectStatus::PendingClosure, \App\Enums\ProjectStatus::Completed, \App\Enums\ProjectStatus::Closed], true))
        <section class="bg-white p-6 rounded-2xl shadow-sm border {{ $project->status === \App\Enums\ProjectStatus::PendingClosure ? 'border-amber-200' : 'border-slate-100' }} space-y-3" aria-labelledby="tajuk-penutupan">
            <div class="flex flex-col md:flex-row md:items-start justify-between gap-3">
                <div>
                    <h3 id="tajuk-penutupan" class="text-sm font-bold text-slate-800">Penutupan Projek</h3>
                    @switch($project->status)
                        @case(\App\Enums\ProjectStatus::PendingClosure)
                            <p class="text-xs text-amber-900 mt-1">Penutupan telah dimohon dan menunggu pengesahan Urus Setia. Tarikh siap projek: <span class="font-bold tabular-nums">{{ $project->actual_completion_date?->format('d/m/Y') ?? '-' }}</span>. Aktiviti tidak boleh dikemas kini sehingga keputusan dibuat.</p>
                            @if ($canDecideClosure && $this->missingReportCount > 0)
                                <p class="text-xs text-amber-900 mt-1">Perhatian: {{ $this->missingReportCount }} laporan kemajuan bulanan belum dihantar.</p>
                            @endif
                            @break
                        @case(\App\Enums\ProjectStatus::Completed)
                            <p class="text-xs text-emerald-800 mt-1">Projek <span class="font-bold">Selesai</span>. Tarikh siap: <span class="tabular-nums">{{ $project->actual_completion_date?->format('d/m/Y') ?? '-' }}</span>. Disahkan oleh {{ $project->closer?->name ?? '-' }} pada {{ $project->closed_at?->format('d/m/Y') }}. Rekod projek kini baca sahaja.</p>
                            @break
                        @case(\App\Enums\ProjectStatus::Closed)
                            <p class="text-xs text-red-800 mt-1">Projek <span class="font-bold">Ditutup tanpa siap</span> oleh {{ $project->closer?->name ?? '-' }} pada {{ $project->closed_at?->format('d/m/Y') }}. Rekod projek kini baca sahaja.</p>
                            @if ($project->closure_remarks)<p class="text-xs text-slate-700 mt-1 whitespace-pre-line">{{ $project->closure_remarks }}</p>@endif
                            @break
                        @default
                            @if ($canRequestClosure)
                                @if ($this->closureBlocker)
                                    <p class="text-xs text-slate-600 mt-1">{{ $this->closureBlocker }}</p>
                                @else
                                    <p class="text-xs text-emerald-800 mt-1">Semua aktiviti telah siap. Penutupan projek boleh dimohon untuk pengesahan Urus Setia.</p>
                                @endif
                            @endif
                    @endswitch
                </div>
                <div class="flex flex-wrap gap-2 shrink-0">
                    @if ($canRequestClosure && ! $this->closureBlocker)
                        <button type="button" wire:click="openClosureModal('request')" class="px-3 py-1.5 bg-emerald-700 hover:bg-emerald-800 text-white text-xs font-bold rounded-lg transition">Mohon Penutupan</button>
                    @endif
                    @if ($canWithdrawClosure)
                        <button type="button" wire:click="openClosureModal('withdraw')" class="px-3 py-1.5 bg-white border border-slate-300 text-slate-700 hover:bg-slate-50 text-xs font-bold rounded-lg transition">Tarik Balik Permohonan</button>
                    @endif
                    @if ($canDecideClosure)
                        <button type="button" wire:click="openClosureModal('approve')" class="px-3 py-1.5 bg-emerald-700 hover:bg-emerald-800 text-white text-xs font-bold rounded-lg transition">Sahkan Penutupan</button>
                        <button type="button" wire:click="openClosureModal('reject')" class="px-3 py-1.5 bg-white border border-red-300 text-red-800 hover:bg-red-50 text-xs font-bold rounded-lg transition">Tolak</button>
                    @endif
                    @if ($canTerminate)
                        <button type="button" wire:click="openClosureModal('terminate')" class="px-3 py-1.5 bg-white border border-red-300 text-red-800 hover:bg-red-50 text-xs font-bold rounded-lg transition">Tutup Projek (Tanpa Siap)</button>
                    @endif
                </div>
            </div>

            @if ($this->closureHistory->isNotEmpty())
                <ul class="space-y-2 pt-2 border-t border-slate-100">
                    @foreach ($this->closureHistory as $event)
                        <li wire:key="penutupan-{{ $event->id }}" class="text-xs">
                            <span class="font-bold {{ $event->event_type->tone() }}">{{ $event->event_type->label() }}</span>
                            <span class="text-slate-500">· {{ $event->created_at?->format('d/m/Y h:i A') }} · {{ $event->actor?->name ?? '-' }}</span>
                            @if ($event->remarks)<p class="text-slate-700 whitespace-pre-line">{{ $event->remarks }}</p>@endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    @endif

    {{-- Struktur & jadual --}}
    @foreach ($project->phases as $phase)
        <section wire:key="fasa-{{ $phase->id }}" class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
            <div class="p-4 border-b border-slate-100">
                <h3 class="text-sm font-bold text-slate-800">{{ $project->is_phased ? 'Fasa ' . $phase->phase_number . ($phase->name ? ' – ' . $phase->name : '') : 'Aktiviti Projek' }}</h3>
                <p class="text-[11px] text-slate-500 mt-0.5">
                    Jumlah pemberat: <span class="font-bold tabular-nums">{{ number_format($phase->totalWeight(), 2) }}</span>
                    @if ($project->is_phased && abs($phase->totalWeight() - 100) > 0.001)
                        (dikira secara berkadar kepada 100% bagi fasa ini)
                    @endif
                    @if ($project->is_phased && ($phaseProgress = $progress->phases[$phase->phase_number] ?? null))
                        · Sebenar <span class="font-bold tabular-nums">{{ number_format($phaseProgress->actual, 2) }}%</span>
                        · Dirancang <span class="font-bold tabular-nums">{{ $phaseProgress->planned === null ? '-' : number_format($phaseProgress->planned, 2) . '%' }}</span>
                    @endif
                </p>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[760px] text-left text-xs">
                    <thead class="bg-slate-50 text-slate-600 font-bold border-b border-slate-100">
                        <tr>
                            <th scope="col" class="p-3 w-10">#</th>
                            <th scope="col" class="p-3">Aktiviti</th>
                            <th scope="col" class="p-3 text-right">Pemberat (%)</th>
                            <th scope="col" class="p-3">Dokumen Wajib</th>
                            <th scope="col" class="p-3">Tarikh Sasaran</th>
                            <th scope="col" class="p-3">Tarikh Sebenar Siap</th>
                            <th scope="col" class="p-3">Status</th>
                            @if ($canManage)<th scope="col" class="p-3"><span class="sr-only">Tindakan</span></th>@endif
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($phase->activities as $activity)
                            @php
                                $status = $activity->statusOn(today());
                                $documents = $this->activityAttachments->get($activity->id, collect());
                            @endphp
                            <tr wire:key="aktiviti-{{ $activity->id }}">
                                <td class="p-3 tabular-nums text-slate-500">{{ $activity->sequence }}</td>
                                <td class="p-3 font-semibold text-slate-800">{{ $activity->name }}</td>
                                <td class="p-3 text-right tabular-nums">{{ number_format((float) $activity->weight, 2) }}</td>
                                <td class="p-3 text-slate-600">
                                    {{ $activity->requires_document ? ($activity->document_label ?? 'Ya') : '-' }}
                                    @foreach ($documents as $document)
                                        <a href="{{ route('projects.attachments.show', [$project->uuid, $document->uuid]) }}" target="_blank" rel="noopener" class="block mt-0.5 text-[11px] font-semibold text-sky-800 hover:underline break-all">📄 {{ $document->file_name }}</a>
                                    @endforeach
                                    @if ($activity->requires_document && $documents->isEmpty())
                                        <span class="block mt-0.5 text-[11px] text-amber-700">Belum dimuat naik</span>
                                    @endif
                                </td>
                                <td class="p-3 tabular-nums">{{ $activity->target_date?->format('d/m/Y') ?? '-' }}</td>
                                <td class="p-3 tabular-nums">{{ $activity->actual_completion_date?->format('d/m/Y') ?? '-' }}</td>
                                <td class="p-3"><span class="inline-flex px-2 py-0.5 rounded-md text-[11px] font-bold ring-1 {{ $status->badgeClasses() }}">{{ $status->label() }}</span></td>
                                @if ($canManage)
                                    <td class="p-3 text-right whitespace-nowrap">
                                        <button type="button" wire:click="openActivityModal('{{ $activity->uuid }}')" class="px-2.5 py-1 text-[11px] font-bold rounded-lg transition {{ $activity->is_completed ? 'bg-slate-100 text-slate-700 hover:bg-slate-200' : 'bg-sky-700 text-white hover:bg-sky-800' }}">
                                            {{ $activity->is_completed ? 'Kemas Kini' : 'Tanda Siap' }}
                                        </button>
                                    </td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endforeach

    {{-- SP3: Laporan kemajuan bulanan --}}
    <section class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
        <div class="p-4 border-b border-slate-100 flex flex-col md:flex-row md:items-end justify-between gap-3">
            <div>
                <h3 class="text-sm font-bold text-slate-800">Laporan Kemajuan Bulanan</h3>
                <p class="text-[11px] text-slate-500 mt-0.5">Satu laporan bagi setiap bulan. Tarikh akhir mengikut Tarikh Status Kemaskini Kemajuan Projek mesyuarat. Laporan lewat masih diterima dan ditanda "Lewat".</p>
            </div>
            @if ($canManageReports && count($this->availablePeriods) > 0)
                <div class="flex flex-col sm:flex-row gap-2 sm:items-start">
                    <div>
                        <label for="bulan-laporan" class="sr-only">Bulan laporan</label>
                        <select id="bulan-laporan" wire:model="reportPeriod" class="text-xs border-slate-200 rounded-lg shadow-sm focus:border-sky-700 focus:ring-sky-700/20">
                            <option value="">-- Pilih bulan --</option>
                            @foreach ($this->availablePeriods as [$year, $month])
                                <option value="{{ $year }}-{{ $month }}">{{ \App\Models\ProjectProgressReport::MONTHS[$month] }} {{ $year }}</option>
                            @endforeach
                        </select>
                        @error('period') <span class="text-red-600 text-xs block mt-1">{{ $message }}</span> @enderror
                    </div>
                    <button type="button" wire:click="createReport" wire:loading.attr="disabled" wire:target="createReport" class="px-3 py-1.5 bg-sky-700 hover:bg-sky-800 text-white text-xs font-bold rounded-lg transition disabled:opacity-50">Cipta Laporan</button>
                </div>
            @endif
        </div>
        <div class="overflow-x-auto">
            <table class="w-full min-w-[720px] text-left text-xs">
                <thead class="bg-slate-50 text-slate-600 font-bold border-b border-slate-100">
                    <tr>
                        <th scope="col" class="p-3">Bulan</th>
                        <th scope="col" class="p-3">Status</th>
                        <th scope="col" class="p-3">Tarikh Akhir</th>
                        <th scope="col" class="p-3">Dihantar</th>
                        <th scope="col" class="p-3"><span class="sr-only">Tindakan</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($this->reports as $report)
                        <tr wire:key="laporan-{{ $report->id }}">
                            <td class="p-3 font-semibold text-slate-800">{{ $report->periodLabel() }}</td>
                            <td class="p-3">
                                <span class="inline-flex px-2 py-0.5 rounded-md text-[11px] font-bold {{ $report->isSubmitted() ? 'bg-emerald-50 text-emerald-800' : 'bg-slate-100 text-slate-700' }}">{{ $report->status->label() }}</span>
                                @if ($report->is_late)
                                    <span class="inline-flex px-2 py-0.5 rounded-md text-[11px] font-bold bg-red-50 text-red-800 ring-1 ring-red-200">Lewat</span>
                                @endif
                            </td>
                            <td class="p-3 tabular-nums">{{ $report->due_date?->format('d/m/Y') ?? 'Belum ditetapkan' }}</td>
                            <td class="p-3 tabular-nums">{{ $report->submitted_at ? $report->submitted_at->format('d/m/Y') . ' · ' . ($report->submitter?->name ?? '-') : '-' }}</td>
                            <td class="p-3 text-right">
                                <a href="{{ route('projects.reports.show', [$project->uuid, $report->uuid]) }}" class="text-[13px] font-bold text-sky-800 hover:underline">{{ $canManageReports && ! $report->isSubmitted() ? 'Kemas Kini' : 'Papar' }}</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="p-6 text-center text-sm text-slate-500">Tiada laporan kemajuan bulanan.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    {{-- SP3: Sejarah kemas kini aktiviti --}}
    @if ($this->progressHistory->isNotEmpty())
        <section class="bg-white p-6 rounded-2xl shadow-sm border border-slate-100 space-y-3">
            <h3 class="text-sm font-bold text-slate-800">Sejarah Kemas Kini Aktiviti</h3>
            <ul class="space-y-2">
                @foreach ($this->progressHistory as $update)
                    <li wire:key="kemaskini-{{ $update->id }}" class="p-3 rounded-xl border border-slate-100 bg-slate-50 text-xs">
                        <p class="text-slate-500">{{ $update->created_at?->format('d/m/Y h:i A') }} · {{ $update->updater?->name ?? '-' }}</p>
                        <p class="text-slate-800 mt-0.5">
                            <span class="font-bold {{ $update->change_type === \App\Enums\ProgressUpdateType::Completed ? 'text-emerald-800' : 'text-red-800' }}">{{ $update->change_type->label() }}:</span>
                            {{ $update->activity?->name ?? '-' }}{{ $project->is_phased && $update->activity?->phase ? ' (Fasa ' . $update->activity->phase->phase_number . ')' : '' }}
                            @if ($update->completion_date)
                                · tarikh siap <span class="tabular-nums">{{ $update->completion_date->format('d/m/Y') }}</span>
                            @endif
                        </p>
                        @if ($update->remarks)
                            <p class="text-slate-600 mt-0.5 whitespace-pre-line">{{ $update->remarks }}</p>
                        @endif
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    {{-- Sejarah pindaan jadual --}}
    <section class="bg-white p-6 rounded-2xl shadow-sm border border-slate-100 space-y-3">
        <h3 class="text-sm font-bold text-slate-800">Sejarah Pindaan Jadual</h3>
        @forelse ($this->scheduleHistory as $batch => $changes)
            @php
                $first = $changes->first();
            @endphp
            <div wire:key="pindaan-{{ $batch }}" class="p-3 rounded-xl border border-slate-100 bg-slate-50 text-xs space-y-2">
                <p class="text-slate-500">{{ $first->created_at?->format('d/m/Y h:i A') }} · {{ $first->changer?->name ?? '-' }}</p>
                <p class="text-slate-800"><span class="font-bold">Justifikasi:</span> <span class="whitespace-pre-line">{{ $first->justification }}</span></p>
                <ul class="list-disc pl-5 space-y-0.5 text-slate-700">
                    @foreach ($changes as $change)
                        <li>
                            {{ $change->field === \App\Enums\ScheduleField::TargetDate ? ($change->activity?->name ?? 'Aktiviti') . ($project->is_phased && $change->activity?->phase ? ' (Fasa ' . $change->activity->phase->phase_number . ')' : '') : $change->field->label() }}:
                            <span class="tabular-nums">{{ $change->old_value?->format('d/m/Y') ?? '-' }}</span> → <span class="tabular-nums font-semibold">{{ $change->new_value?->format('d/m/Y') ?? '-' }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        @empty
            <p class="text-xs text-slate-500">Tiada pindaan jadual. Jadual semasa ialah jadual yang ditetapkan semasa pemantauan dimulakan.</p>
        @endforelse
    </section>

    @if ($this->unlockHistory->isNotEmpty())
        <section class="bg-white p-6 rounded-2xl shadow-sm border border-slate-100 space-y-3">
            <h3 class="text-sm font-bold text-slate-800">Sejarah Buka Kunci Struktur</h3>
            @foreach ($this->unlockHistory as $unlock)
                <div wire:key="buka-{{ $unlock->id }}" class="p-3 rounded-xl border border-slate-100 bg-slate-50 text-xs space-y-1">
                    <p class="text-slate-500">{{ $unlock->created_at?->format('d/m/Y h:i A') }} · {{ $unlock->unlocker?->name ?? '-' }}</p>
                    <p class="text-slate-800 whitespace-pre-line">{{ $unlock->remarks }}</p>
                </div>
            @endforeach
        </section>
    @endif

    {{-- Modal: Pinda Jadual --}}
    @if ($showScheduleModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-sm p-4">
            <div class="bg-white rounded-2xl shadow-xl w-full max-w-3xl p-6 space-y-4 max-h-[90vh] overflow-y-auto" role="dialog" aria-modal="true" aria-labelledby="tajuk-pinda-jadual">
                <h3 id="tajuk-pinda-jadual" class="text-base font-bold text-slate-800 border-b pb-2">Pinda Jadual Projek</h3>
                <p class="text-[11px] text-slate-500">Pindaan tidak memerlukan kelulusan Urus Setia. Setiap tarikh yang berubah direkodkan dalam sejarah bersama justifikasi; jadual terdahulu tidak dipadam.</p>
                @error('schedule') <div class="p-3 bg-red-50 border border-red-200 text-red-700 text-xs font-semibold rounded-xl">{{ $message }}</div> @enderror

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="pinda-mula" class="block text-xs font-bold text-slate-700">Tarikh Mula Projek</label>
                        <input id="pinda-mula" type="date" wire:model="plannedStartDate" class="mt-1 {{ $inputClass }}">
                        @error('planned_start_date') <span class="text-red-600 text-xs block mt-1">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label for="pinda-tamat" class="block text-xs font-bold text-slate-700">Tarikh Tamat Projek</label>
                        <input id="pinda-tamat" type="date" wire:model="plannedEndDate" class="mt-1 {{ $inputClass }}">
                        @error('planned_end_date') <span class="text-red-600 text-xs block mt-1">{{ $message }}</span> @enderror
                    </div>
                </div>

                @foreach ($project->phases as $phase)
                    <div class="space-y-2">
                        <p class="text-xs font-bold text-slate-700">{{ $project->is_phased ? 'Fasa ' . $phase->phase_number . ($phase->name ? ' – ' . $phase->name : '') : 'Tarikh Sasaran Aktiviti' }}</p>
                        @foreach ($phase->activities as $activity)
                            <div wire:key="pinda-{{ $activity->uuid }}" class="grid grid-cols-1 sm:grid-cols-[1fr_12rem] gap-2 items-center">
                                <label for="pinda-{{ $activity->uuid }}" class="text-xs text-slate-700">{{ $activity->sequence }}. {{ $activity->name }}</label>
                                <div>
                                    <input id="pinda-{{ $activity->uuid }}" type="date" wire:model="targetDates.{{ $activity->uuid }}" class="{{ $inputClass }}">
                                    @error("activities.{$activity->uuid}") <span class="text-red-600 text-xs block mt-1">{{ $message }}</span> @enderror
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endforeach

                <div>
                    <label for="justifikasi" class="block text-xs font-bold text-slate-700">Justifikasi Pindaan <span class="text-red-600">*</span></label>
                    <textarea id="justifikasi" wire:model="justification" rows="3" maxlength="2000" class="mt-1 {{ $inputClass }}" placeholder="Contoh: Penghantaran perkakasan ditangguhkan oleh pembekal selama dua minggu."></textarea>
                    @error('justification') <span class="text-red-600 text-xs block mt-1">{{ $message }}</span> @enderror
                </div>

                <div class="flex justify-end gap-2 pt-2 border-t">
                    <button type="button" wire:click="closeModals" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold rounded-xl transition">Batal</button>
                    <button type="button" wire:click="saveSchedule" wire:loading.attr="disabled" wire:target="saveSchedule" class="px-4 py-2 bg-sky-700 hover:bg-sky-800 text-white text-xs font-bold rounded-xl transition disabled:opacity-50">Simpan Pindaan</button>
                </div>
            </div>
        </div>
    @endif

    {{-- SP3 Modal: Kemas Kini Aktiviti --}}
    @if ($showActivityModal && ($selected = $this->selectedActivity))
        @php
            $selectedDocuments = $this->activityAttachments->get($selected->id, collect());
        @endphp
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-sm p-4">
            <div class="bg-white rounded-2xl shadow-xl w-full max-w-xl p-6 space-y-4 max-h-[90vh] overflow-y-auto" role="dialog" aria-modal="true" aria-labelledby="tajuk-aktiviti">
                <div class="border-b pb-2">
                    <h3 id="tajuk-aktiviti" class="text-base font-bold text-slate-800">{{ $selected->name }}</h3>
                    <p class="text-[11px] text-slate-500">
                        {{ $project->is_phased ? 'Fasa ' . $selected->phase?->phase_number . ' · ' : '' }}Pemberat {{ number_format((float) $selected->weight, 2) }}% · Tarikh sasaran {{ $selected->target_date?->format('d/m/Y') ?? '-' }}
                    </p>
                </div>

                @error('activity') <div class="p-3 bg-red-50 border border-red-200 text-red-700 text-xs font-semibold rounded-xl">{{ $message }}</div> @enderror
                @if (session()->has('modal_message'))
                    <div class="p-3 bg-emerald-50 border border-emerald-200 text-emerald-700 text-xs font-semibold rounded-xl">{{ session('modal_message') }}</div>
                @endif

                {{-- Dokumen sokongan --}}
                <div class="space-y-2">
                    <p class="text-xs font-bold text-slate-700">
                        Dokumen Sokongan
                        @if ($selected->requires_document)
                            <span class="font-normal text-slate-500">· wajib: {{ $selected->document_label ?? 'Dokumen sokongan' }}</span>
                        @else
                            <span class="font-normal text-slate-500">· tidak wajib</span>
                        @endif
                    </p>
                    @forelse ($selectedDocuments as $document)
                        <div wire:key="dok-{{ $document->id }}" class="flex items-center justify-between gap-2 p-2 rounded-lg bg-slate-50 text-xs">
                            <a href="{{ route('projects.attachments.show', [$project->uuid, $document->uuid]) }}" target="_blank" rel="noopener" class="font-semibold text-sky-800 hover:underline break-all">📄 {{ $document->file_name }}</a>
                            <span class="shrink-0 text-[11px] text-slate-500">{{ $document->created_at?->format('d/m/Y') }}</span>
                            <button type="button" wire:click="deleteDocument('{{ $document->uuid }}')" wire:confirm="Padam dokumen ini?" class="shrink-0 text-[11px] font-bold text-red-700 hover:underline">Padam</button>
                        </div>
                    @empty
                        <p class="text-xs text-slate-500">Tiada dokumen dimuat naik.</p>
                    @endforelse
                    @error('attachment') <span class="text-red-600 text-xs block">{{ $message }}</span> @enderror

                    <div class="flex flex-col sm:flex-row gap-2 sm:items-center">
                        <label for="muat-naik-dokumen" class="sr-only">Pilih dokumen PDF</label>
                        <input id="muat-naik-dokumen" type="file" wire:model="upload" accept="application/pdf,.pdf" class="block w-full text-xs text-slate-600 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:bg-slate-100 file:text-xs file:font-bold">
                        <button type="button" wire:click="uploadActivityDocument" wire:loading.attr="disabled" wire:target="upload,uploadActivityDocument" class="shrink-0 px-3 py-1.5 bg-slate-700 hover:bg-slate-800 text-white text-xs font-bold rounded-lg transition disabled:opacity-50">Muat Naik</button>
                    </div>
                    <p class="text-[11px] text-slate-500">PDF sahaja, maksimum 25 MB.</p>
                    @error('upload') <span class="text-red-600 text-xs block">{{ $message }}</span> @enderror
                </div>

                <div class="border-t pt-4 space-y-3">
                    @if ($selected->is_completed)
                        <p class="text-xs text-slate-700">Aktiviti ini telah ditanda <span class="font-bold text-emerald-800">Siap</span> pada <span class="font-bold tabular-nums">{{ $selected->actual_completion_date?->format('d/m/Y') }}</span>. Untuk membetulkan tarikh, batalkan tanda siap dan tanda semula.</p>
                        <div>
                            <label for="catatan-aktiviti" class="block text-xs font-bold text-slate-700">Catatan Pembatalan <span class="text-red-600">*</span></label>
                            <textarea id="catatan-aktiviti" wire:model="activityRemarks" rows="2" maxlength="2000" class="mt-1 {{ $inputClass }}" placeholder="Contoh: Tarikh siap tersilap; dokumen SST belum ditandatangani."></textarea>
                            @error('remarks') <span class="text-red-600 text-xs block mt-1">{{ $message }}</span> @enderror
                        </div>
                    @else
                        @error('document') <div class="p-3 bg-amber-50 border border-amber-200 text-amber-900 text-xs font-semibold rounded-xl">{{ $message }}</div> @enderror
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label for="tarikh-siap" class="block text-xs font-bold text-slate-700">Tarikh Sebenar Siap <span class="text-red-600">*</span></label>
                                <input id="tarikh-siap" type="date" wire:model="completionDate" max="{{ today()->toDateString() }}" class="mt-1 {{ $inputClass }}">
                                @error('completionDate') <span class="text-red-600 text-xs block mt-1">{{ $message }}</span> @enderror
                            </div>
                        </div>
                        <div>
                            <label for="catatan-siap" class="block text-xs font-bold text-slate-700">Catatan (pilihan)</label>
                            <textarea id="catatan-siap" wire:model="activityRemarks" rows="2" maxlength="2000" class="mt-1 {{ $inputClass }}"></textarea>
                            @error('remarks') <span class="text-red-600 text-xs block mt-1">{{ $message }}</span> @enderror
                        </div>
                        @if (! $project->isStructureLocked())
                            <p class="text-[11px] text-slate-500">Selepas aktiviti pertama ditanda siap, struktur fasa dan aktiviti akan dikunci.</p>
                        @endif
                    @endif
                </div>

                <div class="flex justify-end gap-2 pt-2 border-t">
                    <button type="button" wire:click="closeModals" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold rounded-xl transition">Tutup</button>
                    @if ($selected->is_completed)
                        <button type="button" wire:click="uncompleteActivity" wire:loading.attr="disabled" wire:target="uncompleteActivity" class="px-4 py-2 bg-red-700 hover:bg-red-800 text-white text-xs font-bold rounded-xl transition disabled:opacity-50">Batal Tanda Siap</button>
                    @else
                        <button type="button" wire:click="completeActivity" wire:loading.attr="disabled" wire:target="completeActivity" class="px-4 py-2 bg-emerald-700 hover:bg-emerald-800 text-white text-xs font-bold rounded-xl transition disabled:opacity-50">Tanda Siap</button>
                    @endif
                </div>
            </div>
        </div>
    @endif

    {{-- SP4 Modal: Penutupan --}}
    @if ($showClosureModal)
        @php
            $closureTitles = [
                'request' => ['Mohon Penutupan Projek', 'Permohonan akan dihantar kepada Urus Setia untuk pengesahan. Aktiviti tidak boleh dikemas kini semasa menunggu keputusan.', false, 'Hantar Permohonan', 'bg-emerald-700 hover:bg-emerald-800'],
                'withdraw' => ['Tarik Balik Permohonan Penutupan', 'Projek akan kembali Dalam Pelaksanaan.', false, 'Tarik Balik', 'bg-slate-700 hover:bg-slate-800'],
                'approve' => ['Sahkan Penutupan Projek', 'Projek akan menjadi Selesai dan baca sahaja. Tindakan ini tidak boleh dibatalkan.', false, 'Sahkan', 'bg-emerald-700 hover:bg-emerald-800'],
                'reject' => ['Tolak Permohonan Penutupan', 'Projek akan kembali Dalam Pelaksanaan. Nyatakan sebab penolakan.', true, 'Tolak', 'bg-red-700 hover:bg-red-800'],
                'terminate' => ['Tutup Projek Tanpa Siap', 'Gunakan bagi projek yang dihentikan atau dibatalkan. Projek akan menjadi Ditutup dan baca sahaja. Tindakan ini tidak boleh dibatalkan.', true, 'Tutup Projek', 'bg-red-700 hover:bg-red-800'],
            ];
            [$closureTitle, $closureHint, $closureRequired, $closureButton, $closureButtonClass] = $closureTitles[$closureAction] ?? $closureTitles['request'];
        @endphp
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-sm p-4">
            <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg p-6 space-y-4" role="dialog" aria-modal="true" aria-labelledby="tajuk-modal-penutupan">
                <h3 id="tajuk-modal-penutupan" class="text-base font-bold text-slate-800 border-b pb-2">{{ $closureTitle }}</h3>
                <p class="text-[11px] text-slate-500">{{ $closureHint }}</p>
                @error('closure') <div class="p-3 bg-red-50 border border-red-200 text-red-700 text-xs font-semibold rounded-xl">{{ $message }}</div> @enderror
                <div>
                    <label for="ulasan-penutupan" class="block text-xs font-bold text-slate-700">{{ $closureRequired ? 'Ulasan' : 'Catatan (pilihan)' }} @if ($closureRequired)<span class="text-red-600">*</span>@endif</label>
                    <textarea id="ulasan-penutupan" wire:model="closureRemarks" rows="3" maxlength="2000" class="mt-1 {{ $inputClass }}"></textarea>
                    @error('closureRemarks') <span class="text-red-600 text-xs block mt-1">{{ $message }}</span> @enderror
                </div>
                <div class="flex justify-end gap-2 pt-2 border-t">
                    <button type="button" wire:click="closeModals" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold rounded-xl transition">Batal</button>
                    <button type="button" wire:click="submitClosure" wire:loading.attr="disabled" wire:target="submitClosure" class="px-4 py-2 text-white text-xs font-bold rounded-xl transition disabled:opacity-50 {{ $closureButtonClass }}">{{ $closureButton }}</button>
                </div>
            </div>
        </div>
    @endif

    {{-- Modal: Buka Kunci Struktur --}}
    @if ($showUnlockModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-sm p-4">
            <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg p-6 space-y-4" role="dialog" aria-modal="true" aria-labelledby="tajuk-buka-kunci">
                <h3 id="tajuk-buka-kunci" class="text-base font-bold text-slate-800 border-b pb-2">Buka Kunci Struktur Projek</h3>
                <p class="text-[11px] text-slate-500">Pegawai projek akan dapat meminda fasa dan aktiviti. Aktiviti yang telah ditanda siap tidak boleh dikeluarkan, dan struktur dikunci semula apabila disimpan.</p>
                <div>
                    <label for="catatan-buka" class="block text-xs font-bold text-slate-700">Catatan <span class="text-red-600">*</span></label>
                    <textarea id="catatan-buka" wire:model="unlockRemarks" rows="3" maxlength="2000" class="mt-1 {{ $inputClass }}"></textarea>
                    @error('unlockRemarks') <span class="text-red-600 text-xs block mt-1">{{ $message }}</span> @enderror
                </div>
                <div class="flex justify-end gap-2 pt-2 border-t">
                    <button type="button" wire:click="closeModals" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold rounded-xl transition">Batal</button>
                    <button type="button" wire:click="unlockStructure" wire:loading.attr="disabled" wire:target="unlockStructure" class="px-4 py-2 bg-emerald-700 hover:bg-emerald-800 text-white text-xs font-bold rounded-xl transition disabled:opacity-50">Buka Kunci</button>
                </div>
            </div>
        </div>
    @endif

    {{-- Modal: Rekod JPICT --}}
    @if ($showJpictModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-sm p-4">
            <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg p-6 space-y-4" role="dialog" aria-modal="true" aria-labelledby="tajuk-jpict">
                <h3 id="tajuk-jpict" class="text-base font-bold text-slate-800 border-b pb-2">Rekod Kelulusan JPICT</h3>
                <p class="text-[11px] text-slate-500">Rekod ini pilihan. Penanda "Menunggu JPICT" dibuang selepas tarikh kelulusan direkodkan.</p>
                <div>
                    <label for="tarikh-jpict" class="block text-xs font-bold text-slate-700">Tarikh Kelulusan JPICT <span class="text-red-600">*</span></label>
                    <input id="tarikh-jpict" type="date" wire:model="jpictDate" max="{{ today()->toDateString() }}" class="mt-1 {{ $inputClass }}">
                    @error('jpictDate') <span class="text-red-600 text-xs block mt-1">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label for="rujukan-jpict" class="block text-xs font-bold text-slate-700">Rujukan Mesyuarat / Surat (pilihan)</label>
                    <input id="rujukan-jpict" type="text" wire:model="jpictReference" maxlength="100" class="mt-1 {{ $inputClass }}">
                    @error('jpictReference') <span class="text-red-600 text-xs block mt-1">{{ $message }}</span> @enderror
                </div>
                <div class="flex justify-end gap-2 pt-2 border-t">
                    <button type="button" wire:click="closeModals" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold rounded-xl transition">Batal</button>
                    <button type="button" wire:click="saveJpict" wire:loading.attr="disabled" wire:target="saveJpict" class="px-4 py-2 bg-amber-700 hover:bg-amber-800 text-white text-xs font-bold rounded-xl transition disabled:opacity-50">Simpan</button>
                </div>
            </div>
        </div>
    @endif
</div>
