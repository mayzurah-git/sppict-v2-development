<div class="space-y-6" wire:key="phase-1-form">
    <div class="border-b pb-2">
        <h3 class="text-lg font-semibold text-gray-700">Maklumat Am</h3>
        <p class="text-xs text-gray-500">Sila nyatakan maklumat asas permohonan projek ICT agensi anda.</p>
    </div>

    <x-form.input label="Nama Projek" wire:model="form.title" :error="$errors->first('form.title')" required placeholder="Contoh: Naik Taraf Infrastruktur Rangkaian Agensi" />

    <x-form.select label="Kategori Permohonan" wire:model="form.application_category" :error="$errors->first('form.application_category')" required>
        <option value="" disabled>Pilih kategori permohonan</option>
        @foreach ($this->applicationCategoryOptions as $option)
            <option value="{{ $option->code }}">{{ $option->name . (! $option->is_active || $option->trashed() ? ' (Tidak Aktif)' : '') }}</option>
        @endforeach
    </x-form.select>

    <div>
        <label class="block text-xs font-medium text-gray-700">Objektif Projek *</label>
        <textarea wire:model="form.objectives" rows="3" class="mt-1 block w-full rounded-md border-gray-300 text-xs shadow-sm focus:border-blue-500 focus:ring-blue-500" placeholder="Senaraikan objektif utama yang ingin dicapai..."></textarea>
        @error('form.objectives') <span class="text-red-500 text-xs block mt-1">{{ $message }}</span> @enderror
    </div>

    <div>
        <label class="block text-xs font-medium text-gray-700">Skop Projek *</label>
        <textarea wire:model="form.project_scope" rows="3" class="mt-1 block w-full rounded-md border-gray-300 text-xs shadow-sm focus:border-blue-500 focus:ring-blue-500" placeholder="Nyatakan skop kerja dan batasan pelaksanaan..."></textarea>
        @error('form.project_scope') <span class="text-red-500 text-xs block mt-1">{{ $message }}</span> @enderror
    </div>
</div>