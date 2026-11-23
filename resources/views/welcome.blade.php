<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>SPPICT v2 - Portal Permohonan & Pemantauan Projek ICT</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,800,900&display=swap" rel="stylesheet" />

    <!-- Pemanggil Tailwind CSS & JS melalui Vite -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans antialiased text-slate-900 bg-[#f8faf9]">

    <div class="min-h-screen flex flex-col md:flex-row">

        <!-- SEKSYEN KIRI: BRANDING & IDENTITI SPPICT V2 -->
        <x-brand-panel class="md:w-1/2 md:min-h-screen" />

        <!-- SEKSYEN KANAN: LIVEWIRE LOGIN COMPONENT -->
        <main class="flex-1 flex items-start md:items-center justify-center px-6 py-10 md:py-14">
            <div class="w-full max-w-[420px]">

                @auth
                    <!-- Paparan Jika Pengguna Sudah Log Masuk -->
                    <div class="flex flex-col gap-6">
                        <div class="flex flex-col gap-2">
                            <h2 class="text-3xl font-black tracking-tight text-slate-900">Selamat Kembali!</h2>
                            <p class="text-base text-slate-600">Anda kini log masuk sebagai <strong class="text-slate-900">{{ Auth::user()->name }}</strong>.</p>
                        </div>

                        <a href="{{ url('/dashboard') }}" class="h-12 w-full flex items-center justify-center gap-2 rounded-[10px] bg-green-900 text-white text-[15px] font-bold transition hover:bg-green-800 focus:outline-none focus-visible:ring-[3px] focus-visible:ring-amber-500 focus-visible:ring-offset-2">
                            Teruskan ke Papan Pemuka →
                        </a>
                    </div>
                @else
                    <!-- Memanggil Komponen Livewire Volt Login Secara Asal -->
                    <livewire:pages.auth.login />
                @endauth

            </div>
        </main>

    </div>

</body>
</html>
