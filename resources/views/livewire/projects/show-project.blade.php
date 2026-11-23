@php
    $project = $this->project;
    $application = $project->application;
    $user = auth()->user();
    $canManage = $user->can('manage', $project);
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

    @if (! $canManage && ! $project->isReadOnly())
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
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($phase->activities as $activity)
                            @php($status = $activity->statusOn(today()))
                            <tr wire:key="aktiviti-{{ $activity->id }}">
                                <td class="p-3 tabular-nums text-slate-500">{{ $activity->sequence }}</td>
                                <td class="p-3 font-semibold text-slate-800">{{ $activity->name }}</td>
                                <td class="p-3 text-right tabular-nums">{{ number_format((float) $activity->weight, 2) }}</td>
                                <td class="p-3 text-slate-600">{{ $activity->requires_document ? ($activity->document_label ?? 'Ya') : '-' }}</td>
                                <td class="p-3 tabular-nums">{{ $activity->target_date?->format('d/m/Y') ?? '-' }}</td>
                                <td class="p-3 tabular-nums">{{ $activity->actual_completion_date?->format('d/m/Y') ?? '-' }}</td>
                                <td class="p-3"><span class="inline-flex px-2 py-0.5 rounded-md text-[11px] font-bold ring-1 {{ $status->badgeClasses() }}">{{ $status->label() }}</span></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endforeach

    {{-- Sejarah pindaan jadual --}}
    <section class="bg-white p-6 rounded-2xl shadow-sm border border-slate-100 space-y-3">
        <h3 class="text-sm font-bold text-slate-800">Sejarah Pindaan Jadual</h3>
        @forelse ($this->scheduleHistory as $batch => $changes)
            @php($first = $changes->first())
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
