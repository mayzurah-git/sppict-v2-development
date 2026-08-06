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
<body class="font-sans antialiased text-slate-900 bg-slate-50">

    <div class="min-h-screen flex flex-col md:flex-row">
        
        <!-- SEKSYEN KIRI: BRANDING & IDENTITI SPPICT V2 -->
        <div class="w-full md:w-1/2 bg-gradient-to-br from-indigo-950 via-blue-900 to-indigo-900 p-8 lg:p-12 flex flex-col justify-between relative overflow-hidden text-white min-h-[400px] md:min-h-screen">
            
            <!-- Hiasan Bulatan Gradient Latar Belakang -->
            <div class="absolute -top-24 -left-24 w-96 h-96 bg-blue-600/20 rounded-full blur-3xl pointer-events-none"></div>
            <div class="absolute -bottom-24 -right-24 w-96 h-96 bg-amber-500/10 rounded-full blur-3xl pointer-events-none"></div>

            <!-- Logo & Nama Kerajaan Negeri Sembilan -->
            <div class="relative z-10 flex items-center space-x-3">
                <img src="https://www.ns.gov.my/images/logo_ns.png" alt="Jata Negeri Sembilan" class="h-12 w-auto drop-shadow-md" onError="this.src='https://upload.wikimedia.org/wikipedia/commons/thumb/8/87/Coat_of_arms_of_Negeri_Sembilan.svg/1200px-Coat_of_arms_of_Negeri_Sembilan.svg.png'">
                <div>
                    <span class="block text-xs uppercase tracking-widest text-amber-400 font-bold">Kerajaan Negeri Sembilan</span>
                    <span class="text-sm font-semibold text-blue-200">Setiausaha Kerajaan Negeri Sembilan</span>
                </div>
            </div>

            <!-- Tajuk Utama & Keterangan -->
            <div class="relative z-10 my-auto py-10 space-y-6 max-w-lg">
                <h1 class="text-4xl lg:text-5xl font-black tracking-tight leading-none text-white">
                    SPPICT <span class="text-amber-400">v2</span>
                </h1>
                
                <p class="text-blue-200/90 text-sm md:text-base leading-relaxed">
                    Sistem Permohonan & Pemantauan Kelulusan Projek ICT Negeri Sembilan secara berpusat, efisien dan telus.
                </p>

                <!-- Kad Feature Highlight -->
                <div class="space-y-3 pt-4">
                    <div class="flex items-start space-x-3 bg-white/10 backdrop-blur-md p-3.5 rounded-xl border border-white/10 hover:bg-white/15 transition group">
                        <div class="p-2 bg-amber-500/20 text-amber-400 rounded-lg group-hover:scale-110 transition">
                            ⚡
                        </div>
                        <div>
                            <h4 class="text-xs font-bold text-white uppercase tracking-wider">Permohonan Projek Berfasa</h4>
                            <p class="text-xs text-blue-200/80 mt-0.5">Pengisian borang berfasa Fasa 1 hingga Fasa 5 dengan spesifikasi dinamik.</p>
                        </div>
                    </div>

                    <div class="flex items-start space-x-3 bg-white/10 backdrop-blur-md p-3.5 rounded-xl border border-white/10 hover:bg-white/15 transition group">
                        <div class="p-2 bg-blue-500/20 text-blue-300 rounded-lg group-hover:scale-110 transition">
                            📊
                        </div>
                        <div>
                            <h4 class="text-xs font-bold text-white uppercase tracking-wider">Pemantauan Kemajuan Real-Time</h4>
                            <p class="text-xs text-blue-200/80 mt-0.5">Penjejakan status kelulusan mesyuarat dan laporan kemajuan fizikal & kewangan.</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer Hak Cipta Kiri -->
            <div class="relative z-10 text-xs text-blue-300/60 pt-6 border-t border-white/10 flex justify-between items-center">
                <span>© 2026 Hak Cipta Terpelihara SUKNS</span>
                <span class="text-[10px] bg-blue-900/80 px-2 py-1 rounded border border-blue-700/50">v2.0.4</span>
            </div>
        </div>

        <!-- SEKSYEN KANAN: LIVEWIRE LOGIN COMPONENT -->
        <div class="w-full md:w-1/2 flex items-center justify-center p-8 lg:p-16 bg-white">
            <div class="w-full max-w-md space-y-8">
                
                @auth
                    <!-- Paparan Jika Pengguna Sudah Log Masuk -->
                    <div class="text-center space-y-4">
                        <h2 class="text-3xl font-extrabold text-slate-900 tracking-tight">Selamat Kembali!</h2>
                        <p class="text-sm text-slate-500">Anda kini log masuk sebagai <strong class="text-blue-600">{{ Auth::user()->name }}</strong>.</p>
                        
                        <a href="{{ url('/dashboard') }}" class="w-full flex justify-center py-3.5 px-4 border border-transparent rounded-xl shadow-lg shadow-blue-600/30 text-sm font-bold text-white bg-blue-600 hover:bg-blue-700 transition">
                            Teruskan Ke Dashboard Utama →
                        </a>
                    </div>
                @else
                    <!-- Memanggil Komponen Livewire Volt Login Secara Asal -->
                    <livewire:pages.auth.login />
                @endauth

            </div>
        </div>

    </div>

</body>
</html>