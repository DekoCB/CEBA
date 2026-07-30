<?php

use App\Modules\Academico\Models\Horario;
use App\Modules\Evaluaciones\Models\Evaluacion;
use App\Modules\Evaluaciones\Services\EvaluacionService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public Horario $horario;

    public ?int $evaluacionId = null;

    public string $nuevoNombre = '';

    public string $nuevaFecha = '';

    public bool $mostrarFormNueva = false;

    /** @var array<int, string> */
    public array $notas = [];

    /** @var array<int, string> */
    public array $observaciones = [];

    public bool $guardado = false;

    public function mount(Horario $horario): void
    {
        Gate::authorize('evaluaciones.ver-horario', $horario);

        $this->horario = $horario;
        $this->nuevaFecha = now()->format('Y-m-d');
    }

    public function crear(EvaluacionService $service): void
    {
        Gate::authorize('evaluaciones.registrar-horario', $this->horario);

        $this->validate([
            'nuevoNombre' => 'required|string|max:150',
            'nuevaFecha' => 'required|date',
        ]);

        $evaluacion = $service->crear($this->horario, $this->nuevoNombre, $this->nuevaFecha);

        $this->reset(['nuevoNombre', 'mostrarFormNueva']);
        $this->nuevaFecha = now()->format('Y-m-d');
        $this->evaluacionId = $evaluacion->id;
    }

    public function seleccionar(int $evaluacionId, EvaluacionService $service): void
    {
        $this->evaluacionId = $evaluacionId;
        $this->guardado = false;
        $this->cargarNotas($service);
    }

    private function cargarNotas(EvaluacionService $service): void
    {
        if (! $this->evaluacionId) {
            return;
        }

        $evaluacion = Evaluacion::query()->where('horario_id', $this->horario->id)->find($this->evaluacionId);

        if (! $evaluacion) {
            $this->evaluacionId = null;

            return;
        }

        $estudiantes = $service->estudiantesDelHorario($this->horario);
        $existentes = $service->calificacionesDe($evaluacion);

        $this->notas = [];
        $this->observaciones = [];

        foreach ($estudiantes as $estudiante) {
            $calificacion = $existentes->get($estudiante->id);
            $this->notas[$estudiante->id] = $calificacion ? (string) $calificacion->nota_numerica : '';
            $this->observaciones[$estudiante->id] = $calificacion?->observaciones ?? '';
        }
    }

    public function guardarNotas(EvaluacionService $service): void
    {
        Gate::authorize('evaluaciones.registrar-horario', $this->horario);

        $evaluacion = Evaluacion::query()->where('horario_id', $this->horario->id)->findOrFail($this->evaluacionId);

        $rules = [];
        foreach (array_keys($this->notas) as $estudianteId) {
            $rules["notas.{$estudianteId}"] = 'nullable|numeric|min:0|max:20';
        }
        $this->validate($rules);

        $estudiantes = $service->estudiantesDelHorario($this->horario)->keyBy('id');

        foreach ($this->notas as $estudianteId => $nota) {
            if ($nota === '' || $nota === null) {
                continue;
            }

            $estudiante = $estudiantes->get($estudianteId);

            if (! $estudiante) {
                continue;
            }

            $service->calificar(
                $evaluacion,
                $estudiante,
                (float) $nota,
                $this->observaciones[$estudianteId] ?: null,
                Auth::id(),
            );
        }

        $this->guardado = true;
    }

    public function publicar(EvaluacionService $service): void
    {
        abort_unless(Auth::user()->hasPermissionTo('evaluaciones.publicar'), 403);

        $evaluacion = Evaluacion::query()->where('horario_id', $this->horario->id)->findOrFail($this->evaluacionId);

        $service->publicar($evaluacion);
    }

    public function with(EvaluacionService $service): array
    {
        $user = Auth::user();
        $puedeRegistrar = Gate::allows('evaluaciones.registrar-horario', $this->horario);
        $puedeSupervisar = ! $puedeRegistrar && $user->hasPermissionTo('evaluaciones.ver');
        $puedePublicar = $user->hasPermissionTo('evaluaciones.publicar');

        if ($puedeRegistrar || $puedeSupervisar) {
            $evaluaciones = $service->evaluacionesDelHorario($this->horario);
            $estudiantes = $service->estudiantesDelHorario($this->horario);
            $evaluacionSeleccionada = $this->evaluacionId
                ? $evaluaciones->firstWhere('id', $this->evaluacionId)
                : null;

            return [
                'puedeRegistrar' => $puedeRegistrar,
                'puedeSupervisar' => $puedeSupervisar,
                'puedePublicar' => $puedePublicar,
                'evaluaciones' => $evaluaciones,
                'estudiantes' => $estudiantes,
                'evaluacionSeleccionada' => $evaluacionSeleccionada,
                'misCalificaciones' => collect(),
                'promedio' => null,
            ];
        }

        $misCalificaciones = collect();
        $promedio = null;

        if ($user->estudiante) {
            $misCalificaciones = $service->misCalificaciones($user->estudiante, $this->horario);
            $promedio = $service->promedioDelEstudiante($user->estudiante, $this->horario);
        }

        return [
            'puedeRegistrar' => false,
            'puedeSupervisar' => false,
            'puedePublicar' => false,
            'evaluaciones' => collect(),
            'estudiantes' => collect(),
            'evaluacionSeleccionada' => null,
            'misCalificaciones' => $misCalificaciones,
            'promedio' => $promedio,
            'miEstudianteId' => $user->estudiante?->id,
        ];
    }
}; ?>

