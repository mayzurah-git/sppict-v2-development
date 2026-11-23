{{-- size="lg": gaya borang permohonan (lebih besar, warna fokus ikut peranan). --}}
@props(['label' => '', 'error' => null, 'required' => false, 'size' => 'sm', 'focus' => 'focus:border-blue-500 focus:ring-blue-500'])

@php($id = $attributes->get('id') ?? ($attributes->wire('model')->value() ? 's-' . md5($attributes->wire('model')->value()) : null))

<div @class(['space-y-1.5' => $size === 'lg'])>
    @if ($label)
        <label @if ($id) for="{{ $id }}" @endif @class(['block', 'text-xs font-medium text-gray-700' => $size !== 'lg', 'text-[13px] font-bold text-slate-700' => $size === 'lg'])>
            {{ $label }} @if($required)<span class="text-red-600">*</span>@endif
        </label>
    @endif

    <select @if ($id) id="{{ $id }}" @endif {{ $attributes->except('id')->merge(['class' => $size === 'lg'
        ? 'block w-full h-11 rounded-xl text-[15px] text-slate-900 bg-white ' . ($error ? 'border-red-400 ' : 'border-slate-300 ') . $focus
        : 'mt-1 block w-full rounded-md border-gray-300 text-xs shadow-sm focus:border-blue-500 focus:ring-blue-500']) }}>
        {{ $slot }}
    </select>

    @if ($error)
        <span class="text-red-600 text-xs block mt-1">{{ $error }}</span>
    @endif
</div>
