<?php

use App\Modules\Academico\Models\Curso;
use App\Modules\Academico\Models\Horario;
use App\Modules\Matricula\Models\DocumentoEstudiante;
use App\Modules\Matricula\Models\Estudiante;
use App\Modules\Matricula\Models\Matricula;
use App\Modules\Matricula\Services\DocumentoEstudianteService;
use App\Modules\Matricula\Services\MatriculaService;
use App\Modules\Pagos\Models\PlanPago;
use App\Modules\Pagos\Services\PlanPagoService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public Estudiante $estudiante;

    public string $observacionesTexto = '';

    public ?int $editandoFechaFinMatriculaId = null;

    public string $fechaFinEstudioNueva = '';

    public ?int $editandoHorarioMatriculaId = null;

    public ?int $editandoHorarioCursoId = null;

    public string $horarioSeleccionado = '';

    public ?int $editandoMontoPlanId = null;

    public string $montoTotalNuevo = '';

    public function mount(Estudiante $estudiante): void
    {
        Gate::authorize('matricula.ver');

        $this->estudiante = $estudiante->load(['media', 'user.media']);
        $this->observacionesTexto = $estudiante->observaciones ?? '';
    }

    public function verificarDocumento(int $documentoId, DocumentoEstudianteService $service): void
    {
        Gate::authorize('matricula.editar');

        $documento = DocumentoEstudiante::query()->findOrFail($documentoId);
        $service->verificar($documento);

        session()->flash('status', 'Documento marcado como verificado.');
    }

    public function guardarObservaciones(): void
    {
        Gate::authorize('matricula.editar');

        $this->estudiante->update(['observaciones' => $this->observacionesTexto ?: null]);

        session()->flash('status', 'Observaciones actualizadas.');
    }

    public function editarFechaFinEstudio(int $matriculaId): void
    {
        Gate::authorize('matricula.editar');

        $matricula = Matricula::query()->findOrFail($matriculaId);

        $this->editandoFechaFinMatriculaId = $matriculaId;
        $this->fechaFinEstudioNueva = $matricula->fecha_fin_estudio?->format('Y-m-d') ?? '';
    }

    public function cancelarEdicionFechaFinEstudio(): void
    {
        $this->editandoFechaFinMatriculaId = null;
        $this->fechaFinEstudioNueva = '';
    }

    public function guardarFechaFinEstudio(MatriculaService $service): void
    {
        Gate::authorize('matricula.editar');

        if ($this->editandoFechaFinMatriculaId === null) {
            return;
        }

        $this->validate(['fechaFinEstudioNueva' => 'required|date']);

        $matricula = Matricula::query()->findOrFail($this->editandoFechaFinMatriculaId);

        $service->reasignarFechaFinEstudio($matricula, $this->fechaFinEstudioNueva);

        $this->editandoFechaFinMatriculaId = null;
        $this->fechaFinEstudioNueva = '';

        session()->flash('status', 'Fin de estudios actualizado.');
    }

    public function editarHorario(int $matriculaId, int $cursoId): void
    {
        Gate::authorize('matricula.editar');

        $matricula = Matricula::query()->findOrFail($matriculaId);
        $asignado = $matricula->horarios()->where('curso_id', $cursoId)->first();

        $this->editandoHorarioMatriculaId = $matriculaId;
        $this->editandoHorarioCursoId = $cursoId;
        $this->horarioSeleccionado = $asignado !== null ? (string) $asignado->id : '';
    }

    public function cancelarEdicionHorario(): void
    {
        $this->editandoHorarioMatriculaId = null;
        $this->editandoHorarioCursoId = null;
        $this->horarioSeleccionado = '';
    }

    public function guardarHorario(MatriculaService $service): void
    {
        Gate::authorize('matricula.editar');

        if ($this->editandoHorarioMatriculaId === null || $this->editandoHorarioCursoId === null) {
            return;
        }

        $matricula = Matricula::query()->findOrFail($this->editandoHorarioMatriculaId);

        $service->asignarHorarioDeCurso(
            $matricula,
            $this->editandoHorarioCursoId,
            $this->horarioSeleccionado !== '' ? (int) $this->horarioSeleccionado : null,
        );

        $this->editandoHorarioMatriculaId = null;
        $this->editandoHorarioCursoId = null;
        $this->horarioSeleccionado = '';

        session()->flash('status', 'Horario actualizado.');
    }

    public function editarMontoPlan(int $planId): void
    {
        Gate::authorize('pagos.gestionar');

        $plan = PlanPago::query()->findOrFail($planId);

        $this->editandoMontoPlanId = $planId;
        $this->montoTotalNuevo = (string) $plan->monto_total;
    }

    public function cancelarEdicionMontoPlan(): void
    {
        $this->editandoMontoPlanId = null;
        $this->montoTotalNuevo = '';
    }

    public function guardarMontoPlan(PlanPagoService $service): void
    {
        Gate::authorize('pagos.gestionar');

        if ($this->editandoMontoPlanId === null) {
            return;
        }

        $this->validate(['montoTotalNuevo' => 'required|numeric|min:0.01']);

        $plan = PlanPago::query()->findOrFail($this->editandoMontoPlanId);

        $service->editarMontoTotal($plan, (float) $this->montoTotalNuevo);

        $this->editandoMontoPlanId = null;
        $this->montoTotalNuevo = '';

        session()->flash('status', 'Monto del plan de pago actualizado.');
    }

    /**
     * Los cursos del grado y ciclo de esta matrícula, cada uno con sus
     * horarios disponibles (por si tiene varias secciones), cuál está
     * asignado explícitamente (si alguno) y si hace falta elegir uno
     * ("ambiguo" = el curso tiene más de una sección, ver
     * Matricula::scopeDelHorario()).
     *
     * @return Collection<int, array{curso: Curso, opciones: Collection<int, Horario>, asignado: ?Horario, ambiguo: bool}>
     */
    private function cursosConHorarios(Matricula $matricula): Collection
    {
        $horariosPorCurso = Horario::query()
            ->where('ciclo_id', $matricula->ciclo_id)
            ->where('grado_id', $matricula->grado_id)
            ->with(['curso', 'docente', 'dias'])
            ->get()
            ->groupBy('curso_id');

        $asignadosPorCurso = $matricula->horarios->keyBy('curso_id');

        return $horariosPorCurso
            ->map(fn (Collection $opciones, int $cursoId) => [
                'curso' => $opciones->first()->curso,
                'opciones' => $opciones->sortBy(fn (Horario $horario) => $horario->diasResumen())->values(),
                'asignado' => $asignadosPorCurso->get($cursoId),
                'ambiguo' => $opciones->count() > 1,
            ])
            ->sortBy(fn (array $entrada) => $entrada['curso']->nombre)
            ->values();
    }

    public function with(PlanPagoService $planes): array
    {
        $this->estudiante->refresh();

        $matriculas = $this->estudiante->matriculas()->with(['ciclo', 'grado', 'horarios.curso', 'horarios.docente', 'media'])->latest('fecha_matricula')->get();

        return [
            'documentos' => $this->estudiante->documentos()->with('media')->get(),
            'examenes' => $this->estudiante->examenesUbicacion()->with('gradoAsignado')->latest('fecha')->get(),
            'matriculas' => $matriculas,
            'cursosConHorarios' => $matriculas->mapWithKeys(fn (Matricula $matricula) => [$matricula->id => $this->cursosConHorarios($matricula)]),
            'planesPorMatricula' => Auth::user()->hasPermissionTo('pagos.ver')
                ? $matriculas->mapWithKeys(fn (Matricula $matricula) => [$matricula->id => $planes->planDe($matricula)])
                : collect(),
        ];
    }
}; ?>

