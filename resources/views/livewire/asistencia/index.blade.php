<?php

use App\Modules\Asistencia\Services\AsistenciaService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public function mount(): void
    {
        $user = Auth::user();

        abort_unless(
            $user->hasAnyPermission(['asistencia.ver', 'asistencia.registrar', 'asistencia.ver_propio']),
            403,
        );
    }

    public function with(AsistenciaService $service): array
    {
        $user = Auth::user();

        if ($user->hasPermissionTo('asistencia.registrar')) {
            return ['horarios' => $service->horariosDelDocente($user->id), 'rol' => 'docente'];
        }

        if ($user->hasPermissionTo('asistencia.ver_propio') && $user->estudiante) {
            return ['horarios' => $service->horariosDelEstudiante($user->estudiante), 'rol' => 'estudiante'];
        }

        return ['horarios' => $service->todos(), 'rol' => 'supervisor'];
    }
}; ?>

<div>
    <x-slot name="header">
        <h1 class="font-display text-2xl text-ink">Asistencia</h1>
        <p class="mt-1 text-sm text-ink-dim">
            @if ($rol === 'docente')
                Toma asistencia en tus horarios asignados este ciclo.
            @elseif ($rol === 'estudiante')
                Tu asistencia en los cursos donde estás matriculado.
            @else
                Supervisión de asistencia de todos los horarios.
            @endif
        </p>
    </x-slot>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @forelse ($horarios as $horario)
            <a
                href="{{ route('asistencia.show', $horario) }}"
                wire:navigate
                class="block rounded-lg border border-border bg-surface p-4 transition hover:border-accent"
            >
                <p class="font-display text-lg text-ink">{{ $horario->curso->nombre }}</p>
                <p class="mt-1 text-sm text-ink-dim">{{ $horario->grado->nombre }} · {{ $horario->ciclo->nombre }}</p>
                <p class="mt-3 text-xs text-ink-faint">
                    @if ($rol !== 'docente')
                        {{ $horario->docente->name }} ·
                    @endif
                    {{ $horario->dia_semana->label() }} {{ substr($horario->hora_inicio, 0, 5) }}–{{ substr($horario->hora_fin, 0, 5) }}
                </p>
            </a>
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
