<div class="space-y-6" wire:key="phase-3-form">
    <div class="border-b pb-2 flex justify-between items-center">
        <div>
            <h3 class="text-lg font-semibold text-gray-700">Fasa 3: Perincian Projek</h3>
            <p class="text-xs text-gray-500">Sila masukkan spesifikasi teknikal, kuantiti, dan kos seunit bagi setiap perolehan.</p>
        </div>
        <button type="button" wire:click="addCategoryGroup" class="px-3 py-1.5 bg-blue-50 text-blue-700 hover:bg-blue-100 border border-blue-200 text-xs font-bold rounded-lg transition">
            + Tambah Kategori Projek
        </button>
    </div>

    <!-- SEMAKAN JUMLAH KOS -->
    <div class="p-3 bg-blue-50 border border-blue-200 rounded-lg flex justify-between items-center text-xs">
        <span class="text-blue-900 font-medium">Anggaran Kos Projek (Fasa 2): <strong class="text-blue-700">RM {{ number_format((float)($form->estimated_cost ?: 0), 2) }}</strong></span>
        <span class="text-blue-900 font-medium">Jumlah Perincian Semasa: <strong class="{{ abs((float)$form->getTotalDetailsCostAttribute() - (float)($form->estimated_cost ?: 0)) < 0.01 ? 'text-emerald-600' : 'text-amber-600' }}">RM {{ number_format((float)$form->getTotalDetailsCostAttribute(), 2) }}</strong></span>
    </div>

    @error('details_total')
        <div class="p-3 bg-red-100 border-l-4 border-red-500 text-red-700 text-xs rounded">
            {{ $message }}
        </div>
    @enderror

    <!-- SENARAI KUMPULAN ITEM DINAMIK -->
    @foreach ($form->details as $catIndex => $category)
        <div class="p-4 bg-gray-50 border border-gray-200 rounded-xl space-y-4 relative">
            <div class="flex justify-between items-center">
                <div class="w-1/2">
                    <label class="block text-xs font-bold text-gray-700 uppercase">Kategori Projek #{{ $catIndex + 1 }} *</label>
                                    <select wire:model="details.{{ $catIndex }}.item_category" class="mt-1 block w-full rounded-md border-gray-300 text-sm font-semibold focus:border-blue-500 focus:ring-blue-500">
                                        <option value="Projek Baharu">A. PROJEK BAHARU</option>
                                        <option value="Peningkatan Sistem">B. PENINGKATAN SISTEM</option>
                                        <option value="Peluasan Sistem/Projek">C. PELUASAN SISTEM / PROJEK</option>
                                        <option value="Penambahbaikan Peralatan">D. PENAMBAHBAIKAN PERALATAN</option>
                                        <option value="Penyelenggaraan">E. PENYELENGGARAAN</option>
                                        <option value="Khidmat Perunding ICT">F. KHIDMAT PERUNDING ICT</option>
                                    </select>
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
                + Tambah Baris Item
            </button>
        </div>
    @endforeach
</div>