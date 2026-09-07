<div class="max-w-7xl mx-auto p-6 space-y-6">
    <div class="flex flex-col md:flex-row justify-between items-center gap-4 bg-white p-6 rounded-2xl shadow-sm border border-slate-100">
        <div>
            <h2 class="text-xl font-black text-slate-900">Pengurusan Jawatan & Gred</h2>
            <p class="text-xs text-slate-500 mt-0.5">Kawalan rekod gred perkhidmatan dan jawatan rasmi.</p>
        </div>
        <button wire:click="openModal" class="px-4 py-2.5 bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold rounded-xl shadow-md transition">
            + Tambah {{ $activeTab === 'grades' ? 'Gred' : 'Jawatan' }} Baharu
        </button>
    </div>

    @if (session()->has('message'))
        <div class="p-4 bg-emerald-50 border-l-4 border-emerald-500 text-emerald-700 text-xs rounded-r-xl">
            {{ session('message') }}
        </div>
    @endif

    <!-- TAB NAVIGASI -->
    <div class="flex border-b border-slate-200 gap-4">
        <button wire:click="$set('activeTab', 'grades')" class="pb-3 text-xs font-bold transition border-b-2 {{ $activeTab === 'grades' ? 'border-blue-600 text-blue-600' : 'border-transparent text-slate-400 hover:text-slate-600' }}">
            Gred Perkhidmatan
        </button>
        <button wire:click="$set('activeTab', 'positions')" class="pb-3 text-xs font-bold transition border-b-2 {{ $activeTab === 'positions' ? 'border-blue-600 text-blue-600' : 'border-transparent text-slate-400 hover:text-slate-600' }}">
            Jawatan
        </button>
    </div>

    <!-- CARIAN & JADUAL -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden space-y-4 p-4">
        <input type="text" wire:model.live.debounce.300ms="search" placeholder="Cari..." class="w-full md:w-1/3 text-xs border-slate-200 rounded-xl shadow-sm focus:border-blue-500 focus:ring-blue-500">

        @if ($activeTab === 'grades')
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-slate-50 text-slate-600 font-bold border-b border-slate-100 uppercase">
                        <tr>
                            <th class="p-3.5">Gred</th>
                            <th class="p-3.5">Skim Perkhidmatan</th>
                            <th class="p-3.5 text-center">Tindakan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($grades as $g)
                            <tr class="hover:bg-slate-50/80 transition">
                                <td class="p-3.5 font-bold text-blue-600">{{ $g->name }}</td>
                                <td class="p-3.5 text-slate-600">{{ $g->scheme ?? '-' }}</td>
                                <td class="p-3.5 text-center">
                                    <button wire:click="openModal({{ $g->id }})" class="px-3 py-1 bg-amber-50 text-amber-700 border border-amber-200 rounded-lg font-bold text-xs">✏️ Edit</button>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="p-8 text-center text-slate-400">Tiada rekod gred.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div>{{ $grades->links() }}</div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-slate-50 text-slate-600 font-bold border-b border-slate-100 uppercase">
                        <tr>
                            <th class="p-3.5">Jawatan</th>
                            <th class="p-3.5 text-center">Tindakan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($positions as $p)
                            <tr class="hover:bg-slate-50/80 transition">
                                <td class="p-3.5 font-bold text-slate-800">{{ $p->title }}</td>
                                <td class="p-3.5 text-center">
                                    <button wire:click="openModal({{ $p->id }})" class="px-3 py-1 bg-amber-50 text-amber-700 border border-amber-200 rounded-lg font-bold text-xs">✏️ Edit</button>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="2" class="p-8 text-center text-slate-400">Tiada rekod jawatan.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div>{{ $positions->links() }}</div>
        @endif
    </div>

    <!-- MODAL POPUP -->
    @if ($showModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-sm">
            <div class="bg-white rounded-2xl shadow-xl w-full max-w-md p-6 space-y-4">
                <h3 class="text-base font-bold text-slate-800 border-b pb-2">
                    {{ $activeTab === 'grades' ? ($gradeId ? 'Kemaskini Gred' : 'Tambah Gred') : ($positionId ? 'Kemaskini Jawatan' : 'Tambah Jawatan') }}
                </h3>

                <div class="space-y-3">
                    @if ($activeTab === 'grades')
                        <x-form.input label="Kod Gred" wire:model="gradeName" placeholder="Contoh: F41" required />
                        @error('gradeName') <span class="text-red-500 text-xs block">{{ $message }}</span> @enderror

                        <x-form.input label="Skim Perkhidmatan" wire:model="gradeScheme" placeholder="Contoh: Teknologi Maklumat" />
                    @else
                        <x-form.input label="Jawatan" wire:model="positionTitle" placeholder="Contoh: Pegawai Teknologi Maklumat" required />
                        @error('positionTitle') <span class="text-red-500 text-xs block">{{ $message }}</span> @enderror
                    @endif
                </div>

                <div class="flex justify-end space-x-2 pt-4 border-t">
                    <button type="button" wire:click="closeModal" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold rounded-xl">Batal</button>
                    <button type="button" wire:click="save" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold rounded-xl">Simpan</button>
                </div>
            </div>
        </div>
    @endif
</div>
