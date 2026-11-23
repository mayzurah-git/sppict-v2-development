<div class="max-w-7xl mx-auto p-6 space-y-6">
    <!-- Header -->
    <div class="flex flex-col md:flex-row justify-between items-center gap-4 bg-white p-6 rounded-2xl shadow-sm border border-slate-100">
        <div>
            <h2 class="text-xl font-black text-slate-900">Pengurusan Agensi</h2>
            <p class="text-xs text-slate-500 mt-0.5">Senarai agensi kerajaan yang berdaftar di bawah sistem SPPICT v2.</p>
        </div>
        <button wire:click="openModal" class="px-4 py-2.5 bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold rounded-xl shadow-md transition">
            + Tambah Agensi Baharu
        </button>
    </div>

    @if (session()->has('message'))
        <div class="p-4 bg-emerald-50 border-l-4 border-emerald-500 text-emerald-700 text-xs rounded-r-xl">
            {{ session('message') }}
        </div>
    @endif

    <!-- Carian & Jadual -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden space-y-4 p-4">
        <input type="text" wire:model.live.debounce.300ms="search" placeholder="Cari Nama / Kod Agensi..." class="w-full md:w-1/3 text-xs border-slate-200 rounded-xl shadow-sm focus:border-blue-500 focus:ring-blue-500">

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-50 text-slate-600 font-bold border-b border-slate-100 uppercase">
                    <tr>
                        <th class="p-3.5">Kod</th>
                        <th class="p-3.5">Nama Agensi</th>
                        <th class="p-3.5">Kategori</th>
                        <th class="p-3.5 text-center">Tindakan</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($agencies as $agency)
                        <tr class="hover:bg-slate-50/80 transition">
                            <td class="p-3.5 font-bold text-blue-600">{{ $agency->code }}</td>
                            <td class="p-3.5 font-semibold text-slate-800">{{ $agency->name }}</td>
                            <td class="p-3.5 text-slate-500"><span class="bg-slate-100 px-2.5 py-1 rounded-md text-[11px] font-medium">{{ $agency->category ?? 'SUK' }}</span></td>
                            <td class="p-3.5 text-center">
                                <button wire:click="openModal({{ $agency->id }})" class="px-3 py-1 bg-amber-50 text-amber-700 border border-amber-200 hover:bg-amber-100 rounded-lg font-bold text-xs transition">
                                    ✏️ Edit
                                </button>
                                <button wire:click="deleteAgency('{{ $agency->uuid }}')" wire:confirm="Adakah anda pasti mahu hapuskan agensi ini?" class="px-3 py-1 bg-red-50 text-red-700 border border-red-200 hover:bg-red-100 rounded-lg font-bold text-xs transition">
                                    🗑️ Hapus
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="p-8 text-center text-slate-400">Tiada agensi dijumpai.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div>{{ $agencies->links() }}</div>
    </div>

    <!-- MODAL POPUP TAMBAH / EDIT AGENSI -->
    @if ($showModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-sm">
            <div class="bg-white rounded-2xl shadow-xl w-full max-w-md p-6 space-y-4">
                <h3 class="text-base font-bold text-slate-800 border-b pb-2">
                    {{ $agencyId ? 'Kemaskini Agensi' : 'Tambah Agensi Baharu' }}
                </h3>

                <div class="space-y-3">
                    <x-form.input label="Kod Agensi" wire:model="code" placeholder="Contoh: PTGNS / NBD" required />
                    @error('code') <span class="text-red-500 text-xs block">{{ $message }}</span> @enderror

                    <x-form.input label="Nama Agensi" wire:model="name" placeholder="Contoh: Pejabat Tanah dan Galian Negeri Sembilan" required />
                    @error('name') <span class="text-red-500 text-xs block">{{ $message }}</span> @enderror

                    <x-form.select label="Kategori Agensi" wire:model="category" required>
                        <option value="" disabled>Pilih kategori agensi</option>
                        @foreach (\App\Livewire\Admin\AgencyManagement::CATEGORY_OPTIONS as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </x-form.select>
                    @error('category') <span class="text-red-500 text-xs block">{{ $message }}</span> @enderror
                </div>

                <div class="flex justify-end space-x-2 pt-4 border-t">
                    <button type="button" wire:click="closeModal" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold rounded-xl transition">Batal</button>
                    <button type="button" wire:click="saveAgency" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold rounded-xl transition">Simpan</button>
                </div>
            </div>
        </div>
    @endif
</div>
