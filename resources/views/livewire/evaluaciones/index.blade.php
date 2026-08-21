<?php

use App\Modules\Evaluaciones\Services\EvaluacionService;
use App\Shared\Enums\RolEnum;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public function mount(): void
    {
        $user = Auth::user();

        abort_unless(
            $user->hasAnyPermission(['evaluaciones.ver', 'evaluaciones.registrar', 'evaluaciones.ver_propio']),
            403,
        );
    }

    public function with(EvaluacionService $service): array
    {
        $user = Auth::user();

        // hasPermissionTo() no basta: Dirección tiene este permiso vía '*'
        // sin ser realmente docente, y quedaría viendo "sus" horarios
        // (ninguno) en vez de la vista de supervisión.
        if ($user->hasPermissionTo('evaluaciones.registrar') && $user->hasRole(RolEnum::DOCENTE->value)) {
            $horarios = $service->horariosDelDocente($user->id);
            $rol = 'docente';
        } elseif ($user->hasPermissionTo('evaluaciones.ver_propio') && $user->estudiante) {
            $horarios = $service->horariosDelEstudiante($user->estudiante);
            $rol = 'estudiante';
        } else {
            $horarios = $service->todos();
            $rol = 'supervisor';
        }

        // Un mismo curso+ciclo normalmente tiene un único Horario, pero se
        // agrupan por curso+ciclo por si dos docentes llegaran a dictarlo
        // en paralelo: quien mira elegiría cuál quiere ver en vez de
        // toparse con dos tarjetas casi idénticas.
        $grupos = $horarios->groupBy(fn ($horario) => $horario->curso_id.'|'.$horario->ciclo_id);

        return ['grupos' => $grupos, 'rol' => $rol];
    }
}; ?>

<div>
    <x-slot name="header">
        <h1 class="font-display text-2xl text-ink">Evaluaciones</h1>
        <p class="mt-1 text-sm text-ink-dim">
            @if ($rol === 'docente')
                Registra y publica notas en tus horarios asignados este ciclo.
            @elseif ($rol === 'estudiante')
                Tus notas en los cursos donde estás matriculado.
            @else
                Supervisión de evaluaciones de todos los horarios.
            @endif
        </p>
    </x-slot>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @forelse ($grupos as $grupo)
            @php $primero = $grupo->first(); @endphp
            @if ($grupo->count() === 1)
                <a
                    href="{{ route('evaluaciones.show', $primero) }}"
                    wire:navigate
                    class="block rounded-lg border border-border bg-surface p-4 transition hover:border-accent"
                >
                    <x-curso-portada :curso="$primero->curso" class="mb-3" />
                    <p class="font-display text-lg text-ink">{{ $primero->curso->nombre }}</p>
                    <p class="mt-1 text-sm text-ink-dim">{{ $primero->grado->nombre }} · {{ $primero->ciclo->nombre }}</p>
                    <p class="mt-3 text-xs text-ink-faint">
                        @if ($rol !== 'docente')
                            {{ $primero->docente->name }} ·
                        @endif
                        {{ $primero->diasResumen() }}
                    </p>
                </a>
            @else
                <div class="rounded-lg border border-border bg-surface p-4">
                    <x-curso-portada :curso="$primero->curso" class="mb-3" />
                    <p class="font-display text-lg text-ink">{{ $primero->curso->nombre }}</p>
                    <p class="mt-1 text-sm text-ink-dim">{{ $primero->grado->nombre }} · {{ $primero->ciclo->nombre }}</p>

                    <p class="mt-3 text-xs text-ink-faint">Selecciona un horario</p>
                    <div class="mt-2 flex flex-wrap gap-2">
                        @foreach ($grupo->sortBy(fn ($opcion) => $opcion->docente->name) as $opcion)
                            <a
                                href="{{ route('evaluaciones.show', $opcion) }}"
                                wire:navigate
                                class="rounded-full border border-border bg-surface-2 px-3 py-1 text-xs font-medium text-ink transition hover:border-accent hover:text-accent"
                            >
                                {{ $opcion->docente->name }}
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif
        @empty
            <p class="col-span-full py-8 text-center text-sm text-ink-faint">
                @if ($rol === 'docente')
                    Todavía no tienes horarios asignados este ciclo.
                @elseif ($rol === 'estudiante')
                    Todavía no hay horarios para tu matrícula actual.
                @else
                    No hay horarios registrados.
                @endif
            </p>
        @endforelse
    </div>
</div>
