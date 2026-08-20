<?php

use App\Modules\Matricula\Models\Estudiante;
use App\Modules\Matricula\Models\Matricula;
use App\Modules\Pagos\Enums\MetodoPagoEnum;
use App\Modules\Pagos\Enums\NumeroCuotasEnum;
use App\Modules\Pagos\Enums\TipoConceptoEnum;
use App\Modules\Pagos\Models\ConceptoPago;
use App\Modules\Pagos\Models\Pago;
use App\Modules\Pagos\Models\PlanPago;
use App\Modules\Pagos\Services\ConceptoPagoService;
use App\Modules\Pagos\Services\PagoService;
use App\Modules\Pagos\Services\PlanPagoService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new #[Layout('layouts.app')] class extends Component
{
    use WithFileUploads;

    public string $tab = 'aprobacion';

    // Registrar pago directo
    public string $terminoBusqueda = '';

    public ?int $estudianteSeleccionadoId = null;

    public string $estudianteSeleccionadoNombre = '';

    public string $conceptoId = '';

    public string $detalle = '';

    public string $monto = '';

    public string $metodo = '';

    public $comprobante = null;

    // Rechazo de pago
    /** @var array<int, string> */
    public array $motivoRechazo = [];

    // Crear plan de pago — indexado por matricula_id, porque el listado
    // de "matrículas sin plan" puede mostrar varias filas a la vez.
    /** @var array<int, string> */
    public array $numeroCuotasPorMatricula = [];

    /** @var array<int, string> */
    public array $montoTotalPorMatricula = [];

    // Filtro de historial
    public string $filtroEstado = '';

    public function mount(): void
    {
        $user = Auth::user();

        abort_unless(
            $user->hasAnyPermission(['pagos.ver', 'pagos.registrar', 'pagos.gestionar', 'pagos.aprobar', 'pagos.rechazar']),
            403,
        );

        $this->tab = match (true) {
            $user->hasPermissionTo('pagos.aprobar') => 'aprobacion',
            $user->hasAnyPermission(['pagos.registrar', 'pagos.gestionar']) => 'registrar',
            default => 'historial',
        };
    }

    public function seleccionarEstudiante(int $estudianteId, string $nombre): void
    {
        $this->estudianteSeleccionadoId = $estudianteId;
        $this->estudianteSeleccionadoNombre = $nombre;
        $this->terminoBusqueda = '';
    }

    public function registrarPago(PagoService $service): void
    {
        abort_unless(Auth::user()->hasAnyPermission(['pagos.registrar', 'pagos.gestionar']), 403);

        $this->validate([
            'estudianteSeleccionadoId' => 'required|integer',
            'conceptoId' => 'required|integer|exists:conceptos_pago,id',
            'monto' => 'required|numeric|min:0.01',
            'metodo' => 'required|string|in:'.implode(',', array_column(MetodoPagoEnum::cases(), 'value')),
            'comprobante' => 'nullable|file|max:5120',
        ]);

        $estudiante = Estudiante::query()->findOrFail($this->estudianteSeleccionadoId);
        $concepto = ConceptoPago::query()->findOrFail($this->conceptoId);

        if ($concepto->tipo === TipoConceptoEnum::OTRO && trim($this->detalle) === '') {
            $this->addError('detalle', 'Escribe el concepto específico de este cobro.');

            return;
        }

        $service->registrar($estudiante, $concepto, (float) $this->monto, $this->metodo, null, $this->comprobante, Auth::id(), $this->detalle ?: null);

        $this->reset(['estudianteSeleccionadoId', 'estudianteSeleccionadoNombre', 'conceptoId', 'detalle', 'monto', 'metodo', 'comprobante']);
        session()->flash('status', 'Pago registrado. Queda pendiente de aprobación de Tesorería.');
    }

    public function crearPlan(int $matriculaId, PlanPagoService $service): void
    {
        abort_unless(Auth::user()->hasAnyPermission(['pagos.gestionar']), 403);

        $this->validate([
            "numeroCuotasPorMatricula.{$matriculaId}" => ['required', 'integer', Rule::in(array_column(NumeroCuotasEnum::cases(), 'value'))],
            "montoTotalPorMatricula.{$matriculaId}" => 'required|numeric|min:1',
        ]);

        $matricula = Matricula::query()->findOrFail($matriculaId);

        $service->crear(
            $matricula,
            NumeroCuotasEnum::from((int) $this->numeroCuotasPorMatricula[$matriculaId]),
            (float) $this->montoTotalPorMatricula[$matriculaId],
        );

        unset($this->numeroCuotasPorMatricula[$matriculaId], $this->montoTotalPorMatricula[$matriculaId]);
        session()->flash('status', 'Plan de pago creado.');
    }

    public function aprobar(int $pagoId, PagoService $service): void
    {
        Gate::authorize('pagos.aprobar');

        $service->aprobar(Pago::query()->findOrFail($pagoId), Auth::id());

        session()->flash('status', 'Pago aprobado y recibo generado.');
    }

    public function rechazar(int $pagoId, PagoService $service): void
    {
        Gate::authorize('pagos.rechazar');

        $this->validate(['motivoRechazo.'.$pagoId => 'required|string|max:255']);

        $service->rechazar(Pago::query()->findOrFail($pagoId), Auth::id(), $this->motivoRechazo[$pagoId]);

        unset($this->motivoRechazo[$pagoId]);
        session()->flash('status', 'Pago rechazado.');
    }

    public function with(PagoService $pagos, ConceptoPagoService $conceptos): array
    {
        $user = Auth::user();
        $puedeAprobar = $user->hasPermissionTo('pagos.aprobar');
        $puedeRegistrar = $user->hasAnyPermission(['pagos.registrar', 'pagos.gestionar']);
        $puedeVerHistorial = $user->hasAnyPermission(['pagos.ver', 'pagos.gestionar']);

        $resultadosBusqueda = collect();
        if ($this->terminoBusqueda !== '' && $puedeRegistrar) {
            $resultadosBusqueda = Estudiante::query()
                ->where(function ($query) {
                    $termino = $this->terminoBusqueda;
                    $query->where('nombres', 'like', "%{$termino}%")
                        ->orWhere('apellidos', 'like', "%{$termino}%")
                        ->orWhere('dni', 'like', "%{$termino}%");
                })
                ->limit(8)
                ->get();
        }

        $matriculasSinPlan = collect();
        if ($puedeRegistrar) {
            $matriculasSinPlan = Matricula::query()
                ->where('estado', 'aprobada')
                ->whereNotIn('id', PlanPago::query()->pluck('matricula_id'))
                ->with(['estudiante', 'ciclo', 'grado'])
                ->get();

            foreach ($matriculasSinPlan as $matricula) {
                $this->numeroCuotasPorMatricula[$matricula->id] ??= '6';
                $this->montoTotalPorMatricula[$matricula->id] ??= '';
            }
        }

        $historial = $puedeVerHistorial
            ? $pagos->todos()->when($this->filtroEstado, fn ($coleccion) => $coleccion->where('estado', $this->filtroEstado))
            : collect();

        $conceptosActivos = $conceptos->activos();

        return [
            'puedeAprobar' => $puedeAprobar,
            'puedeRegistrar' => $puedeRegistrar,
            'puedeVerHistorial' => $puedeVerHistorial,
            'colaAprobacion' => $puedeAprobar ? $pagos->pendientesDeAprobacion() : collect(),
            'historial' => $historial,
            'resultadosBusqueda' => $resultadosBusqueda,
            'matriculasSinPlan' => $matriculasSinPlan,
            'conceptosActivos' => $conceptosActivos,
            'mostrarDetalleLibre' => $conceptosActivos->firstWhere('id', (int) $this->conceptoId)?->tipo === TipoConceptoEnum::OTRO,
            'numerosCuotas' => NumeroCuotasEnum::cases(),
            'metodosPago' => MetodoPagoEnum::cases(),
        ];
    }
}; ?>

