<?php

use App\Models\User;
use App\Modules\Academico\Enums\DiaSemanaEnum;
use App\Modules\Academico\Models\Aula;
use App\Modules\Academico\Models\Ciclo;
use App\Modules\Academico\Models\Curso;
use App\Modules\Academico\Models\Grado;
use App\Modules\Academico\Services\HorarioService;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public bool $mostrarModal = false;

    public string $cicloFiltro = '';

    public string $cursoId = '';

    public string $docenteId = '';

    public string $aulaId = '';

    public string $cicloId = '';

    public string $gradoId = '';

    public string $diaSemana = '';

    public string $horaInicio = '';

    public string $horaFin = '';

    public function mount(): void
    {
        Gate::authorize('academico.ver');

        $activo = Ciclo::query()->where('estado', 'activo')->first();
        $this->cicloFiltro = $activo ? (string) $activo->id : '';
    }

    public function abrirModal(): void
    {
        Gate::authorize('academico.gestionar');

        $this->resetValidation();
        $this->reset(['cursoId', 'docenteId', 'aulaId', 'gradoId', 'diaSemana', 'horaInicio', 'horaFin']);
        $this->cicloId = $this->cicloFiltro;
        $this->mostrarModal = true;
    }

    public function guardar(HorarioService $service): void
    {
        Gate::authorize('academico.gestionar');

        $this->validate([
            'cursoId' => 'required|integer|exists:cursos,id',
            'docenteId' => 'required|integer|exists:users,id',
            'aulaId' => 'required|integer|exists:aulas,id',
            'cicloId' => 'required|integer|exists:ciclos,id',
            'gradoId' => 'required|integer|exists:grados,id',
            'diaSemana' => 'required|string|in:'.implode(',', array_column(DiaSemanaEnum::cases(), 'value')),
            'horaInicio' => 'required|date_format:H:i',
            'horaFin' => 'required|date_format:H:i',
        ]);

        $service->crear([
            'curso_id' => (int) $this->cursoId,
            'docente_id' => (int) $this->docenteId,
            'aula_id' => (int) $this->aulaId,
            'ciclo_id' => (int) $this->cicloId,
            'grado_id' => (int) $this->gradoId,
            'dia_semana' => DiaSemanaEnum::from($this->diaSemana),
            'hora_inicio' => $this->horaInicio.':00',
            'hora_fin' => $this->horaFin.':00',
        ]);

        $this->mostrarModal = false;
        session()->flash('status', 'Horario creado correctamente.');
    }

    public function with(HorarioService $service): array
    {
        return [
            'ciclos' => Ciclo::query()->orderByDesc('fecha_inicio')->get(),
            'horarios' => $this->cicloFiltro ? $service->delCiclo((int) $this->cicloFiltro) : collect(),
            'cursos' => Curso::query()->where('activo', true)->orderBy('nombre')->get(),
            'docentes' => User::role('docente')->orderBy('name')->get(),
            'aulas' => Aula::query()->where('activa', true)->orderBy('nombre')->get(),
            'grados' => Grado::query()->where('activo', true)->orderBy('nombre')->get(),
            'dias' => DiaSemanaEnum::cases(),
        ];
    }
}; ?>

