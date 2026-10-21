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

    @if ($temporaryPassword)
        <div class="p-5 bg-emerald-50 border border-emerald-200 rounded-2xl space-y-3" role="alert">
            <p class="text-sm font-bold text-emerald-800">Kata laluan sementara bagi {{ $temporaryPasswordFor }}</p>
            <div class="text-xs text-emerald-900 space-y-1">
                <p>Dipaparkan <strong>sekali sahaja</strong>:</p>
                <p class="inline-block font-mono text-base tracking-wider bg-white border border-emerald-300 rounded-lg px-3 py-1.5 select-all">{{ $temporaryPassword }}</p>
                <p>Sampaikan kata laluan ini kepada pengguna secara selamat. Pengguna wajib menukar kata laluan selepas log masuk.</p>
            </div>
            <button type="button" wire:click="dismissTemporaryPassword" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-xl transition">
                Saya telah merekodkan kata laluan ini
            </button>
        </div>
    @endif

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

    @if (session()->has('warning'))
        <div class="p-4 bg-amber-50 border-l-4 border-amber-500 text-amber-800 text-xs rounded-r-xl">
            ⚠ {{ session('warning') }}
        </div>
    @endif

    <!-- Carian, Tapis & Jadual -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden space-y-4 p-4">
        <div class="flex flex-col md:flex-row gap-3">
            <input type="text" wire:model.live.debounce.300ms="search" placeholder="Cari Nama / E-mel / No. KP..." class="w-full md:w-1/3 text-xs border-slate-200 rounded-xl shadow-sm focus:border-blue-500 focus:ring-blue-500">
            <select wire:model.live="roleFilter" class="w-full md:w-1/4 text-xs border-slate-200 rounded-xl shadow-sm focus:border-blue-500 focus:ring-blue-500">
                <option value="">-- Semua Peranan --</option>
                @foreach ($roleLabels as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-50 text-slate-600 font-bold border-b border-slate-100 uppercase">
                    <tr>
                        <th class="p-3.5">Nama & E-mel</th>
                        <th class="p-3.5">No. KP</th>
                        <th class="p-3.5">Jawatan / Gred</th>
                        <th class="p-3.5">Agensi</th>
                        <th class="p-3.5 text-center">Peranan</th>
                        <th class="p-3.5 text-center">Status</th>
                        <th class="p-3.5 text-center">Tindakan</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($users as $user)
                        @php($userRole = $user->roles->first()?->name)
                        <tr class="hover:bg-slate-50/80 transition" wire:key="user-{{ $user->uuid }}">
                            <td class="p-3.5">
                                <p class="font-bold text-slate-800">{{ $user->name }}</p>
                                <p class="text-[10px] text-slate-400">{{ $user->email }}</p>
                            </td>
                            <td class="p-3.5 font-mono text-slate-600">{{ \App\Livewire\Users\RegistrationRequests::maskIc($user->no_kad_pengenalan) }}</td>
                            <td class="p-3.5 font-medium text-slate-700">
                                {{ $user->positionRelation?->title ?? ($user->getAttribute('position') ?: '-') }}
                                @if ($user->grade) <span class="text-slate-400">/ {{ $user->grade->code }}</span> @endif
                            </td>
                            <td class="p-3.5 font-medium text-slate-600">{{ $user->agency->name ?? 'Tiada Agensi' }}</td>
                            <td class="p-3.5 text-center">
                                @switch($userRole)
                                    @case('superadmin')
                                        <span class="px-2.5 py-1 bg-red-50 text-red-700 font-bold rounded-full text-[10px]">Superadmin</span>
                                        @break
                                    @case('pentadbir_urus_setia')
                                        <span class="px-2.5 py-1 bg-purple-50 text-purple-700 font-bold rounded-full text-[10px]">Urus Setia</span>
                                        @break
                                    @case('pengurusan')
                                        <span class="px-2.5 py-1 bg-teal-50 text-teal-700 font-bold rounded-full text-[10px]">Pengurusan</span>
                                        @break
                                    @case('pengguna_biasa')
                                        <span class="px-2.5 py-1 bg-blue-50 text-blue-700 font-bold rounded-full text-[10px]">Pemohon Agensi</span>
                                        @break
                                    @default
                                        <span class="px-2.5 py-1 bg-slate-100 text-slate-500 font-bold rounded-full text-[10px]">Tiada Peranan</span>
                                @endswitch
                            </td>
                            <td class="p-3.5 text-center">
                                @include('livewire.partials.user-status-badge', ['user' => $user])
                                @if ($user->must_change_password)
                                    <span class="block mt-1 text-[9px] text-amber-600">Kata laluan sementara</span>
                                @endif
                            </td>
                            <td class="p-3.5 text-center space-x-1 whitespace-nowrap">
                                <button wire:click="openModal('{{ $user->uuid }}')" class="px-2.5 py-1 bg-amber-50 text-amber-700 border border-amber-200 hover:bg-amber-100 rounded-lg font-bold text-xs transition">
                                    ✏️ Edit
                                </button>
                                <button wire:click="resetPassword('{{ $user->uuid }}')" wire:loading.attr="disabled"
                                    wire:confirm="Set semula kata laluan {{ $user->name }}? Kata laluan sementara baharu akan dijana dan pengguna wajib menukarnya."
                                    class="px-2.5 py-1 bg-slate-100 hover:bg-slate-200 text-slate-700 border border-slate-200 rounded-lg font-bold text-xs transition">
                                    🔑 Reset Pass
                                </button>
                                @if ($user->isActive())
                                    <button wire:click="openDeactivate('{{ $user->uuid }}')" class="px-2.5 py-1 bg-red-50 text-red-700 border border-red-200 hover:bg-red-100 rounded-lg font-bold text-xs transition">
                                        ⛔ Nyahaktif
                                    </button>
                                @elseif ($user->isInactive())
                                    <button wire:click="reactivate('{{ $user->uuid }}')" wire:loading.attr="disabled"
                                        wire:confirm="Aktifkan semula akaun {{ $user->name }}? Pengguna akan dapat log masuk semula."
                                        class="px-2.5 py-1 bg-emerald-50 text-emerald-700 border border-emerald-200 hover:bg-emerald-100 rounded-lg font-bold text-xs transition">
                                        ✅ Aktifkan Semula
                                    </button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="p-8 text-center text-slate-400">Tiada pengguna dijumpai.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div>{{ $users->links() }}</div>
    </div>

    <!-- MODAL POPUP TAMBAH / EDIT PENGGUNA -->
    @if ($showModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-sm">
            <div class="bg-white rounded-2xl shadow-xl w-full max-w-2xl p-6 space-y-4 max-h-[90vh] overflow-y-auto">
                <h3 class="text-base font-bold text-slate-800 border-b pb-2">
                    {{ $userUuid ? 'Kemaskini Pengguna' : 'Tambah Pengguna Baharu' }}
                </h3>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div>
                        <x-form.input label="No. Kad Pengenalan" wire:model="no_kad_pengenalan" inputmode="numeric" maxlength="12" hint="12 digit, tanpa sengkang." required />
                        @error('no_kad_pengenalan') <span class="text-red-500 text-xs block">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <x-form.input label="Nama Penuh" wire:model="name" required />
                        @error('name') <span class="text-red-500 text-xs block">{{ $message }}</span> @enderror
                    </div>

                    <div class="md:col-span-2">
                        <x-form.select label="Agensi Pengguna" wire:model="agency_id" required>
                            <option value="">-- Pilih Agensi --</option>
                            @foreach ($agencies as $agency)
                                <option value="{{ $agency->id }}">{{ $agency->name }}</option>
                            @endforeach
                        </x-form.select>
                        @error('agency_id') <span class="text-red-500 text-xs block">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <x-form.select label="Jawatan" wire:model.live="position_id" required>
                            <option value="">-- Pilih Jawatan --</option>
                            @foreach ($positions as $position)
                                <option value="{{ $position->id }}">{{ $position->title }}</option>
                            @endforeach
                        </x-form.select>
                        @error('position_id') <span class="text-red-500 text-xs block">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <x-form.input label="Skim Perkhidmatan" readonly tabindex="-1" class="bg-slate-100 text-slate-600 cursor-not-allowed"
                            value="{{ $serviceScheme ? $serviceScheme->code . ' — ' . $serviceScheme->name : '' }}"
                            placeholder="Dipaparkan selepas Jawatan dipilih" />
                    </div>

                    <div>
                        <x-form.select label="Gred" wire:model="grade_id" wire:key="grades-for-{{ $position_id ?: 'none' }}" :disabled="$grades->isEmpty()" required>
                            <option value="">{{ $position_id === '' ? '-- Pilih Jawatan dahulu --' : '-- Pilih Gred --' }}</option>
                            @foreach ($grades as $grade)
                                <option value="{{ $grade->id }}">{{ $grade->code }}</option>
                            @endforeach
                        </x-form.select>
                        @if ($position_id !== '' && $grades->isEmpty())
                            <span class="text-red-500 text-xs block">Tiada Gred yang sah bagi Jawatan ini.</span>
                        @endif
                        @error('grade_id') <span class="text-red-500 text-xs block">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <x-form.select label="Peranan / Tahap Capaian" wire:model="role" required>
                            <option value="">-- Pilih Peranan --</option>
                            @foreach ($roleOptions as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </x-form.select>
                        @error('role') <span class="text-red-500 text-xs block">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <x-form.input label="No. Telefon Pejabat" type="tel" wire:model="phone_office" inputmode="numeric" maxlength="11" hint="Digit sahaja, cth. 067659999" required />
                        @error('phone_office') <span class="text-red-500 text-xs block">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <x-form.input label="No. Telefon HP" type="tel" wire:model="phone_mobile" inputmode="numeric" maxlength="11" hint="Digit sahaja, cth. 0123456789" required />
                        @error('phone_mobile') <span class="text-red-500 text-xs block">{{ $message }}</span> @enderror
                    </div>

                    <div class="md:col-span-2">
                        <x-form.input label="E-mel Rasmi" type="email" wire:model="email" required />
                        @error('email') <span class="text-red-500 text-xs block">{{ $message }}</span> @enderror
                    </div>
                </div>

                @unless ($userUuid)
                    <p class="text-[11px] text-slate-500">Kata laluan sementara akan dijana secara automatik dan dipaparkan sekali selepas akaun didaftarkan.</p>
                @endunless

                <div class="flex justify-end space-x-2 pt-4 border-t">
                    <button type="button" wire:click="closeModal" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold rounded-xl transition">Batal</button>
                    <button type="button" wire:click="saveUser" wire:loading.attr="disabled" wire:target="saveUser" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 disabled:opacity-60 text-white text-xs font-bold rounded-xl transition">Simpan</button>
                </div>
            </div>
        </div>
    @endif

    {{-- UM11: modal Nyahaktif Akaun --}}
    @include('livewire.partials.deactivate-user-modal')
</div>
