<section id="contacto" class="bg-[#0a0a0a] py-20 sm:py-28">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <x-landing.section-title subtitle="Estamos para orientarte. Escríbenos y te respondemos a la brevedad.">
            Contáctanos
        </x-landing.section-title>

        <div class="mx-auto grid max-w-5xl grid-cols-1 gap-8 lg:grid-cols-5">
            <div class="space-y-6 lg:col-span-2">
                <div x-data x-reveal class="rounded-2xl border border-white/5 bg-[#1a1a1c] p-6">
                    <x-landing.icon-circle icon="chat-bubble-left-right" color="green" />
                    <h3 class="mt-4 font-sans text-base font-bold text-white">WhatsApp</h3>
                    <p class="mt-1 text-sm text-gray-400">{{ $whatsappNumeroVisible }}</p>
                    <x-landing.cta-button
                        :href="$whatsappHref('Hola, quiero información sobre CEBA Peruano Británico.')"
                        target="_blank"
                        variant="whatsapp"
                        icon="chat-bubble-left-right"
                        class="mt-4 w-full"
                    >
                        Enviar Mensaje
                    </x-landing.cta-button>
                </div>

                <div x-data x-reveal class="rounded-2xl border border-white/5 bg-[#1a1a1c] p-6">
                    <x-landing.icon-circle icon="envelope" color="blue" />
                    <h3 class="mt-4 font-sans text-base font-bold text-white">Correo Electrónico</h3>
                    <a href="mailto:{{ $emailContacto }}" class="mt-1 block break-all text-sm text-gray-400 hover:text-white">
                        {{ $emailContacto }}
                    </a>
                </div>

                <div x-data x-reveal class="rounded-2xl border border-white/5 bg-[#1a1a1c] p-6">
                    <x-landing.icon-circle icon="map-pin" color="red" />
                    <h3 class="mt-4 font-sans text-base font-bold text-white">¿Dónde estamos?</h3>
                    <p class="mt-1 text-sm text-gray-400">Escríbenos por WhatsApp y coordinamos contigo el punto de atención más cercano.</p>
                </div>
            </div>

            <div x-data x-reveal class="rounded-2xl border border-white/5 bg-[#1a1a1c] p-6 sm:p-8 lg:col-span-3">
                <h3 class="font-sans text-lg font-bold text-white">Envíanos un Mensaje</h3>

                @if ($enviado)
                    <div class="mt-6 flex items-start gap-3 rounded-xl bg-emerald-500/10 p-4">
                        <x-heroicon-o-check-circle class="mt-0.5 h-6 w-6 shrink-0 text-emerald-400" />
                        <div>
                            <p class="text-sm font-semibold text-emerald-400">¡Mensaje enviado!</p>
                            <p class="mt-1 text-sm text-gray-300">Gracias por escribirnos. Te contactaremos muy pronto por correo o WhatsApp.</p>
                        </div>
                    </div>
                @endif

                <form wire:submit="enviarMensaje" class="mt-6 space-y-4">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label for="nombre" class="text-xs font-semibold uppercase tracking-wide text-gray-400">Nombre Completo</label>
                            <input
                                type="text"
                                id="nombre"
                                wire:model="nombre"
                                class="mt-1.5 block w-full rounded-lg border-white/10 bg-[#0a0a0a] text-sm text-white placeholder:text-gray-600 focus:border-red-500 focus:ring-red-500"
                                placeholder="Tu nombre y apellido"
                            >
                            @error('nombre') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="email" class="text-xs font-semibold uppercase tracking-wide text-gray-400">Correo Electrónico</label>
                            <input
                                type="email"
                                id="email"
                                wire:model="email"
                                class="mt-1.5 block w-full rounded-lg border-white/10 bg-[#0a0a0a] text-sm text-white placeholder:text-gray-600 focus:border-red-500 focus:ring-red-500"
                                placeholder="tu@correo.com"
                            >
                            @error('email') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label for="telefono" class="text-xs font-semibold uppercase tracking-wide text-gray-400">Teléfono / WhatsApp</label>
                            <input
                                type="text"
                                id="telefono"
                                wire:model="telefono"
                                class="mt-1.5 block w-full rounded-lg border-white/10 bg-[#0a0a0a] text-sm text-white placeholder:text-gray-600 focus:border-red-500 focus:ring-red-500"
                                placeholder="9XX XXX XXX"
                            >
                            @error('telefono') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="programaInteres" class="text-xs font-semibold uppercase tracking-wide text-gray-400">Programa de interés</label>
                            <select
                                id="programaInteres"
                                wire:model="programaInteres"
                                class="mt-1.5 block w-full rounded-lg border-white/10 bg-[#0a0a0a] text-sm text-white focus:border-red-500 focus:ring-red-500"
                            >
                                <option value="">Selecciona una opción</option>
                                <option value="Secundaria EBA">Secundaria EBA</option>
                                <option value="Aún no estoy seguro">Aún no estoy seguro</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label for="mensaje" class="text-xs font-semibold uppercase tracking-wide text-gray-400">Mensaje</label>
                        <textarea
                            id="mensaje"
                            wire:model="mensaje"
                            rows="4"
                            class="mt-1.5 block w-full rounded-lg border-white/10 bg-[#0a0a0a] text-sm text-white placeholder:text-gray-600 focus:border-red-500 focus:ring-red-500"
                            placeholder="Cuéntanos en qué podemos ayudarte…"
                        ></textarea>
                        @error('mensaje') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
                    </div>

                    <button
                        type="submit"
                        wire:loading.attr="disabled"
                        wire:target="enviarMensaje"
                        class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-red-600 px-6 py-3 text-sm font-bold text-white transition hover:bg-red-700 disabled:opacity-60 sm:w-auto"
                    >
                        <span wire:loading.remove wire:target="enviarMensaje">Enviar Mensaje</span>
                        <span wire:loading wire:target="enviarMensaje">Enviando…</span>
                    </button>
                </form>
            </div>
        </div>
    </div>
</section>
