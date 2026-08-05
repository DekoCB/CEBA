<section id="programas" class="bg-[#0a0a0a] py-20 sm:py-28">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <x-landing.section-title subtitle="Un programa pensado para que termines tu secundaria sin dejar de trabajar.">
            Programas Educativos
        </x-landing.section-title>

        <div x-data x-reveal class="mx-auto max-w-4xl rounded-3xl border border-white/10 bg-[#0f1b3d] p-8 sm:p-10">
            <span class="inline-flex items-center rounded-full bg-red-600/20 px-3 py-1 text-xs font-bold text-red-400">
                Educación Básica Alternativa
            </span>
            <h3 class="mt-4 font-sans text-2xl font-extrabold text-white sm:text-3xl">Secundaria EBA</h3>
            <p class="mt-2 max-w-xl text-sm text-gray-300">
                Culmina los años de secundaria que te faltan con un plan de estudios oficial, adaptado a jóvenes y
                adultos, con la posibilidad de avanzar dos grados en un mismo año.
            </p>

            <div class="mt-8 grid grid-cols-1 gap-x-8 gap-y-3 sm:grid-cols-2">
                @foreach ([
                    'Horarios flexibles, compatibles con el trabajo',
                    'Modalidad virtual y presencial',
                    'Certificación oficial reconocida por el MINEDU',
                    'Matrícula totalmente gratuita',
                    'Pensión mensual de solo S/ 80',
                    'Posibilidad de avanzar dos grados por año',
                    'Ingreso desde los 14 años (con primaria completa)',
                    'Acompañamiento docente durante todo el año',
                ] as $beneficio)
                    <p class="flex items-start gap-2 text-sm text-gray-200">
                        <x-heroicon-o-check class="mt-0.5 h-5 w-5 shrink-0 text-emerald-400" />
                        {{ $beneficio }}
                    </p>
                @endforeach
            </div>

            <x-landing.cta-button href="#contacto" variant="primary" icon="pencil-square" class="mt-8">
                ¡Inscríbete Ahora!
            </x-landing.cta-button>
        </div>

        <div class="mx-auto mt-20 grid max-w-6xl grid-cols-1 items-center gap-12 lg:grid-cols-2">
            <div x-data x-reveal>
                <h3 class="font-sans text-2xl font-extrabold text-white">Metodología de Enseñanza</h3>
                <p class="mt-3 text-sm text-gray-400">
                    Nuestra metodología está diseñada específicamente para jóvenes y adultos que retoman sus
                    estudios, respetando su experiencia de vida y su disponibilidad de tiempo.
                </p>
                <div class="mt-6 space-y-3">
                    @foreach ([
                        'Clases dinámicas y adaptadas al ritmo de cada estudiante',
                        'Materiales y evaluaciones accesibles en modalidad virtual',
                        'Sesiones presenciales de refuerzo cuando se necesitan',
                        'Seguimiento cercano del avance de cada estudiante',
                    ] as $punto)
                        <p class="flex items-start gap-2 text-sm text-gray-300">
                            <x-heroicon-o-check class="mt-0.5 h-5 w-5 shrink-0 text-emerald-400" />
                            {{ $punto }}
                        </p>
                    @endforeach
                </div>
            </div>

            <div x-data x-reveal class="rounded-3xl border border-white/10 bg-gradient-to-br from-[#1a1a1c] to-[#0f1b3d] p-8">
                <x-heroicon-o-puzzle-piece class="h-12 w-12 text-red-500" />
                <p class="mt-4 font-sans text-xl font-bold text-white">Aprender a tu ritmo, sin dejar de vivir tu día a día.</p>
                <p class="mt-3 text-sm text-gray-400">
                    Combinamos flexibilidad de horarios con acompañamiento constante, para que estudiar sea
                    compatible con tu trabajo y tu familia.
                </p>
            </div>
        </div>

        <div class="mt-20">
            <h3 class="text-center font-sans text-xl font-bold text-white">Áreas de Especialización</h3>
            <div class="mx-auto mt-8 grid max-w-5xl grid-cols-2 gap-4 sm:grid-cols-3">
                @foreach ([
                    ['icon' => 'calculator', 'color' => 'blue', 'label' => 'Matemática y Ciencias'],
                    ['icon' => 'pencil-square', 'color' => 'red', 'label' => 'Comunicación y Lenguaje'],
                    ['icon' => 'globe-alt', 'color' => 'green', 'label' => 'Ciencias Sociales'],
                    ['icon' => 'briefcase', 'color' => 'amber', 'label' => 'Educación para el Trabajo'],
                    ['icon' => 'language', 'color' => 'pink', 'label' => 'Inglés'],
                    ['icon' => 'heart', 'color' => 'blue', 'label' => 'Psicología Educativa'],
                ] as $area)
                    <div x-data x-reveal class="flex flex-col items-center gap-3 rounded-2xl border border-white/5 bg-[#1a1a1c] p-5 text-center">
                        <x-landing.icon-circle :icon="$area['icon']" :color="$area['color']" size="sm" />
                        <p class="text-xs font-semibold text-white">{{ $area['label'] }}</p>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="mt-20">
            <h3 class="text-center font-sans text-xl font-bold text-white">Nuestro Enfoque Pedagógico</h3>
            <div class="mx-auto mt-8 grid max-w-5xl grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-5">
                @foreach ([
                    ['icon' => 'academic-cap', 'color' => 'red', 'label' => 'Docentes titulados'],
                    ['icon' => 'user-group', 'color' => 'blue', 'label' => 'Experiencia en educación de adultos'],
                    ['icon' => 'puzzle-piece', 'color' => 'green', 'label' => 'Metodología flexible'],
                    ['icon' => 'computer-desktop', 'color' => 'amber', 'label' => 'Tecnología educativa'],
                    ['icon' => 'heart', 'color' => 'pink', 'label' => 'Acompañamiento continuo'],
                ] as $enfoque)
                    <div x-data x-reveal class="flex flex-col items-center rounded-2xl border border-white/5 bg-[#1a1a1c] p-5 text-center">
                        <x-landing.icon-circle :icon="$enfoque['icon']" :color="$enfoque['color']" size="sm" />
                        <p class="mt-3 text-xs font-semibold text-white">{{ $enfoque['label'] }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</section>
