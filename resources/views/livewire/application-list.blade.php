<div class="bg-white border border-slate-200 rounded-[14px] overflow-hidden">
    @php
        $statusBadges = [
            'DRAFT' => ['label' => 'Draf', 'class' => 'bg-amber-100 text-amber-800'],
            'SUBMITTED' => ['label' => 'Dihantar', 'class' => 'bg-blue-100 text-blue-800'],
            'IN_REVIEW' => ['label' => 'Dalam Semakan', 'class' => 'bg-indigo-100 text-indigo-800'],
            'NEEDS_AMENDMENT' => ['label' => 'Perlu Pembetulan', 'class' => 'bg-purple-100 text-purple-800'],
            'APPROVED' => ['label' => 'Lulus', 'class' => 'bg-emerald-100 text-emerald-800'],
            'REJECTED' => ['label' => 'Ditolak', 'class' => 'bg-red-100 text-red-800'],
        ];
    @endphp

    <!-- Carian & Tapis Status -->
    <div class="px-5 py-4 flex flex-col sm:flex-row gap-3 border-b border-slate-200">
        <div class="relative flex-1 sm:max-w-sm">
            <svg aria-hidden="true" class="pointer-events-none absolute left-3 top-[11px] w-[18px] h-[18px] text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="7" /><path d="m20 20-3.5-3.5" /></svg>
            <label for="carian-permohonan" class="sr-only">Cari permohonan</label>
            <input id="carian-permohonan" type="search" wire:model.live.debounce.300ms="search" placeholder="Cari No. Rujukan / Nama Projek…"
                class="w-full h-10 pl-[38px] pr-3 text-sm border-slate-300 rounded-[10px] focus:border-green-700 focus:ring-green-700/20">
        </div>
        {{-- Urus Setia hanya melihat permohonan berstatus Dihantar, jadi tapisan status tidak dipaparkan. --}}
        @unless ($isUrusSetia)
            <div class="sm:w-56">
                <label for="tapis-status" class="sr-only">Tapis status</label>
                <select id="tapis-status" wire:model.live="statusFilter" class="w-full h-10 text-sm border-slate-300 rounded-[10px] focus:border-green-700 focus:ring-green-700/20">
                    <option value="">Semua status</option>
                    <option value="DRAFT">Draf</option>
                    <option value="SUBMITTED">Dihantar</option>
                    <option value="NEEDS_AMENDMENT">Perlu Pembetulan</option>
                    <option value="APPROVED">Lulus</option>
                    <option value="REJECTED">Ditolak</option>
                </select>
            </div>
        @endunless
    </div>

    <!-- Jadual Senarai Permohonan -->
    {{-- Urus Setia: jadual muat tanpa tatal mendatar. Lajur yang tidak muat pada skrin kecil
         disembunyikan dan maklumatnya dipaparkan di bawah Nama Projek. --}}
    @php
        $fit = $isUrusSetia;
        $refCol = $fit ? 'hidden sm:table-cell' : '';
        $categoryCol = $fit ? 'hidden lg:table-cell' : '';
        $costCol = $fit ? 'hidden sm:table-cell' : '';
    @endphp
    <div class="overflow-x-auto">
        <table @class(['w-full text-left text-sm', 'min-w-[820px]' => ! $fit])>
            <thead class="bg-slate-50 border-b border-slate-200">
                <tr class="text-xs font-bold text-slate-600 whitespace-nowrap">
                    <th scope="col" class="px-4 py-3 {{ $refCol }}">No. Rujukan</th>
                    <th scope="col" class="px-4 py-3">Nama Projek</th>
                    <th scope="col" class="px-4 py-3 {{ $categoryCol }}">Kategori</th>
                    <th scope="col" class="px-4 py-3 text-right {{ $costCol }}">Anggaran Kos (RM)</th>
                    <th scope="col" class="px-4 py-3">Status</th>
                    {{-- Jadual "Permohonan untuk Semakan" (Urus Setia) tidak memaparkan tarikh kemaskini. --}}
                    @unless ($isUrusSetia)
                        <th scope="col" class="px-4 py-3">Kemaskini</th>
                    @endunless
                    <th scope="col" class="px-4 py-3"><span class="sr-only">Tindakan</span></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($applications as $app)
                    <tr wire:key="app-{{ $app->id }}" class="hover:bg-slate-50 transition">
                        @php($category = $app->applicationCategoryMaster?->name ?? $app->application_category ?? '-')
                        <td class="px-4 py-3.5 font-bold whitespace-nowrap tabular-nums {{ $refCol }}">{{ $app->reference_number }}</td>
                        @if ($fit)
                            <td class="px-4 py-3.5">
                                <span class="flex flex-col gap-0.5">
                                    <span class="text-xs font-bold tabular-nums sm:hidden">{{ $app->reference_number }}</span>
                                    <span class="font-semibold text-slate-900 break-words">{{ $app->title }}</span>
                                    <span class="text-xs text-slate-500 lg:hidden">
                                        {{ $category }}<span class="sm:hidden"> · RM {{ number_format($app->estimated_cost, 2) }}</span>
                                    </span>
                                </span>
                            </td>
                        @else
                            <td class="px-4 py-3.5 font-semibold text-slate-900 max-w-xs truncate" title="{{ $app->title }}">{{ $app->title }}</td>
                        @endif
                        <td class="px-4 py-3.5 text-slate-600 {{ $categoryCol }}">{{ $category }}</td>
                        <td class="px-4 py-3.5 text-right tabular-nums whitespace-nowrap {{ $costCol }}">{{ number_format($app->estimated_cost, 2) }}</td>
                        <td class="px-4 py-3.5">
                            @php($badge = $statusBadges[$app->status] ?? ['label' => $app->status, 'class' => 'bg-gray-100 text-gray-800'])
                            <span class="inline-flex px-2.5 py-1 rounded-full text-xs font-bold whitespace-nowrap {{ $badge['class'] }}">{{ $badge['label'] }}</span>
                        </td>
                        @unless ($isUrusSetia)
                            <td class="px-4 py-3.5 text-slate-500 whitespace-nowrap tabular-nums">{{ $app->updated_at ? $app->updated_at->format('d/m/Y H:i') : '-' }}</td>
                        @endunless
                        <td class="px-4 py-3.5 text-right whitespace-nowrap">
                            @can('update', $app)
                                <a href="{{ route('application.edit', $app->uuid) }}" class="inline-flex items-center min-h-8 text-[13px] font-bold text-green-900 hover:underline">Kemaskini</a>
                            @else
                                <a href="{{ route('application.view', $app->uuid) }}" class="inline-flex items-center min-h-8 text-[13px] font-bold text-green-900 hover:underline">{{ $isUrusSetia ? 'Semak' : 'Papar' }}</a>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ $isUrusSetia ? 6 : 7 }}" class="px-5 py-10 text-center text-sm text-slate-500">Tiada rekod permohonan dijumpai.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($applications->hasPages())
        <div class="px-5 py-4 border-t border-slate-100">
            {{ $applications->links() }}
        </div>
    @endif
</div>
