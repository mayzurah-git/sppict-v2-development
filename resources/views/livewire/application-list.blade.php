<div class="space-y-4">
    <!-- Carian & Tapis Status -->
    <div class="flex flex-col md:flex-row justify-between items-center gap-3 bg-white p-4 rounded-xl shadow-sm border border-gray-100">
        <div class="w-full md:w-1/3">
            <input type="text" wire:model.live.debounce.300ms="search" placeholder="Cari No. Rujukan / Tajuk Projek..." class="w-full text-xs border-gray-300 rounded-lg shadow-sm focus:border-blue-500 focus:ring-blue-500">
        </div>
        <div class="w-full md:w-1/4">
            <select wire:model.live="statusFilter" class="w-full text-xs border-gray-300 rounded-lg shadow-sm focus:border-blue-500 focus:ring-blue-500">
                <option value="">-- Semua Status --</option>
                <option value="DRAFT">Draf</option>
                <option value="SUBMITTED">Dihantar</option>
                <option value="IN_REVIEW">Dalam Semakan</option>
                <option value="AMENDMENT_REQUIRED">Pembetulan</option>
                <option value="APPROVED">Lulus</option>
                <option value="REJECTED">Ditolak</option>
            </select>
        </div>
    </div>

    <!-- Jadual Senarai Permohonan -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-50 text-slate-700 font-bold border-b border-slate-200">
                    <tr>
                        <th class="p-3.5">No. Rujukan</th>
                        <th class="p-3.5">Tajuk Permohonan Projek</th>
                        <th class="p-3.5">Kategori</th>
                        <th class="p-3.5 text-right">Anggaran Kos (RM)</th>
                        <th class="p-3.5 text-center">Status</th>
                        <th class="p-3.5 text-center">Tarikh Kemaskini</th>
                        <th class="p-3.5 text-center">Tindakan</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($applications as $app)
                        <tr class="hover:bg-slate-50 transition">
                            <td class="p-3.5 font-bold text-blue-600">
                                {{ $app->reference_number }}
                            </td>
                            <td class="p-3.5 font-medium text-slate-800 max-w-xs truncate" title="{{ $app->title }}">
                                {{ $app->title }}
                            </td>
                            <td class="p-3.5 text-slate-500">
                                {{ $app->project_category }}
                            </td>
                            <td class="p-3.5 text-right font-bold text-slate-900">
                                {{ number_format($app->estimated_cost, 2) }}
                            </td>
                            <td class="p-3.5 text-center">
                                @switch($app->status)
                                    @case('DRAFT')
                                        <span class="px-2.5 py-1 bg-amber-100 text-amber-800 font-bold rounded-full text-[10px]">Draf</span>
                                        @break
                                    @case('SUBMITTED')
                                        <span class="px-2.5 py-1 bg-blue-100 text-blue-800 font-bold rounded-full text-[10px]">Dihantar</span>
                                        @break
                                    @case('IN_REVIEW')
                                        <span class="px-2.5 py-1 bg-indigo-100 text-indigo-800 font-bold rounded-full text-[10px]">Dalam Semakan</span>
                                        @break
                                    @case('AMENDMENT_REQUIRED')
                                        <span class="px-2.5 py-1 bg-purple-100 text-purple-800 font-bold rounded-full text-[10px]">Perlu Pembetulan</span>
                                        @break
                                    @case('APPROVED')
                                        <span class="px-2.5 py-1 bg-emerald-100 text-emerald-800 font-bold rounded-full text-[10px]">Lulus</span>
                                        @break
                                    @case('REJECTED')
                                        <span class="px-2.5 py-1 bg-red-100 text-red-800 font-bold rounded-full text-[10px]">Ditolak</span>
                                        @break
                                    @default
                                        <span class="px-2.5 py-1 bg-gray-100 text-gray-800 font-bold rounded-full text-[10px]">{{ $app->status }}</span>
                                @endswitch
                            </td>
                            <td class="p-3.5 text-center text-slate-500">
                                {{ $app->updated_at ? $app->updated_at->format('d/m/Y H:i') : '-' }}
                            </td>
                            <td class="p-3.5 text-center whitespace-nowrap">
                                @if ($app->status === 'DRAFT')
                                    <a href="{{ route('application.edit', $app->uuid) }}" class="px-3 py-1.5 bg-amber-500 hover:bg-amber-600 text-white font-bold rounded-lg text-xs shadow-sm transition inline-flex items-center space-x-1">
                                        <span>✏️</span>
                                        <span>Kemaskini</span>
                                    </a>
                                @else
                                    <a href="{{ route('application.view', $app->uuid) }}" class="px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold rounded-lg text-xs transition inline-flex items-center space-x-1">
                                        <span>👁️</span>
                                        <span>Papar</span>
                                    </a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="p-8 text-center text-slate-400">
                                Tiada rekod permohonan dijumpai.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="p-4 border-t border-slate-100">
            {{ $applications->links() }}
        </div>
    </div>
</div>
