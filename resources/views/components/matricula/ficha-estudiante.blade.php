@props(['estudiante', 'documentos', 'examenes', 'matriculas', 'horariosPorMatricula' => [], 'editandoHorarioMatriculaId' => null, 'horarioSeleccionado' => ''])

{{--
    Contenido de la ficha del estudiante, compartido entre la página
    completa (matricula/show.blade.php, para acceso directo por URL) y el
    modal "Ver" de matricula/index.blade.php. El wire:click de "Verificar"
    documento llama al componente Livewire que envuelve este parcial, así
    que ambos (show y index) deben exponer verificarDocumento().
--}}
<div class="space-y-6">
    <div class="flex justify-center">
        @if ($estudiante->fotoUrl())
            <img src="{{ $estudiante->fotoUrl() }}" alt="" class="h-24 w-24 shrink-0 rounded-full object-cover">
        @else
            <span class="flex h-24 w-24 shrink-0 items-center justify-center rounded-full border border-dashed border-border bg-surface-2 text-ink-faint">
                <x-heroicon-o-user class="h-10 w-10" />
            </span>
        @endif
    </div>

    <div class="rounded-lg border border-border bg-surface p-6">
        <h2 class="text-sm font-semibold text-ink">Datos personales</h2>
        <dl class="mt-4 grid grid-cols-1 gap-4 text-sm sm:grid-cols-2">
            <div><dt class="text-ink-faint">Fecha de nacimiento</dt><dd class="text-ink">{{ $estudiante->fecha_nacimiento->format('d/m/Y') }}</dd></div>
            <div><dt class="text-ink-faint">Estado civil</dt><dd class="text-ink">{{ $estudiante->estado_civil?->label() ?? '—' }}</dd></div>
            <div><dt class="text-ink-faint">Celular</dt><dd class="text-ink">{{ $estudiante->celular ?? '—' }}</dd></div>
            <div><dt class="text-ink-faint">Correo</dt><dd class="text-ink">{{ $estudiante->email ?? '—' }}</dd></div>
            <div class="sm:col-span-2"><dt class="text-ink-faint">Dirección</dt><dd class="text-ink">{{ $estudiante->direccion ?? '—' }}</dd></div>
            <div><dt class="text-ink-faint">Grado actual</dt><dd class="text-ink">{{ $estudiante->gradoActual?->nombre ?? '—' }}</dd></div>
            <div><dt class="text-ink-faint">Ciclos completados</dt><dd class="text-ink">{{ $estudiante->ciclos_completados }} / 4</dd></div>
        </dl>
    </div>

    <div class="rounded-lg border border-border bg-surface p-6">
        <h2 class="text-sm font-semibold text-ink">Observaciones</h2>
        <p class="mt-1 text-xs text-ink-faint">Acuerdos especiales, documentos pendientes de entregar, casos particulares (ej. examen de ubicación).</p>
        @can('matricula.editar')
            <form wire:submit="guardarObservaciones" class="mt-4">
                <textarea wire:model="observacionesTexto" rows="3" class="block w-full rounded-md border-border bg-surface text-sm text-ink focus:border-accent focus:ring-accent" placeholder="Sin observaciones registradas."></textarea>
                <div class="mt-2 flex justify-end">
                    <x-secondary-button type="submit">Guardar observaciones</x-secondary-button>
                </div>
            </form>
        @else
            <p class="mt-4 whitespace-pre-line text-sm text-ink">{{ $estudiante->observaciones ?: 'Sin observaciones registradas.' }}</p>
        @endcan
    </div>

    @if ($estudiante->es_menor_edad && $estudiante->apoderado)
        <div class="rounded-lg border border-border bg-surface p-6">
            <h2 class="text-sm font-semibold text-ink">Apoderado</h2>
            <dl class="mt-4 grid grid-cols-1 gap-4 text-sm sm:grid-cols-2">
                <div><dt class="text-ink-faint">Nombres</dt><dd class="text-ink">{{ $estudiante->apoderado->nombres }}</dd></div>
                <div><dt class="text-ink-faint">DNI</dt><dd class="text-ink">{{ $estudiante->apoderado->dni }}</dd></div>
                <div><dt class="text-ink-faint">Parentesco</dt><dd class="text-ink">{{ $estudiante->apoderado->parentesco }}</dd></div>
                <div><dt class="text-ink-faint">Celular</dt><dd class="text-ink">{{ $estudiante->apoderado->celular }}</dd></div>
            </dl>
        </div>
    @endif

    @if ($estudiante->institucionProcedencia)
        <div class="rounded-lg border border-border bg-surface p-6">
            <h2 class="text-sm font-semibold text-ink">Institución de procedencia</h2>
            <dl class="mt-4 grid grid-cols-1 gap-4 text-sm sm:grid-cols-2">
                <div class="sm:col-span-2"><dt class="text-ink-faint">Colegio</dt><dd class="text-ink">{{ $estudiante->institucionProcedencia->nombre_colegio }}</dd></div>
                <div><dt class="text-ink-faint">Ubicación</dt><dd class="text-ink">{{ $estudiante->institucionProcedencia->ubicacion ?? '—' }}</dd></div>
                <div><dt class="text-ink-faint">Año de egreso</dt><dd class="text-ink">{{ $estudiante->institucionProcedencia->anio_egreso ?? '—' }}</dd></div>
            </dl>
        </div>
    @endif

    <div class="rounded-lg border border-border bg-surface p-6">
        <h2 class="text-sm font-semibold text-ink">Documentos</h2>
        <div class="mt-4 divide-y divide-border">
            @forelse ($documentos as $documento)
                <div class="flex items-center justify-between py-3 text-sm">
                    <div>
                        <p class="text-ink">{{ $documento->tipo->label() }}</p>
                        @if ($documento->getFirstMedia('archivo'))
                            <a href="{{ $documento->getFirstMediaUrl('archivo') }}" target="_blank" class="text-xs text-accent hover:underline">Ver archivo</a>
                        @endif
                    </div>
                    <div class="flex items-center gap-3">
                        <span @class(['rounded-full px-2 py-0.5 text-xs font-medium', 'bg-ok/10 text-ok' => $documento->verificado, 'bg-warn/10 text-warn' => ! $documento->verificado])>
                            {{ $documento->verificado ? 'Verificado' : 'Pendiente' }}
                        </span>
                        @can('matricula.editar')
                            @unless ($documento->verificado)
                                <button wire:click="verificarDocumento({{ $documento->id }})" class="text-xs font-medium text-accent hover:underline">Verificar</button>
                            @endunless
                        @endcan
                    </div>
                </div>
            @empty
                <p class="py-4 text-sm text-ink-faint">No se han subido documentos.</p>
            @endforelse
        </div>
    </div>

    @if ($examenes->isNotEmpty())
        <div class="rounded-lg border border-border bg-surface p-6">
            <h2 class="text-sm font-semibold text-ink">Exámenes de ubicación</h2>
            <div class="mt-4 divide-y divide-border">
                @foreach ($examenes as $examen)
                    <div class="py-3 text-sm">
                        <p class="text-ink">{{ $examen->fecha->format('d/m/Y') }} · S/ {{ number_format((float) $examen->costo, 2) }}</p>
                        <p class="text-ink-faint">Resultado: {{ $examen->resultado ?? '—' }} @if($examen->gradoAsignado) · Grado asignado: {{ $examen->gradoAsignado->nombre }} @endif</p>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <div class="rounded-lg border border-border bg-surface p-6">
        <h2 class="text-sm font-semibold text-ink">Matrículas</h2>
        <div class="mt-4 divide-y divide-border">
            @forelse ($matriculas as $matricula)
                <div class="py-3 text-sm">
                    <div class="flex items-center justify-between">
                        <p class="text-ink">{{ $matricula->ciclo->nombre }} · {{ $matricula->grado->nombre }}</p>
                        <span @class([
                            'rounded-full px-2 py-0.5 text-xs font-medium',
                            'bg-ok/10 text-ok' => $matricula->estado->value === 'aprobada',
                            'bg-warn/10 text-warn' => $matricula->estado->value === 'pendiente' || $matricula->estado->value === 'observada',
                            'bg-danger/10 text-danger' => $matricula->estado->value === 'anulada',
                        ])>
                            {{ $matricula->estado->label() }}
                        </span>
                    </div>
                    <p class="mt-1 text-ink-faint">Matriculado el {{ $matricula->fecha_matricula->format('d/m/Y') }}</p>

                    <div class="mt-2 flex items-center gap-2">
                        @if ($matricula->horario)
                            <p class="text-ink-faint">
                                Sección: {{ $matricula->horario->seccion ? "Sección {$matricula->horario->seccion}" : 'Sin dividir' }}
                                @if ($matricula->horario->docente) · {{ $matricula->horario->docente->name }} @endif
                            </p>
                        @else
                            <p class="text-ink-faint">Sin sección asignada</p>
                        @endif

                        @can('matricula.editar')
                            @if ((($horariosPorMatricula[$matricula->id] ?? collect())->count() > 1) && $editandoHorarioMatriculaId !== $matricula->id)
                                <button type="button" wire:click="editarHorario({{ $matricula->id }})" class="text-xs font-medium text-accent hover:underline">Editar sección</button>
                            @endif
                        @endcan
                    </div>

                    @can('matricula.editar')
                        @if ($editandoHorarioMatriculaId === $matricula->id)
                            <form wire:submit="guardarHorario" class="mt-2 flex flex-wrap items-center gap-2">
                                <x-select-input
                                    wire:model="horarioSeleccionado"
                                    class="w-64 text-xs"
                                    placeholder="Selecciona una sección"
                                    :options="collect($horariosPorMatricula[$matricula->id] ?? [])->mapWithKeys(fn ($horario) => [$horario->id => trim('Sección '.$horario->seccion.' · '.$horario->diasResumen().' · '.($horario->docente->name ?? 'sin docente'))])"
                                />
                                <x-secondary-button type="submit">Guardar</x-secondary-button>
                                <button type="button" wire:click="cancelarEdicionHorario" class="text-xs text-ink-faint hover:text-ink">Cancelar</button>
                            </form>
                            <x-input-error :messages="$errors->get('horario')" class="mt-1" />
                        @endif
                    @endcan

                    <div class="mt-2 flex gap-4">
                        @if ($matricula->getFirstMedia('ficha'))
                            <a href="{{ $matricula->getFirstMediaUrl('ficha') }}" target="_blank" class="text-xs font-medium text-accent hover:underline">Ficha de matrícula (PDF)</a>
                        @else
                            <span class="text-xs text-ink-faint">Generando ficha…</span>
                        @endif
                        @if ($matricula->getFirstMedia('constancia'))
                            <a href="{{ $matricula->getFirstMediaUrl('constancia') }}" target="_blank" class="text-xs font-medium text-accent hover:underline">Constancia de vacante (PDF)</a>
                        @else
                            <span class="text-xs text-ink-faint">Generando constancia…</span>
                        @endif
                    </div>
                </div>
            @empty
                <p class="py-4 text-sm text-ink-faint">Este estudiante todavía no tiene matrículas registradas.</p>
            @endforelse
        </div>
    </div>
</div>
