{{-- Pemilih tarikh Flatpickr: dipaparkan dd/mm/yyyy, nilai Livewire dalam format Y-m-d. --}}
@props(['label' => '', 'error' => null, 'required' => false, 'minDate' => null, 'id' => null])

@php($id ??= 'date-' . md5($attributes->wire('model')->value()))

<div>
    @if ($label)
        <label for="{{ $id }}" class="block text-xs font-medium text-gray-700">
            {{ $label }} @if($required)<span class="text-red-500">*</span>@endif
        </label>
    @endif

    <div wire:ignore
         x-data="{ value: @entangle($attributes->wire('model')), picker: null }"
         x-init="
            picker = window.flatpickr($refs.input, {
                dateFormat: 'Y-m-d',
                altInput: true,
                altFormat: 'd/m/Y',
                allowInput: true,
                minDate: @js($minDate),
                defaultDate: value || null,
                onChange: (dates, dateStr) => { value = dateStr },
            });
            $watch('value', (newValue) => picker.setDate(newValue || null, false));
         ">
        <input x-ref="input" id="{{ $id }}" type="text" placeholder="dd/mm/yyyy" autocomplete="off"
               class="mt-1 block w-full rounded-md border-gray-300 text-xs shadow-sm focus:border-blue-500 focus:ring-blue-500 bg-white">
    </div>

    @if ($error)
        <span class="text-red-500 text-xs block mt-1">{{ $error }}</span>
    @endif
</div>
