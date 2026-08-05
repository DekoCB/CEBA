@php
    $enlacesRapidos = [
        '#inicio' => 'Inicio',
        '#nosotros' => 'Nosotros',
        '#programas' => 'Programas',
        '#admision' => 'Admisión',
        '#docentes' => 'Docentes',
        '#noticias' => 'Noticias',
        '#contacto' => 'Contacto',
    ];
@endphp

<footer class="border-t border-white/5 bg-[#0f1b3d]">
    <div class="mx-auto max-w-7xl px-4 py-16 sm:px-6 lg:px-8">
        <div class="grid grid-cols-1 gap-10 sm:grid-cols-3">
            <div>
                <div class="flex items-center gap-3">
                    <img src="{{ asset('images/Logo.png') }}" alt="{{ $nombreInstitucion }}" class="h-9 w-9 rounded-lg object-contain">
                    <span class="font-sans text-sm font-extrabold text-white">{{ $nombreInstitucion }}</span>
                </div>
                <p class="mt-4 text-sm text-gray-400">
                    Institución educativa acreditada por el MINEDU, dedicada a la Educación Básica Alternativa para
                    jóvenes y adultos.
                </p>
                <p class="mt-4 font-sans text-sm font-bold text-red-500">Tu segunda oportunidad empieza aquí.</p>
            </div>

            <div>
                <h3 class="font-sans text-sm font-bold uppercase tracking-wide text-white">Enlaces Rápidos</h3>
                <ul class="mt-4 space-y-2.5">
                    @foreach ($enlacesRapidos as $href => $etiqueta)
                        <li>
                            <a href="{{ $href }}" class="text-sm text-gray-400 transition hover:text-white">{{ $etiqueta }}</a>
                        </li>
                    @endforeach
                </ul>
            </div>

            <div>
                <h3 class="font-sans text-sm font-bold uppercase tracking-wide text-white">Contacto</h3>
                <ul class="mt-4 space-y-3">
                    <li class="flex items-center gap-2 text-sm text-gray-400">
                        <x-heroicon-o-phone class="h-4 w-4 shrink-0 text-red-500" />
                        {{ $whatsappNumeroVisible }}
                    </li>
                    <li class="flex items-center gap-2 text-sm text-gray-400">
                        <x-heroicon-o-envelope class="h-4 w-4 shrink-0 text-red-500" />
                        <span class="break-all">{{ $emailContacto }}</span>
                    </li>
                </ul>
            </div>
        </div>

        <div class="mt-12 border-t border-white/5 pt-6 text-center">
            <p class="text-xs text-gray-500">© {{ now()->year }} {{ $nombreInstitucion }}. Todos los derechos reservados.</p>
        </div>
    </div>
</footer>
