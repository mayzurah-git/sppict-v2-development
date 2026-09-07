<div class="max-w-7xl mx-auto p-6 space-y-6">
    <!-- Header -->
    <div class="flex flex-col md:flex-row justify-between items-center gap-4 bg-white p-6 rounded-2xl shadow-sm border border-slate-100">
        <div>
            <h2 class="text-xl font-black text-slate-900">Pengurusan Pengguna & Peranan</h2>
            <p class="text-xs text-slate-500 mt-0.5">Kawalan akaun pengguna, peranan capaian, dan agensi.</p>
        </div>
        <button wire:click="openModal" class="px-4 py-2.5 bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold rounded-xl shadow-md transition">
            + Tambah Pengguna Baharu
        </button>
    </div>

    @if (session()->has('message'))
        <div class="p-4 bg-emerald-50 border-l-4 border-emerald-500 text-emerald-700 text-xs rounded-r-xl">
            {{ session('message') }}
        </div>
    @endif

    <!-- Carian, Tapis & Jadual -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden space-y-4 p-4">
        <div class="flex flex-col md:flex-row gap-3">
            <input type="text" wire:model.live.debounce.300ms="search" placeholder="Cari Nama / E-mel..." class="w-full md:w-1/3 text-xs border-slate-200 rounded-xl shadow-sm focus:border-blue-500 focus:ring-blue-500">
            <select wire:model.live="roleFilter" class="w-full md:w-1/4 text-xs border-slate-200 rounded-xl shadow-sm focus:border-blue-500 focus:ring-blue-500">
                <option value="">-- Semua Peranan --</option>
                <option value="superadmin">Superadmin</option>
                <option value="pentadbir_urus_setia">Urus Setia</option>
                <option value="pengguna_biasa">Pemohon Agensi</option>
            </select>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-50 text-slate-600 font-bold border-b border-slate-100 uppercase">
                    <tr>
                        <th class="p-3.5">Nama & E-mel</th>
                        <th class="p-3.5">Jawatan</th>
                        <th class="p-3.5">Agensi</th>
                        <th class="p-3.5 text-center">Peranan</th>
                        <th class="p-3.5 text-center">Tindakan</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($users as $user)
                        <tr class="hover:bg-slate-50/80 transition">
                            <td class="p-3.5">
                                <p class="font-bold text-slate-800">{{ $user->name }}</p>
                                <p class="text-[10px] text-slate-400">{{ $user->email }}</p>
                            </td>
                            <td class="p-3.5 font-medium text-slate-700">{{ $user->position ?? '-' }}</td>
                            <td class="p-3.5 font-medium text-slate-600">{{ $user->agency->name ?? 'Tiada Agensi' }}</td>
                            <td class="p-3.5 text-center">
                                @switch($user->getRoleNames()->first())
                                    @case('superadmin')
                                        <span class="px-2.5 py-1 bg-red-50 text-red-700 font-bold rounded-full text-[10px]">Superadmin</span>
                                        @break
                                    @case('pentadbir_urus_setia')
                                        <span class="px-2.5 py-1 bg-purple-50 text-purple-700 font-bold rounded-full text-[10px]">Urus Setia</span>
                                        @break
                                    @default
                                        <span class="px-2.5 py-1 bg-blue-50 text-blue-700 font-bold rounded-full text-[10px]">Pemohon</span>
                                @endswitch
                            </td>
                            <td class="p-3.5 text-center space-x-1">
                                <button wire:click="openModal({{ $user->id }})" class="px-2.5 py-1 bg-amber-50 text-amber-700 border border-amber-200 hover:bg-amber-100 rounded-lg font-bold text-xs transition">
                                    ✏️ Edit
                                </button>
                                <button wire:click="resetPassword({{ $user->id }})" wire:confirm="Adakah anda pasti untuk set semula kata laluan pengguna ini?" class="px-2.5 py-1 bg-slate-100 hover:bg-slate-200 text-slate-700 border border-slate-200 rounded-lg font-bold text-xs transition">
                                    🔑 Reset Pass
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="p-8 text-center text-slate-400">Tiada pengguna dijumpai.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div>{{ $users->links() }}</div>
    </div>

    <!-- MODAL POPUP TAMBAH / EDIT PENGGUNA -->
    @if ($showModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-sm">
            <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg p-6 space-y-4 max-h-[90vh] overflow-y-auto">
                <h3 class="text-base font-bold text-slate-800 border-b pb-2">
                    {{ $userId ? 'Kemaskini Pengguna' : 'Tambah Pengguna Baharu' }}
                </h3>

                <div class="space-y-3">
                    <x-form.input label="Nama Penuh" wire:model="name" required />
                    @error('name') <span class="text-red-500 text-xs block">{{ $message }}</span> @enderror

                    <x-form.input label="E-mel Rasmi" type="email" wire:model="email" required />
                    @error('email') <span class="text-red-500 text-xs block">{{ $message }}</span> @enderror

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <x-form.input label="Jawatan & Gred" wire:model="position" required />
                            @error('position') <span class="text-red-500 text-xs block">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <x-form.input label="No. Telefon" wire:model="phone_number" required />
                            @error('phone_number') <span class="text-red-500 text-xs block">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <x-form.select label="Agensi Pengguna" wire:model="agency_id" required>
                        <option value="">-- Pilih Agensi --</option>
                        @foreach ($agencies as $agency)
                            <option value="{{ $agency->id }}">{{ $agency->code }} - {{ $agency->name }}</option>
                        @endforeach
                    </x-form.select>
                    @error('agency_id') <span class="text-red-500 text-xs block">{{ $message }}</span> @enderror

                    <x-form.select label="Peranan / Role" wire:model="role" required>
                        <option value="pengguna_biasa">Pemohon Agensi</option>
                        <option value="pentadbir_urus_setia">Urus Setia / Pegawai Penilai</option>
                        <option value="superadmin">Superadmin</option>
                    </x-form.select>

                    <x-form.input label="Kata Laluan {{ $userId ? '(Biarkan kosong jika tidak diubah)' : '*' }}" type="password" wire:model="password" />
                    @error('password') <span class="text-red-500 text-xs block">{{ $message }}</span> @enderror
                </div>

                <div class="flex justify-end space-x-2 pt-4 border-t">
                    <button type="button" wire:click="closeModal" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold rounded-xl transition">Batal</button>
                    <button type="button" wire:click="saveUser" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold rounded-xl transition">Simpan</button>
                </div>
            </div>
        </div>
    @endif
</div>