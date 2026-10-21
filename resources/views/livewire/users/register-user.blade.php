<div class="max-w-4xl mx-auto p-6 space-y-6">
    <div class="flex flex-col md:flex-row justify-between items-center gap-4 bg-white p-6 rounded-2xl shadow-sm border border-slate-100">
        <div>
            <h2 class="text-xl font-black text-slate-900">Daftar Pengguna</h2>
            <p class="text-xs text-slate-500 mt-0.5">Pendaftaran akaun oleh Urus Setia. Akaun terus aktif dengan kata laluan sementara yang wajib ditukar.</p>
        </div>
        <a href="{{ route('users.requests') }}" class="px-4 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold rounded-xl transition">
            Permohonan Pendaftaran
        </a>
    </div>

    @if ($temporaryPassword)
        <div class="p-5 bg-emerald-50 border border-emerald-200 rounded-2xl space-y-3" role="alert">
            <p class="text-sm font-bold text-emerald-800">Akaun {{ $createdUserName }} berjaya didaftarkan.</p>
            <div class="text-xs text-emerald-900 space-y-1">
                <p>Kata laluan sementara (dipaparkan <strong>sekali sahaja</strong>):</p>
                <p class="inline-block font-mono text-base tracking-wider bg-white border border-emerald-300 rounded-lg px-3 py-1.5 select-all">{{ $temporaryPassword }}</p>
                <p>Sampaikan kata laluan ini kepada pengguna secara selamat. Pengguna wajib menukar kata laluan selepas log masuk pertama.</p>
            </div>
            <button type="button" wire:click="dismissTemporaryPassword" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-xl transition">
                Saya telah merekodkan kata laluan ini
            </button>
        </div>
    @endif

    <form wire:submit="save" class="bg-white p-6 rounded-2xl shadow-sm border border-slate-100 space-y-4" novalidate>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <x-form.input label="No. Kad Pengenalan" wire:model="no_kad_pengenalan" inputmode="numeric" maxlength="12" hint="12 digit, tanpa sengkang." required />
                @error('no_kad_pengenalan') <span class="text-red-500 text-xs block mt-1">{{ $message }}</span> @enderror
            </div>
            <div>
                <x-form.input label="Nama Penuh" wire:model="name" required />
                @error('name') <span class="text-red-500 text-xs block mt-1">{{ $message }}</span> @enderror
            </div>

            <div class="md:col-span-2">
                <x-form.select label="Jabatan/Agensi" wire:model="agency_id" required>
                    <option value="">-- Pilih Jabatan/Agensi --</option>
                    @foreach ($agencies as $agency)
                        <option value="{{ $agency->id }}">{{ $agency->name }}</option>
                    @endforeach
                </x-form.select>
                @error('agency_id') <span class="text-red-500 text-xs block mt-1">{{ $message }}</span> @enderror
            </div>

            <div>
                <x-form.select label="Jawatan" wire:model.live="position_id" required>
                    <option value="">-- Pilih Jawatan --</option>
                    @foreach ($positions as $position)
                        <option value="{{ $position->id }}">{{ $position->title }}</option>
                    @endforeach
                </x-form.select>
                @error('position_id') <span class="text-red-500 text-xs block mt-1">{{ $message }}</span> @enderror
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
                    <span class="text-red-500 text-xs block mt-1">Tiada Gred yang sah bagi Jawatan ini.</span>
                @endif
                @error('grade_id') <span class="text-red-500 text-xs block mt-1">{{ $message }}</span> @enderror
            </div>
            <div>
                <x-form.select label="Tahap Capaian" wire:model="role" required>
                    <option value="">-- Pilih Tahap Capaian --</option>
                    @foreach ($roleOptions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </x-form.select>
                @error('role') <span class="text-red-500 text-xs block mt-1">{{ $message }}</span> @enderror
            </div>

            <div>
                <x-form.input label="No. Telefon Pejabat" type="tel" wire:model="phone_office" inputmode="numeric" maxlength="11" hint="Digit sahaja, cth. 067659999" required />
                @error('phone_office') <span class="text-red-500 text-xs block mt-1">{{ $message }}</span> @enderror
            </div>
            <div>
                <x-form.input label="No. Telefon HP" type="tel" wire:model="phone_mobile" inputmode="numeric" maxlength="11" hint="Digit sahaja, cth. 0123456789" required />
                @error('phone_mobile') <span class="text-red-500 text-xs block mt-1">{{ $message }}</span> @enderror
            </div>

            <div class="md:col-span-2">
                <x-form.input label="E-mel Rasmi" type="email" wire:model="email" required />
                @error('email') <span class="text-red-500 text-xs block mt-1">{{ $message }}</span> @enderror
            </div>
        </div>

        <div class="flex justify-end pt-4 border-t">
            <button type="submit" wire:loading.attr="disabled" wire:target="save"
                class="px-4 py-2 bg-blue-600 hover:bg-blue-700 disabled:opacity-60 text-white text-xs font-bold rounded-xl transition">
                <span wire:loading.remove wire:target="save">Daftar Pengguna</span>
                <span wire:loading wire:target="save">Mendaftar...</span>
            </button>
        </div>
    </form>
</div>
