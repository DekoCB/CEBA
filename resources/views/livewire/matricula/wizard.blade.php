<?php

use App\Modules\Academico\Enums\TipoPublicoEnum;
use App\Modules\Academico\Models\Ciclo;
use App\Modules\Academico\Models\Grado;
use App\Modules\Matricula\DTOs\RegistrarApoderadoData;
use App\Modules\Matricula\DTOs\RegistrarEstudianteData;
use App\Modules\Matricula\DTOs\RegistrarMatriculaData;
use App\Modules\Matricula\Enums\EstadoCivilEnum;
use App\Modules\Matricula\Enums\TipoDocumentoEnum;
use App\Modules\Matricula\Services\DocumentoEstudianteService;
use App\Modules\Matricula\Services\ExamenUbicacionService;
use App\Modules\Matricula\Services\MatriculaService;
use App\Shared\ValueObjects\Dni;
use App\Shared\ValueObjects\Telefono;
use Illuminate\Support\Facades\Gate;
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

    public string $email = '';

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

    public string $observacionesMatricula = '';

    public function mount(): void
    {
        Gate::authorize('matricula.crear');
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
                'email' => 'nullable|email|max:150',
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
    ): void {
        Gate::authorize('matricula.crear');

        $this->validate([
            'cicloId' => 'required|integer|exists:ciclos,id',
            'gradoId' => 'required|integer|exists:grados,id',
        ]);

        $estudiante = $matriculaService->registrarEstudiante(new RegistrarEstudianteData(
            nombres: $this->nombres,
            apellidos: $this->apellidos,
            dni: new Dni($this->dni),
            fechaNacimiento: $this->fechaNacimiento,
            estadoCivil: $this->estadoCivil !== '' ? EstadoCivilEnum::from($this->estadoCivil) : null,
            direccion: $this->direccion ?: null,
            celular: $this->celular !== '' ? new Telefono($this->celular) : null,
            email: $this->email ?: null,
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

        $matriculaService->matricular($estudiante, new RegistrarMatriculaData(
            cicloId: (int) $this->cicloId,
            gradoId: (int) $this->gradoId,
            observaciones: $this->observacionesMatricula ?: null,
            registradoPor: auth()->id(),
        ));

        $this->dispatch('matricula-registrada', estudianteId: $estudiante->id, nombre: $estudiante->nombreCompleto());
    }

    public function with(): array
    {
        $gradosCompatibles = Grado::query()
            ->where('activo', true)
            ->where('tipo_publico', $this->esMenorDeEdad ? TipoPublicoEnum::MENOR : TipoPublicoEnum::MAYOR)
            ->orderBy('orden')
            ->get();

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
            'gradosCompatibles' => $gradosCompatibles,
            'todosLosGrados' => Grado::query()->where('activo', true)->orderBy('orden')->get(),
            'ciclosDisponibles' => $ciclosConMatriculaAbierta,
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
                <p class="mt-1 text-sm text-ink-dim">Paso {{ $paso }} de 5</p>
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
            @for ($i = 1; $i <= 5; $i++)
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
                    <x-text-input wire:model="dni" id="dni" class="mt-1 block w-full" />
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
                    <select wire:model="estadoCivil" id="estadoCivil" class="mt-1 block w-full rounded-md border-border bg-surface text-sm text-ink focus:border-accent focus:ring-accent">
                        <option value="">Sin especificar</option>
                        @foreach ($estadosCiviles as $opcion)
                            <option value="{{ $opcion->value }}">{{ $opcion->label() }}</option>
                        @endforeach
                    </select>
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
                    <x-input-label for="email" value="Correo (opcional)" />
                    <x-text-input wire:model="email" id="email" type="email" class="mt-1 block w-full" />
                    <x-input-error :messages="$errors->get('email')" class="mt-1" />
                </div>
                <div class="sm:col-span-2">
                    <x-input-label for="foto" value="Fotografía (opcional)" />
                    <input wire:model="foto" id="foto" type="file" accept="image/*" class="mt-1 block w-full text-sm text-ink-dim file:mr-3 file:rounded-md file:border-0 file:bg-surface-2 file:px-3 file:py-2 file:text-sm file:text-ink">
                    <x-input-error :messages="$errors->get('foto')" class="mt-1" />
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
                        <select wire:model="examenGradoAsignadoId" id="examenGradoAsignadoId" class="mt-1 block w-full rounded-md border-border bg-surface text-sm text-ink focus:border-accent focus:ring-accent">
                            <option value="">Sin asignar</option>
                            @foreach ($todosLosGrados as $grado)
                                <option value="{{ $grado->id }}">{{ $grado->nombre }}</option>
                            @endforeach
                        </select>
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
                    <select wire:model="cicloId" id="cicloId" class="mt-1 block w-full rounded-md border-border bg-surface text-sm text-ink focus:border-accent focus:ring-accent">
                        <option value="">Selecciona…</option>
                        @foreach ($ciclosDisponibles as $ciclo)
                            <option value="{{ $ciclo->id }}">{{ $ciclo->nombre }}</option>
                        @endforeach
                    </select>
                    @if ($ciclosDisponibles->isEmpty())
                        <p class="mt-1 text-xs text-danger">No hay ciclos con periodo de matrícula abierto hoy.</p>
                    @endif
                    <x-input-error :messages="$errors->get('cicloId')" class="mt-1" />
                </div>
                <div>
                    <x-input-label for="gradoId" value="Grado" />
                    <select wire:model="gradoId" id="gradoId" class="mt-1 block w-full rounded-md border-border bg-surface text-sm text-ink focus:border-accent focus:ring-accent">
                        <option value="">Selecciona…</option>
                        @foreach ($gradosCompatibles as $grado)
                            <option value="{{ $grado->id }}">{{ $grado->nombre }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('gradoId')" class="mt-1" />
                </div>
                <div class="sm:col-span-2">
                    <x-input-label for="observacionesMatricula" value="Observaciones (opcional)" />
                    <textarea wire:model="observacionesMatricula" id="observacionesMatricula" rows="2" class="mt-1 block w-full rounded-md border-border bg-surface text-sm text-ink focus:border-accent focus:ring-accent"></textarea>
                </div>
            </div>
        @endif

        <div class="mt-6 flex justify-between border-t border-border pt-4">
            @if ($paso > 1)
                <x-secondary-button type="button" wire:click="retroceder">Atrás</x-secondary-button>
            @else
                <span></span>
            @endif

            @if ($paso < 5)
                <x-primary-button type="button" wire:click="avanzar">Continuar</x-primary-button>
            @else
                <x-primary-button type="button" wire:click="confirmar">Confirmar matrícula</x-primary-button>
            @endif
        </div>
        </div>
    </div>
</div>