<div>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="font-display text-2xl text-ink">Horarios</h1>
                <p class="mt-1 text-sm text-ink-dim">Un aula y un docente no pueden tener dos clases a la misma hora.</p>
            </div>
            @can('academico.gestionar')
                <button wire:click="abrirModal" class="inline-flex items-center gap-2 rounded-md bg-accent px-4 py-2 text-sm font-medium text-white hover:opacity-90">
                    <x-heroicon-o-plus class="h-4 w-4" />
                    Nuevo horario
                </button>
            @endcan
        </div>
    </x-slot>

    @if (session('status'))
        <div class="mb-4 rounded-md border border-ok/30 bg-ok/10 px-4 py-3 text-sm text-ok">{{ session('status') }}</div>
    @endif

    <div class="mb-4">
        <select wire:model.live="cicloFiltro" class="w-full rounded-md border-border bg-surface text-sm text-ink focus:border-accent focus:ring-accent sm:max-w-xs">
            <option value="">Selecciona un ciclo…</option>
            @foreach ($ciclos as $ciclo)
                <option value="{{ $ciclo->id }}">{{ $ciclo->nombre }}</option>
            @endforeach
        </select>
    </div>

    <div class="overflow-hidden rounded-lg border border-border bg-surface">
        <table class="min-w-full divide-y divide-border text-sm">
            <thead class="bg-surface-2">
                <tr>
                    <th class="px-4 py-3 text-left font-mono text-xs uppercase tracking-wide text-ink-faint">Curso</th>
                    <th class="px-4 py-3 text-left font-mono text-xs uppercase tracking-wide text-ink-faint">Docente</th>
                    <th class="px-4 py-3 text-left font-mono text-xs uppercase tracking-wide text-ink-faint">Aula</th>
                    <th class="px-4 py-3 text-left font-mono text-xs uppercase tracking-wide text-ink-faint">Grado</th>
                    <th class="px-4 py-3 text-left font-mono text-xs uppercase tracking-wide text-ink-faint">Día</th>
                    <th class="px-4 py-3 text-left font-mono text-xs uppercase tracking-wide text-ink-faint">Horario</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-border">
                @forelse ($horarios as $horario)
                    <tr wire:key="horario-{{ $horario->id }}">
                        <td class="px-4 py-3 font-medium text-ink">{{ $horario->curso->nombre }}</td>
                        <td class="px-4 py-3 text-ink-dim">{{ $horario->docente->name }}</td>
                        <td class="px-4 py-3 text-ink-dim">{{ $horario->aula->nombre }}</td>
                        <td class="px-4 py-3 text-ink-dim">{{ $horario->grado->nombre }}</td>
                        <td class="px-4 py-3 text-ink-dim">{{ $horario->dia_semana->label() }}</td>
                        <td class="px-4 py-3 font-mono text-ink-dim">{{ substr($horario->hora_inicio, 0, 5) }}–{{ substr($horario->hora_fin, 0, 5) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-8 text-center text-sm text-ink-faint">{{ $cicloFiltro ? 'Este ciclo no tiene horarios todavía.' : 'Selecciona un ciclo para ver sus horarios.' }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($mostrarModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-ink/40 px-4" wire:click.self="$set('mostrarModal', false)">
            <div class="w-full max-w-lg rounded-lg border border-border bg-surface p-6 shadow-lg">
                <h2 class="font-display text-lg text-ink">Nuevo horario</h2>

                <form wire:submit="guardar" class="mt-4 space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <x-input-label for="cicloId" value="Ciclo" />
                            <select wire:model="cicloId" id="cicloId" class="mt-1 block w-full rounded-md border-border bg-surface text-sm text-ink focus:border-accent focus:ring-accent">
                                <option value="">Selecciona…</option>
                                @foreach ($ciclos as $ciclo)
                                    <option value="{{ $ciclo->id }}">{{ $ciclo->nombre }}</option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('cicloId')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label for="gradoId" value="Grado" />
                            <select wire:model="gradoId" id="gradoId" class="mt-1 block w-full rounded-md border-border bg-surface text-sm text-ink focus:border-accent focus:ring-accent">
                                <option value="">Selecciona…</option>
                                @foreach ($grados as $grado)
                                    <option value="{{ $grado->id }}">{{ $grado->nombre }}</option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('gradoId')" class="mt-1" />
                        </div>
                    </div>

                    <div>
                        <x-input-label for="cursoId" value="Curso" />
                        <select wire:model="cursoId" id="cursoId" class="mt-1 block w-full rounded-md border-border bg-surface text-sm text-ink focus:border-accent focus:ring-accent">
                            <option value="">Selecciona…</option>
                            @foreach ($cursos as $curso)
                                <option value="{{ $curso->id }}">{{ $curso->nombre }} ({{ $curso->codigo }})</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('cursoId')" class="mt-1" />
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <x-input-label for="docenteId" value="Docente" />
                            <select wire:model="docenteId" id="docenteId" class="mt-1 block w-full rounded-md border-border bg-surface text-sm text-ink focus:border-accent focus:ring-accent">
                                <option value="">Selecciona…</option>
                                @foreach ($docentes as $docente)
                                    <option value="{{ $docente->id }}">{{ $docente->name }}</option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('docenteId')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label for="aulaId" value="Aula" />
                            <select wire:model="aulaId" id="aulaId" class="mt-1 block w-full rounded-md border-border bg-surface text-sm text-ink focus:border-accent focus:ring-accent">
                                <option value="">Selecciona…</option>
                                @foreach ($aulas as $aula)
                                    <option value="{{ $aula->id }}">{{ $aula->nombre }}</option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('aulaId')" class="mt-1" />
                        </div>
                    </div>

                    <div class="grid grid-cols-3 gap-4">
                        <div>
                            <x-input-label for="diaSemana" value="Día" />
                            <select wire:model="diaSemana" id="diaSemana" class="mt-1 block w-full rounded-md border-border bg-surface text-sm text-ink focus:border-accent focus:ring-accent">
                                <option value="">Selecciona…</option>
                                @foreach ($dias as $dia)
                                    <option value="{{ $dia->value }}">{{ $dia->label() }}</option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('diaSemana')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label for="horaInicio" value="Hora inicio" />
                            <x-text-input wire:model="horaInicio" id="horaInicio" type="time" class="mt-1 block w-full" />
                            <x-input-error :messages="$errors->get('horaInicio')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label for="horaFin" value="Hora fin" />
                            <x-text-input wire:model="horaFin" id="horaFin" type="time" class="mt-1 block w-full" />
                            <x-input-error :messages="$errors->get('horaFin')" class="mt-1" />
                        </div>
                    </div>

                    <div class="flex justify-end gap-3 pt-2">
                        <x-secondary-button type="button" wire:click="$set('mostrarModal', false)">Cancelar</x-secondary-button>
                        <x-primary-button type="submit">Crear horario</x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
