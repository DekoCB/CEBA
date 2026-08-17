@php
    $mensajeWhatsapp = $whatsappHref('Hola, quiero información sobre la matrícula en '.$nombreInstitucion.'.');

    $tituloLineas = [
        ['texto' => 'Termina tu Secundaria', 'acento' => false],
        ['texto' => 'en Corto Tiempo', 'acento' => true],
    ];

    $letrasWordmark = str_split('CEBA');
@endphp

{{--
    Hero "portada" adaptado del concepto Loopstack: titular serif que se
    revela palabra por palabra desde una máscara borrosa, wordmark gigante
    que se revela letra por letra, y un cursor personalizado (anillo +
    píldora "glass") que sigue al puntero. A diferencia del original -- una
    sola pantalla fija sin scroll -- aquí todo vive en flujo normal dentro
    de un min-h-screen, para que el resto de la landing (Nosotros,
    Programas, Admisión...) siga debajo con scroll normal.
--}}
<section
    id="inicio"
    x-data="{
        activo: false,
        sobreBoton: false,
        mouseX: 0, mouseY: 0,
        pildoraX: 0, pildoraY: 0,
        anilloX: 0, anilloY: 0,
        escala: 0, escalaObjetivo: 0,
        primerMovimiento: true,
        iniciarCursor() {
            if (! window.matchMedia('(hover: hover) and (pointer: fine)').matches) {
                return;
            }

            const anillo = this.$refs.anilloCursor;
            const pildora = this.$refs.pildoraCursor;
            const boton = this.$refs.botonCta;

            this.$el.addEventListener('mousemove', (evento) => {
                this.mouseX = evento.clientX;
                this.mouseY = evento.clientY;

                if (this.primerMovimiento) {
                    this.pildoraX = this.mouseX; this.pildoraY = this.mouseY;
                    this.anilloX = this.mouseX; this.anilloY = this.mouseY;
                    this.primerMovimiento = false;
                    anillo.classList.add('is-active');
                    pildora.classList.add('is-active');
                }

                if (! this.sobreBoton) this.escalaObjetivo = 1;
            });

            this.$el.addEventListener('mouseleave', () => { this.escalaObjetivo = 0; });
            this.$el.addEventListener('mouseenter', () => { if (! this.sobreBoton) this.escalaObjetivo = 1; });

            boton.addEventListener('mouseenter', () => {
                this.sobreBoton = true;
                this.escalaObjetivo = 0;
                anillo.classList.add('is-expanded');
            });
            boton.addEventListener('mouseleave', () => {
                this.sobreBoton = false;
                this.escalaObjetivo = 1;
                anillo.classList.remove('is-expanded');
            });

            const actualizarFisica = () => {
                // La píldora se retrasa detrás del puntero (LERP); el anillo lo sigue al instante.
                this.pildoraX += (this.mouseX - this.pildoraX) * 0.08;
                this.pildoraY += (this.mouseY - this.pildoraY) * 0.08;
                this.anilloX = this.mouseX;
                this.anilloY = this.mouseY;
                this.escala += (this.escalaObjetivo - this.escala) * 0.15;

                const escalaAnillo = anillo.classList.contains('is-expanded') ? 1.6 * this.escala : this.escala;

                pildora.style.transform = `translate3d(${this.pildoraX}px, ${this.pildoraY}px, 0) translate(-50%, -50%) scale(${this.escala})`;
                anillo.style.transform = `translate3d(${this.anilloX}px, ${this.anilloY}px, 0) translate(-50%, -50%) scale(${escalaAnillo})`;

                requestAnimationFrame(actualizarFisica);
            };
            actualizarFisica();
        },
    }"
    x-init="iniciarCursor()"
    class="relative flex min-h-screen flex-col overflow-hidden bg-[#0a0a0a] pt-16"
