<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,800,900&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans text-slate-900 antialiased bg-[#f8faf9]">
        <div class="min-h-screen flex flex-col md:flex-row">
            <x-brand-panel class="md:w-1/2 md:min-h-screen" />

            <main class="flex-1 flex items-start md:items-center justify-center px-6 py-10 md:py-14">
                <div class="w-full max-w-[420px]">
                    {{ $slot }}
                </div>
            </main>
        </div>
    </body>
</html>
