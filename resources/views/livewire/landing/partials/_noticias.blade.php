@php
    $categorias = ['todos' => 'Todos', 'admision' => 'Admisión', 'evento' => 'Evento', 'taller' => 'Taller', 'motivacional' => 'Motivacional'];

    $noticias = collect([
        ['categoria' => 'admision', 'etiqueta' => 'Admisión', 'fecha' => 'Agosto 2026', 'titulo' => 'Matrículas abiertas para el nuevo ciclo de secundaria', 'color' => 'red'],
        ['categoria' => 'evento', 'etiqueta' => 'Evento', 'fecha' => 'Agosto 2026', 'titulo' => 'Bienvenida a nuestros nuevos estudiantes', 'color' => 'blue'],
        ['categoria' => 'taller', 'etiqueta' => 'Taller', 'fecha' => 'Agosto 2026', 'titulo' => 'Taller de orientación vocacional y proyecto de vida', 'color' => 'amber'],
        ['categoria' => 'motivacional', 'etiqueta' => 'Motivacional', 'fecha' => 'Agosto 2026', 'titulo' => 'Volver a estudiar sí se puede: historias de nuestros egresados', 'color' => 'green'],
    ]);
@endphp

{{--
    El filtro es puramente client-side (Alpine): estas noticias son datos
    estáticos, así que no hay razón para ir al servidor. Antes usaba una
    propiedad Livewire ($set), lo que forzaba un re-render de TODO el
    componente landing.index (todas las secciones viven en un solo
    componente Volt) en cada clic, causando lentitud y que otras secciones
    perdieran su estado de Alpine al hacer el morph completo del DOM.
--}}
<section
    id="noticias"
    x-data="{ filtro: 'todos', categorias: @js($noticias->pluck('categoria')) }"
    class="bg-[#0a0a0a] py-20 sm:py-28"
>
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <x-landing.section-title subtitle="Entérate de nuestras actividades, talleres y fechas de matrícula.">
            Noticias y Avisos
        </x-landing.section-title>

        <div class="mb-10 flex flex-wrap justify-center gap-2">
            @foreach ($categorias as $valor => $etiqueta)
                <button
                    type="button"
                    @click="filtro = '{{ $valor }}'"
                    :class="filtro === '{{ $valor }}' ? 'bg-red-600 text-white' : 'bg-[#1a1a1c] text-gray-400 hover:text-white'"
                    class="rounded-full px-4 py-2 text-sm font-semibold transition"
                >
                    {{ $etiqueta }}
                </button>
            @endforeach
        </div>

        <div class="mx-auto grid max-w-5xl grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ($noticias as $noticia)
                <div
                    x-show="filtro === 'todos' || filtro === '{{ $noticia['categoria'] }}'"
                    x-transition:enter="transition ease-out duration-200"
                    x-transition:enter-start="opacity-0 scale-95"
                    x-transition:enter-end="opacity-100 scale-100"
                    x-transition:leave="transition ease-in duration-100"
                    x-transition:leave-start="opacity-100 scale-100"
                    x-transition:leave-end="opacity-0 scale-95"
                    class="h-full"
                >
                    <x-landing.news-card
                        :categoria="$noticia['etiqueta']"
                        :fecha="$noticia['fecha']"
                        :titulo="$noticia['titulo']"
                        :color="$noticia['color']"
                    />
                </div>
            @endforeach

            <p x-show="filtro !== 'todos' && ! categorias.includes(filtro)" x-cloak class="col-span-full text-center text-sm text-gray-500">
                No hay avisos en esta categoría por ahora.
            </p>
        </div>
    </div>
</section>
