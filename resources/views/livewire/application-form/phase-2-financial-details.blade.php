<div class="space-y-6" wire:key="phase-2-form">
    <div class="border-b pb-2">
        <h3 class="text-lg font-semibold text-gray-700">Fasa 2: Maklumat Kewangan & Perolehan</h3>
        <p class="text-xs text-gray-500">Nyatakan anggaran kos, jenis perolehan, dan tempoh pelaksanaan projek.</p>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <x-form.select label="Jenis Perolehan" wire:model="form.procurement_type" :error="$errors->first('form.procurement_type')" required>
            <option value="Pembekalan / Perkhidmatan Tidak Bermasa (One-Off)">Pembekalan / Perkhidmatan Tidak Bermasa (One-Off)</option>
            <option value="Pembekalan / Perkhidmatan Bermasa">Pembekalan / Perkhidmatan Bermasa</option>
            <option value="Penyelenggaraan">Penyelenggaraan</option>
            <option value="Pembangunan Aplikasi">Pembangunan Aplikasi</option>
        </x-form.select>

        <x-form.select label="Kaedah Perolehan" wire:model="form.procurement_method" :error="$errors->first('form.procurement_method')" required>
            <option value="Sebut Harga">Sebut Harga</option>
            <option value="Tender Terbuka">Tender Terbuka</option>
            <option value="Rundingan Terus">Rundingan Terus</option>
        </x-form.select>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <x-form.input label="Anggaran Kos Siling (RM)" type="number" step="0.01" wire:model="form.ceiling_cost" :error="$errors->first('form.ceiling_cost')" required placeholder="0.00" />
        <x-form.input label="Anggaran Kos Projek (RM)" type="number" step="0.01" wire:model="form.estimated_cost" :error="$errors->first('form.estimated_cost')" required placeholder="0.00" />
        <x-form.input label="Tempoh Pelaksanaan (Bulan)" type="number" wire:model="form.expected_duration_months" :error="$errors->first('form.expected_duration_months')" required placeholder="Contoh: 12" />
    </div>

    <x-form.input label="Kod Hasil / Punca Peruntukan" wire:model="form.outcome_code" :error="$errors->first('form.outcome_code')" required placeholder="Contoh: P11 / Peruntukan Pembangunan Negeri" />
</div>