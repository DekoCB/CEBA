<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <link rel="icon" type="image/png" href="{{ asset('images/Logo.png') }}">

        @include('partials.theme-boot-script')

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800|quantico:400,700&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="bg-bg font-sans text-ink antialiased">
        <div class="fondo-login" aria-hidden="true"></div>

        <div class="relative flex min-h-screen flex-col items-center justify-center px-4 py-10">
            <a href="{{ route('landing') }}" wire:navigate class="absolute left-4 top-4 flex items-center gap-1.5 text-sm text-ink-faint transition hover:text-ink sm:left-8 sm:top-8">
                <x-heroicon-o-arrow-left class="h-4 w-4" />
                Volver al inicio
            </a>

            <div class="flex flex-col items-center">
                <img src="{{ asset('images/Logo.png') }}" alt="{{ config('app.name') }}" class="h-14 w-14 rounded-xl object-contain">
                <p class="mt-3 font-sans text-base font-extrabold text-ink">CEBA Peruano Británico</p>
                <p class="text-xs text-ink-faint">Acreditado por el MINEDU</p>
            </div>

            <div class="mt-8 w-full overflow-hidden rounded-2xl border border-border bg-surface px-6 py-6 shadow-2xl shadow-black/20 sm:max-w-md sm:px-8 sm:py-8">
                {{ $slot }}
            </div>
        </div>
    </body>
</html>
