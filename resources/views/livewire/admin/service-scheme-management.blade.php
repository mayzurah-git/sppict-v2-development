<div class="max-w-7xl mx-auto p-6 space-y-6">
    <div class="flex flex-col md:flex-row justify-between items-center gap-4 bg-white p-6 rounded-2xl shadow-sm border border-slate-100">
        <div>
            <h2 class="text-xl font-black text-slate-900">Pengurusan Skim Perkhidmatan</h2>
            <p class="text-xs text-slate-500 mt-0.5">Master data skim perkhidmatan rasmi SPPICT v2.</p>
        </div>
        <button wire:click="openModal" class="px-4 py-2.5 bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold rounded-xl shadow-md transition">
            + Tambah Skim Perkhidmatan
        </button>
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
        <input type="text" wire:model.live.debounce.300ms="search" placeholder="Cari Kod / Nama Skim..." class="w-full md:w-1/3 text-xs border-slate-200 rounded-xl shadow-sm focus:border-blue-500 focus:ring-blue-500">

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-50 text-slate-600 font-bold border-b border-slate-100 uppercase">
                    <tr>
                        <th class="p-3.5">Kod</th>
                        <th class="p-3.5">Nama Skim</th>
                        <th class="p-3.5">Status</th>
                        <th class="p-3.5 text-center">Bil. Jawatan</th>
                        <th class="p-3.5 text-center">Tindakan</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($schemes as $scheme)
                        <tr class="hover:bg-slate-50/80 transition">
                            <td class="p-3.5 font-bold text-blue-600">{{ $scheme->code }}</td>
                            <td class="p-3.5 font-semibold text-slate-800">{{ $scheme->name }}</td>
                            <td class="p-3.5">
                                @if ($scheme->is_active)
                                    <span class="px-2.5 py-1 bg-emerald-50 text-emerald-700 font-bold rounded-full text-[10px]">Aktif</span>
                                @else
                                    <span class="px-2.5 py-1 bg-slate-100 text-slate-600 font-bold rounded-full text-[10px]">Tidak Aktif</span>
                                @endif
                            </td>
                            <td class="p-3.5 text-center text-slate-600">{{ $scheme->positions_count }}</td>
                            <td class="p-3.5 text-center space-x-1">
                                <button wire:click="openModal('{{ $scheme->uuid }}')" class="px-3 py-1 bg-amber-50 text-amber-700 border border-amber-200 hover:bg-amber-100 rounded-lg font-bold text-xs transition">
                                    Edit
                                </button>
                                @if ($scheme->is_active)
                                    <button wire:click="setStatus('{{ $scheme->uuid }}', false)" class="px-3 py-1 bg-slate-100 text-slate-700 border border-slate-200 hover:bg-slate-200 rounded-lg font-bold text-xs transition">
                                        Nyahaktif
                                    </button>
                                @else
                                    <button wire:click="setStatus('{{ $scheme->uuid }}', true)" class="px-3 py-1 bg-emerald-50 text-emerald-700 border border-emerald-200 hover:bg-emerald-100 rounded-lg font-bold text-xs transition">
                                        Aktifkan
                                    </button>
                                @endif
                                <button wire:click="delete('{{ $scheme->uuid }}')" wire:confirm="Adakah anda pasti mahu soft delete skim ini?" class="px-3 py-1 bg-red-50 text-red-700 border border-red-200 hover:bg-red-100 rounded-lg font-bold text-xs transition">
                                    Hapus
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="p-8 text-center text-slate-400">Tiada skim perkhidmatan dijumpai.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div>{{ $schemes->links() }}</div>
    </div>

    @if ($showModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-sm">
            <div class="bg-white rounded-2xl shadow-xl w-full max-w-md p-6 space-y-4">
                <h3 class="text-base font-bold text-slate-800 border-b pb-2">
                    {{ $serviceSchemeUuid ? 'Kemaskini Skim Perkhidmatan' : 'Tambah Skim Perkhidmatan' }}
                </h3>

                <div class="space-y-3">
                    <x-form.input label="Kod Skim" wire:model="code" required />
                    @error('code') <span class="text-red-500 text-xs block">{{ $message }}</span> @enderror

                    <x-form.input label="Nama Skim Perkhidmatan" wire:model="name" required />
                    @error('name') <span class="text-red-500 text-xs block">{{ $message }}</span> @enderror

                    <div>
                        <label class="block text-xs font-medium text-gray-700">Penerangan</label>
                        <textarea wire:model="description" rows="3" class="mt-1 block w-full rounded-md border-gray-300 text-xs shadow-sm focus:border-blue-500 focus:ring-blue-500"></textarea>
                        @error('description') <span class="text-red-500 text-xs block mt-1">{{ $message }}</span> @enderror
                    </div>

                    <label class="inline-flex items-center gap-2 text-xs font-medium text-gray-700">
                        <input type="checkbox" wire:model="is_active" class="rounded border-gray-300 text-blue-600 shadow-sm focus:ring-blue-500">
                        Aktif
                    </label>
                    @error('is_active') <span class="text-red-500 text-xs block">{{ $message }}</span> @enderror
                </div>

                <div class="flex justify-end space-x-2 pt-4 border-t">
                    <button type="button" wire:click="closeModal" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold rounded-xl">Batal</button>
                    <button type="button" wire:click="save" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold rounded-xl">Simpan</button>
                </div>
            </div>
        </div>
    @endif
</div>