<div class="max-w-3xl space-y-6">
    <x-slot name="header">
        <a href="{{ route('matricula.index') }}" wire:navigate class="text-sm text-ink-faint hover:text-ink">← Matrícula</a>
        <div class="mt-1 flex items-center gap-3">
            <h1 class="font-display text-2xl text-ink">{{ $estudiante->nombreCompleto() }}</h1>
            <span @class([
                'rounded-full px-2 py-0.5 text-xs font-medium',
                'bg-ok/10 text-ok' => $estudiante->estado->value === 'activo',
                'bg-ink-faint/10 text-ink-faint' => $estudiante->estado->value !== 'activo',
            ])>
                {{ $estudiante->estado->label() }}
            </span>
        </div>
        <p class="mt-1 text-sm text-ink-dim">DNI {{ $estudiante->dni }} · {{ $estudiante->es_menor_edad ? 'Menor de edad' : 'Mayor de edad' }}</p>
    </x-slot>

    @if (session('status'))
        <div class="rounded-md border border-ok/30 bg-ok/10 px-4 py-3 text-sm text-ok">{{ session('status') }}</div>
    @endif

    <x-matricula.ficha-estudiante
        :estudiante="$estudiante"
        :documentos="$documentos"
        :examenes="$examenes"
        :matriculas="$matriculas"
        :cursos-con-horarios="$cursosConHorarios"
        :editando-horario-matricula-id="$editandoHorarioMatriculaId"
        :editando-horario-curso-id="$editandoHorarioCursoId"
        :horario-seleccionado="$horarioSeleccionado"
        :editando-fecha-fin-matricula-id="$editandoFechaFinMatriculaId"
        :fecha-fin-estudio-nueva="$fechaFinEstudioNueva"
        :planes-por-matricula="$planesPorMatricula"
        :editando-monto-plan-id="$editandoMontoPlanId"
        :monto-total-nuevo="$montoTotalNuevo"
    />
</div>
