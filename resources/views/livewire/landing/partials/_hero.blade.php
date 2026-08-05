<section id="inicio" class="relative overflow-hidden bg-[#0a0a0a] pt-16 sm:pt-20">
    <div class="hero-glow pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_20%_20%,rgba(220,38,38,0.12),transparent_45%),radial-gradient(circle_at_80%_0%,rgba(15,27,61,0.6),transparent_40%)]"></div>

    <div class="relative mx-auto grid max-w-7xl grid-cols-1 items-center gap-12 px-4 py-16 sm:px-6 lg:grid-cols-2 lg:px-8 lg:py-24">
        <div>
            <div x-data x-reveal>
                <span class="inline-flex items-center gap-2 rounded-full bg-emerald-500/10 px-4 py-1.5 text-xs font-bold text-emerald-400">
                    <x-heroicon-o-check-circle class="h-4 w-4" />
                    Acreditado por el MINEDU
                </span>
            </div>

            <div x-data x-reveal.75>
                <h1 class="mt-6 font-sans text-4xl font-extrabold leading-tight text-white sm:text-5xl">
                    Termina tu Secundaria
                    <span class="block text-red-500">en Corto Tiempo</span>
                </h1>
            </div>

            <div x-data x-reveal.150>
                <p class="mt-6 max-w-lg text-lg text-gray-400">
                    En {{ $nombreInstitucion }} acompañamos a jóvenes y adultos a culminar su educación secundaria con
                    horarios flexibles, pensados para quienes trabajan o tienen otras responsabilidades.
                </p>
            </div>

            <div x-data x-reveal.225 class="mt-8 grid grid-cols-2 gap-3 sm:max-w-md">
                <x-landing.icon-card icon="clock" color="blue" title="2 grados en 1 año" compact />
                <x-landing.icon-card icon="document-check" color="green" title="Certificado Oficial" compact />
                <x-landing.icon-card icon="computer-desktop" color="amber" title="Virtual y Presencial" compact />
                <x-landing.icon-card icon="banknotes" color="pink" title="S/ 80 Mensual" compact />
            </div>

            <div x-data x-reveal.300 class="mt-8 flex flex-wrap gap-4">
                <x-landing.cta-button :href="$whatsappHref('Hola, quiero información sobre la matrícula en CEBA Peruano Británico.')" target="_blank" variant="whatsapp" icon="chat-bubble-left-right">
                    Contáctanos por WhatsApp
                </x-landing.cta-button>
                <x-landing.cta-button href="#contacto" variant="secondary" icon="pencil-square">
                    Llenar formulario
                </x-landing.cta-button>
            </div>
        </div>

        <div x-data x-reveal.150 class="relative">
            <div class="relative overflow-hidden rounded-3xl border border-white/10 bg-gradient-to-br from-[#0f1b3d] via-[#12142a] to-[#1a1a1c] p-8 shadow-2xl shadow-black/50">
                <x-heroicon-o-academic-cap class="h-14 w-14 text-red-500" />
                <p class="mt-4 font-sans text-2xl font-extrabold text-white">Tu segunda oportunidad<br>empieza aquí.</p>

                <div class="mt-6 flex flex-wrap gap-2">
                    <span class="rounded-full bg-white/10 px-3 py-1 text-xs font-semibold text-white">Matrícula gratis</span>
                    <span class="rounded-full bg-white/10 px-3 py-1 text-xs font-semibold text-white">Solo S/ 80 al mes</span>
                    <span class="rounded-full bg-white/10 px-3 py-1 text-xs font-semibold text-white">Modalidad EBA</span>
                </div>

                <div class="mt-8 space-y-3 border-t border-white/10 pt-6">
                    <p class="flex items-center gap-2 text-sm text-gray-300">
                        <x-heroicon-o-phone class="h-4 w-4 text-red-500" />
                        {{ $whatsappNumeroVisible }}
                    </p>
                    <p class="flex items-center gap-2 text-sm text-gray-300">
                        <x-heroicon-o-envelope class="h-4 w-4 text-red-500" />
                        {{ $emailContacto }}
                    </p>
                </div>

                <div class="mt-6 flex items-center gap-2 rounded-xl bg-black/30 px-4 py-3">
                    <x-heroicon-o-shield-check class="h-5 w-5 shrink-0 text-emerald-400" />
                    <p class="text-xs text-gray-300">Institución educativa acreditada por el Ministerio de Educación del Perú.</p>
                </div>
            </div>
        </div>
    </div>
</section>
