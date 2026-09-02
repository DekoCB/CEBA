@props(['nombre', 'subtitulo' => 'Aquí tienes un resumen de lo que está activo hoy en CEBA.'])

{{--
    Panel de saludo del Dashboard: degradado propio (ver .dashboard-hero-gradient
    en app.css), no ligado al tema claro/oscuro -- el texto siempre es blanco.
    La mascota vive FUERA de la caja con el degradado (que sí recorta sus
    propias esquinas con overflow-hidden) para poder sobresalir del borde
    en vez de quedar cortada por el radio -- por eso el wrapper exterior no
    tiene overflow-hidden y la caja del degradado es un div aparte.
--}}
<div class="relative">
    <div class="dashboard-hero-gradient relative overflow-hidden rounded-2xl px-6 py-7 shadow-lg sm:px-8">
        <div class="relative z-10 max-w-lg">
            <p class="font-display text-2xl font-medium text-white sm:text-3xl">¡Hola, {{ $nombre }}! 👋</p>
            <p class="mt-2 text-sm text-white/75">{{ $subtitulo }}</p>
        </div>
    </div>

    <x-dashboard.mascot class="pointer-events-none absolute -bottom-6 -right-4 hidden h-44 w-44 rotate-6 drop-shadow-2xl sm:block md:-bottom-8 md:-right-5 md:h-56 md:w-56" />
</div>
