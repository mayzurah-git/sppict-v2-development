<div class="space-y-6" wire:key="phase-3-form">
    <div class="border-b pb-2 flex justify-between items-center">
        <div>
            <h3 class="text-lg font-semibold text-gray-700">Perincian Projek</h3>
            <p class="text-xs text-gray-500">Sila masukkan spesifikasi teknikal, kuantiti, dan kos seunit bagi setiap perolehan.</p>
        </div>
    </div>

    @php($summary = $form->costSummary())
    @php($sstRateLabel = rtrim(rtrim(number_format($summary['sst_rate'], 2), '0'), '.'))
    @php($overCeiling = $summary['ceiling_balance'] < 0)

    <!-- RUJUKAN KOS DARIPADA FASA 2 -->
    <div>
    <div class="grid grid-cols-2 gap-3">

        {{-- Anggaran Kos Siling --}}
        <div class="px-4 py-3 bg-slate-50 border border-slate-200 rounded-lg">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <p class="text-[11px] font-semibold text-slate-500 uppercase">
                        Anggaran Kos Siling
                    </p>

                    <p class="text-lg font-black text-slate-900 leading-tight">
                        RM {{ number_format($summary['ceiling'], 2) }}
                    </p>

                    <p class="text-[11px] text-slate-500 mt-0.5">
                        Had maksimum termasuk SST
                    </p>
                </div>
            </div>
        </div>

        {{-- Anggaran Kos Projek --}}
        <div class="px-4 py-3 bg-slate-50 border border-slate-200 rounded-lg">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <p class="text-[11px] font-semibold text-slate-500 uppercase">
                        Anggaran Kos Projek
                    </p>

                    <p class="text-lg font-black text-slate-900 leading-tight">
                        RM {{ number_format($summary['estimated'], 2) }}
                    </p>

                </div>
            </div>
        </div>

    </div>
