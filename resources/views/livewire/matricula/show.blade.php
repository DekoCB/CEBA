<?php

use App\Modules\Academico\Enums\TipoPublicoEnum;
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

    public ?int $editandoSeccionMatriculaId = null;

    public string $seccionSeleccionada = '';

    public ?int $editandoHorarioMatriculaId = null;

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

        session()->flash('status', 'Sección actualizada.');
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
     * Mismo criterio que wizard.blade.php: solo se listan las secciones
     * cuando el grado realmente está dividido en Grupo A/B (al menos un
     * horario del grado/ciclo declara una sección propia).
     *
     * @return Collection<int, string>
     */
    private function seccionesParaMatricula(Matricula $matricula): Collection
    {
        $publicoEsperado = $this->estudiante->es_menor_edad ? TipoPublicoEnum::MENOR : TipoPublicoEnum::MAYOR;

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
     * Todos los horarios (cualquier curso) del grado y ciclo de la
     * matrícula, sin filtrar por sección ni por edad: a diferencia de
     * seccionesParaMatricula(), esto alimenta "Editar horario", que es
     * independiente de a qué sección pertenece el estudiante.
     *
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
        $this->estudiante->refresh();

        $matriculas = $this->estudiante->matriculas()->with(['ciclo', 'grado', 'horario.curso', 'horario.docente', 'media'])->latest('fecha_matricula')->get();

        return [
            'documentos' => $this->estudiante->documentos()->with('media')->get(),
            'examenes' => $this->estudiante->examenesUbicacion()->with('gradoAsignado')->latest('fecha')->get(),
            'matriculas' => $matriculas,
            'seccionesPorMatricula' => $matriculas->mapWithKeys(fn (Matricula $matricula) => [$matricula->id => $this->seccionesParaMatricula($matricula)]),
            'horariosPorMatricula' => $matriculas->mapWithKeys(fn (Matricula $matricula) => [$matricula->id => $this->horariosParaMatricula($matricula)]),
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
        :secciones-por-matricula="$seccionesPorMatricula"
        :editando-seccion-matricula-id="$editandoSeccionMatriculaId"
        :seccion-seleccionada="$seccionSeleccionada"
        :horarios-por-matricula="$horariosPorMatricula"
        :editando-horario-matricula-id="$editandoHorarioMatriculaId"
        :horario-seleccionado="$horarioSeleccionado"
        :planes-por-matricula="$planesPorMatricula"
        :editando-monto-plan-id="$editandoMontoPlanId"
        :monto-total-nuevo="$montoTotalNuevo"
    />
</div>
