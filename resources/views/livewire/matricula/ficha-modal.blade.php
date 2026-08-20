<?php

use App\Modules\Academico\Enums\TipoPublicoEnum;
use App\Modules\Academico\Models\Horario;
use App\Modules\Matricula\Models\DocumentoEstudiante;
use App\Modules\Matricula\Models\Estudiante;
use App\Modules\Matricula\Models\Matricula;
use App\Modules\Matricula\Services\DocumentoEstudianteService;
use App\Modules\Matricula\Services\MatriculaService;
use App\Modules\Pagos\Services\PlanPagoService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\On;
use Livewire\Volt\Component;

/**
 * Modal "Ver" de matricula/index.blade.php, aislado en su propio componente
 * para que abrirlo no dispare una re-consulta de la lista paginada completa
 * (con eso el modal se anima al instante y solo carga los datos del
 * estudiante seleccionado, no toda la tabla).
 */
new class extends Component
{
    public ?int $estudianteId = null;

    public string $observacionesTexto = '';

    public ?int $editandoSeccionMatriculaId = null;

    public string $seccionSeleccionada = '';

    public ?int $editandoHorarioMatriculaId = null;

    public string $horarioSeleccionado = '';

    #[On('ver-estudiante')]
    public function abrir(int $estudianteId): void
    {
        Gate::authorize('matricula.ver');

        $this->estudianteId = $estudianteId;
        $this->observacionesTexto = Estudiante::query()->find($estudianteId)?->observaciones ?? '';
        $this->editandoSeccionMatriculaId = null;
        $this->seccionSeleccionada = '';
        $this->editandoHorarioMatriculaId = null;
        $this->horarioSeleccionado = '';
    }

    public function verificarDocumento(int $documentoId, DocumentoEstudianteService $service): void
    {
        Gate::authorize('matricula.editar');

        $documento = DocumentoEstudiante::query()->findOrFail($documentoId);
        $service->verificar($documento);
    }

    public function guardarObservaciones(): void
    {
        Gate::authorize('matricula.editar');

        Estudiante::query()->findOrFail($this->estudianteId)->update(['observaciones' => $this->observacionesTexto ?: null]);
    }

    public function editarSeccion(int $matriculaId): void
    {
        Gate::authorize('matricula.editar');

        $matricula = Matricula::query()->findOrFail($matriculaId);

        $this->editandoSeccionMatriculaId = $matriculaId;
        $this->seccionSeleccionada = $matricula->seccion ?? '';
    }

    public function cancelarEdicionSeccion(): void
    {
        $this->editandoSeccionMatriculaId = null;
        $this->seccionSeleccionada = '';
    }

    public function guardarSeccion(MatriculaService $service): void
    {
        Gate::authorize('matricula.editar');

        if ($this->editandoSeccionMatriculaId === null) {
            return;
        }

        $matricula = Matricula::query()->findOrFail($this->editandoSeccionMatriculaId);

        $service->reasignarSeccion($matricula, $this->seccionSeleccionada !== '' ? $this->seccionSeleccionada : null);

        $this->editandoSeccionMatriculaId = null;
        $this->seccionSeleccionada = '';
    }

    public function editarHorario(int $matriculaId): void
    {
        Gate::authorize('matricula.editar');

        $matricula = Matricula::query()->findOrFail($matriculaId);

        $this->editandoHorarioMatriculaId = $matriculaId;
        $this->horarioSeleccionado = $matricula->horario_id !== null ? (string) $matricula->horario_id : '';
    }

    public function cancelarEdicionHorario(): void
    {
        $this->editandoHorarioMatriculaId = null;
        $this->horarioSeleccionado = '';
    }

    public function guardarHorario(MatriculaService $service): void
    {
        Gate::authorize('matricula.editar');

        if ($this->editandoHorarioMatriculaId === null) {
            return;
        }

        $matricula = Matricula::query()->findOrFail($this->editandoHorarioMatriculaId);

        $service->reasignarHorario($matricula, $this->horarioSeleccionado !== '' ? (int) $this->horarioSeleccionado : null);

        $this->editandoHorarioMatriculaId = null;
        $this->horarioSeleccionado = '';
    }

    /**
     * @return Collection<int, string>
     */
    private function seccionesParaMatricula(Matricula $matricula, Estudiante $estudiante): Collection
    {
        $publicoEsperado = $estudiante->es_menor_edad ? TipoPublicoEnum::MENOR : TipoPublicoEnum::MAYOR;

        return Horario::query()
            ->where('ciclo_id', $matricula->ciclo_id)
            ->where('grado_id', $matricula->grado_id)
            ->whereNotNull('seccion')
            ->where(fn ($query) => $query->whereNull('tipo_publico')->orWhere('tipo_publico', $publicoEsperado))
            ->pluck('seccion')
            ->unique()
            ->sort()
            ->values();
    }

    /**
     * @return Collection<int, Horario>
     */
    private function horariosParaMatricula(Matricula $matricula): Collection
    {
        return Horario::query()
            ->where('ciclo_id', $matricula->ciclo_id)
            ->where('grado_id', $matricula->grado_id)
            ->with(['curso', 'docente', 'dias'])
            ->get()
            ->sortBy(fn (Horario $horario) => ($horario->seccion ?? '').'|'.$horario->curso->nombre)
            ->values();
    }

    public function with(PlanPagoService $planes): array
    {
        $estudiante = $this->estudianteId ? Estudiante::query()->with(['media', 'user.media'])->find($this->estudianteId) : null;

        $matriculas = $estudiante?->matriculas()->with(['ciclo', 'grado', 'horario.curso', 'horario.docente', 'media'])->latest('fecha_matricula')->get();

        return [
            'estudiante' => $estudiante,
            'documentos' => $estudiante?->documentos()->with('media')->get(),
            'examenes' => $estudiante?->examenesUbicacion()->with('gradoAsignado')->latest('fecha')->get(),
            'matriculas' => $matriculas,
            'seccionesPorMatricula' => $matriculas?->mapWithKeys(fn (Matricula $matricula) => [$matricula->id => $this->seccionesParaMatricula($matricula, $estudiante)]) ?? collect(),
            'horariosPorMatricula' => $matriculas?->mapWithKeys(fn (Matricula $matricula) => [$matricula->id => $this->horariosParaMatricula($matricula)]) ?? collect(),
            'planesPorMatricula' => $matriculas !== null && Auth::user()->hasPermissionTo('pagos.ver')
                ? $matriculas->mapWithKeys(fn (Matricula $matricula) => [$matricula->id => $planes->planDe($matricula)])
                : collect(),
        ];
    }
}; ?>

