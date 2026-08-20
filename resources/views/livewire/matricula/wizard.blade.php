<?php

use App\Modules\Academico\Enums\TipoPublicoEnum;
use App\Modules\Academico\Models\Ciclo;
use App\Modules\Academico\Models\Grado;
use App\Modules\Academico\Models\Horario;
use App\Modules\Matricula\DTOs\RegistrarApoderadoData;
use App\Modules\Matricula\DTOs\RegistrarEstudianteData;
use App\Modules\Matricula\DTOs\RegistrarMatriculaData;
use App\Modules\Matricula\Enums\EstadoCivilEnum;
use App\Modules\Matricula\Enums\TipoDocumentoEnum;
use App\Modules\Matricula\Services\DocumentoEstudianteService;
use App\Modules\Matricula\Services\ExamenUbicacionService;
use App\Modules\Matricula\Services\MatriculaService;
use App\Modules\Pagos\Enums\NumeroCuotasEnum;
use App\Modules\Pagos\Services\PlanPagoService;
use App\Shared\ValueObjects\Dni;
use App\Shared\ValueObjects\Telefono;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new class extends Component
{
    use WithFileUploads;

    public int $paso = 1;

    // Paso 1 — estudiante
    public string $nombres = '';

    public string $apellidos = '';

    public string $dni = '';

    public string $fechaNacimiento = '';

    public string $estadoCivil = '';

    public string $direccion = '';

    public string $celular = '';

    public string $observacionesEstudiante = '';

    public $foto = null;

    // Paso 2 — apoderado (condicional)
    public string $apoderadoNombres = '';

    public string $apoderadoDni = '';

    public string $apoderadoCelular = '';

    public string $apoderadoCorreo = '';

    public string $apoderadoDireccion = '';

    public string $apoderadoParentesco = '';

    // Paso 3 — documentos e institución de procedencia
    public $dniEstudianteArchivo = null;

    public $dniApoderadoArchivo = null;

    public $certificadoArchivo = null;

    public $constanciaArchivo = null;

    public string $colegioNombre = '';

    public string $colegioUbicacion = '';

    public string $colegioAnioEgreso = '';

    // Paso 4 — examen de ubicación (opcional)
    public bool $registrarExamen = false;

    public string $examenFecha = '';

    public string $examenCosto = '';

    public string $examenResultado = '';

    public string $examenGradoAsignadoId = '';

    public string $examenObservaciones = '';

    // Paso 5 — matrícula
    public string $cicloId = '';

    public string $gradoId = '';

    public string $horarioId = '';

    public string $observacionesMatricula = '';

    // Paso 6 — cronograma de pagos (opcional)
    public bool $configurarCronograma = false;

    public string $numeroCuotasCronograma = '6';

    public string $montoTotalCronograma = '';

    /** @var array<int, string> */
    public array $cuotaMontos = [];

    /** @var array<int, string> */
    public array $cuotaFechas = [];

    public function mount(): void
    {
        Gate::authorize('matricula.crear');
    }

    public function updatedGradoId(): void
    {
        $this->horarioId = '';
    }

    public function updatedCicloId(): void
    {
        $this->horarioId = '';
    }

    /**
     * Solo las secciones (Grupo A/B) reales del grado, compatibles con la
     * edad del estudiante: los horarios que declaran una sección propia y
     * no declaran público (sin restricción) o coinciden con el suyo. Si el
     * grado no está realmente dividido en secciones -- aunque tenga varios
     * horarios, uno por cada curso -- no hay nada que elegir y se devuelve
     * vacío, igual que MatriculaService::resolverHorario() decide cuándo
     * pedir sección.
     *
     * @return Collection<int, Horario>
     */
    #[Computed]
    public function horariosDelGrado()
    {
        if ($this->cicloId === '' || $this->gradoId === '') {
            return collect();
        }

        $publicoEsperado = $this->esMenorDeEdad ? TipoPublicoEnum::MENOR : TipoPublicoEnum::MAYOR;

        $horarios = Horario::query()
            ->where('ciclo_id', (int) $this->cicloId)
            ->where('grado_id', (int) $this->gradoId)
            ->where(fn ($query) => $query->whereNull('tipo_publico')->orWhere('tipo_publico', $publicoEsperado))
            ->with(['docente', 'aula', 'dias'])
            ->get();

        if ($horarios->pluck('seccion')->filter()->isEmpty()) {
            return collect();
        }

        // Un grado dividido puede tener varios cursos, cada uno con su
        // propio par de horarios A/B: no se listan todas esas filas (se
        // verían "Sección A" repetida una vez por curso), solo una por
        // letra de sección -- cuál curso queda como representante no
        // importa, el horario_id elegido solo funciona como etiqueta de a
        // qué grupo pertenece el estudiante (ver Matricula::scopeDelHorario()).
        return $horarios->filter(fn (Horario $horario) => $horario->seccion !== null)
            ->unique('seccion')
            ->sortBy('seccion')
            ->values();
    }

    #[Computed]
    public function esMenorDeEdad(): bool
    {
        if ($this->fechaNacimiento === '') {
            return false;
        }

        return MatriculaService::esMenorDeEdad($this->fechaNacimiento);
    }

    public function avanzar(MatriculaService $service): void
    {
        if ($this->paso === 1) {
            $this->validate([
                'nombres' => 'required|string|max:100',
                'apellidos' => 'required|string|max:100',
                'dni' => 'required|string|min:8|max:12',
                'fechaNacimiento' => 'required|date|before:today',
                'estadoCivil' => 'nullable|string|in:'.implode(',', array_column(EstadoCivilEnum::cases(), 'value')),
                'direccion' => 'nullable|string|max:150',
                'celular' => 'nullable|string',
            ]);

            if (! $service->dniDisponible($this->dni)) {
                $this->addError('dni', 'Ya existe un estudiante registrado con este DNI.');

                return;
            }

            $this->paso = $this->esMenorDeEdad ? 2 : 3;

            return;
        }

        if ($this->paso === 2) {
            $this->validate([
                'apoderadoNombres' => 'required|string|max:150',
                'apoderadoDni' => 'required|string|min:8|max:12',
                'apoderadoCelular' => 'required|string',
                'apoderadoCorreo' => 'nullable|email|max:150',
                'apoderadoDireccion' => 'nullable|string|max:150',
                'apoderadoParentesco' => 'required|string|max:50',
            ]);

            $this->paso = 3;

            return;
        }

        if ($this->paso === 3) {
            $this->validate([
                'dniEstudianteArchivo' => 'required|file|mimes:pdf,jpg,jpeg,png|max:4096',
                'dniApoderadoArchivo' => $this->esMenorDeEdad ? 'required|file|mimes:pdf,jpg,jpeg,png|max:4096' : 'nullable|file|mimes:pdf,jpg,jpeg,png|max:4096',
                'certificadoArchivo' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:4096',
                'constanciaArchivo' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:4096',
                'colegioNombre' => 'nullable|string|max:150',
                'colegioUbicacion' => 'nullable|string|max:150',
                'colegioAnioEgreso' => 'nullable|integer|min:1950|max:'.now()->year,
            ]);

            $this->paso = 4;

            return;
        }

        if ($this->paso === 4) {
            if ($this->registrarExamen) {
                $this->validate([
                    'examenFecha' => 'required|date',
                    'examenCosto' => 'required|numeric|min:0',
                    'examenResultado' => 'nullable|string|max:30',
                    'examenGradoAsignadoId' => 'nullable|integer|exists:grados,id',
                    'examenObservaciones' => 'nullable|string',
                ]);
            }

            $this->paso = 5;

            return;
        }

        if ($this->paso === 5) {
            $this->validate([
                'cicloId' => 'required|integer|exists:ciclos,id',
                'gradoId' => 'required|integer|exists:grados,id',
                'horarioId' => 'nullable|integer|exists:horarios,id',
            ]);

            $this->paso = 6;
        }
    }

    /**
     * Reparte montoTotalCronograma en partes iguales entre las cuotas
     * elegidas (la última absorbe el redondeo), con vencimientos mensuales
     * desde hoy como aproximación -- la fecha real de matrícula recién se
     * fija al confirmar. Es solo el punto de partida: cada monto y fecha
     * queda editable después para ajustarlo caso por caso.
     */
    public function generarCronogramaAutomatico(): void
    {
        $this->validate([
            'numeroCuotasCronograma' => ['required', Rule::in(array_column(NumeroCuotasEnum::cases(), 'value'))],
            'montoTotalCronograma' => 'required|numeric|min:1',
        ]);

        $numero = (int) $this->numeroCuotasCronograma;
        $total = (float) $this->montoTotalCronograma;
        $montoPorCuota = round($total / $numero, 2);
        $montoAcumulado = 0.0;

        $this->cuotaMontos = [];
        $this->cuotaFechas = [];

        for ($i = 1; $i <= $numero; $i++) {
            $esUltima = $i === $numero;
            $monto = $esUltima ? round($total - $montoAcumulado, 2) : $montoPorCuota;
            $montoAcumulado += $monto;

            $this->cuotaMontos[$i] = number_format($monto, 2, '.', '');
            $this->cuotaFechas[$i] = now()->addMonths($i)->format('Y-m-d');
        }
    }

    public function retroceder(): void
    {
        if ($this->paso === 3 && ! $this->esMenorDeEdad) {
            $this->paso = 1;

            return;
        }

        $this->paso = max(1, $this->paso - 1);
    }

    public function cancelar(): void
    {
        $this->dispatch('wizard-cerrado');
    }

    public function confirmar(
        MatriculaService $matriculaService,
        DocumentoEstudianteService $documentoService,
        ExamenUbicacionService $examenService,
        PlanPagoService $planPagoService,
    ): void {
        Gate::authorize('matricula.crear');

        if ($this->configurarCronograma) {
            $this->validate([
                'numeroCuotasCronograma' => ['required', Rule::in(array_column(NumeroCuotasEnum::cases(), 'value'))],
                'cuotaMontos' => 'required|array|min:1',
                'cuotaMontos.*' => 'required|numeric|min:0.01',
                'cuotaFechas' => 'required|array|min:1',
                'cuotaFechas.*' => 'required|date',
            ]);

            if (count($this->cuotaMontos) !== (int) $this->numeroCuotasCronograma) {
                $this->addError('numeroCuotasCronograma', 'Genera el cronograma automático antes de confirmar la matrícula.');

                return;
            }
        }

        // Todo el registro (estudiante, apoderado, documentos, examen y
        // matrícula) va en una sola transacción: si matricular() falla al
        // final (grado incoherente con la edad, periodo cerrado, sección
        // faltante, etc.), el estudiante recién creado NO debe quedar
        // huérfano en la base de datos -- si quedara, un reintento desde
        // este mismo paso 5 (sin volver al paso 1) chocaría con el DNI ya
        // registrado y rompería con un error sin manejar.
        $estudiante = DB::transaction(function () use ($matriculaService, $documentoService, $examenService, $planPagoService) {
            $estudiante = $matriculaService->registrarEstudiante(new RegistrarEstudianteData(
                nombres: $this->nombres,
                apellidos: $this->apellidos,
                dni: new Dni($this->dni),
                fechaNacimiento: $this->fechaNacimiento,
                estadoCivil: $this->estadoCivil !== '' ? EstadoCivilEnum::from($this->estadoCivil) : null,
                direccion: $this->direccion ?: null,
                celular: $this->celular !== '' ? new Telefono($this->celular) : null,
                observaciones: $this->observacionesEstudiante ?: null,
            ));

            if ($this->foto) {
                $estudiante->addMedia($this->foto->getRealPath())
                    ->usingFileName('foto-'.$estudiante->id.'.'.$this->foto->getClientOriginalExtension())
                    ->preservingOriginal()
                    ->toMediaCollection('foto');
            }

            if ($this->esMenorDeEdad) {
                $matriculaService->registrarApoderado($estudiante, new RegistrarApoderadoData(
                    nombres: $this->apoderadoNombres,
                    dni: new Dni($this->apoderadoDni),
                    celular: new Telefono($this->apoderadoCelular),
                    correo: $this->apoderadoCorreo ?: null,
                    direccion: $this->apoderadoDireccion ?: null,
                    parentesco: $this->apoderadoParentesco,
                ));
            }

            if ($this->colegioNombre !== '') {
                $matriculaService->registrarInstitucionProcedencia($estudiante, [
                    'nombre_colegio' => $this->colegioNombre,
                    'ubicacion' => $this->colegioUbicacion ?: null,
                    'anio_egreso' => $this->colegioAnioEgreso !== '' ? (int) $this->colegioAnioEgreso : null,
                ]);
            }

            if ($this->dniEstudianteArchivo) {
                $documentoService->subir($estudiante, TipoDocumentoEnum::DNI_ESTUDIANTE, $this->dniEstudianteArchivo, auth()->id());
            }
            if ($this->dniApoderadoArchivo) {
                $documentoService->subir($estudiante, TipoDocumentoEnum::DNI_APODERADO, $this->dniApoderadoArchivo, auth()->id());
            }
            if ($this->certificadoArchivo) {
                $documentoService->subir($estudiante, TipoDocumentoEnum::CERTIFICADO_ESTUDIOS, $this->certificadoArchivo, auth()->id());
            }
            if ($this->constanciaArchivo) {
                $documentoService->subir($estudiante, TipoDocumentoEnum::CONSTANCIA, $this->constanciaArchivo, auth()->id());
            }

            if ($this->registrarExamen) {
                $examenService->registrar($estudiante, [
                    'fecha' => $this->examenFecha,
                    'costo' => (float) $this->examenCosto,
                    'resultado' => $this->examenResultado ?: null,
                    'grado_asignado_id' => $this->examenGradoAsignadoId !== '' ? (int) $this->examenGradoAsignadoId : null,
                    'observaciones' => $this->examenObservaciones ?: null,
                ]);
            }

            $matricula = $matriculaService->matricular($estudiante, new RegistrarMatriculaData(
                cicloId: (int) $this->cicloId,
                gradoId: (int) $this->gradoId,
                horarioId: $this->horarioId !== '' ? (int) $this->horarioId : null,
                observaciones: $this->observacionesMatricula ?: null,
                registradoPor: auth()->id(),
            ));

            if ($this->configurarCronograma) {
                $cuotas = collect($this->cuotaMontos)
                    ->map(fn ($monto, $numero) => [
                        'monto' => (float) $monto,
                        'fecha_vencimiento' => $this->cuotaFechas[$numero],
                    ])
                    ->values()
                    ->all();

                $planPagoService->crear(
                    $matricula,
                    NumeroCuotasEnum::from((int) $this->numeroCuotasCronograma),
                    array_sum(array_column($cuotas, 'monto')),
                    $cuotas,
                );
            }

            return $estudiante;
        });

        $this->dispatch('matricula-registrada', estudianteId: $estudiante->id, nombre: $estudiante->nombreCompleto());
    }

    public function with(): array
    {
        $grados = Grado::query()->where('activo', true)->orderBy('orden')->get();

        $ciclosConMatriculaAbierta = Ciclo::query()
            ->whereHas('periodosMatricula', function ($query) {
                $query->where('estado', 'abierto')
                    ->where('fecha_inicio', '<=', now())
                    ->where('fecha_fin', '>=', now());
            })
            ->orderByDesc('fecha_inicio')
            ->get();

        return [
            'estadosCiviles' => EstadoCivilEnum::cases(),
            'gradosCompatibles' => $grados,
            'todosLosGrados' => $grados,
            'ciclosDisponibles' => $ciclosConMatriculaAbierta,
            'numerosCuotas' => NumeroCuotasEnum::cases(),
        ];
    }
}; ?>

