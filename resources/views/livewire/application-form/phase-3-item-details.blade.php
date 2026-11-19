{{-- Ringkasan Kos Projek penuh dipaparkan di lajur kanan (application-form.blade.php). --}}
<div class="space-y-4" wire:key="phase-3-form">
    @php($summary = $form->costSummary())
    @php($sstRateLabel = rtrim(rtrim(number_format($summary['sst_rate'], 2), '0'), '.'))
    @php($overCeiling = $summary['ceiling_balance'] < 0)

    <p class="px-3.5 py-2.5 rounded-xl border text-[13px] {{ $theme['panel'] }}">
        Masukkan kos seunit <strong>sebelum SST</strong>. SST {{ $sstRateLabel }}% dikira secara automatik ke atas Jumlah Kecil.
    </p>

    {{-- PP4.4: kaedah alternatif — import Perincian Projek daripada fail CSV templat sistem --}}
    <section aria-labelledby="csv-import-title" class="px-4 py-3.5 rounded-xl border border-slate-200 bg-white space-y-3" wire:key="csv-import">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="min-w-0 flex-[1_1_280px]">
                <h3 id="csv-import-title" class="text-sm font-extrabold text-slate-900">Import daripada Fail CSV <span class="font-semibold text-slate-500">(pilihan)</span></h3>
                <p class="mt-0.5 text-[13px] text-slate-600">
                    Muat turun templat, isi item dalam Excel, simpan sebagai <strong>.csv</strong>, kemudian muat naik.
                    Item akan dipaparkan dalam medan di bawah dan masih boleh disunting.
                    @if ($this->enteredDetailsCount > 0)
                        Perincian sedia ada akan <strong>digantikan</strong>.
                    @endif
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <button type="button" wire:click="downloadDetailsTemplate" class="inline-flex items-center gap-1.5 min-h-10 px-3.5 rounded-[10px] border bg-white text-[13px] font-extrabold transition focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 {{ $theme['buttonOutline'] }}">
                    <svg aria-hidden="true" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 4v11m0 0-4-4m4 4 4-4M5 20h14" /></svg>
                    Muat Turun Templat CSV
                </button>
                <label for="details-csv" class="inline-flex items-center gap-1.5 min-h-10 px-3.5 rounded-[10px] text-[13px] font-extrabold cursor-pointer transition focus-within:ring-2 focus-within:ring-offset-2 {{ $theme['button'] }}">
                    <svg aria-hidden="true" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20V9m0 0-4 4m4-4 4 4M5 4h14" /></svg>
                    Muat Naik CSV
                    <input id="details-csv" type="file" accept=".csv" wire:model="detailsCsv" class="sr-only" aria-describedby="csv-import-title">
                </label>
            </div>
        </div>

        <div wire:loading wire:target="detailsCsv" role="status" class="text-[13px] font-semibold text-slate-600">Memproses fail CSV…</div>

        @if ($csvImportNotice)
            <div role="status" class="px-3.5 py-2.5 rounded-xl bg-green-50 border border-green-200 text-[13px] font-semibold text-green-800">{{ $csvImportNotice }}</div>
        @endif

        @if ($errors->has('detailsCsv'))
            <div role="alert" class="px-3.5 py-2.5 rounded-xl bg-red-50 border border-red-200 text-[13px] text-red-800">
                <p class="font-bold">Fail CSV ditolak. Tiada perubahan dibuat pada Perincian Projek.</p>
                <ul class="mt-1 list-disc pl-5 space-y-0.5">
                    @foreach ($errors->get('detailsCsv') as $message)
                        <li>{{ $message }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <details class="text-[13px] text-slate-600">
            <summary class="cursor-pointer font-bold {{ $theme['link'] }}">Format templat &amp; kod kategori projek</summary>
            <ul class="mt-2 list-disc pl-5 space-y-0.5">
                <li><code>kod_kategori_projek</code>: salah satu kod di bawah.</li>
                <li><code>spesifikasi_teknikal</code>: wajib, 3 hingga {{ number_format(\App\Services\ProjectDetailsCsvService::MAX_SPEC_LENGTH) }} aksara.</li>
                <li><code>kuantiti</code>: nombor bulat 1 hingga {{ number_format(\App\Services\ProjectDetailsCsvService::MAX_QUANTITY) }}.</li>
                <li><code>kos_seunit_rm</code>: sebelum SST, contoh 1500.00.</li>
                <li>Maksimum {{ \App\Services\ProjectDetailsCsvService::MAX_ROWS }} item, saiz fail 1MB. Baris bermula dengan <code>#</code> diabaikan.</li>
            </ul>
            <table class="mt-2 w-full max-w-xl border-collapse text-left">
                <thead>
                    <tr class="text-xs text-slate-500"><th scope="col" class="py-1 pr-3 font-extrabold">Kod</th><th scope="col" class="py-1 font-extrabold">Kategori Projek</th></tr>
                </thead>
                <tbody>
                    @foreach ($this->csvCategories as $csvCategory)
                        <tr class="border-t border-slate-100"><td class="py-1 pr-3"><code>{{ $csvCategory->code }}</code></td><td class="py-1">{{ $csvCategory->label() }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        </details>
    </section>

    {{-- PP4.4: amaran sebelum perincian sedia ada digantikan --}}
    @if ($pendingCsvDetails !== null)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-sm" wire:key="csv-replace-confirm">
            <div role="alertdialog" aria-modal="true" aria-labelledby="csv-replace-title" aria-describedby="csv-replace-desc" class="bg-white rounded-2xl shadow-xl w-full max-w-md p-6 space-y-4">
                <h3 id="csv-replace-title" class="text-base font-bold text-slate-800 border-b pb-2">Gantikan Perincian Projek?</h3>
                <p id="csv-replace-desc" class="text-sm text-slate-600">
                    Perincian sedia ada (<strong>{{ $this->enteredDetailsCount }} item</strong>) akan digantikan dengan
                    <strong>{{ $pendingCsvItemCount }} item</strong> daripada fail CSV. Teruskan?
                </p>
                <div class="flex justify-end gap-2 pt-4 border-t">
                    <button type="button" wire:click="cancelCsvReplace" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-sm font-bold rounded-xl transition">Batal</button>
                    <button type="button" wire:click="confirmCsvReplace" wire:loading.attr="disabled" wire:target="confirmCsvReplace" class="px-4 py-2 bg-red-600 hover:bg-red-700 disabled:opacity-60 text-white text-sm font-bold rounded-xl transition">Ganti</button>
                </div>
            </div>
        </div>
    @endif

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
