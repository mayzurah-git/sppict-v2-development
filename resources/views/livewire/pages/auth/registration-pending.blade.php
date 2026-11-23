<?php

use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
}; ?>

<div class="w-full">
    <div class="mb-4">
        <h1 class="text-2xl font-black text-slate-700 tracking-tight dark:text-slate-100">Pendaftaran Diterima</h1>
    </div>

    <div class="p-4 bg-emerald-50 border-l-4 border-emerald-500 text-emerald-800 text-sm rounded-r-lg space-y-2">
        <p>Terima kasih. Pendaftaran akaun anda telah diterima dan sedang <strong>menunggu kelulusan Urus Setia</strong>.</p>
        <p>Anda boleh log masuk selepas akaun diluluskan.</p>
    </div>

    <div class="flex items-center justify-end mt-6">
        <a href="{{ route('login') }}" wire:navigate
            class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition">
            Ke Halaman Log Masuk
        </a>
    </div>
</div>
