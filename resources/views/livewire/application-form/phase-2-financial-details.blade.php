<div class="space-y-6" wire:key="phase-2-form">
    <fieldset class="space-y-3">
        <legend class="text-xs font-extrabold tracking-[0.08em] uppercase {{ $theme['eyebrow'] }}">Perolehan</legend>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <x-form.select size="lg" :focus="$theme['input']" label="Jenis Perolehan" wire:model="form.procurement_type_id" :error="$errors->first('form.procurement_type_id')" required>
                <option value="" disabled>Pilih jenis perolehan</option>
                @foreach ($this->procurementTypeOptions as $option)
                    <option value="{{ $option->id }}">{{ $option->name . (! $option->is_active || $option->trashed() ? ' (Tidak Aktif)' : '') }}</option>
                @endforeach
            </x-form.select>

            <x-form.select size="lg" :focus="$theme['input']" label="Kaedah Perolehan" wire:model="form.procurement_method_id" :error="$errors->first('form.procurement_method_id')" required>
                <option value="" disabled>Pilih kaedah perolehan</option>
                @foreach ($this->procurementMethodOptions as $option)
                    <option value="{{ $option->id }}">{{ $option->name . (! $option->is_active || $option->trashed() ? ' (Tidak Aktif)' : '') }}</option>
                @endforeach
            </x-form.select>
        </div>
    </fieldset>

    <fieldset class="space-y-3">
        <legend class="text-xs font-extrabold tracking-[0.08em] uppercase {{ $theme['eyebrow'] }}">Kos &amp; Tempoh</legend>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <x-form.input size="lg" :focus-within="$theme['inputWithin']" prefix="RM" label="Anggaran Kos Siling" type="number" step="0.01" inputmode="decimal" wire:model.blur="form.ceiling_cost" :error="$errors->first('form.ceiling_cost')" required placeholder="0.00" hint="Had maksimum termasuk SST (jika dikenakan)." />
            <x-form.input size="lg" :focus-within="$theme['inputWithin']" prefix="RM" label="Anggaran Kos Projek" type="number" step="0.01" inputmode="decimal" wire:model.blur="form.estimated_cost" :error="$errors->first('form.estimated_cost')" required placeholder="0.00" />
            <x-form.input size="lg" :focus-within="$theme['inputWithin']" suffix="bulan" label="Tempoh Pelaksanaan" type="number" inputmode="numeric" wire:model="form.expected_duration_months" :error="$errors->first('form.expected_duration_months')" required placeholder="Contoh: 12" />
        </div>
    </fieldset>

    <x-form.input size="lg" :focus-within="$theme['inputWithin']" label="Rujukan Kelulusan" wire:model="form.outcome_code" :error="$errors->first('form.outcome_code')" required placeholder="Contoh: Bajet Mengurus" />
</div>
