@props(['secciones', 'campo'])

{{--
    Checklist "subir también a" compartido entre material, clase grabada,
    tarea y foro: todas las secciones/grupos (A, B...) del mismo curso
    académico, grado y ciclo -- para replicar de una sola vez en vez de
    repetir la carga sección por sección. Como curso/grado/ciclo ya son
    iguales para todas las opciones (eso es justo lo que las agrupa), lo
    único que distingue cada fila es el grupo y quién la dicta.
--}}
@if ($secciones->count() > 1)
    <div>
        <x-input-label value="Subir también a" />
        <div class="mt-1 max-h-40 space-y-1 overflow-y-auto rounded-md border border-border bg-surface p-2">
            @foreach ($secciones as $seccionOpcion)
                <label class="flex items-center gap-2 text-sm text-ink">
                    <input
                        type="checkbox"
                        value="{{ $seccionOpcion->id }}"
                        wire:model="{{ $campo }}"
                        class="rounded border-border text-accent focus:ring-accent"
                    >
                    {{ $seccionOpcion->horario->seccion ? 'Grupo '.$seccionOpcion->horario->seccion : 'Sin grupo' }} · {{ $seccionOpcion->horario->docente->name }}
                </label>
            @endforeach
        </div>
        <x-input-error :messages="$errors->get($campo)" class="mt-1" />
    </div>
@endif
