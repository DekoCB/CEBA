@php
    $categorias = ['todos' => 'Todos', 'admision' => 'Admisión', 'evento' => 'Evento', 'taller' => 'Taller', 'motivacional' => 'Motivacional'];

    $noticias = collect([
        ['categoria' => 'admision', 'etiqueta' => 'Admisión', 'fecha' => 'Agosto 2026', 'titulo' => 'Matrículas abiertas para el nuevo ciclo de secundaria', 'color' => 'red'],
        ['categoria' => 'evento', 'etiqueta' => 'Evento', 'fecha' => 'Agosto 2026', 'titulo' => 'Bienvenida a nuestros nuevos estudiantes', 'color' => 'blue'],
        ['categoria' => 'taller', 'etiqueta' => 'Taller', 'fecha' => 'Agosto 2026', 'titulo' => 'Taller de orientación vocacional y proyecto de vida', 'color' => 'amber'],
        ['categoria' => 'motivacional', 'etiqueta' => 'Motivacional', 'fecha' => 'Agosto 2026', 'titulo' => 'Volver a estudiar sí se puede: historias de nuestros egresados', 'color' => 'green'],
    ])->filter(fn ($noticia) => $filtroNoticias === 'todos' || $noticia['categoria'] === $filtroNoticias);
@endphp

<section id="noticias" class="bg-[#0a0a0a] py-20 sm:py-28">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <x-landing.section-title subtitle="Entérate de nuestras actividades, talleres y fechas de matrícula.">
            Noticias y Avisos
        </x-landing.section-title>

        <div class="mb-10 flex flex-wrap justify-center gap-2">
            @foreach ($categorias as $valor => $etiqueta)
                <button
                    type="button"
                    wire:click="$set('filtroNoticias', '{{ $valor }}')"
                    @class([
                        'rounded-full px-4 py-2 text-sm font-semibold transition',
                        'bg-red-600 text-white' => $filtroNoticias === $valor,
                        'bg-[#1a1a1c] text-gray-400 hover:text-white' => $filtroNoticias !== $valor,
                    ])
                >
                    {{ $etiqueta }}
                </button>
            @endforeach
        </div>

        <div class="mx-auto grid max-w-5xl grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">
            @forelse ($noticias as $noticia)
                <div wire:key="noticia-{{ $noticia['categoria'] }}">
                    <x-landing.news-card
                        :categoria="$noticia['etiqueta']"
                        :fecha="$noticia['fecha']"
                        :titulo="$noticia['titulo']"
                        :color="$noticia['color']"
                    />
                </div>
            @empty
                <p class="col-span-full text-center text-sm text-gray-500">No hay avisos en esta categoría por ahora.</p>
            @endforelse
        </div>
    </div>
</section>
