<?php

use App\Modules\Asistencia\Services\AsistenciaService;
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
            $user->hasAnyPermission(['asistencia.ver', 'asistencia.registrar', 'asistencia.ver_propio']),
            403,
        );
    }

    public function with(AsistenciaService $service): array
    {
        $user = Auth::user();

        // hasPermissionTo() no basta: Dirección tiene este permiso vía '*'
        // sin ser realmente docente, y quedaría viendo "sus" horarios
        // (ninguno) en vez de la vista de supervisión.
        if ($user->hasPermissionTo('asistencia.registrar') && $user->hasRole(RolEnum::DOCENTE->value)) {
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

    @if ($rol === 'estudiante')
        <div class="mb-4">
            <a
                href="{{ route('asistencia.marcar') }}"
                wire:navigate
                class="flex items-center justify-between gap-3 rounded-lg border border-accent/30 bg-accent-soft px-4 py-3 transition hover:border-accent"
            >
                <span class="flex items-center gap-3">
                    <x-heroicon-o-qr-code class="h-6 w-6 shrink-0 text-accent" />
                    <span>
                        <span class="block text-sm font-semibold text-accent">Marcar mi asistencia</span>
                        <span class="block text-xs text-ink-dim">Confirma tu DNI si tienes clase ahora mismo.</span>
                    </span>
                </span>
                <x-heroicon-o-chevron-right class="h-5 w-5 shrink-0 text-accent" />
            </a>
        </div>
    @endif

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
                    {{ $horario->diasResumen() }}
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
