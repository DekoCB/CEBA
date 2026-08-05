@php
    $enlaces = [
        '#inicio' => 'Inicio',
        '#nosotros' => 'Nosotros',
        '#programas' => 'Programas',
        '#admision' => 'Admisión',
        '#docentes' => 'Docentes',
        '#noticias' => 'Noticias',
        '#contacto' => 'Contacto',
    ];
@endphp

<header
    x-data="{ menuAbierto: false, conScroll: false }"
    x-init="window.addEventListener('scroll', () => conScroll = window.scrollY > 12)"
    :class="conScroll ? 'bg-[#0f1b3d] shadow-lg shadow-black/30' : 'bg-[#0f1b3d]/80 backdrop-blur'"
    class="sticky top-0 z-50 border-b border-white/5 transition-colors"
>
    <div class="mx-auto flex h-16 max-w-7xl items-center justify-between px-4 sm:px-6 lg:px-8">
        <a href="#inicio" class="flex items-center gap-3">
            <img src="{{ asset('images/Logo.png') }}" alt="{{ $nombreInstitucion }}" class="h-9 w-9 rounded-lg object-contain">
            <span class="leading-tight">
                <span class="block font-sans text-sm font-extrabold text-white">{{ $nombreInstitucion }}</span>
                <span class="block text-[11px] font-medium text-gray-400">Acreditado por el MINEDU</span>
            </span>
        </a>

        <nav class="hidden items-center gap-7 lg:flex">
            @foreach ($enlaces as $href => $etiqueta)
                <a href="{{ $href }}" class="text-sm font-medium text-gray-300 transition hover:text-white">{{ $etiqueta }}</a>
            @endforeach
        </nav>

        <div class="hidden items-center gap-4 lg:flex">
            <a href="{{ route('login') }}" wire:navigate class="text-sm font-medium text-gray-400 transition hover:text-white">Iniciar sesión</a>
            <x-landing.cta-button href="#contacto" variant="primary">Matricúlate Ahora</x-landing.cta-button>
        </div>

        <button
            type="button"
            @click="menuAbierto = ! menuAbierto"
            class="text-white lg:hidden"
            aria-label="Abrir menú"
        >
            <x-heroicon-o-bars-3 x-show="! menuAbierto" class="h-7 w-7" />
            <x-heroicon-o-x-mark x-show="menuAbierto" x-cloak class="h-7 w-7" />
        </button>
    </div>

    <div
        x-show="menuAbierto"
        x-cloak
        x-transition
        @click="menuAbierto = false"
        class="border-t border-white/5 bg-[#0f1b3d] px-4 pb-4 pt-2 lg:hidden"
    >
        <nav class="flex flex-col gap-1">
            @foreach ($enlaces as $href => $etiqueta)
                <a href="{{ $href }}" class="rounded-lg px-3 py-2.5 text-sm font-medium text-gray-300 transition hover:bg-white/5 hover:text-white">{{ $etiqueta }}</a>
            @endforeach
            <a href="{{ route('login') }}" wire:navigate class="rounded-lg px-3 py-2.5 text-sm font-medium text-gray-400 transition hover:bg-white/5 hover:text-white">Iniciar sesión</a>
        </nav>
        <x-landing.cta-button href="#contacto" variant="primary" class="mt-3 w-full">Matricúlate Ahora</x-landing.cta-button>
    </div>
</header>
