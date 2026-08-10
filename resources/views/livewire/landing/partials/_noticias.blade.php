@php
    $noticias = collect([
        ['categoria' => 'admision', 'etiqueta' => 'Admisión', 'fecha' => 'Agosto 2026', 'titulo' => 'Matrículas abiertas para el nuevo ciclo de secundaria', 'color' => 'red'],
        ['categoria' => 'evento', 'etiqueta' => 'Evento', 'fecha' => 'Agosto 2026', 'titulo' => 'Bienvenida a nuestros nuevos estudiantes', 'color' => 'blue'],
        ['categoria' => 'taller', 'etiqueta' => 'Taller', 'fecha' => 'Agosto 2026', 'titulo' => 'Taller de orientación vocacional y proyecto de vida', 'color' => 'amber'],
        ['categoria' => 'motivacional', 'etiqueta' => 'Motivacional', 'fecha' => 'Agosto 2026', 'titulo' => 'Volver a estudiar sí se puede: historias de nuestros egresados', 'color' => 'green'],
    ]);

    $categorias = $noticias->pluck('etiqueta', 'categoria')->all();

    // Primer índice de cada categoría dentro de $noticias -- el botón de
    // filtro simplemente hace girar el carrusel hasta esa tarjeta.
    $indicePorCategoria = $noticias->pluck('categoria')->flip();

@endphp

{{--
    Carrusel 3D en anillo (desktop) para "Noticias y Avisos": las tarjetas
    están fijas alrededor de un cilindro (rotateY + translateZ) y el anillo
    entero gira -- mismo patrón que la rueda de "Proceso de Inscripción"
    (autoplay, pausa al pasar el mouse, botón que salta a un índice), solo
    que en 3D en vez de en un plano. Todo es client-side (Alpine): estos
    datos son estáticos, así que no hay razón para ir al servidor -- ver
    nota en _admision.blade.php sobre por qué eso importa en esta página.
    En móvil, una cuadrícula simple con el mismo filtro (un anillo 3D no se
    lee bien en una pantalla angosta).
--}}
<section id="noticias" class="bg-[#0a0a0a] py-20 sm:py-28">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <x-landing.section-title subtitle="Entérate de nuestras actividades, talleres y fechas de matrícula.">
            Noticias y Avisos
        </x-landing.section-title>

        <div
            x-data="{
                activo: 0,
                total: {{ $noticias->count() }},
                temporizador: null,
                ir(indice) {
                    this.activo = indice;
                    this.reiniciarAutoplay();
                },
                siguiente() { this.activo = (this.activo + 1) % this.total },
                anterior() { this.activo = (this.activo + this.total - 1) % this.total },
                avanzarYReiniciar() { this.siguiente(); this.reiniciarAutoplay(); },
                retrocederYReiniciar() { this.anterior(); this.reiniciarAutoplay(); },
                iniciarAutoplay() {
                    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
                    this.temporizador = setInterval(() => this.siguiente(), 7000);
                },
                reiniciarAutoplay() {
                    clearInterval(this.temporizador);
                    this.iniciarAutoplay();
                },
                // Desplazamiento de una tarjeta respecto a la activa, normalizado
                // al camino más corto alrededor del anillo (p. ej. con 4 tarjetas,
                // valores en el rango [-2, 1]).
                delta(indice) {
                    const mitad = Math.floor(this.total / 2);
                    return ((indice - this.activo + mitad) % this.total + this.total) % this.total - mitad;
                },
                // Estilo 'coverflow' de cada tarjeta según su desplazamiento: la
                // activa queda de frente y al centro; las demás se inclinan,
                // se alejan (translateZ negativo) y se atenúan según qué tan
                // lejos estén, hacia el lado que les corresponda.
                estiloTarjeta(indice) {
                    const d = this.delta(indice);
                    if (d === 0) {
                        return 'transform: translateZ(60px) scale(1); opacity: 1; filter: brightness(1) saturate(1); z-index: 5;';
                    }
                    const signo = d > 0 ? 1 : -1;
                    const magnitud = Math.abs(d);
                    const corrimiento = signo * 165 * magnitud;
                    const profundidad = -150 * magnitud;
                    const angulo = -signo * 38;
                    const escala = Math.max(1 - magnitud * 0.16, 0.6);
                    const opacidad = Math.max(1 - magnitud * 0.38, 0.22);
                    const brillo = Math.max(1 - magnitud * 0.3, 0.4);
                    return `transform: translateX(${corrimiento}px) translateZ(${profundidad}px) rotateY(${angulo}deg) scale(${escala}); opacity: ${opacidad}; filter: brightness(${brillo}) saturate(${brillo}); z-index: ${5 - magnitud};`;
                },
            }"
            x-init="iniciarAutoplay()"
        >
            <div class="mb-10 flex flex-wrap justify-center gap-2">
                @foreach ($categorias as $valor => $etiqueta)
                    <button
                        type="button"
                        @click="ir({{ $indicePorCategoria[$valor] }})"
                        :class="activo === {{ $indicePorCategoria[$valor] }} ? 'bg-red-600 text-white' : 'bg-[#1a1a1c] text-gray-400 hover:text-white'"
                        class="rounded-full px-4 py-2 text-sm font-semibold transition"
                    >
                        {{ $etiqueta }}
                    </button>
                @endforeach
            </div>

            {{-- Menos de lg: cuadrícula simple, siempre las 4 a la vista. --}}
            <div class="mx-auto grid max-w-5xl grid-cols-1 gap-6 sm:grid-cols-2 lg:hidden">
                @foreach ($noticias as $noticia)
                    <div class="h-full">
                        <x-landing.news-card
                            :categoria="$noticia['etiqueta']"
                            :fecha="$noticia['fecha']"
                            :titulo="$noticia['titulo']"
                            :color="$noticia['color']"
                        />
                    </div>
                @endforeach
            </div>

            {{-- Desde lg: anillo 3D. --}}
            <div
                @mouseenter="clearInterval(temporizador)"
                @mouseleave="iniciarAutoplay()"
                class="noticias-carousel-viewport relative mx-auto hidden max-w-3xl lg:block"
            >
                <button
                    type="button"
                    @click="retrocederYReiniciar()"
                    class="absolute left-0 top-1/2 z-10 -translate-y-1/2 rounded-full border border-white/10 bg-[#1a1a1c] p-2 text-gray-400 transition hover:scale-110 hover:border-white/20 hover:text-white active:scale-95"
                    aria-label="Noticia anterior"
                >
                    <x-heroicon-o-chevron-left class="h-5 w-5" />
                </button>
                <button
                    type="button"
                    @click="avanzarYReiniciar()"
                    class="absolute right-0 top-1/2 z-10 -translate-y-1/2 rounded-full border border-white/10 bg-[#1a1a1c] p-2 text-gray-400 transition hover:scale-110 hover:border-white/20 hover:text-white active:scale-95"
                    aria-label="Siguiente noticia"
                >
                    <x-heroicon-o-chevron-right class="h-5 w-5" />
                </button>

                <div class="noticias-carousel-ring mx-auto">
                    @foreach ($noticias as $indice => $noticia)
                        <button
                            type="button"
                            @click="ir({{ $indice }})"
                            :class="activo === {{ $indice }} ? 'is-front' : ''"
                            :style="estiloTarjeta({{ $indice }})"
                            class="noticias-carousel-item"
                        >
                            <x-landing.news-card
                                :categoria="$noticia['etiqueta']"
                                :fecha="$noticia['fecha']"
                                :titulo="$noticia['titulo']"
                                :color="$noticia['color']"
                            />
                        </button>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</section>
