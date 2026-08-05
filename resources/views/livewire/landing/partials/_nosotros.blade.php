<section id="nosotros" class="bg-[#0a0a0a] py-20 sm:py-28">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <x-landing.section-title subtitle="Formamos personas capaces de culminar su educación y proyectar un mejor futuro.">
            Sobre Nosotros
        </x-landing.section-title>

        <div x-data x-reveal class="mx-auto flex max-w-4xl items-start gap-5 rounded-2xl border border-white/5 bg-[#1a1a1c] p-8">
            <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-red-500/10 text-red-400">
                <x-heroicon-o-book-open class="h-6 w-6" />
            </div>
            <div>
                <h3 class="font-sans text-lg font-bold text-white">Nuestra Historia</h3>
                <p class="mt-2 text-sm leading-relaxed text-gray-400">
                    {{ $nombreInstitucion }} nace con el propósito de abrir una segunda oportunidad educativa para
                    jóvenes y adultos que, por trabajo, familia u otras circunstancias, no pudieron culminar su
                    secundaria en la edad regular. Bajo la modalidad de Educación Básica Alternativa (EBA) y
                    acreditados por el MINEDU, acompañamos a cada estudiante con horarios flexibles y una
                    metodología pensada para su realidad.
                </p>
            </div>
        </div>

        <div class="mx-auto mt-8 grid max-w-4xl grid-cols-1 gap-6 sm:grid-cols-2">
            <div x-data x-reveal class="rounded-2xl border border-white/5 bg-[#1a1a1c] p-8">
                <div class="flex h-12 w-12 items-center justify-center rounded-full bg-blue-500/10 text-blue-400">
                    <x-heroicon-o-flag class="h-6 w-6" />
                </div>
                <h3 class="mt-4 font-sans text-lg font-bold text-white">Nuestra Misión</h3>
                <p class="mt-2 text-sm leading-relaxed text-gray-400">
                    Brindar una educación secundaria de calidad, flexible y accesible, que permita a jóvenes y
                    adultos culminar sus estudios y continuar desarrollándose personal y profesionalmente.
                </p>
            </div>
            <div x-data x-reveal class="rounded-2xl border border-white/5 bg-[#1a1a1c] p-8">
                <div class="flex h-12 w-12 items-center justify-center rounded-full bg-emerald-500/10 text-emerald-400">
                    <x-heroicon-o-sparkles class="h-6 w-6" />
                </div>
                <h3 class="mt-4 font-sans text-lg font-bold text-white">Nuestra Visión</h3>
                <p class="mt-2 text-sm leading-relaxed text-gray-400">
                    Ser una institución referente en Educación Básica Alternativa, reconocida por acompañar a sus
                    estudiantes hasta la culminación de sus estudios y su ingreso a nuevas oportunidades.
                </p>
            </div>
        </div>

        <div class="mt-16">
            <h3 class="text-center font-sans text-xl font-bold text-white">Nuestros Valores</h3>
            <div class="mx-auto mt-8 grid max-w-5xl grid-cols-2 gap-4 lg:grid-cols-4">
                <div x-data x-reveal>
                    <x-landing.icon-card icon="heart" color="red" title="Respeto">
                        Valoramos la historia y el ritmo de cada estudiante.
                    </x-landing.icon-card>
                </div>
                <div x-data x-reveal>
                    <x-landing.icon-card icon="trophy" color="amber" title="Excelencia">
                        Buscamos la mejor formación posible en cada curso.
                    </x-landing.icon-card>
                </div>
                <div x-data x-reveal>
                    <x-landing.icon-card icon="user-group" color="blue" title="Inclusión">
                        Puertas abiertas para todas las edades y realidades.
                    </x-landing.icon-card>
                </div>
                <div x-data x-reveal>
                    <x-landing.icon-card icon="shield-check" color="green" title="Compromiso">
                        Acompañamos a cada estudiante hasta su certificación.
                    </x-landing.icon-card>
                </div>
            </div>
        </div>
    </div>
</section>
