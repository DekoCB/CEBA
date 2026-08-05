<section class="bg-[#0a0a0a] py-20 sm:py-28">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <x-landing.section-title subtitle="Terminar tu secundaria abre muchas más puertas de las que imaginas.">
            Beneficios de Estudiar con Nosotros
        </x-landing.section-title>

        <div class="mx-auto grid max-w-5xl grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-5">
            @foreach ([
                ['icon' => 'document-check', 'color' => 'red', 'label' => 'Certificación oficial reconocida'],
                ['icon' => 'academic-cap', 'color' => 'blue', 'label' => 'Acceso a educación superior'],
                ['icon' => 'briefcase', 'color' => 'green', 'label' => 'Mejores oportunidades laborales'],
                ['icon' => 'computer-desktop', 'color' => 'amber', 'label' => 'Desarrollo de habilidades digitales'],
                ['icon' => 'clock', 'color' => 'pink', 'label' => 'Horarios compatibles con el trabajo'],
            ] as $beneficio)
                <div x-data x-reveal class="flex flex-col items-center gap-3 rounded-2xl border border-white/5 bg-[#1a1a1c] p-5 text-center">
                    <x-landing.icon-circle :icon="$beneficio['icon']" :color="$beneficio['color']" />
                    <p class="text-xs font-semibold leading-snug text-white">{{ $beneficio['label'] }}</p>
                </div>
            @endforeach
        </div>
    </div>
</section>
