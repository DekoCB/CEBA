<!DOCTYPE html>
<html lang="es" class="dark">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="description" content="CEBA Peruano Británico — Institución educativa acreditada por el MINEDU. Termina tu secundaria en modalidad flexible, virtual y presencial.">

        <title>CEBA Peruano Británico — Educación Básica Alternativa</title>

        <link rel="icon" type="image/png" href="{{ asset('images/Logo.png') }}">

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])

        <style>
            html { scroll-behavior: smooth; }
        </style>
    </head>
    <body class="bg-[#0a0a0a] font-sans text-white antialiased" style="scrollbar-color: #333 #0a0a0a;">
        {{ $slot }}
    </body>
</html>