<div>
    <x-slot name="header">
        <h1 class="font-display text-2xl text-ink">Pagos y Cobranza</h1>
        <p class="mt-1 text-sm text-ink-dim">Cola de aprobación, registro de pagos y planes de cuotas.</p>
    </x-slot>

    @if (session('status'))
        <div class="mb-4 rounded-md border border-ok/30 bg-ok/10 px-4 py-3 text-sm text-ok">
            {{ session('status') }}
        </div>
    @endif

    <div class="mb-6 flex gap-1 border-b border-border">
        @if ($puedeAprobar)
            <button wire:click="$set('tab', 'aprobacion')" @class(['border-b-2 px-4 py-2 font-display text-sm font-medium transition', 'border-accent text-accent' => $tab === 'aprobacion', 'border-transparent text-ink-faint hover:text-ink' => $tab !== 'aprobacion'])>
                Cola de aprobación
                @if ($colaAprobacion->isNotEmpty())
                    <span class="ml-1 rounded-full bg-warn/15 px-1.5 py-0.5 text-xs text-warn">{{ $colaAprobacion->count() }}</span>
                @endif
            </button>
        @endif
        @if ($puedeRegistrar)
            <button wire:click="$set('tab', 'registrar')" @class(['border-b-2 px-4 py-2 font-display text-sm font-medium transition', 'border-accent text-accent' => $tab === 'registrar', 'border-transparent text-ink-faint hover:text-ink' => $tab !== 'registrar'])>
                Registrar pago
            </button>
            <button wire:click="$set('tab', 'planes')" @class(['border-b-2 px-4 py-2 font-display text-sm font-medium transition', 'border-accent text-accent' => $tab === 'planes', 'border-transparent text-ink-faint hover:text-ink' => $tab !== 'planes'])>
                Planes de pago
                @if ($matriculasSinPlan->isNotEmpty())
                    <span class="ml-1 rounded-full bg-warn/15 px-1.5 py-0.5 text-xs text-warn">{{ $matriculasSinPlan->count() }}</span>
                @endif
            </button>
        @endif
        @if ($puedeVerHistorial)
            <button wire:click="$set('tab', 'historial')" @class(['border-b-2 px-4 py-2 font-display text-sm font-medium transition', 'border-accent text-accent' => $tab === 'historial', 'border-transparent text-ink-faint hover:text-ink' => $tab !== 'historial'])>
                Historial
            </button>
        @endif
    </div>

    {{-- Cola de aprobación --}}
    @if ($tab === 'aprobacion' && $puedeAprobar)
        <div class="divide-y divide-border rounded-lg border border-border bg-surface">
            @forelse ($colaAprobacion as $pago)
                <div class="px-4 py-4 text-sm">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-ink">{{ $pago->estudiante?->nombreCompleto() ?? '—' }}</p>
                            <p class="text-xs text-ink-faint">
                                {{ $pago->concepto->nombre }}{{ $pago->detalle ? " — {$pago->detalle}" : '' }}
                                @if ($pago->cuota)
                                    · cuota {{ $pago->cuota->numero }}
                                @endif
                                · {{ $pago->metodo->label() }} · {{ $pago->fecha_pago->format('d/m/Y') }}
                            </p>
                            @if ($pago->getFirstMedia('comprobante'))
                                <a href="{{ $pago->getFirstMediaUrl('comprobante') }}" target="_blank" class="mt-1 inline-block text-xs font-medium text-accent hover:underline">Ver comprobante</a>
                            @endif
                        </div>
                        <p class="font-display text-lg text-ink">S/ {{ number_format((float) $pago->monto, 2) }}</p>
                    </div>

                    <div class="mt-3 flex flex-wrap items-center gap-2">
                        <x-secondary-button type="button" wire:click="aprobar({{ $pago->id }})" wire:confirm="¿Aprobar este pago? Se generará el recibo automáticamente.">
                            Aprobar
                        </x-secondary-button>
                        <input
                            type="text"
                            wire:model="motivoRechazo.{{ $pago->id }}"
                            placeholder="Motivo de rechazo…"
                            class="w-52 rounded-md border-border bg-surface text-xs text-ink placeholder:text-ink-faint focus:border-accent focus:ring-accent"
                        >
                        <button type="button" wire:click="rechazar({{ $pago->id }})" class="text-xs font-medium text-danger hover:underline">Rechazar</button>
                        <x-input-error :messages="$errors->get('motivoRechazo.'.$pago->id)" class="mt-0" />
                    </div>
                </div>
            @empty
                <p class="px-4 py-8 text-center text-sm text-ink-faint">No hay pagos pendientes de aprobación.</p>
            @endforelse
        </div>
    @endif

    {{-- Registrar pago --}}
    @if ($tab === 'registrar' && $puedeRegistrar)
        <div class="max-w-xl space-y-4 rounded-lg border border-border bg-surface p-6">
            <div>
                <x-input-label value="Estudiante" />
                @if ($estudianteSeleccionadoId)
                    <div class="mt-1 flex items-center justify-between rounded-md bg-accent-soft px-3 py-2 text-sm text-accent">
                        {{ $estudianteSeleccionadoNombre }}
                        <button type="button" wire:click="$set('estudianteSeleccionadoId', null)" class="text-xs underline">Cambiar</button>
                    </div>
                @else
                    <x-text-input wire:model.live.debounce.300ms="terminoBusqueda" class="mt-1 block w-full" placeholder="Buscar por nombre, apellido o DNI…" />
                    @if ($resultadosBusqueda->isNotEmpty())
                        <div class="mt-1 divide-y divide-border rounded-md border border-border bg-surface">
                            @foreach ($resultadosBusqueda as $estudiante)
                                <button
                                    type="button"
                                    wire:click="seleccionarEstudiante({{ $estudiante->id }}, '{{ addslashes($estudiante->nombreCompleto()) }}')"
                                    class="block w-full px-3 py-2 text-left text-sm hover:bg-surface-2"
                                >
                                    {{ $estudiante->nombreCompleto() }} <span class="text-ink-faint">· {{ $estudiante->dni }}</span>
                                </button>
                            @endforeach
                        </div>
                    @endif
                @endif
                <x-input-error :messages="$errors->get('estudianteSeleccionadoId')" class="mt-1" />
            </div>

            <div>
                <x-input-label for="conceptoId" value="Concepto" />
                <x-select-input
                    wire:model.live="conceptoId"
                    id="conceptoId"
                    class="mt-1 block w-full"
                    :options="collect($conceptosActivos)->mapWithKeys(fn ($concepto) => [$concepto->id => $concepto->nombre.' (S/ '.number_format((float) $concepto->monto_base, 2).')'])"
                />
                <x-input-error :messages="$errors->get('conceptoId')" class="mt-1" />
            </div>

            @if ($mostrarDetalleLibre)
                <div>
                    <x-input-label for="detalle" value="Detalle del concepto" />
                    <x-text-input wire:model="detalle" id="detalle" class="mt-1 block w-full" placeholder="Ej. duplicado de constancia, exoneración…" />
                    <x-input-error :messages="$errors->get('detalle')" class="mt-1" />
                </div>
            @endif

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <x-input-label for="monto" value="Monto (S/)" />
                    <x-text-input wire:model="monto" id="monto" type="number" step="0.01" min="0" class="mt-1 block w-full" />
                    <x-input-error :messages="$errors->get('monto')" class="mt-1" />
                </div>
                <div>
                    <x-input-label for="metodo" value="Método" />
                    <x-select-input
                        wire:model="metodo"
                        id="metodo"
                        class="mt-1 block w-full"
                        :options="collect($metodosPago)->mapWithKeys(fn ($metodoOpcion) => [$metodoOpcion->value => $metodoOpcion->label()])"
                    />
                    <x-input-error :messages="$errors->get('metodo')" class="mt-1" />
                </div>
            </div>

            <div>
                <x-input-label for="comprobante" value="Comprobante (opcional)" />
                <input wire:model="comprobante" id="comprobante" type="file" class="mt-1 block w-full text-sm text-ink-dim file:mr-3 file:rounded-md file:border-0 file:bg-surface-2 file:px-3 file:py-2 file:text-sm file:text-ink">
                <x-input-error :messages="$errors->get('comprobante')" class="mt-1" />
            </div>

            <div class="flex justify-end">
                <x-primary-button type="button" wire:click="registrarPago">Registrar pago</x-primary-button>
            </div>
        </div>
    @endif

    {{-- Planes de pago --}}
    @if ($tab === 'planes' && $puedeRegistrar)
        <div class="space-y-4">
            <p class="text-sm text-ink-dim">Matrículas aprobadas sin un plan de pago asignado este ciclo.</p>
            <div class="divide-y divide-border rounded-lg border border-border bg-surface">
                @forelse ($matriculasSinPlan as $matricula)
                    <div class="flex flex-wrap items-center justify-between gap-3 px-4 py-3 text-sm">
                        <div>
                            <p class="text-ink">{{ $matricula->estudiante?->nombreCompleto() ?? '—' }}</p>
                            <p class="text-xs text-ink-faint">{{ $matricula->grado->nombre }} · {{ $matricula->ciclo->nombre }}</p>
                        </div>
                        <form wire:submit="crearPlan({{ $matricula->id }})" class="flex items-start gap-2">
                            <div>
                                <x-select-input
                                    wire:model="numeroCuotasPorMatricula.{{ $matricula->id }}"
                                    class="text-xs"
                                    :options="collect($numerosCuotas)->mapWithKeys(fn ($opcion) => [$opcion->value => $opcion->label()])"
                                />
                            </div>
                            <div>
                                <input
                                    type="number"
                                    step="0.01"
                                    min="1"
                                    placeholder="Monto total"
                                    wire:model="montoTotalPorMatricula.{{ $matricula->id }}"
                                    class="w-28 rounded-md border-border bg-surface text-xs text-ink placeholder:text-ink-faint focus:border-accent focus:ring-accent"
                                >
                                <x-input-error :messages="$errors->get('montoTotalPorMatricula.'.$matricula->id)" class="mt-1" />
                            </div>
                            <button type="submit" class="text-xs font-medium text-accent hover:underline">
                                Crear plan
                            </button>
                        </form>
                    </div>
                @empty
                    <p class="px-4 py-8 text-center text-sm text-ink-faint">Todas las matrículas aprobadas ya tienen un plan de pago.</p>
                @endforelse
            </div>
        </div>
    @endif

    {{-- Historial --}}
    @if ($tab === 'historial' && $puedeVerHistorial)
        <div class="space-y-4">
            <x-select-input
                wire:model.live="filtroEstado"
                class="w-full sm:max-w-xs"
                :options="[
                    '' => 'Todos los estados',
                    'pendiente' => 'Pendiente',
                    'aprobado' => 'Aprobado',
                    'rechazado' => 'Rechazado',
                ]"
            />

            <div class="divide-y divide-border rounded-lg border border-border bg-surface">
                @forelse ($historial as $pago)
                    <div class="flex items-center justify-between gap-4 px-4 py-3 text-sm">
                        <div>
                            <p class="text-ink">{{ $pago->estudiante?->nombreCompleto() ?? '—' }}</p>
                            <p class="text-xs text-ink-faint">{{ $pago->concepto->nombre }}{{ $pago->detalle ? " — {$pago->detalle}" : '' }} · {{ $pago->fecha_pago->format('d/m/Y') }}</p>
                            @if ($pago->estado->value === 'rechazado' && $pago->motivo_rechazo)
                                <p class="text-xs text-danger">{{ $pago->motivo_rechazo }}</p>
                            @endif
                        </div>
                        <div class="text-right">
                            <p class="font-display text-ink">S/ {{ number_format((float) $pago->monto, 2) }}</p>
                            <span @class([
                                'rounded-full px-2 py-0.5 text-xs',
                                'bg-ok/10 text-ok' => $pago->estado->value === 'aprobado',
                                'bg-warn/10 text-warn' => $pago->estado->value === 'pendiente',
                                'bg-danger/10 text-danger' => $pago->estado->value === 'rechazado',
                            ])>{{ $pago->estado->label() }}</span>
                            @if ($pago->recibo && $pago->recibo->getFirstMedia('pdf'))
                                <a href="{{ $pago->recibo->getFirstMediaUrl('pdf') }}" target="_blank" class="block text-xs font-medium text-accent hover:underline">Recibo</a>
                            @endif
                        </div>
                    </div>
                @empty
                    <p class="px-4 py-8 text-center text-sm text-ink-faint">No hay pagos registrados.</p>
                @endforelse
            </div>
        </div>
    @endif
</div>
