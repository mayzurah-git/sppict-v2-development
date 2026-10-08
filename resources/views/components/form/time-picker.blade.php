{{-- Pemilih masa Flatpickr: dipaparkan 12 jam (contoh "9.30 pagi"), nilai Livewire dalam format H:i. --}}
@props(['label' => '', 'error' => null, 'required' => false, 'id' => null])

@php($id ??= 'time-' . md5($attributes->wire('model')->value()))

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
                enableTime: true,
                noCalendar: true,
                time_24hr: false,
                dateFormat: 'H:i',
                altInput: true,
                altFormat: 'h.i K',
                minuteIncrement: 15,
                defaultDate: value || null,
                onChange: (dates, timeStr) => { value = timeStr },
            });
            $watch('value', (newValue) => picker.setDate(newValue || null, false));
         ">
        <input x-ref="input" id="{{ $id }}" type="text" placeholder="Pilih masa" autocomplete="off"
               class="mt-1 block w-full rounded-md border-gray-300 text-xs shadow-sm focus:border-blue-500 focus:ring-blue-500 bg-white">
    </div>

    @if ($error)
        <span class="text-red-500 text-xs block mt-1">{{ $error }}</span>
    @endif
</div>
