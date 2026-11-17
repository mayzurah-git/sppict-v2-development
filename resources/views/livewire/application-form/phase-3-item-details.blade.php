{{-- Ringkasan Kos Projek penuh dipaparkan di lajur kanan (application-form.blade.php). --}}
<div class="space-y-4" wire:key="phase-3-form">
    @php($summary = $form->costSummary())
    @php($sstRateLabel = rtrim(rtrim(number_format($summary['sst_rate'], 2), '0'), '.'))
    @php($overCeiling = $summary['ceiling_balance'] < 0)

    <p class="px-3.5 py-2.5 rounded-xl border text-[13px] {{ $theme['panel'] }}">
        Masukkan kos seunit <strong>sebelum SST</strong>. SST {{ $sstRateLabel }}% dikira secara automatik ke atas Jumlah Kecil.
    </p>

    <!-- Bar status kos (melekat semasa skrol) -->
    <div @class(['sticky top-0 z-20 px-3.5 py-2 bg-white/95 backdrop-blur border rounded-xl shadow-sm flex flex-wrap justify-between items-center gap-x-4 gap-y-1 text-[13px]', 'border-red-300' => $overCeiling, 'border-slate-200' => ! $overCeiling])>
        <span class="text-slate-600">
            Jumlah Keseluruhan:
            <strong @class(['tabular-nums', 'text-red-700' => $overCeiling, 'text-slate-900' => ! $overCeiling])>RM {{ number_format($summary['total'], 2) }}</strong>
            <span class="text-slate-500">/ RM {{ number_format($summary['ceiling'], 2) }}</span>
        </span>
        <span class="text-slate-600">
            Baki Siling:
            <strong @class(['tabular-nums', 'text-red-700' => $overCeiling, 'text-green-700' => ! $overCeiling])>{{ $overCeiling ? '-' : '' }}RM {{ number_format(abs($summary['ceiling_balance']), 2) }}</strong>
        </span>
    </div>

    @if ($form->hasManualSstItem())
        <div role="alert" class="px-3.5 py-2.5 rounded-xl bg-amber-50 border border-amber-200 text-[13px] text-amber-900">
            Terdapat item yang mengandungi perkataan <strong>"SST"</strong>. SST telah dikira secara automatik. Sila buang item tersebut supaya SST tidak dikira dua kali.
        </div>
    @endif

    <!-- Kumpulan item ikut kategori projek -->
    @foreach ($form->details as $catIndex => $category)
        <div class="border border-slate-200 rounded-xl overflow-hidden" wire:key="category-{{ $catIndex }}">
            <div class="flex flex-wrap items-center gap-x-4 gap-y-2 px-4 py-3 bg-slate-50 border-b border-slate-100">
                <label for="kategori-projek-{{ $catIndex }}" class="text-xs font-extrabold tracking-wide uppercase text-slate-700">Kategori Projek #{{ $catIndex + 1 }} <span class="text-red-600">*</span></label>
                <select id="kategori-projek-{{ $catIndex }}" wire:model="form.details.{{ $catIndex }}.project_category_id" class="flex-[1_1_240px] min-w-0 h-10 rounded-[10px] border-slate-300 bg-white text-sm {{ $theme['input'] }}">
                    <option value="" disabled>Pilih kategori projek</option>
                    @foreach ($this->projectCategoryOptions as $option)
                        {{-- Kategori tidak aktif hanya dipaparkan pada kumpulan yang telah menggunakannya --}}
                        @if ($option->is_active && ! $option->trashed())
                            <option value="{{ $option->id }}">{{ $option->label() }}</option>
                        @elseif ((string) $option->id === (string) ($category['project_category_id'] ?? ''))
                            <option value="{{ $option->id }}">{{ $option->label() }} (Tidak Aktif)</option>
                        @endif
                    @endforeach
                </select>
                @if (count($form->details) > 1)
                    <button type="button" wire:click="removeCategoryGroup({{ $catIndex }})" class="inline-flex items-center gap-1 min-h-9 px-2.5 rounded-lg text-[13px] font-bold text-red-700 hover:bg-red-50">
                        <svg aria-hidden="true" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 7h16M10 11v6M14 11v6M6 7l1 13h10l1-13M9 7V4h6v3" /></svg>
                        Padam Kumpulan
                    </button>
                @endif
                @error("form.details.{$catIndex}.project_category_id") <span class="basis-full text-red-600 text-xs">{{ $message }}</span> @enderror
            </div>

            <div class="overflow-x-auto">
                <table class="w-full min-w-[620px] border-collapse text-sm">
                    <thead>
                        <tr class="text-left text-xs text-slate-600">
                            <th scope="col" class="px-4 py-2.5 font-extrabold">Spesifikasi Teknikal</th>
                            <th scope="col" class="px-2 py-2.5 font-extrabold w-24">Kuantiti</th>
                            <th scope="col" class="px-2 py-2.5 font-extrabold w-40">Kos Seunit (RM)</th>
                            <th scope="col" class="px-4 py-2.5 font-extrabold w-36 text-right">Jumlah (RM)</th>
                            <th scope="col" class="w-12"><span class="sr-only">Buang</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($category['items'] as $itemIndex => $item)
                            <tr class="border-t border-slate-100" wire:key="item-{{ $catIndex }}-{{ $itemIndex }}">
                                <td class="pl-4 pr-2 py-2 align-top">
                                    <input type="text" aria-label="Spesifikasi Teknikal" wire:model="form.details.{{ $catIndex }}.items.{{ $itemIndex }}.technical_specifications" class="w-full h-10 rounded-[10px] border-slate-300 text-sm {{ $theme['input'] }}" placeholder="Spesifikasi / Jenama / Model">
                                    @error("form.details.{$catIndex}.items.{$itemIndex}.technical_specifications") <span class="text-red-600 text-xs block mt-1">{{ $message }}</span> @enderror
                                </td>
                                <td class="px-2 py-2 align-top">
                                    <input type="number" min="1" aria-label="Kuantiti" wire:model.live.debounce.400ms="form.details.{{ $catIndex }}.items.{{ $itemIndex }}.unit_quantity" class="w-full h-10 rounded-[10px] border-slate-300 text-sm text-right tabular-nums {{ $theme['input'] }}">
                                </td>
                                <td class="px-2 py-2 align-top">
                                    <input type="number" step="0.01" min="0" aria-label="Kos Seunit (RM)" wire:model.live.debounce.400ms="form.details.{{ $catIndex }}.items.{{ $itemIndex }}.unit_cost" class="w-full h-10 rounded-[10px] border-slate-300 text-sm text-right tabular-nums {{ $theme['input'] }}">
                                </td>
                                <td class="px-4 py-2 align-top text-right font-bold tabular-nums leading-10">
                                    {{ number_format(((int) ($item['unit_quantity'] ?? 0)) * ((float) ($item['unit_cost'] ?? 0)), 2) }}
                                </td>
                                <td class="pr-2 py-2 align-top text-center">
                                    @if (count($category['items']) > 1)
                                        <button type="button" wire:click="removeSubItem({{ $catIndex }}, {{ $itemIndex }})" aria-label="Buang item" class="w-10 h-10 inline-flex items-center justify-center rounded-[10px] text-slate-500 hover:bg-red-50 hover:text-red-700">
                                            <svg aria-hidden="true" class="w-[18px] h-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 7h16M10 11v6M14 11v6M6 7l1 13h10l1-13M9 7V4h6v3" /></svg>
                                        </button>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="px-4 py-2.5 border-t border-slate-100">
                <button type="button" wire:click="addSubItem({{ $catIndex }})" class="inline-flex items-center gap-1.5 min-h-10 px-3 rounded-[10px] border border-dashed border-slate-300 text-[13px] font-extrabold hover:bg-slate-50 {{ $theme['link'] }}">
                    <svg aria-hidden="true" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M12 5v14M5 12h14" /></svg>
                    Tambah Item
                </button>
            </div>
        </div>
    @endforeach

    <button type="button" wire:click="addCategoryGroup" class="inline-flex items-center gap-1.5 min-h-11 px-4 rounded-xl border bg-white text-sm font-extrabold transition {{ $theme['buttonOutline'] }}">
        <svg aria-hidden="true" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M12 5v14M5 12h14" /></svg>
        Tambah Kategori Projek
    </button>

    @error('details_total')
        <div role="alert" class="px-3.5 py-2.5 rounded-xl bg-red-50 border border-red-200 text-[13px] text-red-800">{{ $message }}</div>
    @enderror
</div>
