<div class="space-y-5" wire:key="phase-1-form">
    <x-form.input size="lg" :focus-within="$theme['inputWithin']" label="Nama Projek" wire:model="form.title" :error="$errors->first('form.title')" required placeholder="Contoh: Naik Taraf Infrastruktur Rangkaian Agensi" />

    <div class="max-w-md">
        <x-form.select size="lg" :focus="$theme['input']" label="Kategori Permohonan" wire:model="form.application_category" :error="$errors->first('form.application_category')" required>
            <option value="" disabled>Pilih kategori permohonan</option>
            @foreach ($this->applicationCategoryOptions as $option)
                <option value="{{ $option->code }}">{{ $option->name . (! $option->is_active || $option->trashed() ? ' (Tidak Aktif)' : '') }}</option>
            @endforeach
        </x-form.select>
    </div>

    {{-- Teks berformat (Trix); HTML dibersihkan di pelayan (App\Support\RichText) sebelum disimpan. --}}
    <x-form.rich-text size="lg" :focus="$theme['input']" label="Objektif Projek" wire:model="form.objectives" :error="$errors->first('form.objectives')" required
        hint="Senaraikan objektif utama yang ingin dicapai." />

    <x-form.rich-text size="lg" :focus="$theme['input']" label="Skop Projek" wire:model="form.project_scope" :error="$errors->first('form.project_scope')" required
        hint="Nyatakan skop kerja dan batasan pelaksanaan." />
</div>
