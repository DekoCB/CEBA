@props(['nombre', 'subtitulo' => 'Aquí tienes un resumen de lo que está activo hoy en CEBA.'])

{{--
    Panel de saludo del Dashboard: degradado propio (ver .dashboard-hero-gradient
    en app.css), no ligado al tema claro/oscuro -- el texto siempre es blanco.
    La mascota (x-dashboard.mascot) se recorta en pantallas angostas para no
    empujar el texto.
--}}
<div class="dashboard-hero-gradient relative overflow-hidden rounded-2xl px-6 py-7 shadow-lg sm:px-8">
    <div class="relative z-10 max-w-lg">
        <p class="font-display text-2xl font-medium text-white sm:text-3xl">¡Hola, {{ $nombre }}! 👋</p>
        <p class="mt-2 text-sm text-white/75">{{ $subtitulo }}</p>
    </div>

    <x-dashboard.mascot class="pointer-events-none absolute -bottom-3 -right-2 hidden h-40 w-40 rotate-6 drop-shadow-xl sm:block md:h-48 md:w-48" />
</div>
