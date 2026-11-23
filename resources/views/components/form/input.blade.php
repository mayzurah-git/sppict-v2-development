{{-- size="lg": gaya borang permohonan (lebih besar, warna fokus ikut peranan), dengan pilihan prefix/suffix (cth. "RM", "bulan"). --}}
@props(['label' => '', 'error' => null, 'required' => false, 'hint' => null, 'size' => 'sm', 'prefix' => null, 'suffix' => null, 'focus' => 'focus:border-blue-500 focus:ring-blue-500', 'focusWithin' => 'focus-within:border-blue-500 focus-within:ring-blue-500/20'])

@php($id = $attributes->get('id') ?? ($attributes->wire('model')->value() ? 'f-' . md5($attributes->wire('model')->value()) : null))

<div @class(['space-y-1.5' => $size === 'lg'])>
    @if ($label)
        <label @if ($id) for="{{ $id }}" @endif @class(['block', 'text-xs font-medium text-gray-700' => $size !== 'lg', 'text-[13px] font-bold text-slate-700' => $size === 'lg'])>
            {{ $label }} @if($required)<span class="text-red-600">*</span>@endif
        </label>
    @endif

    @if ($size === 'lg')
        <div @class(['flex rounded-xl border bg-white overflow-hidden focus-within:ring-2', 'border-red-400' => $error, 'border-slate-300' => ! $error, $focusWithin])>
            @if ($prefix)<span class="flex items-center px-3 bg-slate-100 text-sm font-bold text-slate-600 border-r border-slate-200">{{ $prefix }}</span>@endif
            <input @if ($id) id="{{ $id }}" @endif {{ $attributes->except('id')->merge(['class' => 'flex-1 min-w-0 h-11 px-3.5 border-0 text-[15px] text-slate-900 focus:ring-0']) }}>
            @if ($suffix)<span class="flex items-center px-3 bg-slate-100 text-sm font-bold text-slate-600 border-l border-slate-200">{{ $suffix }}</span>@endif
        </div>
    @else
        <input @if ($id) id="{{ $id }}" @endif {{ $attributes->except('id')->merge(['class' => 'mt-1 block w-full rounded-md border-gray-300 text-xs shadow-sm focus:border-blue-500 focus:ring-blue-500']) }}>
    @endif

    @if ($hint)
        <span @class(['block', 'text-[10px] text-gray-400 mt-0.5' => $size !== 'lg', 'text-xs text-slate-500' => $size === 'lg'])>{{ $hint }}</span>
    @endif

    @if ($error)
        <span class="text-red-600 text-xs block mt-1">{{ $error }}</span>
    @endif
</div>
