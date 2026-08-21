<?php

use App\Models\User;
use App\Modules\Academico\Enums\DiaSemanaEnum;
use App\Modules\Academico\Enums\FranjaHorarioEnum;
use App\Modules\Academico\Models\Aula;
use App\Modules\Academico\Models\Ciclo;
use App\Modules\Academico\Models\Curso;
use App\Modules\Academico\Models\Grado;
use App\Modules\Academico\Models\Horario;
use App\Modules\Academico\Services\HorarioService;
use Illuminate\Support\Collection;
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

    public string $franjaPreset = '';

    public string $horaInicioHora = '';

    public string $horaInicioMinuto = '';

    public string $horaFinHora = '';

    public string $horaFinMinuto = '';

    /**
     * @return array<string, string>
     */
    public function franjasDisponibles(): array
    {
        return collect(FranjaHorarioEnum::cases())->mapWithKeys(fn (FranjaHorarioEnum $franja) => [$franja->value => $franja->label()])->all();
    }

    /**
     * @return array<string, string>
     */
    public function horasDisponibles(): array
    {
        return collect(range(0, 23))->mapWithKeys(fn (int $hora) => [sprintf('%02d', $hora) => sprintf('%02d', $hora)])->all();
    }

    /**
     * Minutos en pasos de 15: los horarios de la institución siempre caen en
     * una hora exacta o cuarto, y un selector de 60 opciones sería incómodo.
     *
     * @return array<string, string>
     */
    public function minutosDisponibles(): array
    {
        return ['00' => '00', '15' => '15', '30' => '30', '45' => '45'];
    }

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
        $this->reset(['cursoId', 'docenteId', 'aulaId', 'gradoId', 'franjaPreset', 'horaInicioHora', 'horaInicioMinuto', 'horaFinHora', 'horaFinMinuto']);
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
            'franjaPreset' => 'required|string|in:'.implode(',', array_column(FranjaHorarioEnum::cases(), 'value')),
            'horaInicioHora' => 'required|string|in:'.implode(',', array_keys($this->horasDisponibles())),
            'horaInicioMinuto' => 'required|string|in:'.implode(',', array_keys($this->minutosDisponibles())),
            'horaFinHora' => 'required|string|in:'.implode(',', array_keys($this->horasDisponibles())),
            'horaFinMinuto' => 'required|string|in:'.implode(',', array_keys($this->minutosDisponibles())),
        ]);

        $horaInicio = "{$this->horaInicioHora}:{$this->horaInicioMinuto}";
        $horaFin = "{$this->horaFinHora}:{$this->horaFinMinuto}";

        $dias = array_map(fn (DiaSemanaEnum $dia) => [
            'dia_semana' => $dia,
            'hora_inicio' => $horaInicio.':00',
            'hora_fin' => $horaFin.':00',
        ], FranjaHorarioEnum::from($this->franjaPreset)->dias());

        $service->crear([
            'curso_id' => (int) $this->cursoId,
            'docente_id' => (int) $this->docenteId,
            'aula_id' => (int) $this->aulaId,
            'ciclo_id' => (int) $this->cicloId,
            'grado_id' => (int) $this->gradoId,
            'dias' => $dias,
        ]);

        $this->mostrarModal = false;
        session()->flash('status', 'Horario creado correctamente.');
    }

    public function with(HorarioService $service): array
    {
        $horarios = $this->cicloFiltro ? $service->delCiclo((int) $this->cicloFiltro) : collect();

        return [
            'ciclos' => Ciclo::query()->orderByDesc('fecha_inicio')->get(),
            'horariosPorFranja' => $this->agruparPorFranjaYGrado($horarios),
            'cursos' => Curso::query()->where('activo', true)->orderBy('nombre')->get(),
            'docentes' => User::role('docente')->orderBy('name')->get(),
            'aulas' => Aula::query()->where('activa', true)->orderBy('nombre')->get(),
            'grados' => Grado::query()->where('activo', true)->orderBy('nombre')->get(),
            'franjas' => $this->franjasDisponibles(),
            'horas' => $this->horasDisponibles(),
            'minutos' => $this->minutosDisponibles(),
        ];
    }

    /**
     * La vista organizada como la institución realmente trabaja: primero
     * por franja (Lunes-Miércoles / Martes-Jueves / Domingo), y dentro de
     * cada una por grado -- así se ven de una los grupos A y B de un mismo
     * grado, en vez de una tabla plana ordenada por curso.
     *
     * @param  Collection<int, Horario>  $horarios
     * @return Collection<int, array{label: string, porGrado: Collection<string, Collection<int, Horario>>}>
     */
    private function agruparPorFranjaYGrado($horarios)
    {
        $grupos = collect(FranjaHorarioEnum::cases())
            ->mapWithKeys(function (FranjaHorarioEnum $franja) use ($horarios) {
                $deLaFranja = $horarios->filter(fn (Horario $horario) => $horario->franja() === $franja);

                return [$franja->value => [
                    'label' => $franja->label(),
                    'porGrado' => $deLaFranja->groupBy(fn (Horario $horario) => $horario->grado->nombre)->sortKeys(),
                ]];
            });

        $sinFranja = $horarios->filter(fn (Horario $horario) => $horario->franja() === null);

        if ($sinFranja->isNotEmpty()) {
            $grupos['otros'] = [
                'label' => 'Otros días',
                'porGrado' => $sinFranja->groupBy(fn (Horario $horario) => $horario->grado->nombre)->sortKeys(),
            ];
        }

        return $grupos->filter(fn (array $grupo) => $grupo['porGrado']->isNotEmpty());
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
            <button wire:click="abrirModal" class="inline-flex items-center gap-2 rounded-md bg-accent px-4 py-2 font-display text-sm font-medium text-white hover:opacity-90">
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

    @forelse ($horariosPorFranja as $grupoFranja)
        <div class="mb-6">
            <h2 class="mb-2 font-display text-lg text-ink">{{ $grupoFranja['label'] }}</h2>

            @foreach ($grupoFranja['porGrado'] as $nombreGrado => $horariosDelGrado)
                <div class="mb-4 overflow-hidden rounded-lg border border-border bg-surface">
                    <div class="border-b border-border bg-surface-2 px-4 py-2 font-display text-sm text-ink">{{ $nombreGrado }}</div>
                    <table class="min-w-full divide-y divide-border text-sm">
                        <thead class="bg-surface-2">
                            <tr>
                                <th class="px-4 py-3 text-left font-mono text-xs uppercase tracking-wide text-ink-faint">Curso</th>
                                <th class="px-4 py-3 text-left font-mono text-xs uppercase tracking-wide text-ink-faint">Docente</th>
                                <th class="px-4 py-3 text-left font-mono text-xs uppercase tracking-wide text-ink-faint">Aula</th>
                                <th class="px-4 py-3 text-left font-mono text-xs uppercase tracking-wide text-ink-faint">Horario</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            @foreach ($horariosDelGrado->sortBy(fn ($horario) => $horario->curso->nombre) as $horario)
                                <tr wire:key="horario-{{ $horario->id }}">
                                    <td class="px-4 py-3 font-medium text-ink">{{ $horario->curso->nombre }}</td>
                                    <td class="px-4 py-3 text-ink-dim">{{ $horario->docente->name }}</td>
                                    <td class="px-4 py-3 text-ink-dim">{{ $horario->aula->nombre }}</td>
                                    <td class="px-4 py-3 font-mono text-ink-dim">{{ $horario->diasResumen() }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endforeach
        </div>
    @empty
        <div class="rounded-lg border border-border bg-surface px-4 py-8 text-center text-sm text-ink-faint">
            {{ $cicloFiltro ? 'Este ciclo no tiene horarios todavía.' : 'Selecciona un ciclo para ver sus horarios.' }}
        </div>
    @endforelse

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
            class="max-h-[90vh] w-full max-w-xl overflow-y-auto rounded-lg border border-border bg-surface p-6 shadow-lg"
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

                    <div>
                        <x-input-label value="Días de clase" />
                        <p class="mt-1 text-xs text-ink-faint">Ningún curso se dicta en un día suelto: elige la franja.</p>

                        <div class="mt-2 space-y-2">
                            @foreach ($franjas as $valor => $etiqueta)
                                <label class="flex items-center gap-2 rounded-md border border-border p-3 text-sm text-ink">
                                    <input
                                        type="radio"
                                        value="{{ $valor }}"
                                        wire:model="franjaPreset"
                                        class="border-border text-accent focus:ring-accent"
                                    >
                                    {{ $etiqueta }}
                                </label>
                            @endforeach
                        </div>

                        <x-input-error :messages="$errors->get('franjaPreset')" class="mt-1" />
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <x-input-label value="Hora inicio" />
                            <div class="mt-1 grid grid-cols-2 gap-2">
                                <x-select-input wire:model="horaInicioHora" placeholder="Hora" :options="$horas" />
                                <x-select-input wire:model="horaInicioMinuto" placeholder="Min" :options="$minutos" />
                            </div>
                            <x-input-error :messages="$errors->get('horaInicioHora')" class="mt-1" />
                            <x-input-error :messages="$errors->get('horaInicioMinuto')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label value="Hora fin" />
                            <div class="mt-1 grid grid-cols-2 gap-2">
                                <x-select-input wire:model="horaFinHora" placeholder="Hora" :options="$horas" />
                                <x-select-input wire:model="horaFinMinuto" placeholder="Min" :options="$minutos" />
                            </div>
                            <x-input-error :messages="$errors->get('horaFinHora')" class="mt-1" />
                            <x-input-error :messages="$errors->get('horaFinMinuto')" class="mt-1" />
                        </div>
                    </div>

                    {{-- Los choques de aula/docente los valida HorarioService::crear(), que lanza sus errores bajo la clave "dias". --}}
                    <x-input-error :messages="$errors->get('dias')" class="mt-1" />

                    <div class="flex justify-end gap-3 pt-2">
                        <x-secondary-button type="button" wire:click="$set('mostrarModal', false)">Cancelar</x-secondary-button>
                        <x-primary-button type="submit">Crear horario</x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
