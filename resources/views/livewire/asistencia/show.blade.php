<?php

use App\Modules\Academico\Models\Horario;
use App\Modules\Asistencia\Enums\EstadoAsistenciaEnum;
use App\Modules\Asistencia\Models\Asistencia;
use App\Modules\Asistencia\Services\AsistenciaService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new #[Layout('layouts.app')] class extends Component
{
    use WithFileUploads;

    public Horario $horario;

    public string $fecha = '';

    /** @var array<int, string> */
    public array $estados = [];

    /** @var array<int, string|null> */
    public array $observaciones = [];

    /** @var array<int, \Livewire\Features\SupportFileUploads\TemporaryUploadedFile|null> */
    public array $justificantes = [];

    /** @var array<int, Asistencia> */
    public array $registrosExistentes = [];

    public bool $guardado = false;

    public function mount(Horario $horario, AsistenciaService $service): void
    {
        Gate::authorize('asistencia.ver-horario', $horario);

        $this->horario = $horario;
        $this->fecha = now()->format('Y-m-d');

        if ($this->puedeVerRegistros()) {
            $this->cargarRegistros($service);
        }
    }

    public function updatedFecha(AsistenciaService $service): void
    {
        $this->guardado = false;

        if ($this->puedeVerRegistros()) {
            $this->cargarRegistros($service);
        }
    }

    /**
     * Determina si el usuario debe ver los estados ya registrados: incluye
     * a quien puede registrar (el docente del horario, o Dirección) y a
     * quien solo supervisa (p. ej. Coordinador, con asistencia.ver) — antes
     * solo se cargaba para el primer grupo, dejando la lista de un
     * supervisor siempre en blanco aunque sí hubiera asistencia guardada.
     */
    private function puedeVerRegistros(): bool
    {
        return Gate::allows('asistencia.registrar-horario', $this->horario)
            || Auth::user()->hasPermissionTo('asistencia.ver');
    }

    private function cargarRegistros(AsistenciaService $service): void
    {
        $estudiantes = $service->estudiantesDelHorario($this->horario);
        $existentes = $service->deSesion($this->horario, $this->fecha);

        $this->estados = [];
        $this->observaciones = [];
        $this->justificantes = [];
        $this->registrosExistentes = [];

        foreach ($estudiantes as $estudiante) {
            $registro = $existentes->get($estudiante->id);

            $this->estados[$estudiante->id] = $registro?->estado->value
                ?? EstadoAsistenciaEnum::PRESENTE->value;
            $this->observaciones[$estudiante->id] = $registro?->observacion;

            if ($registro) {
                $this->registrosExistentes[$estudiante->id] = $registro;
            }
        }
    }

    public function guardar(AsistenciaService $service): void
    {
        Gate::authorize('asistencia.registrar-horario', $this->horario);

        $valores = implode(',', array_column(EstadoAsistenciaEnum::cases(), 'value'));
        $rules = [];

        foreach (array_keys($this->estados) as $estudianteId) {
            $rules["estados.{$estudianteId}"] = "required|string|in:{$valores}";
            $rules["observaciones.{$estudianteId}"] = 'nullable|string|max:255';
            $rules["justificantes.{$estudianteId}"] = 'nullable|file|mimes:pdf,jpg,jpeg,png|max:4096';
        }

        $this->validate($rules);

        $service->registrar($this->horario, $this->fecha, $this->estados, $this->observaciones, $this->justificantes);

        $this->justificantes = [];
        $this->guardado = true;

        $this->cargarRegistros($service);
    }

    public function with(AsistenciaService $service): array
    {
        $user = Auth::user();
        $puedeRegistrar = Gate::allows('asistencia.registrar-horario', $this->horario);
        $puedeSupervisar = ! $puedeRegistrar && $user->hasPermissionTo('asistencia.ver');

        $miResumen = null;

        if (! $puedeRegistrar && ! $puedeSupervisar && $user->estudiante) {
            $miResumen = $service->resumenEstudiante($user->estudiante, $this->horario);
        }

        return [
            'puedeRegistrar' => $puedeRegistrar,
            'puedeSupervisar' => $puedeSupervisar,
            'estudiantes' => ($puedeRegistrar || $puedeSupervisar) ? $service->estudiantesDelHorario($this->horario) : collect(),
            'fechasRegistradas' => $service->fechasRegistradas($this->horario),
            'estadosDisponibles' => EstadoAsistenciaEnum::cases(),
            'miResumen' => $miResumen,
        ];
    }
}; ?>

