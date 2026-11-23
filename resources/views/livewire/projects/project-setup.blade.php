@php
    $application = $this->application;
    $template = $this->template;
    $isStructureEdit = $projectUuid !== null;
    $existingDates = $this->existingDates;
    $completedKeys = $this->completedKeys;
    $inputClass = 'block w-full text-xs border-slate-200 rounded-lg shadow-sm focus:border-sky-700 focus:ring-sky-700/20';
@endphp

<div class="max-w-6xl mx-auto p-6 space-y-6">
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 bg-white p-6 rounded-2xl shadow-sm border border-slate-100">
        <div>
            <div class="flex flex-wrap items-center gap-2">
                <span class="text-xs font-bold text-sky-700 bg-sky-50 px-2.5 py-1 rounded-md">{{ $application->reference_number }}</span>
                <span class="text-xs font-bold text-emerald-700 bg-emerald-50 px-2.5 py-1 rounded-md">Lulus JTICT</span>
            </div>
            <h2 class="text-xl font-black text-slate-800 mt-2">{{ $isStructureEdit ? 'Pinda Struktur Projek' : 'Mulakan Pemantauan Projek' }}</h2>
            <p class="text-sm font-semibold text-slate-700 mt-0.5">{{ $application->title }}</p>
            <p class="text-xs text-slate-500 mt-0.5">{{ $application->agency?->name }}</p>
        </div>
        <a href="{{ $isStructureEdit ? route('projects.show', $projectUuid) : route('projects.index') }}" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold rounded-xl transition">← Kembali</a>
    </div>

    @if (! $template)
        <div class="p-4 bg-red-50 border border-red-200 text-red-800 text-xs rounded-xl">
            Tiada templat pemberat aktif bagi Jenis Perolehan permohonan ini ({{ $application->procurementType?->name ?? $application->procurement_type ?? '-' }}). Pemantauan tidak boleh dimulakan. Sila hubungi Urus Setia.
        </div>
    @else
        <form wire:submit="save" class="space-y-6">
            @error('template') <div class="p-3 bg-red-50 border border-red-200 text-red-700 text-xs font-semibold rounded-xl">{{ $message }}</div> @enderror
            @error('phases') <div class="p-3 bg-red-50 border border-red-200 text-red-700 text-xs font-semibold rounded-xl">{{ $message }}</div> @enderror

            {{-- Templat & jenis projek --}}
            <section class="bg-white p-6 rounded-2xl shadow-sm border border-slate-100 space-y-4">
                <div>
                    <h3 class="text-sm font-bold text-slate-800">Templat Pemberat</h3>
                    <p class="text-xs text-slate-600 mt-1">Templat {{ $template->code }} – {{ $template->name }} (versi {{ $template->version }}). Ditentukan oleh Jenis Perolehan permohonan dan tidak boleh diubah.</p>
                </div>

                @if ($template->allows_phases)
                    <fieldset>
                        <legend class="text-xs font-bold text-slate-700">Jenis Projek</legend>
                        <div class="mt-2 flex flex-col sm:flex-row gap-3">
                            <label class="flex items-center gap-2 text-xs">
                                <input type="radio" wire:model.live="isPhased" value="0" @checked(! $isPhased) class="text-sky-700 focus:ring-sky-700"> Tidak Berfasa
                            </label>
                            <label class="flex items-center gap-2 text-xs">
                                <input type="radio" wire:model.live="isPhased" value="1" @checked($isPhased) class="text-sky-700 focus:ring-sky-700"> Berfasa
                            </label>
                        </div>
                        <p class="text-[11px] text-slate-500 mt-1">Projek Berfasa: setiap fasa dikira 100% berdasarkan aktiviti yang dipilih; kemajuan projek ialah purata kemajuan semua fasa.</p>
                        @error('is_phased') <span class="text-red-600 text-xs block mt-1">{{ $message }}</span> @enderror
                    </fieldset>
                @endif

                <p class="text-[11px] text-slate-500">Struktur fasa dan aktiviti boleh dipinda sehingga aktiviti pertama ditanda siap. Selepas itu struktur dikunci dan hanya Urus Setia boleh membukanya semula.</p>
            </section>

            {{-- Tempoh projek --}}
            <section class="bg-white p-6 rounded-2xl shadow-sm border border-slate-100 space-y-3">
                <h3 class="text-sm font-bold text-slate-800">Tempoh Projek</h3>
                @if ($isStructureEdit)
                    <p class="text-xs text-slate-700">
                        {{ \Illuminate\Support\Carbon::parse($plannedStartDate)->format('d/m/Y') }} – {{ \Illuminate\Support\Carbon::parse($plannedEndDate)->format('d/m/Y') }}
                    </p>
                    <p class="text-[11px] text-slate-500">Tarikh projek dan tarikh sasaran aktiviti sedia ada dipinda melalui <span class="font-semibold">Pinda Jadual</span> (justifikasi wajib). Hanya aktiviti baharu memerlukan tarikh sasaran di sini.</p>
                @else
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 max-w-xl">
                        <div>
                            <label for="tarikh-mula" class="block text-xs font-bold text-slate-700">Tarikh Mula Projek <span class="text-red-600">*</span></label>
                            <input id="tarikh-mula" type="date" wire:model.live="plannedStartDate" class="mt-1 {{ $inputClass }}" required>
                            @error('planned_start_date') <span class="text-red-600 text-xs block mt-1">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label for="tarikh-tamat" class="block text-xs font-bold text-slate-700">Tarikh Tamat Projek <span class="text-red-600">*</span></label>
                            <input id="tarikh-tamat" type="date" wire:model.live="plannedEndDate" min="{{ $plannedStartDate }}" class="mt-1 {{ $inputClass }}" required>
                            @error('planned_end_date') <span class="text-red-600 text-xs block mt-1">{{ $message }}</span> @enderror
                        </div>
                    </div>
                    <p class="text-[11px] text-slate-500">Setiap tarikh sasaran aktiviti mesti dalam tempoh projek.</p>
                @endif
            </section>

            {{-- Fasa & aktiviti --}}
            @foreach ($phases as $index => $phase)
                @php
                    $number = $index + 1;
                    $selectedWeight = $templateActivities->filter(fn ($a) => ! empty($phase['activities'][$a->id]['selected']))->sum(fn ($a) => (float) $a->weight);
                @endphp
                <section wire:key="fasa-{{ $index }}" class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
                    <div class="p-4 border-b border-slate-100 flex flex-col md:flex-row md:items-center justify-between gap-3">
                        <div class="flex-1">
                            @if ($isPhased)
                                <label for="nama-fasa-{{ $index }}" class="block text-xs font-bold text-slate-700">Fasa {{ $number }}</label>
                                <input id="nama-fasa-{{ $index }}" type="text" wire:model="phases.{{ $index }}.name" maxlength="100" placeholder="Nama fasa (pilihan), contoh: Modul Pendaftaran" class="mt-1 max-w-md {{ $inputClass }}">
                                @error("phases.{$index}.name") <span class="text-red-600 text-xs block mt-1">{{ $message }}</span> @enderror
                            @else
                                <h3 class="text-sm font-bold text-slate-800">Aktiviti Projek</h3>
                            @endif
                            <p class="text-[11px] text-slate-500 mt-1">
                                Jumlah pemberat {{ $isPhased ? 'dipilih' : '' }}: <span class="font-bold tabular-nums">{{ number_format($selectedWeight, 2) }}</span>
                                @if ($isPhased && $selectedWeight > 0 && abs($selectedWeight - 100) > 0.001)
                                    (dikira secara berkadar kepada 100% bagi fasa ini)
                                @endif
                            </p>
                            @error("phases.{$index}.activities") <span class="text-red-600 text-xs block mt-1">{{ $message }}</span> @enderror
                        </div>
                        @if ($isPhased && count($phases) > 2)
                            <button type="button" wire:click="removePhase({{ $index }})" wire:confirm="Keluarkan Fasa {{ $number }}?" class="shrink-0 px-3 py-1.5 text-xs font-bold text-red-700 bg-red-50 hover:bg-red-100 rounded-lg transition">Keluarkan Fasa</button>
                        @endif
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[720px] text-left text-xs">
                            <thead class="bg-slate-50 text-slate-600 font-bold border-b border-slate-100">
                                <tr>
                                    @if ($isPhased)<th scope="col" class="p-3 w-14">Pilih</th>@endif
                                    <th scope="col" class="p-3 w-10">#</th>
                                    <th scope="col" class="p-3">Aktiviti</th>
                                    <th scope="col" class="p-3 text-right">Pemberat (%)</th>
                                    <th scope="col" class="p-3">Dokumen Wajib</th>
                                    <th scope="col" class="p-3 w-48">Tarikh Sasaran</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach ($templateActivities as $activity)
                                    @php
                                        $selected = ! empty($phase['activities'][$activity->id]['selected']);
                                        $key = $number . ':' . $activity->id;
                                        $isExisting = array_key_exists($key, $existingDates);
                                        $isCompleted = in_array($key, $completedKeys, true);
                                    @endphp
                                    <tr wire:key="fasa-{{ $index }}-aktiviti-{{ $activity->id }}" @class(['bg-sky-50/40' => $isPhased && $selected])>
                                        @if ($isPhased)
                                            <td class="p-3">
                                                <input type="checkbox" wire:model.live="phases.{{ $index }}.activities.{{ $activity->id }}.selected" @disabled($isCompleted)
                                                    aria-label="Pilih {{ $activity->name }} bagi Fasa {{ $number }}" class="rounded text-sky-700 focus:ring-sky-700">
                                            </td>
                                        @endif
                                        <td class="p-3 tabular-nums text-slate-500">{{ $activity->sequence }}</td>
                                        <td class="p-3 font-semibold text-slate-800">
                                            {{ $activity->name }}
                                            @if ($isCompleted)<span class="ml-1 text-[11px] font-bold text-emerald-700">(telah ditanda siap)</span>@endif
                                        </td>
                                        <td class="p-3 text-right tabular-nums">{{ number_format((float) $activity->weight, 2) }}</td>
                                        <td class="p-3 text-slate-600">{{ $activity->requires_document ? ($activity->document_label ?? 'Ya') : '-' }}</td>
                                        <td class="p-3">
                                            @if (! $selected)
                                                <span class="text-slate-400">-</span>
                                            @elseif ($isExisting)
                                                <span class="tabular-nums">{{ $existingDates[$key] ? \Illuminate\Support\Carbon::parse($existingDates[$key])->format('d/m/Y') : '-' }}</span>
                                            @else
                                                <input type="date" wire:model="phases.{{ $index }}.activities.{{ $activity->id }}.target_date"
                                                    min="{{ $plannedStartDate }}" max="{{ $plannedEndDate }}" aria-label="Tarikh sasaran {{ $activity->name }}" class="{{ $inputClass }}">
                                            @endif
                                            @error("phases.{$index}.activities.{$activity->id}") <span class="text-red-600 text-xs block mt-1">{{ $message }}</span> @enderror
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </section>
            @endforeach

            @if ($isPhased && count($phases) < \App\Models\Project::MAX_PHASES)
                <button type="button" wire:click="addPhase" class="px-4 py-2 bg-white border border-sky-200 text-sky-800 hover:bg-sky-50 text-xs font-bold rounded-xl transition">+ Tambah Fasa</button>
            @endif

            <div class="flex justify-end gap-2">
                <a href="{{ $isStructureEdit ? route('projects.show', $projectUuid) : route('projects.index') }}" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold rounded-xl transition">Batal</a>
                <button type="submit" wire:loading.attr="disabled" wire:target="save" class="px-4 py-2 bg-sky-700 hover:bg-sky-800 text-white text-xs font-bold rounded-xl transition disabled:opacity-50">
                    {{ $isStructureEdit ? 'Simpan Struktur' : 'Mulakan Pemantauan' }}
                </button>
            </div>
        </form>
    @endif
</div>