<div>
    <x-modal name="ver-ficha" :tv="true" max-width="2xl">
        <div class="flex items-center justify-between border-b border-border px-6 py-4">
            <div>
                <h2 class="font-display text-lg text-ink">{{ $estudiante?->nombreCompleto() ?? 'Ficha del estudiante' }}</h2>
                @if ($estudiante)
                    <p class="text-sm text-ink-dim">DNI {{ $estudiante->dni }} · {{ $estudiante->es_menor_edad ? 'Menor de edad' : 'Mayor de edad' }}</p>
                @endif
            </div>
            <button type="button" x-on:click="$dispatch('close')" class="rounded-md p-1.5 text-ink-faint transition hover:bg-surface-2 hover:text-ink" aria-label="Cerrar">
                <x-heroicon-o-x-mark class="h-5 w-5" />
            </button>
        </div>

        <div class="max-h-[75vh] overflow-y-auto p-6" wire:loading.class="opacity-50">
            @if ($estudiante)
                <x-matricula.ficha-estudiante
                    :estudiante="$estudiante"
                    :documentos="$documentos"
                    :examenes="$examenes"
                    :matriculas="$matriculas"
                    :secciones-por-matricula="$seccionesPorMatricula"
                    :editando-seccion-matricula-id="$editandoSeccionMatriculaId"
                    :seccion-seleccionada="$seccionSeleccionada"
                    :horarios-por-matricula="$horariosPorMatricula"
                    :editando-horario-matricula-id="$editandoHorarioMatriculaId"
                    :horario-seleccionado="$horarioSeleccionado"
                    :planes-por-matricula="$planesPorMatricula"
                />
            @else
                <p class="py-8 text-center text-sm text-ink-faint">Cargando…</p>
            @endif
        </div>
    </x-modal>
</div>
