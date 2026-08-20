<?php

use App\Modules\AulaVirtual\Services\CursoVirtualService;
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
            $user->hasAnyPermission(['aula_virtual.ver', 'aula_virtual.gestionar_propio', 'aula_virtual.ver_propio']),
            403,
        );
    }

    public function with(CursoVirtualService $service): array
    {
        $user = Auth::user();

        // hasPermissionTo() no basta: Dirección tiene este permiso vía '*'
        // sin ser realmente docente, y quedaría viendo "sus" cursos (ninguno)
        // en vez de la vista de supervisión.
        if ($user->hasPermissionTo('aula_virtual.gestionar_propio') && $user->hasRole(RolEnum::DOCENTE->value)) {
            $cursos = $service->delDocente($user->id);
            $rol = 'docente';
        } elseif ($user->hasPermissionTo('aula_virtual.ver_propio') && $user->estudiante) {
            $cursos = $service->delEstudiante($user->estudiante);
            $rol = 'estudiante';
        } else {
            $cursos = $service->todos();
            $rol = 'supervisor';
        }

        // Un mismo curso puede tener varias secciones (Grupo A/B), cada una
        // con su propio Horario y por lo tanto su propio CursoVirtual: se
        // agrupan por curso para que, si quien mira tiene acceso a más de
        // una sección del mismo curso, elija cuál quiere ver en vez de
        // toparse con dos tarjetas casi idénticas sin ninguna pista de cuál
        // es cuál. Un estudiante nunca ve más de una sección por curso (ya
        // viene filtrado por CursoVirtualService::delEstudiante()), así que
        // para él el grupo siempre trae un solo curso virtual.
        $grupos = $cursos->groupBy(fn ($cursoVirtual) => $cursoVirtual->horario->curso_id.'|'.$cursoVirtual->horario->ciclo_id);

        return ['grupos' => $grupos, 'rol' => $rol];
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
        @forelse ($grupos as $grupo)
            @php $primero = $grupo->first(); @endphp
            <div class="rounded-lg border border-border bg-surface p-4">
                <x-curso-portada :curso="$primero->horario->curso" class="mb-3" />
                <p class="font-display text-lg text-ink">{{ $primero->horario->curso->nombre }}</p>
                <p class="mt-1 text-sm text-ink-dim">{{ $primero->horario->grado->nombre }} · {{ $primero->horario->ciclo->nombre }}</p>

                @if ($grupo->count() === 1)
                    <p class="mt-3 text-xs text-ink-faint">
                        @if ($rol !== 'docente')
                            {{ $primero->horario->docente->name }} ·
                        @endif
                        {{ $primero->horario->diasResumen() }}
                        @if ($primero->horario->seccion)
                            · Sección {{ $primero->horario->seccion }}
                        @endif
                    </p>
                    <a href="{{ route('aula-virtual.show', $primero) }}" wire:navigate class="mt-3 block text-sm font-medium text-accent hover:underline">
                        Entrar
                    </a>
                @else
                    <p class="mt-3 text-xs text-ink-faint">Elige tu sección:</p>
                    <div class="mt-2 flex flex-wrap gap-2">
                        @foreach ($grupo->sortBy('horario.seccion') as $opcion)
                            <a
                                href="{{ route('aula-virtual.show', $opcion) }}"
                                wire:navigate
                                class="rounded-full border border-border bg-surface-2 px-3 py-1 text-xs font-medium text-ink transition hover:border-accent hover:text-accent"
                            >
                                Sección {{ $opcion->horario->seccion }}
                            </a>
                        @endforeach
                    </div>
                @endif
            </div>
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