<div
    x-data="{ mostrar: false }"
    x-init="$nextTick(() => mostrar = true)"
    x-show="mostrar"
    x-cloak
    class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-ink/40 px-4 py-8"
    x-on:click.self="mostrar = false; setTimeout(() => $wire.cancelar(), 200)"
    x-transition:enter="ease-out duration-300"
    x-transition:enter-start="opacity-0"
    x-transition:enter-end="opacity-100"
    x-transition:leave="ease-in duration-200"
    x-transition:leave-start="opacity-100"
    x-transition:leave-end="opacity-0"
>
    <div
        x-show="mostrar"
        class="w-full max-w-3xl rounded-lg border border-border bg-surface p-6 shadow-lg"
        x-transition:enter="ease-out duration-300"
        x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
        x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
        x-transition:leave="ease-in duration-200"
        x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
        x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
    >
        <div class="flex items-start justify-between gap-4">
            <div>
                <h1 class="font-display text-2xl text-ink">Nueva matrícula</h1>
                <p class="mt-1 text-sm text-ink-dim">Paso {{ $paso }} de 6</p>
            </div>
            <button
                type="button"
                x-on:click="mostrar = false; setTimeout(() => $wire.cancelar(), 200)"
                class="rounded-md p-1 text-ink-faint hover:bg-surface-2 hover:text-ink"
                aria-label="Cerrar"
            >
                <x-heroicon-o-x-mark class="h-5 w-5" />
            </button>
        </div>

        <div class="mb-6 mt-4 flex gap-1">
            @for ($i = 1; $i <= 6; $i++)
                <div @class(['h-1.5 flex-1 rounded-full', 'bg-accent' => $i <= $paso, 'bg-surface-2' => $i > $paso])></div>
            @endfor
        </div>

        <div>
        {{-- Paso 1: Estudiante --}}
        @if ($paso === 1)
            <h2 class="font-display text-lg text-ink">Datos del estudiante</h2>
            <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <x-input-label for="nombres" value="Nombres" />
                    <x-text-input wire:model="nombres" id="nombres" class="mt-1 block w-full" />
                    <x-input-error :messages="$errors->get('nombres')" class="mt-1" />
                </div>
                <div>
                    <x-input-label for="apellidos" value="Apellidos" />
                    <x-text-input wire:model="apellidos" id="apellidos" class="mt-1 block w-full" />
                    <x-input-error :messages="$errors->get('apellidos')" class="mt-1" />
                </div>
                <div>
                    <x-input-label for="dni" value="DNI" />
                    <x-text-input wire:model.live="dni" id="dni" class="mt-1 block w-full" />
                    <x-input-error :messages="$errors->get('dni')" class="mt-1" />
                </div>
                <div>
                    <x-input-label for="fechaNacimiento" value="Fecha de nacimiento" />
                    <x-date-input wire:model.live="fechaNacimiento" id="fechaNacimiento" class="mt-1 block w-full" />
                    <x-input-error :messages="$errors->get('fechaNacimiento')" class="mt-1" />
                    @if ($fechaNacimiento)
                        <p class="mt-1 text-xs text-ink-faint">{{ $this->esMenorDeEdad ? 'Menor de edad — se pedirán datos del apoderado.' : 'Mayor de edad.' }}</p>
                    @endif
                </div>
                <div>
                    <x-input-label for="estadoCivil" value="Estado civil (opcional)" />
                    <x-select-input
                        wire:model="estadoCivil"
                        id="estadoCivil"
                        class="mt-1 block w-full"
                        :options="collect($estadosCiviles)->mapWithKeys(fn ($opcion) => [$opcion->value => $opcion->label()])->prepend('Sin especificar', '')"
                    />
                </div>
                <div>
                    <x-input-label for="celular" value="Celular" />
                    <x-text-input wire:model="celular" id="celular" class="mt-1 block w-full" />
                    <x-input-error :messages="$errors->get('celular')" class="mt-1" />
                </div>
                <div class="sm:col-span-2">
                    <x-input-label for="direccion" value="Dirección" />
                    <x-text-input wire:model="direccion" id="direccion" class="mt-1 block w-full" />
                    <x-input-error :messages="$errors->get('direccion')" class="mt-1" />
                </div>
                <div class="sm:col-span-2">
                    <x-input-label value="Correo" />
                    <div class="mt-1 rounded-md border border-border bg-surface-2 px-3 py-2 font-mono text-sm text-ink-dim">
                        {{ trim($dni) !== '' ? strtolower(trim($dni)).'@ceba.test' : '—' }}
                    </div>
                    <p class="mt-1 text-xs text-ink-faint">Se asigna automáticamente a partir del DNI, junto con su cuenta de acceso al sistema.</p>
                </div>
                <div class="sm:col-span-2">
                    <x-input-label for="foto" value="Fotografía (opcional)" />
                    <input wire:model="foto" id="foto" type="file" accept="image/*" class="mt-1 block w-full text-sm text-ink-dim file:mr-3 file:rounded-md file:border-0 file:bg-surface-2 file:px-3 file:py-2 file:text-sm file:text-ink">
                    <x-input-error :messages="$errors->get('foto')" class="mt-1" />
                </div>
                <div class="sm:col-span-2">
                    <x-input-label for="observacionesEstudiante" value="Observaciones (opcional)" />
                    <p class="mt-1 text-xs text-ink-faint">Acuerdos especiales, documentos pendientes de entregar, casos particulares (ej. examen de ubicación).</p>
                    <textarea wire:model="observacionesEstudiante" id="observacionesEstudiante" rows="2" class="mt-1 block w-full rounded-md border-border bg-surface text-sm text-ink focus:border-accent focus:ring-accent"></textarea>
                    <x-input-error :messages="$errors->get('observacionesEstudiante')" class="mt-1" />
                </div>
            </div>
        @endif

        {{-- Paso 2: Apoderado --}}
        @if ($paso === 2)
            <h2 class="font-display text-lg text-ink">Datos del apoderado</h2>
            <p class="mt-1 text-sm text-ink-dim">El estudiante es menor de edad, así que necesitamos los datos de su apoderado.</p>
            <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <x-input-label for="apoderadoNombres" value="Nombres completos" />
                    <x-text-input wire:model="apoderadoNombres" id="apoderadoNombres" class="mt-1 block w-full" />
                    <x-input-error :messages="$errors->get('apoderadoNombres')" class="mt-1" />
                </div>
                <div>
                    <x-input-label for="apoderadoDni" value="DNI" />
                    <x-text-input wire:model="apoderadoDni" id="apoderadoDni" class="mt-1 block w-full" />
                    <x-input-error :messages="$errors->get('apoderadoDni')" class="mt-1" />
                </div>
                <div>
                    <x-input-label for="apoderadoParentesco" value="Parentesco" />
                    <x-text-input wire:model="apoderadoParentesco" id="apoderadoParentesco" class="mt-1 block w-full" placeholder="Madre, padre, tío…" />
                    <x-input-error :messages="$errors->get('apoderadoParentesco')" class="mt-1" />
                </div>
                <div>
                    <x-input-label for="apoderadoCelular" value="Celular" />
                    <x-text-input wire:model="apoderadoCelular" id="apoderadoCelular" class="mt-1 block w-full" />
                    <x-input-error :messages="$errors->get('apoderadoCelular')" class="mt-1" />
                </div>
                <div>
                    <x-input-label for="apoderadoCorreo" value="Correo (opcional)" />
                    <x-text-input wire:model="apoderadoCorreo" id="apoderadoCorreo" type="email" class="mt-1 block w-full" />
                    <x-input-error :messages="$errors->get('apoderadoCorreo')" class="mt-1" />
                </div>
                <div class="sm:col-span-2">
                    <x-input-label for="apoderadoDireccion" value="Dirección (opcional)" />
                    <x-text-input wire:model="apoderadoDireccion" id="apoderadoDireccion" class="mt-1 block w-full" />
                    <x-input-error :messages="$errors->get('apoderadoDireccion')" class="mt-1" />
                </div>
            </div>
        @endif

        {{-- Paso 3: Documentos e institución de procedencia --}}
        @if ($paso === 3)
            <h2 class="font-display text-lg text-ink">Documentos e institución de procedencia</h2>
            <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <x-input-label for="dniEstudianteArchivo" value="DNI del estudiante" />
                    <input wire:model="dniEstudianteArchivo" id="dniEstudianteArchivo" type="file" accept=".pdf,image/*" class="mt-1 block w-full text-sm text-ink-dim file:mr-3 file:rounded-md file:border-0 file:bg-surface-2 file:px-3 file:py-2 file:text-sm file:text-ink">
                    <x-input-error :messages="$errors->get('dniEstudianteArchivo')" class="mt-1" />
                </div>
                @if ($this->esMenorDeEdad)
                    <div>
                        <x-input-label for="dniApoderadoArchivo" value="DNI del apoderado" />
                        <input wire:model="dniApoderadoArchivo" id="dniApoderadoArchivo" type="file" accept=".pdf,image/*" class="mt-1 block w-full text-sm text-ink-dim file:mr-3 file:rounded-md file:border-0 file:bg-surface-2 file:px-3 file:py-2 file:text-sm file:text-ink">
                        <x-input-error :messages="$errors->get('dniApoderadoArchivo')" class="mt-1" />
                    </div>
                @endif
                <div>
                    <x-input-label for="certificadoArchivo" value="Certificado de estudios (opcional)" />
                    <input wire:model="certificadoArchivo" id="certificadoArchivo" type="file" accept=".pdf,image/*" class="mt-1 block w-full text-sm text-ink-dim file:mr-3 file:rounded-md file:border-0 file:bg-surface-2 file:px-3 file:py-2 file:text-sm file:text-ink">
                    <x-input-error :messages="$errors->get('certificadoArchivo')" class="mt-1" />
                </div>
                <div>
                    <x-input-label for="constanciaArchivo" value="Constancia (opcional)" />
                    <input wire:model="constanciaArchivo" id="constanciaArchivo" type="file" accept=".pdf,image/*" class="mt-1 block w-full text-sm text-ink-dim file:mr-3 file:rounded-md file:border-0 file:bg-surface-2 file:px-3 file:py-2 file:text-sm file:text-ink">
                    <x-input-error :messages="$errors->get('constanciaArchivo')" class="mt-1" />
                </div>
            </div>

            <h3 class="mt-6 text-sm font-semibold text-ink">Institución de procedencia (opcional)</h3>
            <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <x-input-label for="colegioNombre" value="Colegio anterior" />
                    <x-text-input wire:model="colegioNombre" id="colegioNombre" class="mt-1 block w-full" />
                    <x-input-error :messages="$errors->get('colegioNombre')" class="mt-1" />
                </div>
                <div>
                    <x-input-label for="colegioUbicacion" value="Ubicación" />
                    <x-text-input wire:model="colegioUbicacion" id="colegioUbicacion" class="mt-1 block w-full" />
                    <x-input-error :messages="$errors->get('colegioUbicacion')" class="mt-1" />
                </div>
                <div>
                    <x-input-label for="colegioAnioEgreso" value="Año de egreso" />
                    <x-text-input wire:model="colegioAnioEgreso" id="colegioAnioEgreso" type="number" class="mt-1 block w-full" />
                    <x-input-error :messages="$errors->get('colegioAnioEgreso')" class="mt-1" />
                </div>
            </div>
        @endif

        {{-- Paso 4: Examen de ubicación --}}
        @if ($paso === 4)
            <h2 class="font-display text-lg text-ink">Examen de ubicación</h2>
            <label class="mt-4 flex items-center gap-2 text-sm text-ink-dim">
                <input type="checkbox" wire:model.live="registrarExamen" class="rounded border-border text-accent focus:ring-accent">
                Este estudiante rindió examen de ubicación
            </label>

            @if ($registrarExamen)
                <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <x-input-label for="examenFecha" value="Fecha" />
                        <x-date-input wire:model="examenFecha" id="examenFecha" class="mt-1 block w-full" />
                        <x-input-error :messages="$errors->get('examenFecha')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label for="examenCosto" value="Costo (S/)" />
                        <x-text-input wire:model="examenCosto" id="examenCosto" type="number" step="0.01" class="mt-1 block w-full" />
                        <x-input-error :messages="$errors->get('examenCosto')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label for="examenResultado" value="Resultado" />
                        <x-text-input wire:model="examenResultado" id="examenResultado" class="mt-1 block w-full" placeholder="Ej. Apto" />
                        <x-input-error :messages="$errors->get('examenResultado')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label for="examenGradoAsignadoId" value="Grado asignado" />
                        <x-select-input
                            wire:model="examenGradoAsignadoId"
                            id="examenGradoAsignadoId"
                            class="mt-1 block w-full"
                            :options="collect($todosLosGrados)->mapWithKeys(fn ($grado) => [$grado->id => $grado->nombre])->prepend('Sin asignar', '')"
                        />
                    </div>
                    <div class="sm:col-span-2">
                        <x-input-label for="examenObservaciones" value="Observaciones" />
                        <textarea wire:model="examenObservaciones" id="examenObservaciones" rows="2" class="mt-1 block w-full rounded-md border-border bg-surface text-sm text-ink focus:border-accent focus:ring-accent"></textarea>
                    </div>
                </div>
            @endif
        @endif

        {{-- Paso 5: Matrícula --}}
        @if ($paso === 5)
            <h2 class="font-display text-lg text-ink">Matrícula</h2>
            <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <x-input-label for="cicloId" value="Ciclo" />
                    <x-select-input
                        wire:model.live="cicloId"
                        id="cicloId"
                        class="mt-1 block w-full"
                        :options="collect($ciclosDisponibles)->mapWithKeys(fn ($ciclo) => [$ciclo->id => $ciclo->nombre])"
                    />
                    @if ($ciclosDisponibles->isEmpty())
                        <p class="mt-1 text-xs text-danger">No hay ciclos con periodo de matrícula abierto hoy.</p>
                    @endif
                    <x-input-error :messages="$errors->get('cicloId')" class="mt-1" />
                </div>
                <div>
                    <x-input-label for="gradoId" value="Grado" />
                    <x-select-input
                        wire:model.live="gradoId"
                        id="gradoId"
                        class="mt-1 block w-full"
                        :options="collect($gradosCompatibles)->mapWithKeys(fn ($grado) => [$grado->id => $grado->nombre])"
                    />
                    <x-input-error :messages="$errors->get('gradoId')" class="mt-1" />
                </div>
                @if ($this->horariosDelGrado->count() > 1)
                    <div class="sm:col-span-2">
                        <x-input-label for="horarioId" value="Sección" />
                        <x-select-input
                            wire:model="horarioId"
                            id="horarioId"
                            class="mt-1 block w-full"
                            :options="collect($this->horariosDelGrado)->mapWithKeys(fn ($horario) => [$horario->id => 'Sección '.$horario->seccion.' · '.$horario->diasResumen()])"
                        />
                        <p class="mt-1 text-xs text-ink-faint">Este grado tiene más de una sección en el ciclo elegido: selecciona a cuál se matricula el estudiante.</p>
                        <x-input-error :messages="$errors->get('horarioId')" class="mt-1" />
                        <x-input-error :messages="$errors->get('horario')" class="mt-1" />
                    </div>
                @endif
                <div class="sm:col-span-2">
                    <x-input-label for="observacionesMatricula" value="Observaciones (opcional)" />
                    <textarea wire:model="observacionesMatricula" id="observacionesMatricula" rows="2" class="mt-1 block w-full rounded-md border-border bg-surface text-sm text-ink focus:border-accent focus:ring-accent"></textarea>
                </div>
            </div>
        @endif

        {{-- Paso 6: Cronograma de pagos --}}
        @if ($paso === 6)
            <h2 class="font-display text-lg text-ink">Cronograma de pagos</h2>
            <label class="mt-4 flex items-center gap-2 text-sm text-ink-dim">
                <input type="checkbox" wire:model.live="configurarCronograma" class="rounded border-border text-accent focus:ring-accent">
                Configurar el plan de pagos ahora
            </label>
            <p class="mt-1 text-xs text-ink-faint">Si lo dejas sin marcar, se puede armar después desde Pagos y Cobranza.</p>

            @if ($configurarCronograma)
                <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <div>
                        <x-input-label for="numeroCuotasCronograma" value="Número de cuotas" />
                        <x-select-input
                            wire:model="numeroCuotasCronograma"
                            id="numeroCuotasCronograma"
                            class="mt-1 block w-full"
                            :options="collect($numerosCuotas)->mapWithKeys(fn ($opcion) => [(string) $opcion->value => $opcion->label()])"
                        />
                        <x-input-error :messages="$errors->get('numeroCuotasCronograma')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label for="montoTotalCronograma" value="Monto total (S/)" />
                        <x-text-input wire:model="montoTotalCronograma" id="montoTotalCronograma" type="number" step="0.01" min="0" class="mt-1 block w-full" />
                        <x-input-error :messages="$errors->get('montoTotalCronograma')" class="mt-1" />
                    </div>
                    <div class="flex items-end">
                        <x-secondary-button type="button" wire:click="generarCronogramaAutomatico" class="w-full justify-center">
                            Generar cronograma
                        </x-secondary-button>
                    </div>
                </div>

                @if (count($cuotaMontos) > 0)
                    <div class="mt-4 overflow-hidden rounded-lg border border-border">
                        <table class="min-w-full divide-y divide-border text-sm">
                            <thead class="bg-surface-2">
                                <tr>
                                    <th class="px-4 py-2 text-left font-mono text-xs uppercase tracking-wide text-ink-faint">Cuota</th>
                                    <th class="px-4 py-2 text-left font-mono text-xs uppercase tracking-wide text-ink-faint">Monto (S/)</th>
                                    <th class="px-4 py-2 text-left font-mono text-xs uppercase tracking-wide text-ink-faint">Vencimiento</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border">
                                @foreach ($cuotaMontos as $numero => $monto)
                                    <tr wire:key="cuota-{{ $numero }}">
                                        <td class="px-4 py-2 text-ink-dim">{{ $numero }}</td>
                                        <td class="px-4 py-2">
                                            <input type="number" step="0.01" min="0" wire:model="cuotaMontos.{{ $numero }}" class="w-28 rounded-md border-border bg-surface text-sm text-ink focus:border-accent focus:ring-accent">
                                        </td>
                                        <td class="px-4 py-2">
                                            <x-date-input wire:model="cuotaFechas.{{ $numero }}" class="w-40" />
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <p class="mt-2 text-xs text-ink-faint">Total del cronograma: S/ {{ number_format(array_sum(array_map('floatval', $cuotaMontos)), 2) }}</p>
                    <x-input-error :messages="$errors->get('cuotaMontos')" class="mt-1" />
                    <x-input-error :messages="$errors->get('cuotaMontos.*')" class="mt-1" />
                    <x-input-error :messages="$errors->get('cuotaFechas.*')" class="mt-1" />
                @endif
            @endif
        @endif

        <div class="mt-6 flex justify-between border-t border-border pt-4">
            @if ($paso > 1)
                <x-secondary-button type="button" wire:click="retroceder">Atrás</x-secondary-button>
            @else
                <span></span>
            @endif

            @if ($paso < 6)
                <x-primary-button type="button" wire:click="avanzar">Continuar</x-primary-button>
            @else
                <x-primary-button type="button" wire:click="confirmar">Confirmar matrícula</x-primary-button>
            @endif
        </div>
        </div>
    </div>
</div>
