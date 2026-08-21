<div class="space-y-6" wire:key="phase-5-form">
    <div class="border-b pb-2">
        <h3 class="text-lg font-semibold text-gray-700">Fasa 5: Pegawai Bertanggungjawab & Semakan Akhir</h3>
        <p class="text-xs text-gray-500">Sila sahkan maklumat pegawai untuk dihubungi sebelum membuat penyerahan rasmi.</p>
    </div>

    <!-- PEGAWAI UTAMA -->
    <div class="p-4 bg-gray-50 border rounded-lg space-y-4">
        <h4 class="text-xs font-bold text-blue-800 uppercase tracking-wider flex items-center gap-1.5">
            <span class="w-2 h-2 rounded-full bg-blue-600"></span>
            Pegawai Utama / Pengurus Projek (Pemohon)
        </h4>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <x-form.input label="Nama Pegawai Utama" wire:model="form.officer_name" :error="$errors->first('form.officer_name')" required />
            <x-form.input label="Jawatan & Gred" wire:model="form.officer_position" :error="$errors->first('form.officer_position')" required />
            <x-form.input label="E-mel Rasmi" type="email" wire:model="form.officer_email" :error="$errors->first('form.officer_email')" required />
            <x-form.input label="No. Telefon Pejabat / Bimbit" wire:model="form.officer_phone" :error="$errors->first('form.officer_phone')" required />
        </div>
    </div>

    <!-- TOGGLE CHECKBOX PEGAWAI PENGGANTI -->
    <div class="p-3 bg-gray-100 rounded-lg border border-gray-200">
        <label class="inline-flex items-center cursor-pointer">
            <input type="checkbox" wire:model.live="form.has_secondary_officer" class="rounded border-gray-300 text-blue-600 shadow-sm focus:ring-blue-500">
            <span class="ml-2 text-xs font-bold text-gray-700">Tambah Maklumat Pegawai Pengganti / Urus Setia Agensi (Pilihan)</span>
        </label>
        <p class="text-[11px] text-gray-500 mt-0.5 ml-6">Tanda pilihan ini sekiranya anda ingin mendaftarkan pegawai kedua sebagai alternatif jika berlaku pertukaran pegawai.</p>
    </div>

    <!-- PEGAWAI PENGGANTI (DYNAMIC) -->
    @if ($form->has_secondary_officer)
        <div class="p-4 bg-blue-50/50 border border-blue-200 rounded-lg space-y-4">
            <h4 class="text-xs font-bold text-blue-900 uppercase tracking-wider flex items-center gap-1.5">
                <span class="w-2 h-2 rounded-full bg-amber-500"></span>
                Maklumat Pegawai Pengganti (Alternatif)
            </h4>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <x-form.input label="Nama Pegawai Pengganti" wire:model="form.secondary_officer_name" :error="$errors->first('form.secondary_officer_name')" required placeholder="Contoh: Encik Ahmad bin Hassan" />
                <x-form.input label="Jawatan & Gred" wire:model="form.secondary_officer_position" :error="$errors->first('form.secondary_officer_position')" required placeholder="Contoh: Penolong Pegawai TM F29" />
                <x-form.input label="E-mel Rasmi" type="email" wire:model="form.secondary_officer_email" :error="$errors->first('form.secondary_officer_email')" required placeholder="ahmad@ns.gov.my" />
                <x-form.input label="No. Telefon Pejabat / Bimbit" wire:model="form.secondary_officer_phone" :error="$errors->first('form.secondary_officer_phone')" required placeholder="06-1234567 / 012-3456789" />
            </div>
        </div>
    @endif
</div>