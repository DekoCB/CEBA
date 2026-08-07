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
        <h1 class="font-display text-2xl text-ink">Horarios</h1>
        <p class="mt-1 text-sm text-ink-dim">Un aula y un docente no pueden tener dos clases a la misma hora.</p>
    </x-slot>

    {{-- Ver academico/grados/index.blade.php: el botón no puede vivir en x-slot="header". --}}
    @can('academico.gestionar')
        <div class="mb-4 flex justify-end">
            <button wire:click="abrirModal" class="inline-flex items-center gap-2 rounded-md bg-accent px-4 py-2 text-sm font-medium text-white hover:opacity-90">
                <x-heroicon-o-plus class="h-4 w-4" />
                Nuevo horario
            </button>
        </div>
    @endcan

    @if (session('status'))
        <div class="mb-4 rounded-md border border-ok/30 bg-ok/10 px-4 py-3 text-sm text-ok">{{ session('status') }}</div>
    @endif

    <div class="mb-4">
        <x-select-input
            wire:model.live="cicloFiltro"
            placeholder="Selecciona un ciclo…"
            class="w-full sm:max-w-xs"
            :options="collect($ciclos)->mapWithKeys(fn ($ciclo) => [$ciclo->id => $ciclo->nombre])"
        />
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

    <div
        x-show="$wire.mostrarModal"
        x-cloak
        class="fixed inset-0 z-50 flex items-center justify-center bg-ink/40 px-4"
        wire:click.self="$set('mostrarModal', false)"
        x-transition:enter="ease-out duration-300"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="ease-in duration-200"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
    >
        <div
            x-show="$wire.mostrarModal"
            class="w-full max-w-lg rounded-lg border border-border bg-surface p-6 shadow-lg"
            x-transition:enter="ease-out duration-300"
            x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
            x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
            x-transition:leave="ease-in duration-200"
            x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
            x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
        >
                <h2 class="font-display text-lg text-ink">Nuevo horario</h2>

                <form wire:submit="guardar" class="mt-4 space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <x-input-label for="cicloId" value="Ciclo" />
                            <x-select-input
                                wire:model="cicloId"
                                id="cicloId"
                                class="mt-1 block w-full"
                                :options="collect($ciclos)->mapWithKeys(fn ($ciclo) => [$ciclo->id => $ciclo->nombre])"
                            />
                            <x-input-error :messages="$errors->get('cicloId')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label for="gradoId" value="Grado" />
                            <x-select-input
                                wire:model="gradoId"
                                id="gradoId"
                                class="mt-1 block w-full"
                                :options="collect($grados)->mapWithKeys(fn ($grado) => [$grado->id => $grado->nombre])"
                            />
                            <x-input-error :messages="$errors->get('gradoId')" class="mt-1" />
                        </div>
                    </div>

                    <div>
                        <x-input-label for="cursoId" value="Curso" />
                        <x-select-input
                            wire:model="cursoId"
                            id="cursoId"
                            class="mt-1 block w-full"
                            :options="collect($cursos)->mapWithKeys(fn ($curso) => [$curso->id => $curso->nombre.' ('.$curso->codigo.')'])"
                        />
                        <x-input-error :messages="$errors->get('cursoId')" class="mt-1" />
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <x-input-label for="docenteId" value="Docente" />
                            <x-select-input
                                wire:model="docenteId"
                                id="docenteId"
                                class="mt-1 block w-full"
                                :options="collect($docentes)->mapWithKeys(fn ($docente) => [$docente->id => $docente->name])"
                            />
                            <x-input-error :messages="$errors->get('docenteId')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label for="aulaId" value="Aula" />
                            <x-select-input
                                wire:model="aulaId"
                                id="aulaId"
                                class="mt-1 block w-full"
                                :options="collect($aulas)->mapWithKeys(fn ($aula) => [$aula->id => $aula->nombre])"
                            />
                            <x-input-error :messages="$errors->get('aulaId')" class="mt-1" />
                        </div>
                    </div>

                    <div class="grid grid-cols-3 gap-4">
                        <div>
                            <x-input-label for="diaSemana" value="Día" />
                            <x-select-input
                                wire:model="diaSemana"
                                id="diaSemana"
                                class="mt-1 block w-full"
                                :options="collect($dias)->mapWithKeys(fn ($dia) => [$dia->value => $dia->label()])"
                            />
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
    </div>
</div>