<div>
    <x-slot name="header">
        <a href="{{ route('asistencia.index') }}" wire:navigate class="text-sm text-ink-faint hover:text-ink">← Asistencia</a>
        <h1 class="mt-1 font-display text-2xl text-ink">{{ $horario->curso->nombre }}</h1>
        <p class="mt-1 text-sm text-ink-dim">
            {{ $horario->grado->nombre }} · {{ $horario->ciclo->nombre }} · {{ $horario->docente->name }} ·
            {{ $horario->dia_semana->label() }} {{ substr($horario->hora_inicio, 0, 5) }}–{{ substr($horario->hora_fin, 0, 5) }}
        </p>
    </x-slot>

    @if ($puedeRegistrar || $puedeSupervisar)
        <div class="mb-4 flex flex-wrap items-center gap-3">
            <div>
                <x-input-label for="fecha" value="Fecha de la sesión" />
                <x-date-input wire:model.live="fecha" id="fecha" class="mt-1" />
            </div>

            @if ($fechasRegistradas->isNotEmpty())
                <div class="flex flex-wrap gap-1 pt-5">
                    @foreach ($fechasRegistradas->take(8) as $fechaRegistrada)
                        <button
                            type="button"
                            wire:click="$set('fecha', '{{ $fechaRegistrada }}')"
                            @class([
                                'rounded-full px-2.5 py-1 text-xs font-medium transition',
                                'bg-accent-soft text-accent' => $fecha === $fechaRegistrada,
                                'bg-surface-2 text-ink-faint hover:text-ink' => $fecha !== $fechaRegistrada,
                            ])
                        >
                            {{ \Illuminate\Support\Carbon::parse($fechaRegistrada)->format('d/m') }}
                        </button>
                    @endforeach
                </div>
            @endif
        </div>

        @if ($guardado)
            <p class="mb-4 rounded-md bg-accent-soft px-3 py-2 text-sm text-accent">Asistencia guardada.</p>
        @endif

        <div class="divide-y divide-border rounded-lg border border-border bg-surface">
            @forelse ($estudiantes as $estudiante)
                <div class="px-4 py-3 text-sm" x-data="{ estado: @entangle('estados.'.$estudiante->id) }">
                    <div class="flex items-center justify-between gap-4">
                        <p class="text-ink">{{ $estudiante->nombreCompleto() }}</p>

                        <div class="flex gap-1">
                            @foreach ($estadosDisponibles as $estado)
                                <label
                                    @class([
                                        'cursor-pointer rounded-full px-2.5 py-1 text-xs font-medium transition',
                                        'bg-accent-soft text-accent' => ($estados[$estudiante->id] ?? null) === $estado->value,
                                        'bg-surface-2 text-ink-faint hover:text-ink' => ($estados[$estudiante->id] ?? null) !== $estado->value,
                                        'pointer-events-none opacity-60' => ! $puedeRegistrar,
                                    ])
                                >
                                    <input
                                        type="radio"
                                        class="sr-only"
                                        wire:model="estados.{{ $estudiante->id }}"
                                        value="{{ $estado->value }}"
                                        @disabled(! $puedeRegistrar)
                                    >
                                    {{ $estado->label() }}
                                </label>
                            @endforeach
                        </div>
                    </div>

                    <div x-show="estado === 'justificado'" x-cloak class="mt-3 grid grid-cols-1 gap-3 border-t border-border pt-3 sm:grid-cols-2">
                        <div>
                            <x-input-label :for="'observacion-'.$estudiante->id" value="Motivo de la justificación" />
                            <x-text-input
                                wire:model="observaciones.{{ $estudiante->id }}"
                                :id="'observacion-'.$estudiante->id"
                                class="mt-1 block w-full text-xs"
                                placeholder="Ej. Cita médica, duelo familiar…"
                                :disabled="! $puedeRegistrar"
                            />
                            <x-input-error :messages="$errors->get('observaciones.'.$estudiante->id)" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label :for="'justificante-'.$estudiante->id" value="Documento de sustento" />
                            @if ($puedeRegistrar)
                                <input
                                    type="file"
                                    wire:model="justificantes.{{ $estudiante->id }}"
                                    id="justificante-{{ $estudiante->id }}"
                                    accept=".pdf,image/*"
                                    class="mt-1 block w-full text-xs text-ink-dim file:mr-3 file:rounded-md file:border-0 file:bg-surface-2 file:px-3 file:py-2 file:text-xs file:text-ink"
                                >
                                <x-input-error :messages="$errors->get('justificantes.'.$estudiante->id)" class="mt-1" />
                            @endif
                            @if (($registrosExistentes[$estudiante->id] ?? null)?->getFirstMedia('justificante'))
                                <a href="{{ $registrosExistentes[$estudiante->id]->getFirstMediaUrl('justificante') }}" target="_blank" class="mt-1 inline-block text-xs text-accent hover:underline">Ver documento actual</a>
                            @endif
                        </div>
                    </div>
                </div>
            @empty
                <p class="px-4 py-8 text-center text-sm text-ink-faint">No hay estudiantes matriculados en este horario.</p>
            @endforelse
        </div>

        @if ($puedeRegistrar && $estudiantes->isNotEmpty())
            <div class="mt-4 flex justify-end">
                <x-primary-button type="button" wire:click="guardar">Guardar asistencia</x-primary-button>
            </div>
        @endif
    @elseif ($miResumen)
        <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
            <div class="rounded-lg border border-border bg-surface p-4">
                <p class="text-xs uppercase tracking-wide text-ink-faint">Asistencia</p>
                <p class="mt-1 font-display text-3xl text-accent">{{ $miResumen['porcentaje'] }}%</p>
            </div>
            @foreach ($estadosDisponibles as $estado)
                <div class="rounded-lg border border-border bg-surface p-4">
                    <p class="text-xs uppercase tracking-wide text-ink-faint">{{ $estado->label() }}</p>
                    <p class="mt-1 font-display text-2xl text-ink">{{ $miResumen['por_estado'][$estado->value] }}</p>
                </div>
            @endforeach
        </div>

        <p class="mt-6 text-xs text-ink-faint">
            {{ $miResumen['total'] }} sesiones registradas de un total de {{ $fechasRegistradas->count() }}.
        </p>
    @endif
</div>
