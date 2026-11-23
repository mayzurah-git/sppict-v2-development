@php
    $project = $this->project;
    $report = $this->report;
    $canEdit = $this->canEdit;
    $inputClass = 'block w-full text-xs border-slate-200 rounded-lg shadow-sm focus:border-sky-700 focus:ring-sky-700/20';
@endphp

<div class="max-w-5xl mx-auto p-6 space-y-6">
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 bg-white p-6 rounded-2xl shadow-sm border border-slate-100">
        <div>
            <div class="flex flex-wrap items-center gap-2">
                <span class="text-xs font-bold text-sky-700 bg-sky-50 px-2.5 py-1 rounded-md">{{ $project->application?->reference_number ?? $project->legacy_reference ?? '-' }}</span>
                <span class="text-xs font-bold px-2.5 py-1 rounded-md {{ $report->isSubmitted() ? 'text-emerald-800 bg-emerald-50' : 'text-slate-700 bg-slate-100' }}">{{ $report->status->label() }}</span>
                @if ($report->is_late)
                    <span class="text-xs font-bold text-red-800 bg-red-50 ring-1 ring-red-200 px-2.5 py-1 rounded-md">Lewat</span>
                @endif
            </div>
            <h2 class="text-xl font-black text-slate-800 mt-2">Laporan Kemajuan {{ $report->periodLabel() }}</h2>
            <p class="text-sm font-semibold text-slate-700 mt-0.5">{{ $project->application?->title ?? '-' }}</p>
            <dl class="mt-1.5 flex flex-wrap gap-x-5 gap-y-1 text-xs">
                <div class="flex gap-1"><dt class="text-slate-400">Tarikh akhir:</dt>
                    <dd class="font-semibold text-slate-700">
                        {{ $report->due_date?->format('d/m/Y') ?? 'Belum ditetapkan' }}
                        @if ($report->dueMeeting) <span class="font-normal text-slate-500">({{ $report->dueMeeting->label() }})</span> @endif
                    </dd>
                </div>
                @if ($report->isSubmitted())
                    <div class="flex gap-1"><dt class="text-slate-400">Dihantar:</dt><dd class="font-semibold text-slate-700">{{ $report->submitted_at?->format('d/m/Y h:i A') }} · {{ $report->submitter?->name ?? '-' }}</dd></div>
                    <div class="flex gap-1"><dt class="text-slate-400">Tarikh penilaian:</dt><dd class="font-semibold text-slate-700 tabular-nums">{{ $report->evaluation_date?->format('d/m/Y') }}</dd></div>
                @endif
            </dl>
        </div>
        <a href="{{ route('projects.show', $project->uuid) }}" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold rounded-xl transition">← Maklumat Projek</a>
    </div>

    @if (session()->has('message'))
        <div class="p-3 bg-emerald-50 border border-emerald-200 text-emerald-700 text-xs font-semibold rounded-xl">{{ session('message') }}</div>
    @endif
    @if (session()->has('error'))
        <div class="p-3 bg-red-50 border border-red-200 text-red-700 text-xs font-semibold rounded-xl">{{ session('error') }}</div>
    @endif
    @error('report') <div class="p-3 bg-red-50 border border-red-200 text-red-700 text-xs font-semibold rounded-xl">{{ $message }}</div> @enderror

    @if (! $report->isSubmitted() && ! $canEdit)
        <div class="p-3 bg-slate-50 border border-slate-200 text-slate-600 text-xs rounded-xl">Laporan ini masih Draf dan belum dihantar oleh pegawai projek.</div>
    @endif

    {{-- Kandungan laporan --}}
    <section class="bg-white p-6 rounded-2xl shadow-sm border border-slate-100 space-y-4">
        <h3 class="text-sm font-bold text-slate-800">Kandungan Laporan</h3>
        @foreach ([
            'work_summary' => ['Ringkasan Kemajuan', true, 'Kerja yang telah dilaksanakan dalam bulan ini.'],
            'issues_and_challenges' => ['Isu dan Kekangan', false, 'Contoh: kelewatan pembekal, isu teknikal.'],
            'corrective_action_plan' => ['Tindakan Susulan', false, 'Tindakan untuk menangani isu dan kekangan.'],
        ] as $field => [$label, $required, $hint])
            <div>
                <label for="{{ $field }}" class="block text-xs font-bold text-slate-700">{{ $label }} @if ($required)<span class="text-red-600">*</span>@endif</label>
                @if ($canEdit)
                    <textarea id="{{ $field }}" wire:model="{{ $field }}" rows="4" maxlength="5000" class="mt-1 {{ $inputClass }}" placeholder="{{ $hint }}"></textarea>
                    @error($field) <span class="text-red-600 text-xs block mt-1">{{ $message }}</span> @enderror
                @else
                    <p class="mt-1 text-xs text-slate-800 whitespace-pre-line">{{ $report->{$field} ?: '-' }}</p>
                @endif
            </div>
        @endforeach
    </section>

    {{-- Dokumen laporan --}}
    <section class="bg-white p-6 rounded-2xl shadow-sm border border-slate-100 space-y-3">
        <h3 class="text-sm font-bold text-slate-800">Dokumen Sokongan Laporan</h3>
        @forelse ($report->attachments as $document)
            <div wire:key="dok-laporan-{{ $document->id }}" class="flex items-center justify-between gap-2 p-2 rounded-lg bg-slate-50 text-xs">
                <a href="{{ route('projects.attachments.show', [$project->uuid, $document->uuid]) }}" target="_blank" rel="noopener" class="font-semibold text-sky-800 hover:underline break-all">📄 {{ $document->file_name }}</a>
                <span class="shrink-0 text-[11px] text-slate-500">{{ $document->created_at?->format('d/m/Y') }} · {{ $document->uploader?->name ?? '-' }}</span>
                @if ($canEdit)
                    <button type="button" wire:click="deleteDocument('{{ $document->uuid }}')" wire:confirm="Padam dokumen ini?" class="shrink-0 text-[11px] font-bold text-red-700 hover:underline">Padam</button>
                @endif
            </div>
        @empty
            <p class="text-xs text-slate-500">Tiada dokumen.</p>
        @endforelse
        @error('attachment') <span class="text-red-600 text-xs block">{{ $message }}</span> @enderror

        @if ($canEdit)
            <div class="flex flex-col sm:flex-row gap-2 sm:items-center">
                <label for="dokumen-laporan" class="sr-only">Pilih dokumen PDF</label>
                <input id="dokumen-laporan" type="file" wire:model="upload" accept="application/pdf,.pdf" class="block w-full text-xs text-slate-600 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:bg-slate-100 file:text-xs file:font-bold">
                <button type="button" wire:click="uploadDocument" wire:loading.attr="disabled" wire:target="upload,uploadDocument" class="shrink-0 px-3 py-1.5 bg-slate-700 hover:bg-slate-800 text-white text-xs font-bold rounded-lg transition disabled:opacity-50">Muat Naik</button>
            </div>
            <p class="text-[11px] text-slate-500">PDF sahaja, maksimum 25 MB. Pilihan.</p>
            @error('upload') <span class="text-red-600 text-xs block">{{ $message }}</span> @enderror
        @endif
    </section>

    {{-- Snapshot aktiviti --}}
    @if ($report->isSubmitted())
        <section class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
            <div class="p-4 border-b border-slate-100">
                <h3 class="text-sm font-bold text-slate-800">Pencapaian Aktiviti pada {{ $report->evaluation_date?->format('d/m/Y') }}</h3>
                <p class="text-[11px] text-slate-500 mt-0.5">Snapshot semasa laporan dihantar. Nilai ini tidak berubah walaupun aktiviti dikemas kini kemudian.</p>
                @if ($report->actual_progress_percent !== null)
                    <dl class="mt-3 flex flex-wrap gap-x-6 gap-y-2 text-xs">
                        <div><dt class="text-slate-500">Kemajuan Sebenar</dt><dd class="text-lg font-black tabular-nums">{{ number_format((float) $report->actual_progress_percent, 2) }}%</dd></div>
                        <div><dt class="text-slate-500">Kemajuan Dirancang</dt><dd class="text-lg font-black tabular-nums">{{ $report->planned_progress_percent === null ? '-' : number_format((float) $report->planned_progress_percent, 2) . '%' }}</dd></div>
                        <div>
                            <dt class="text-slate-500">Varians</dt>
                            <dd class="text-lg font-black tabular-nums">
                                @if ($report->variance_percent !== null)
                                    @php
                                        $variance = (float) $report->variance_percent;
                                    @endphp
                                    {{ sprintf('%+.2f', $variance) }}
                                    <span class="ml-1 align-middle inline-flex px-2 py-0.5 rounded-md text-[11px] font-bold ring-1 {{ \App\Enums\ScheduleStatus::indicatorClasses(\App\Enums\ScheduleStatus::indicatorFor($variance)) }}">{{ \App\Enums\ScheduleStatus::fromVariance($variance)->label() }}</span>
                                @else
                                    -
                                @endif
                            </dd>
                        </div>
                    </dl>
                @endif
            </div>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[720px] text-left text-xs">
                    <thead class="bg-slate-50 text-slate-600 font-bold border-b border-slate-100">
                        <tr>
                            @if ($project->is_phased)<th scope="col" class="p-3">Fasa</th>@endif
                            <th scope="col" class="p-3">Aktiviti</th>
                            <th scope="col" class="p-3 text-right">Pemberat (%)</th>
                            <th scope="col" class="p-3">Tarikh Sasaran</th>
                            <th scope="col" class="p-3">Dirancang Siap</th>
                            <th scope="col" class="p-3">Siap</th>
                            <th scope="col" class="p-3">Tarikh Sebenar Siap</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($report->activitySnapshots as $row)
                            <tr wire:key="snapshot-{{ $row->id }}">
                                @if ($project->is_phased)<td class="p-3 tabular-nums">{{ $row->phase_number }}</td>@endif
                                <td class="p-3 font-semibold text-slate-800">{{ $row->sequence }}. {{ $row->name }}</td>
                                <td class="p-3 text-right tabular-nums">{{ number_format((float) $row->weight, 2) }}</td>
                                <td class="p-3 tabular-nums">{{ $row->target_date?->format('d/m/Y') ?? '-' }}</td>
                                <td class="p-3">{{ $row->is_planned_due ? 'Ya' : 'Tidak' }}</td>
                                <td class="p-3 font-bold {{ $row->is_completed ? 'text-emerald-800' : 'text-slate-500' }}">{{ $row->is_completed ? 'Ya' : 'Tidak' }}</td>
                                <td class="p-3 tabular-nums">{{ $row->actual_completion_date?->format('d/m/Y') ?? '-' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif

    @if ($canEdit)
        <div class="flex flex-col sm:flex-row justify-end gap-2">
            <button type="button" wire:click="saveDraft" wire:loading.attr="disabled" wire:target="saveDraft" class="px-4 py-2 bg-white border border-slate-300 text-slate-700 hover:bg-slate-50 text-xs font-bold rounded-xl transition disabled:opacity-50">Simpan Draf</button>
            <button type="button" wire:click="submit" wire:confirm="Hantar laporan {{ $report->periodLabel() }}? Laporan yang dihantar tidak boleh diubah." wire:loading.attr="disabled" wire:target="submit" class="px-4 py-2 bg-sky-700 hover:bg-sky-800 text-white text-xs font-bold rounded-xl transition disabled:opacity-50">Hantar Laporan</button>
        </div>
        <p class="text-[11px] text-slate-500 text-right">Semasa dihantar, sistem merekod pencapaian setiap aktiviti pada tarikh penilaian (hari terakhir bulan laporan, atau hari ini jika bulan semasa).</p>
    @endif
</div>