>
    {{-- Fondo: resplandor de respaldo + imagen de portada + degradado que funde ambos bordes hacia el negro. --}}
    <div class="absolute inset-0 z-0">
        <div class="hero-glow absolute inset-0 bg-[radial-gradient(circle_at_20%_20%,rgba(220,38,38,0.16),transparent_45%),radial-gradient(circle_at_80%_10%,rgba(15,27,61,0.65),transparent_45%)]"></div>

        <img src="{{ asset('images/fondo-pri.gif') }}" alt="" class="absolute inset-0 h-full w-full object-cover opacity-40">

        <div class="hero-loop-fade absolute inset-0"></div>
    </div>

    <div class="relative z-[2] mx-auto flex w-full max-w-4xl flex-1 flex-col items-center px-4 pb-10 pt-14 text-center sm:px-6 lg:pt-20">
        <div x-data x-reveal class="mb-6">
            <span class="inline-flex items-center gap-2 rounded-full bg-emerald-500/10 px-4 py-1.5 text-xs font-bold text-emerald-400">
                <x-heroicon-o-check-circle class="h-4 w-4" />
                Acreditado por el MINEDU
            </span>
        </div>

        <h1 class="hero-loop-title">
            @foreach ($tituloLineas as $indiceLinea => $linea)
                @php $abrirAcento = $linea['acento']; @endphp
                @if ($abrirAcento)<span class="text-red-500">@endif
                @foreach (explode(' ', $linea['texto']) as $indicePalabra => $palabra)
                    @php $retraso = ($indiceLinea * 2 + $indicePalabra) * 0.1; @endphp
                    <span class="hero-loop-word-mask"><span class="hero-loop-word-inner" style="animation-delay: {{ $retraso }}s">{{ $palabra }}</span></span>{{ $loop->last ? '' : ' ' }}
                @endforeach
                @if ($abrirAcento)</span>@endif
                @if (! $loop->last)<br>@endif
            @endforeach
        </h1>

        <a x-ref="botonCta" href="{{ $mensajeWhatsapp }}" target="_blank" rel="noopener" class="hero-loop-btn">
            <span>Contáctanos por WhatsApp</span>
            <span class="hero-loop-dot"></span>
        </a>
    </div>

    {{-- Bloque inferior decorativo (no es el <footer> real del sitio, que sigue más abajo en la página). --}}
    <div class="relative z-[2] mx-auto w-full max-w-6xl px-4 pb-8 sm:px-6 lg:px-8">
        <div class="flex flex-col items-center gap-2 text-center sm:flex-row sm:items-end sm:justify-between sm:text-left">
            <h2 class="hero-loop-tagline-title">Conversemos</h2>
            <h2 class="hero-loop-tagline-title">Aprende. Crece. Gradúate.</h2>
        </div>

        <hr class="hero-loop-divider">

        <div class="flex flex-col items-center gap-5 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex flex-1 items-center justify-center gap-5 sm:justify-start">
                <a href="{{ $mensajeWhatsapp }}" target="_blank" rel="noopener" aria-label="WhatsApp" class="hero-loop-social">
                    <x-heroicon-o-chat-bubble-left-right class="h-5 w-5" />
                </a>
                <a href="tel:+{{ $whatsappNumero }}" aria-label="Llamar" class="hero-loop-social">
                    <x-heroicon-o-phone class="h-[18px] w-[18px]" />
                </a>
                <a href="mailto:{{ $emailContacto }}" aria-label="Correo" class="hero-loop-social">
                    <x-heroicon-o-envelope class="h-5 w-5" />
                </a>
            </div>

            <nav class="flex flex-[2] flex-wrap items-center justify-center gap-x-7 gap-y-2">
                <a href="#nosotros" class="hero-loop-link">Nosotros</a>
                <a href="#programas" class="hero-loop-link">Programas</a>
                <a href="#admision" class="hero-loop-link">Admisión</a>
                <a href="#contacto" class="hero-loop-link">Contacto</a>
            </nav>

            <div class="flex-1 text-center text-sm text-white/45 sm:text-right">© {{ now()->year }} {{ $nombreInstitucion }}</div>
        </div>
    </div>

    <div class="relative z-[2] flex w-full justify-center overflow-hidden pb-4" aria-hidden="true">
        <h2 class="hero-loop-wordmark">@foreach ($letrasWordmark as $indice => $letra)<span class="hero-loop-letter-mask"><span class="hero-loop-letter-inner" style="animation-delay: {{ $indice * 0.09 }}s">{{ $letra }}</span></span>@endforeach</h2>
    </div>

    <div x-ref="anilloCursor" class="hero-loop-ring"></div>
    <div x-ref="pildoraCursor" class="hero-loop-pill">
        <span class="hero-loop-pill-text"><span class="is-white">Matricúlate</span> Ahora</span>
    </div>
</section>
