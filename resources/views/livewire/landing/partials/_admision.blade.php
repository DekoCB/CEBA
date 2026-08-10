<section id="admision" class="bg-[#0a0a0a] py-20 sm:py-28">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <x-landing.section-title subtitle="¡Es muy fácil! Sigue estos pasos y comienza tu secundaria con nosotros.">
            Proceso de Admisión
        </x-landing.section-title>

        <div class="mx-auto grid max-w-4xl grid-cols-1 gap-6 sm:grid-cols-2">
            <div x-data x-reveal class="rounded-2xl border border-amber-500/20 bg-amber-500/5 p-8">
                <x-landing.icon-circle icon="document-check" color="amber" />
                <h3 class="mt-4 font-sans text-lg font-bold text-white">Requisitos de Matrícula</h3>
                <div class="mt-4 space-y-2.5">
                    @foreach ([
                        'DNI original y copia',
                        'Certificado de estudios de primaria o secundaria incompleta',
                        'Partida de nacimiento (opcional)',
                        '2 fotos tamaño carnet',
                        'Edad mínima: 14 años (con primaria completa)',
                    ] as $requisito)
                        <p class="flex items-start gap-2 text-sm text-gray-300">
                            <x-heroicon-o-check class="mt-0.5 h-5 w-5 shrink-0 text-emerald-400" />
                            {{ $requisito }}
                        </p>
                    @endforeach
                </div>
            </div>

            <div x-data x-reveal class="rounded-2xl border border-blue-500/20 bg-blue-500/5 p-8">
                <x-landing.icon-circle icon="calendar-days" color="blue" />
                <h3 class="mt-4 font-sans text-lg font-bold text-white">Fechas Importantes</h3>
                <div class="mt-4 space-y-2.5">
                    @foreach ([
                        'Matrículas abiertas todo el año',
                        'No necesitas esperar al próximo ciclo para inscribirte',
                        'Modalidad flexible: virtual y presencial',
                        'Matrícula 100% gratuita',
                    ] as $fecha)
                        <p class="flex items-start gap-2 text-sm text-gray-300">
                            <x-heroicon-o-check class="mt-0.5 h-5 w-5 shrink-0 text-emerald-400" />
                            {{ $fecha }}
                        </p>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="mt-20">
            <h3 class="text-center font-sans text-xl font-bold text-white">Proceso de Inscripción</h3>

            {{-- Menos de lg: la misma lista de pasos que antes, en columnas. --}}
            <div class="mx-auto mt-10 grid max-w-5xl grid-cols-1 gap-8 sm:grid-cols-2 lg:hidden">
                <div x-data x-reveal>
                    <x-landing.step-number number="1" icon="chat-bubble-left-right" color="red" title="Contacto Inicial">
                        Escríbenos por WhatsApp o llena el formulario de contacto.
                    </x-landing.step-number>
                </div>
                <div x-data x-reveal>
                    <x-landing.step-number number="2" icon="light-bulb" color="blue" title="Información y Orientación">
                        Te explicamos el programa, horarios y costos sin compromiso.
                    </x-landing.step-number>
                </div>
                <div x-data x-reveal>
                    <x-landing.step-number number="3" icon="document-check" color="amber" title="Presentación de Documentos">
                        Nos envías o acercas tus documentos para validar tu ingreso.
                    </x-landing.step-number>
                </div>
                <div x-data x-reveal>
                    <x-landing.step-number number="4" icon="pencil-square" color="green" title="Matrícula">
                        Completamos tu matrícula, totalmente gratuita.
                    </x-landing.step-number>
                </div>
                <div x-data x-reveal>
                    <x-landing.step-number number="5" icon="academic-cap" color="pink" title="Inicio de Clases">
                        Comienzas tus clases y tu acompañamiento docente.
                    </x-landing.step-number>
                </div>
            </div>

            {{--
                Desde lg: rueda circular -- el círculo central muestra el paso
                activo, y los otros 4 quedan como píldoras alrededor. Un
                diagrama radial no se puede leer bien en una pantalla angosta,
                por eso solo se usa desde este punto de quiebre; en móvil
                sigue la lista de arriba.
            --}}
            <div x-data x-reveal class="hidden lg:block">
                <div
                    x-data="{
                        activo: 0,
                        temporizador: null,
                        ir(indice) {
                            this.activo = indice;
                            this.reiniciarAutoplay();
                        },
                        siguiente() { this.activo = (this.activo + 1) % 5 },
                        anterior() { this.activo = (this.activo + 4) % 5 },
                        avanzarYReiniciar() { this.siguiente(); this.reiniciarAutoplay(); },
                        retrocederYReiniciar() { this.anterior(); this.reiniciarAutoplay(); },
                        iniciarAutoplay() {
                            if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
                            this.temporizador = setInterval(() => this.siguiente(), 5000);
                        },
                        reiniciarAutoplay() {
                            clearInterval(this.temporizador);
                            this.iniciarAutoplay();
                        },
                    }"
                    x-init="iniciarAutoplay()"
                    @mouseenter="clearInterval(temporizador)"
                    @mouseleave="iniciarAutoplay()"
                    class="relative mx-auto mt-10 h-[480px] max-w-2xl"
                >
                    <button
                        type="button"
                        @click="retrocederYReiniciar()"
                        class="absolute left-0 top-1/2 z-10 -translate-y-1/2 rounded-full border border-white/10 bg-[#1a1a1c] p-2 text-gray-400 transition hover:scale-110 hover:border-white/20 hover:text-white active:scale-95"
                        aria-label="Paso anterior"
                    >
                        <x-heroicon-o-chevron-left class="h-5 w-5" />
                    </button>
                    <button
                        type="button"
                        @click="avanzarYReiniciar()"
                        class="absolute right-0 top-1/2 z-10 -translate-y-1/2 rounded-full border border-white/10 bg-[#1a1a1c] p-2 text-gray-400 transition hover:scale-110 hover:border-white/20 hover:text-white active:scale-95"
                        aria-label="Paso siguiente"
                    >
                        <x-heroicon-o-chevron-right class="h-5 w-5" />
                    </button>

                    @foreach ([
                        ['icon' => 'chat-bubble-left-right', 'color' => 'red', 'titulo' => 'Contacto Inicial', 'descripcion' => 'Escríbenos por WhatsApp o llena el formulario de contacto.', 'x' => 0, 'y' => -210],
                        ['icon' => 'light-bulb', 'color' => 'blue', 'titulo' => 'Información y Orientación', 'descripcion' => 'Te explicamos el programa, horarios y costos sin compromiso.', 'x' => 200, 'y' => -65],
                        ['icon' => 'document-check', 'color' => 'amber', 'titulo' => 'Presentación de Documentos', 'descripcion' => 'Nos envías o acercas tus documentos para validar tu ingreso.', 'x' => 123, 'y' => 170],
                        ['icon' => 'pencil-square', 'color' => 'green', 'titulo' => 'Matrícula', 'descripcion' => 'Completamos tu matrícula, totalmente gratuita.', 'x' => -123, 'y' => 170],
                        ['icon' => 'academic-cap', 'color' => 'pink', 'titulo' => 'Inicio de Clases', 'descripcion' => 'Comienzas tus clases y tu acompañamiento docente.', 'x' => -200, 'y' => -65],
                    ] as $indice => $paso)
                        @php
                            $colorActivo = [
                                'red' => 'bg-red-600 border-red-600 text-white shadow-lg shadow-red-600/30',
                                'blue' => 'bg-blue-600 border-blue-600 text-white shadow-lg shadow-blue-600/30',
                                'amber' => 'bg-amber-500 border-amber-500 text-white shadow-lg shadow-amber-500/30',
                                'green' => 'bg-emerald-600 border-emerald-600 text-white shadow-lg shadow-emerald-600/30',
                                'pink' => 'bg-fuchsia-600 border-fuchsia-600 text-white shadow-lg shadow-fuchsia-600/30',
                            ][$paso['color']];
                        @endphp
                        <button
                            type="button"
                            @click="ir({{ $indice }})"
                            :class="activo === {{ $indice }} ? '{{ $colorActivo }} scale-105' : 'border-white/10 bg-[#1a1a1c] text-gray-300 hover:-translate-y-0.5 hover:border-white/20 hover:text-white'"
                            class="absolute left-1/2 top-1/2 w-36 -translate-x-1/2 -translate-y-1/2 rounded-2xl border px-4 py-2.5 text-center text-xs font-semibold leading-tight transition duration-300"
                            style="margin-left: {{ $paso['x'] }}px; margin-top: {{ $paso['y'] }}px;"
                        >
                            {{ $indice + 1 }}. {{ $paso['titulo'] }}
                        </button>
                    @endforeach

                    <div class="absolute left-1/2 top-1/2 h-56 w-56 -translate-x-1/2 -translate-y-1/2 rounded-full border border-white/10 bg-[#121214] shadow-2xl shadow-black/50">
                        @foreach ([
                            ['icon' => 'chat-bubble-left-right', 'color' => 'red', 'titulo' => 'Contacto Inicial', 'descripcion' => 'Escríbenos por WhatsApp o llena el formulario de contacto.'],
                            ['icon' => 'light-bulb', 'color' => 'blue', 'titulo' => 'Información y Orientación', 'descripcion' => 'Te explicamos el programa, horarios y costos sin compromiso.'],
                            ['icon' => 'document-check', 'color' => 'amber', 'titulo' => 'Presentación de Documentos', 'descripcion' => 'Nos envías o acercas tus documentos para validar tu ingreso.'],
                            ['icon' => 'pencil-square', 'color' => 'green', 'titulo' => 'Matrícula', 'descripcion' => 'Completamos tu matrícula, totalmente gratuita.'],
                            ['icon' => 'academic-cap', 'color' => 'pink', 'titulo' => 'Inicio de Clases', 'descripcion' => 'Comienzas tus clases y tu acompañamiento docente.'],
                        ] as $indice => $paso)
                            <div
                                x-show="activo === {{ $indice }}"
                                x-cloak
                                x-transition:enter="transition ease-out duration-300"
                                x-transition:enter-start="opacity-0 scale-90"
                                x-transition:enter-end="opacity-100 scale-100"
                                x-transition:leave="transition ease-in duration-150"
                                x-transition:leave-start="opacity-100 scale-100"
                                x-transition:leave-end="opacity-0 scale-90"
                                class="absolute inset-0 flex flex-col items-center justify-center p-8 text-center"
                            >
                                <x-landing.icon-circle :icon="$paso['icon']" :color="$paso['color']" size="sm" />
                                <p class="mt-3 font-sans text-sm font-bold text-white">{{ $indice + 1 }}. {{ $paso['titulo'] }}</p>
                                <p class="mt-1.5 text-xs leading-relaxed text-gray-400">{{ $paso['descripcion'] }}</p>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        <div x-data x-reveal class="mt-20 overflow-hidden rounded-3xl bg-red-600">
            <div class="flex flex-col items-center gap-6 px-6 py-12 text-center sm:px-12">
                <h3 class="font-sans text-3xl font-extrabold text-white">¡Matrícula GRATIS!</h3>
                <p class="max-w-xl text-red-100">
                    Solo pagas S/ 80 de pensión mensual. Escríbenos hoy mismo y asegura tu vacante.
                </p>
                <x-landing.cta-button :href="$whatsappHref('Hola, quiero matricularme en CEBA Peruano Británico. ¿Me pueden orientar?')" target="_blank" variant="dark" icon="chat-bubble-left-right">
                    Contáctanos Ahora
                </x-landing.cta-button>
            </div>
        </div>
    </div>
</section>