<div>
    <x-slot name="header">
        <a href="{{ route('evaluaciones.index') }}" wire:navigate class="text-sm text-ink-faint hover:text-ink">← Evaluaciones</a>
        <h1 class="mt-1 font-display text-2xl text-ink">{{ $horario->curso->nombre }}</h1>
        <p class="mt-1 text-sm text-ink-dim">
            {{ $horario->grado->nombre }} · {{ $horario->ciclo->nombre }} · {{ $horario->docente->name }}
        </p>
    </x-slot>

    @if ($puedeRegistrar || $puedeSupervisar)
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-[280px_1fr]">
            <div>
                @if ($puedeRegistrar)
                    <x-secondary-button type="button" wire:click="$set('mostrarFormNueva', true)" class="mb-3 w-full justify-center">
                        + Nueva evaluación
                    </x-secondary-button>
                @endif

                @if ($mostrarFormNueva)
                    <form wire:submit="crear" class="mb-4 space-y-3 rounded-lg border border-border bg-surface p-4">
                        <div>
                            <x-input-label for="nuevoNombre" value="Nombre" />
                            <x-text-input wire:model="nuevoNombre" id="nuevoNombre" class="mt-1 block w-full" placeholder="Evaluación mensual — julio" />
                            <x-input-error :messages="$errors->get('nuevoNombre')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label for="nuevaFecha" value="Fecha" />
                            <x-text-input wire:model="nuevaFecha" id="nuevaFecha" type="date" class="mt-1 block w-full" />
                            <x-input-error :messages="$errors->get('nuevaFecha')" class="mt-1" />
                        </div>
                        <div class="flex justify-end gap-2">
                            <x-secondary-button type="button" wire:click="$set('mostrarFormNueva', false)">Cancelar</x-secondary-button>
                            <x-primary-button type="submit">Crear</x-primary-button>
                        </div>
                    </form>
                @endif

                <div class="divide-y divide-border rounded-lg border border-border bg-surface">
                    @forelse ($evaluaciones as $evaluacion)
                        <button
                            type="button"
                            wire:click="seleccionar({{ $evaluacion->id }})"
                            @class([
                                'flex w-full items-center justify-between gap-2 px-4 py-3 text-left text-sm transition',
                                'bg-accent-soft' => $evaluacionSeleccionada?->id === $evaluacion->id,
                                'hover:bg-surface-2' => $evaluacionSeleccionada?->id !== $evaluacion->id,
                            ])
                        >
                            <span>
                                <span class="block text-ink">{{ $evaluacion->nombre }}</span>
                                <span class="block text-xs text-ink-faint">{{ $evaluacion->fecha->format('d/m/Y') }}</span>
                            </span>
                            <span @class([
                                'rounded-full px-2 py-0.5 text-xs',
                                'bg-accent-soft text-accent' => $evaluacion->estaPublicada(),
                                'bg-surface-2 text-ink-faint' => ! $evaluacion->estaPublicada(),
                            ])>
                                {{ $evaluacion->estado->label() }}
                            </span>
                        </button>
                    @empty
                        <p class="px-4 py-8 text-center text-sm text-ink-faint">Todavía no hay evaluaciones.</p>
                    @endforelse
                </div>
            </div>

            <div>
                @if ($evaluacionSeleccionada)
                    <div class="mb-4 flex items-center justify-between">
                        <div>
                            <p class="font-display text-lg text-ink">{{ $evaluacionSeleccionada->nombre }}</p>
                            <p class="text-xs text-ink-faint">{{ $evaluacionSeleccionada->fecha->format('d/m/Y') }}</p>
                        </div>
                        @if ($puedePublicar && ! $evaluacionSeleccionada->estaPublicada())
                            <x-secondary-button type="button" wire:click="publicar" wire:confirm="¿Publicar esta evaluación? Los estudiantes podrán ver sus notas.">
                                Publicar
                            </x-secondary-button>
                        @endif
                    </div>

                    @if ($guardado)
                        <p class="mb-4 rounded-md bg-accent-soft px-3 py-2 text-sm text-accent">Notas guardadas.</p>
                    @endif

                    <div class="divide-y divide-border rounded-lg border border-border bg-surface">
                        @forelse ($estudiantes as $estudiante)
                            <div class="flex items-center justify-between gap-4 px-4 py-3 text-sm">
                                <p class="text-ink">{{ $estudiante->nombreCompleto() }}</p>
                                <div class="flex items-center gap-2">
                                    <input
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        max="20"
                                        wire:model="notas.{{ $estudiante->id }}"
                                        placeholder="—"
                                        class="w-20 rounded-md border-border bg-surface text-sm text-ink focus:border-accent focus:ring-accent"
                                        @disabled(! $puedeRegistrar)
                                    >
                                    <input
                                        type="text"
                                        wire:model="observaciones.{{ $estudiante->id }}"
                                        placeholder="Observación (opcional)"
                                        class="w-48 rounded-md border-border bg-surface text-xs text-ink placeholder:text-ink-faint focus:border-accent focus:ring-accent"
                                        @disabled(! $puedeRegistrar)
                                    >
                                </div>
                            </div>
                        @empty
                            <p class="px-4 py-8 text-center text-sm text-ink-faint">No hay estudiantes matriculados en este horario.</p>
                        @endforelse
                    </div>

                    @if ($puedeRegistrar && $estudiantes->isNotEmpty())
                        <div class="mt-4 flex justify-end">
                            <x-primary-button type="button" wire:click="guardarNotas">Guardar notas</x-primary-button>
                        </div>
                    @endif
                @else
                    <p class="rounded-lg border border-dashed border-border p-8 text-center text-sm text-ink-faint">
                        Selecciona una evaluación para ver o registrar notas.
                    </p>
                @endif
            </div>
        </div>
    @else
        <div class="space-y-4">
            <div class="rounded-lg border border-border bg-surface p-4">
                <p class="text-xs uppercase tracking-wide text-ink-faint">Promedio del curso</p>
                <p class="mt-1 font-display text-3xl text-accent">{{ $promedio !== null ? number_format($promedio, 2) : '—' }}</p>
            </div>

            <div class="divide-y divide-border rounded-lg border border-border bg-surface">
                @forelse ($misCalificaciones as $calificacion)
                    <div class="flex items-center justify-between gap-4 px-4 py-3 text-sm">
                        <div>
                            <p class="text-ink">{{ $calificacion->evaluacion->nombre }}</p>
                            <p class="text-xs text-ink-faint">{{ $calificacion->evaluacion->fecha->format('d/m/Y') }}</p>
                        </div>
                        <div class="text-right">
                            <p class="font-display text-lg text-ink">{{ number_format((float) $calificacion->nota_numerica, 2) }}</p>
                            <p class="text-xs text-ink-faint">{{ $calificacion->notaLetra()->value }}</p>
                        </div>
                    </div>
                @empty
                    <p class="px-4 py-8 text-center text-sm text-ink-faint">Todavía no hay notas publicadas para este curso.</p>
                @endforelse
            </div>

            @if ($miEstudianteId)
                <a
                    href="{{ route('evaluaciones.libreta', ['estudiante' => $miEstudianteId, 'ciclo' => $horario->ciclo_id]) }}"
                    wire:navigate
                    class="inline-block text-sm font-medium text-accent hover:underline"
                >
                    Ver mi libreta del ciclo →
                </a>
            @endif
        </div>
    @endif
</div>