</div>
    <!-- BAR STATUS KOS (STICKY SEMASA SKROL) -->
    <div class="sticky top-0 z-20 -mx-1 px-3 py-2 bg-white/95 backdrop-blur border {{ $overCeiling ? 'border-red-300' : 'border-gray-200' }} rounded-lg shadow-sm flex flex-wrap justify-between items-center gap-x-4 gap-y-1 text-xs">
        <span class="text-gray-600">
            Jumlah Keseluruhan:
            <strong class="{{ $overCeiling ? 'text-red-600' : 'text-gray-900' }}">RM {{ number_format($summary['total'], 2) }}</strong>
            <span class="text-gray-400">/ RM {{ number_format($summary['ceiling'], 2) }}</span>
        </span>
        <span class="text-gray-600">
            Baki Siling:
            <strong class="{{ $overCeiling ? 'text-red-600' : 'text-emerald-600' }}">{{ $overCeiling ? '-' : '' }}RM {{ number_format(abs($summary['ceiling_balance']), 2) }}</strong>
        </span>
    </div>

    <!-- PANDUAN SST 
    <div class="p-3 bg-blue-50 border border-blue-200 rounded-lg text-xs text-blue-900">
        <strong>Panduan:</strong> Masukkan kos seunit <strong>sebelum SST</strong>. Jangan masukkan SST sebagai item atau dalam kos seunit.
        SST {{ $sstRateLabel }}% dikira secara automatik ke atas Jumlah Kecil.
    </div>-->
    <button type="button" wire:click="addCategoryGroup" class="px-3 py-1.5 bg-blue-50 text-blue-700 hover:bg-blue-100 border border-blue-200 text-xs font-bold rounded-lg transition">
            + Tambah Kategori Projek
    </button>
    @if ($form->hasManualSstItem())
        <div class="p-3 bg-amber-50 border-l-4 border-amber-500 text-amber-800 text-xs rounded">
            ⚠️ Terdapat item yang mengandungi perkataan <strong>"SST"</strong>. SST telah dikira secara automatik. Sila buang item tersebut supaya SST tidak dikira dua kali.
        </div>
    @endif

    <!-- SENARAI KUMPULAN ITEM DINAMIK -->
    @foreach ($form->details as $catIndex => $category)
        <div class="p-4 bg-gray-50 border border-gray-200 rounded-xl space-y-4 relative">
            <div class="flex justify-between items-center">
                <div class="w-1/2">
                    <label class="block text-xs font-bold text-gray-700 uppercase">Kategori Projek #{{ $catIndex + 1 }} *</label>
                                    <select wire:model="form.details.{{ $catIndex }}.project_category_id" class="mt-1 block w-full rounded-md border-gray-300 text-sm font-semibold focus:border-blue-500 focus:ring-blue-500">
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
                                    @error("form.details.{$catIndex}.project_category_id") <span class="text-red-500 text-xs block mt-1">{{ $message }}</span> @enderror
                </div>
                @if (count($form->details) > 1)
                    <button type="button" wire:click="removeCategoryGroup({{ $catIndex }})" class="text-xs text-red-600 hover:text-red-800 font-bold">
                        🗑️ Padam Kumpulan
                    </button>
                @endif
            </div>

            <!-- JADUAL SUB-ITEM -->
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-xs">
                    <thead>
                        <tr class="bg-gray-100 text-gray-700 border-b">
                            <th class="p-2 w-1/2">Spesifikasi Teknikal</th>
                            <th class="p-2 w-1/6">Kuantiti</th>
                            <th class="p-2 w-1/4">Kos Seunit (RM)</th>
                            <th class="p-2 w-1/6 text-right">Jumlah (RM)</th>
                            <th class="p-2 w-10"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @foreach ($category['items'] as $itemIndex => $item)
                            <tr>
                                <td class="p-2">
                                    <input type="text" wire:model="form.details.{{ $catIndex }}.items.{{ $itemIndex }}.technical_specifications" class="w-full text-xs border-gray-300 rounded-md shadow-sm focus:border-blue-500 focus:ring-blue-500" placeholder="Spesifikasi / Jenama / Model">
                                    @error("form.details.{$catIndex}.items.{$itemIndex}.technical_specifications") <span class="text-red-500 text-[10px] block">{{ $message }}</span> @enderror
                                </td>
                                <td class="p-2">
                                    <input type="number" min="1" wire:model.live="form.details.{{ $catIndex }}.items.{{ $itemIndex }}.unit_quantity" class="w-full text-xs border-gray-300 rounded-md shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                </td>
                                <td class="p-2">
                                    <input type="number" step="0.01" min="0" wire:model.live="form.details.{{ $catIndex }}.items.{{ $itemIndex }}.unit_cost" class="w-full text-xs border-gray-300 rounded-md shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                </td>
                                <td class="p-2 text-right font-bold text-gray-700">
                                    {{ number_format(((int)($item['unit_quantity'] ?? 0)) * ((float)($item['unit_cost'] ?? 0)), 2) }}
                                </td>
                                <td class="p-2 text-center">
                                    @if (count($category['items']) > 1)
                                        <button type="button" wire:click="removeSubItem({{ $catIndex }}, {{ $itemIndex }})" class="text-red-500 hover:text-red-700 font-bold">✕</button>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <button type="button" wire:click="addSubItem({{ $catIndex }})" class="text-xs text-blue-600 hover:text-blue-800 font-bold">
                + Tambah Butiran
            </button>
        </div>
    @endforeach

    <!-- RINGKASAN KOS & SST -->
    <div class="p-4 bg-white border-2 {{ $overCeiling ? 'border-red-300' : 'border-gray-200' }} rounded-xl space-y-3">
        <h4 class="text-xs font-bold text-gray-700 uppercase">Ringkasan Kos Projek</h4>

        <dl class="text-xs divide-y divide-gray-100">
            <div class="flex justify-between py-1.5">
                <dt class="text-gray-600">Jumlah Kecil (sebelum SST)</dt>
                <dd class="font-semibold text-gray-800">RM {{ number_format($summary['subtotal'], 2) }}</dd>
            </div>
            <div class="flex justify-between py-1.5">
                <dt class="text-gray-600">SST ({{ $sstRateLabel }}%)</dt>
                <dd class="font-semibold text-gray-800">RM {{ number_format($summary['sst_amount'], 2) }}</dd>
            </div>
            <div class="flex justify-between py-2">
                <dt class="font-bold text-gray-800">Jumlah Keseluruhan</dt>
                <dd class="text-sm font-black {{ $overCeiling ? 'text-red-600' : 'text-gray-900' }}">RM {{ number_format($summary['total'], 2) }}</dd>
            </div>
            <div class="flex justify-between py-1.5">
                <dt class="text-gray-600">Anggaran Kos Siling (Fasa 2)</dt>
                <dd class="font-semibold text-gray-800">RM {{ number_format($summary['ceiling'], 2) }}</dd>
            </div>
            <div class="flex justify-between py-1.5">
                <dt class="text-gray-600">Baki Siling</dt>
                <dd class="font-bold {{ $overCeiling ? 'text-red-600' : 'text-emerald-600' }}">
                    {{ $overCeiling ? '-' : '' }}RM {{ number_format(abs($summary['ceiling_balance']), 2) }}
                </dd>
            </div>
        </dl>

        <!-- Penggunaan Siling -->
        <div>
            <div class="flex justify-between text-[11px] text-gray-500 mb-1">
                <span>Penggunaan Kos Siling</span>
                <span class="font-bold {{ $overCeiling ? 'text-red-600' : 'text-gray-700' }}">{{ number_format($summary['ceiling_usage_percent'], 1) }}%</span>
            </div>
            <div class="h-2 w-full bg-gray-100 rounded-full overflow-hidden">
                <div class="h-full rounded-full {{ $overCeiling ? 'bg-red-500' : 'bg-emerald-500' }}" style="width: {{ min(100, $summary['ceiling_usage_percent']) }}%"></div>
            </div>
        </div>

        @if ($overCeiling)
            <div class="p-3 bg-red-50 border-l-4 border-red-500 text-red-700 text-xs rounded">
                Jumlah Keseluruhan melebihi Anggaran Kos Siling sebanyak <strong>RM {{ number_format(abs($summary['ceiling_balance']), 2) }}</strong>. Sila kurangkan kos sebelum ke fasa seterusnya.
            </div>
        @endif

        @if (abs($summary['estimated_difference']) >= 0.01 && $summary['subtotal'] > 0)
            <p class="text-[11px] text-gray-500">
                ℹ️ Jumlah Keseluruhan {{ $summary['estimated_difference'] > 0 ? 'melebihi' : 'kurang daripada' }} Anggaran Kos Projek (RM {{ number_format($summary['estimated'], 2) }})
                sebanyak RM {{ number_format(abs($summary['estimated_difference']), 2) }}.
            </p>
        @endif
    </div>

    @error('details_total')
        <div class="p-3 bg-red-100 border-l-4 border-red-500 text-red-700 text-xs rounded">
            {{ $message }}
        </div>
    @enderror
</div>