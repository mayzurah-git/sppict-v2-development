<div class="space-y-6" wire:key="phase-2-form">
    <div class="border-b pb-2">
        <h3 class="text-lg font-semibold text-gray-700">Maklumat Peruntukan & Perolehan</h3>
        <p class="text-xs text-gray-500">Nyatakan anggaran kos, jenis perolehan, dan tempoh pelaksanaan projek.</p>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <x-form.select label="Jenis Perolehan" wire:model="form.procurement_type_id" :error="$errors->first('form.procurement_type_id')" required>
            <option value="" disabled>Pilih jenis perolehan</option>
            @foreach ($this->procurementTypeOptions as $option)
                <option value="{{ $option->id }}">{{ $option->name . (! $option->is_active || $option->trashed() ? ' (Tidak Aktif)' : '') }}</option>
            @endforeach
        </x-form.select>

        <x-form.select label="Kaedah Perolehan" wire:model="form.procurement_method_id" :error="$errors->first('form.procurement_method_id')" required>
            <option value="" disabled>Pilih kaedah perolehan</option>
            @foreach ($this->procurementMethodOptions as $option)
                <option value="{{ $option->id }}">{{ $option->name . (! $option->is_active || $option->trashed() ? ' (Tidak Aktif)' : '') }}</option>
            @endforeach
        </x-form.select>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <x-form.input label="Anggaran Kos Siling (RM)" type="number" step="0.01" wire:model="form.ceiling_cost" :error="$errors->first('form.ceiling_cost')" required placeholder="0.00" />
        <x-form.input label="Anggaran Kos Projek (RM)" type="number" step="0.01" wire:model="form.estimated_cost" :error="$errors->first('form.estimated_cost')" required placeholder="0.00" />
        <x-form.input label="Tempoh Pelaksanaan (Bulan)" type="number" wire:model="form.expected_duration_months" :error="$errors->first('form.expected_duration_months')" required placeholder="Contoh: 12" />
    </div>

    <x-form.input label="Rujukan Kelulusan" wire:model="form.outcome_code" :error="$errors->first('form.outcome_code')" required placeholder="Contoh: Bajet Mengurus" />
</div>