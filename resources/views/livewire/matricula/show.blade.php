<?php

use App\Modules\Academico\Enums\TipoPublicoEnum;
use App\Modules\Academico\Models\Horario;
use App\Modules\Matricula\Models\DocumentoEstudiante;
use App\Modules\Matricula\Models\Estudiante;
use App\Modules\Matricula\Models\Matricula;
use App\Modules\Matricula\Services\DocumentoEstudianteService;
use App\Modules\Matricula\Services\MatriculaService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public Estudiante $estudiante;

    public string $observacionesTexto = '';

    public ?int $editandoHorarioMatriculaId = null;

    public string $horarioSeleccionado = '';

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

        session()->flash('status', 'Sección actualizada.');
    }

    /**
     * Mismo criterio que wizard.blade.php: solo se listan las secciones
     * cuando el grado realmente está dividido en Grupo A/B (al menos un
     * horario del grado/ciclo declara una sección propia).
     *
     * @return Collection<int, Horario>
     */
    private function horariosParaMatricula(Matricula $matricula): Collection
    {
        $publicoEsperado = $this->estudiante->es_menor_edad ? TipoPublicoEnum::MENOR : TipoPublicoEnum::MAYOR;

        $horarios = Horario::query()
            ->where('ciclo_id', $matricula->ciclo_id)
            ->where('grado_id', $matricula->grado_id)
            ->where(fn ($query) => $query->whereNull('tipo_publico')->orWhere('tipo_publico', $publicoEsperado))
            ->with(['docente', 'dias'])
            ->get();

        if ($horarios->pluck('seccion')->filter()->isEmpty()) {
            return collect();
        }

        return $horarios->filter(fn (Horario $horario) => $horario->seccion !== null)->values();
    }

    public function with(): array
    {
        $this->estudiante->refresh();

        $matriculas = $this->estudiante->matriculas()->with(['ciclo', 'grado', 'horario.docente', 'media'])->latest('fecha_matricula')->get();

        return [
            'documentos' => $this->estudiante->documentos()->with('media')->get(),
            'examenes' => $this->estudiante->examenesUbicacion()->with('gradoAsignado')->latest('fecha')->get(),
            'matriculas' => $matriculas,
            'horariosPorMatricula' => $matriculas->mapWithKeys(fn (Matricula $matricula) => [$matricula->id => $this->horariosParaMatricula($matricula)]),
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
        :horarios-por-matricula="$horariosPorMatricula"
        :editando-horario-matricula-id="$editandoHorarioMatriculaId"
        :horario-seleccionado="$horarioSeleccionado"
    />
</div>
