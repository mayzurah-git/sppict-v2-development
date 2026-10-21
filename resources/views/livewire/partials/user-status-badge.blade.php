{{-- UM11: status akaun + butiran Tidak Aktif (sebab, tarikh, pegawai). Memerlukan $user. --}}
@if ($user->isActive())
    <span class="px-2.5 py-1 bg-emerald-50 text-emerald-700 font-bold rounded-full text-[10px]">{{ $user->status->label() }}</span>
@elseif ($user->isPending())
    <span class="px-2.5 py-1 bg-amber-50 text-amber-700 font-bold rounded-full text-[10px]">{{ $user->status->label() }}</span>
@elseif ($user->isInactive())
    <span class="px-2.5 py-1 bg-slate-200 text-slate-700 font-bold rounded-full text-[10px]">{{ $user->status->label() }}</span>
    <span class="block mt-1 text-[10px] text-slate-600">{{ $user->inactive_reason?->label() ?? '-' }}</span>
    @if ($user->status_changed_at)
        <span class="block text-[9px] text-slate-400">{{ $user->status_changed_at->format('d/m/Y') }}{{ $user->statusChanger ? ' · ' . $user->statusChanger->name : '' }}</span>
    @endif
@else
    <span class="px-2.5 py-1 bg-red-50 text-red-700 font-bold rounded-full text-[10px]">{{ $user->status?->label() ?? '-' }}</span>
@endif
{{-- UM11A: log masuk terakhir (asas penyahaktifan automatik) --}}
<span class="block mt-1 text-[9px] text-slate-400">Log masuk: {{ $user->last_login_at?->format('d/m/Y') ?? 'Tiada rekod' }}</span>
