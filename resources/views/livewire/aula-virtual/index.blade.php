<?php

use App\Modules\AulaVirtual\Services\CursoVirtualService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public function mount(): void
    {
        $user = Auth::user();

        abort_unless(
            $user->hasAnyPermission(['aula_virtual.ver', 'aula_virtual.gestionar_propio', 'aula_virtual.ver_propio']),
            403,
        );
    }

    public function with(CursoVirtualService $service): array
    {
        $user = Auth::user();

        if ($user->hasPermissionTo('aula_virtual.gestionar_propio')) {
            return ['cursos' => $service->delDocente($user->id), 'rol' => 'docente'];
        }

        if ($user->hasPermissionTo('aula_virtual.ver_propio') && $user->estudiante) {
            return ['cursos' => $service->delEstudiante($user->estudiante), 'rol' => 'estudiante'];
        }

        return ['cursos' => $service->todos(), 'rol' => 'supervisor'];
    }
}; ?>

<div>
    <x-slot name="header">
        <h1 class="font-display text-2xl text-ink">Aula Virtual</h1>
        <p class="mt-1 text-sm text-ink-dim">
            @if ($rol === 'docente')
                Tus cursos asignados este ciclo.
            @elseif ($rol === 'estudiante')
                Los cursos donde estás matriculado.
            @else
                Todos los cursos virtuales activos.
            @endif
        </p>
    </x-slot>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @forelse ($cursos as $curso)
            <a
                href="{{ route('aula-virtual.show', $curso) }}"
                wire:navigate
                class="block rounded-lg border border-border bg-surface p-4 transition hover:border-accent"
            >
                <p class="font-display text-lg text-ink">{{ $curso->horario->curso->nombre }}</p>
                <p class="mt-1 text-sm text-ink-dim">{{ $curso->horario->grado->nombre }} · {{ $curso->horario->ciclo->nombre }}</p>
                <p class="mt-3 text-xs text-ink-faint">{{ $curso->horario->docente->name }} · {{ $curso->horario->dia_semana->label() }}</p>
            </a>
        @empty
            <p class="col-span-full py-8 text-center text-sm text-ink-faint">
                @if ($rol === 'docente')
                    Todavía no tienes cursos con aula virtual activada.
                @elseif ($rol === 'estudiante')
                    Todavía no hay cursos virtuales para tu matrícula actual.
                @else
                    No hay cursos virtuales activos.
                @endif
            </p>
        @endforelse
    </div>
</div>
