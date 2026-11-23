<div class="max-w-7xl mx-auto p-6 space-y-6">
    <div class="flex flex-col md:flex-row justify-between items-center gap-4 bg-white p-6 rounded-2xl shadow-sm border border-slate-100">
        <div>
            <h2 class="text-xl font-black text-slate-900">Senarai Mesyuarat</h2>
            <p class="text-xs text-slate-500 mt-0.5">Mesyuarat yang telah didaftarkan oleh Urus Setia.</p>
        </div>
        <a href="{{ route('meetings.create') }}" class="px-4 py-2.5 bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold rounded-xl shadow-md transition">
            + Daftar Mesyuarat
        </a>
    </div>

    @if (session()->has('message'))
        <div class="p-4 bg-emerald-50 border-l-4 border-emerald-500 text-emerald-700 text-xs rounded-r-xl">
            {{ session('message') }}
        </div>
    @endif

    @if (session()->has('error'))
        <div class="p-4 bg-red-50 border-l-4 border-red-500 text-red-700 text-xs rounded-r-xl">
            {{ session('error') }}
        </div>
    @endif

    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden space-y-4 p-4">
        <div class="grid grid-cols-1 md:grid-cols-5 gap-3">
            <input type="text" wire:model.live.debounce.300ms="search" placeholder="Cari Tempat..." class="text-xs border-slate-200 rounded-xl shadow-sm focus:border-blue-500 focus:ring-blue-500">

            <select wire:model.live="meetingNameId" class="text-xs border-slate-200 rounded-xl shadow-sm focus:border-blue-500 focus:ring-blue-500">
                <option value="">Semua Nama Mesyuarat</option>
                @foreach ($meetingNames as $meetingName)
                    <option value="{{ $meetingName->id }}">{{ $meetingName->code }}</option>
                @endforeach
            </select>

            <select wire:model.live="meetingYearId" class="text-xs border-slate-200 rounded-xl shadow-sm focus:border-blue-500 focus:ring-blue-500">
                <option value="">Semua Tahun</option>
                @foreach ($meetingYears as $meetingYear)
                    <option value="{{ $meetingYear->id }}">{{ $meetingYear->year }}</option>
                @endforeach
            </select>

            <select wire:model.live="status" class="text-xs border-slate-200 rounded-xl shadow-sm focus:border-blue-500 focus:ring-blue-500">
                <option value="">Semua Status</option>
                <option value="1">Aktif</option>
                <option value="0">Tidak Aktif</option>
            </select>

            <button type="button" wire:click="resetFilters"
                    @disabled($search === '' && $meetingNameId === '' && $meetingYearId === '' && $status === '')
                    class="px-4 py-2 bg-slate-100 hover:bg-slate-200 disabled:opacity-50 disabled:cursor-not-allowed text-slate-700 text-xs font-bold rounded-xl transition">
                ↺ Set Semula
            </button>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-50 text-slate-600 font-bold border-b border-slate-100 uppercase">
                    <tr>
                        <th class="p-3.5">Mesyuarat</th>
                        <th class="p-3.5">Tarikh</th>
                        <th class="p-3.5">Masa</th>
                        <th class="p-3.5">Tempat</th>
                        <th class="p-3.5">Minit Mesyuarat</th>
                        <th class="p-3.5">Tutup Permohonan</th>
                        <th class="p-3.5">Status</th>
                        <th class="p-3.5 text-center">Tindakan</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($meetings as $meeting)
                        <tr class="hover:bg-slate-50/80 transition" wire:key="meeting-{{ $meeting->uuid }}">
                            <td class="p-3.5">
                                <p class="font-bold text-blue-600">{{ $meeting->label() }}</p>
                                <p class="text-[11px] text-slate-500">{{ $meeting->meetingName?->name }}</p>
                            </td>
                            <td class="p-3.5 text-slate-800 whitespace-nowrap">{{ $meeting->meeting_date?->format('d/m/Y') ?? '-' }}</td>
                            <td class="p-3.5 text-slate-800 whitespace-nowrap">{{ $meeting->meetingTimeLabel() }}</td>
                            <td class="p-3.5 text-slate-800">{{ $meeting->venue ?? '-' }}</td>
                            <td class="p-3.5">
                                @if ($meeting->hasMinutes())
                                    <a href="{{ route('meetings.minutes', $meeting->uuid) }}" target="_blank" class="inline-flex items-center gap-1 px-2.5 py-1 bg-blue-50 text-blue-700 border border-blue-200 hover:bg-blue-100 rounded-lg font-bold">
                                        📄 Lihat Minit
                                    </a>
                                @else
                                    <span class="text-slate-400">Tiada</span>
                                @endif
                            </td>
                            <td class="p-3.5 whitespace-nowrap">
                                <span class="text-slate-800">{{ $meeting->submission_closes_at?->format('d/m/Y') ?? '-' }}</span>
                                @if ($meeting->isAcceptingApplicationsToday())
                                    <span class="block mt-1 w-fit px-2 py-0.5 bg-blue-50 text-blue-700 font-bold rounded-full text-[10px]">Dibuka</span>
                                @endif
                            </td>
                            <td class="p-3.5">
                                @if ($meeting->is_active)
                                    <span class="px-2.5 py-1 bg-emerald-50 text-emerald-700 font-bold rounded-full text-[10px]">Aktif</span>
                                @else
                                    <span class="px-2.5 py-1 bg-slate-100 text-slate-600 font-bold rounded-full text-[10px]">Tidak Aktif</span>
                                @endif
                            </td>
                            <td class="p-3.5 text-center whitespace-nowrap space-x-1">
                                <a href="{{ route('meetings.edit', $meeting->uuid) }}" class="inline-block px-3 py-1 bg-amber-50 text-amber-700 border border-amber-200 hover:bg-amber-100 rounded-lg font-bold text-xs transition">
                                    Kemaskini
                                </a>
                                <button wire:click="delete('{{ $meeting->uuid }}')" wire:confirm="Adakah anda pasti mahu soft delete mesyuarat ini?" class="px-3 py-1 bg-red-50 text-red-700 border border-red-200 hover:bg-red-100 rounded-lg font-bold text-xs transition">
                                    Hapus
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="p-8 text-center text-slate-400">Tiada mesyuarat dijumpai.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div>{{ $meetings->links() }}</div>
    </div>
</div>
