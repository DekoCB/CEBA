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
            <div class="mx-auto mt-10 grid max-w-5xl grid-cols-1 gap-8 sm:grid-cols-2 lg:grid-cols-5 lg:gap-4">
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
