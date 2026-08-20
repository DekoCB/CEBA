<?php

use App\Modules\Matricula\Models\Matricula;
use App\Modules\Pagos\Enums\MetodoPagoEnum;
use App\Modules\Pagos\Models\ConceptoPago;
use App\Modules\Pagos\Models\Cuota;
use App\Modules\Pagos\Services\BloqueoAccesoService;
use App\Modules\Pagos\Services\CuentaBancariaService;
use App\Modules\Pagos\Services\PagoService;
use App\Modules\Pagos\Services\PlanPagoService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new #[Layout('layouts.app')] class extends Component
{
    use WithFileUploads;

    /** @var array<int, string> */
    public array $metodoPorCuota = [];

    /** @var array<int, mixed> */
    public array $comprobantePorCuota = [];

    public function mount(): void
    {
        $user = Auth::user();

        abort_unless($user->hasPermissionTo('pagos.ver_propio') && $user->estudiante, 403);
    }

    public function subirComprobante(int $cuotaId, PagoService $service): void
    {
        $estudiante = Auth::user()->estudiante;
        abort_unless($estudiante !== null, 403);

        $this->validate([
            "metodoPorCuota.{$cuotaId}" => 'required|string|in:'.implode(',', array_column(MetodoPagoEnum::cases(), 'value')),
            "comprobantePorCuota.{$cuotaId}" => 'required|file|max:5120',
        ]);

        $cuota = Cuota::query()->with('planPago.matricula')->findOrFail($cuotaId);
        abort_unless($cuota->planPago->matricula?->estudiante_id === $estudiante->id, 403);

        $concepto = ConceptoPago::query()->where('tipo', 'mensualidad')->first()
            ?? ConceptoPago::query()->firstOrFail();

        $service->registrar(
            $estudiante,
            $concepto,
            (float) $cuota->monto,
            $this->metodoPorCuota[$cuotaId],
            $cuota,
            $this->comprobantePorCuota[$cuotaId],
            null,
        );

        unset($this->metodoPorCuota[$cuotaId], $this->comprobantePorCuota[$cuotaId]);
        session()->flash('status', 'Comprobante enviado. Quedará pendiente de aprobación de Tesorería.');
    }

    public function with(PagoService $pagos, PlanPagoService $planes, CuentaBancariaService $cuentas, BloqueoAccesoService $bloqueos): array
    {
        $estudiante = Auth::user()->estudiante;

        $matriculas = Matricula::query()
            ->where('estudiante_id', $estudiante->id)
            ->where('estado', 'aprobada')
            ->with(['ciclo', 'grado'])
            ->latest('fecha_matricula')
            ->get()
            ->map(fn (Matricula $matricula) => [
                'matricula' => $matricula,
                'plan' => $planes->planDe($matricula),
            ]);

        return [
            'matriculas' => $matriculas,
            'misPagos' => $pagos->misPagos($estudiante),
            'cuentasBancarias' => $cuentas->activas(),
            'estaBloqueado' => $bloqueos->estaBloqueado($estudiante),
        ];
    }
}; ?>

<div class="max-w-3xl space-y-6">
    <x-slot name="header">
        <h1 class="font-display text-2xl text-ink">Mi estado de cuenta</h1>
        <p class="mt-1 text-sm text-ink-dim">Cuotas, pagos y cuentas para pagar tu mensualidad.</p>
    </x-slot>

    @if (session('status'))
        <div class="rounded-md border border-ok/30 bg-ok/10 px-4 py-3 text-sm text-ok">
            {{ session('status') }}
        </div>
    @endif

    @if ($estaBloqueado)
        <div class="rounded-md border border-danger/30 bg-danger/10 px-4 py-3 text-sm text-danger">
            Tienes cuotas vencidas sin pagar. Mientras la deuda siga activa, tu libreta de notas no estará disponible.
            Regulariza tus pagos o comunícate con Cobranza para un compromiso de pago.
        </div>
    @endif

    @foreach ($matriculas as $item)
        <div class="rounded-lg border border-border bg-surface p-6">
            <h2 class="text-sm font-semibold text-ink">{{ $item['matricula']->grado->nombre }} · {{ $item['matricula']->ciclo->nombre }}</h2>

            @if (! $item['plan'])
                <p class="mt-3 text-sm text-ink-faint">Todavía no se te ha asignado un plan de pago para este ciclo.</p>
            @else
                <div class="mt-4 divide-y divide-border">
                    @foreach ($item['plan']->cuotas as $cuota)
                        <div class="py-3 text-sm">
                            <div class="flex items-center justify-between gap-4">
                                <div>
                                    <p class="text-ink">Cuota {{ $cuota->numero }} de {{ $item['plan']->numero_cuotas }}</p>
                                    <p class="text-xs text-ink-faint">Vence {{ $cuota->fecha_vencimiento->format('d/m/Y') }}</p>
                                </div>
                                <div class="text-right">
                                    <p class="font-display text-ink">S/ {{ number_format((float) $cuota->monto, 2) }}</p>
                                    <span @class([
                                        'rounded-full px-2 py-0.5 text-xs',
                                        'bg-ok/10 text-ok' => $cuota->estado->value === 'pagado',
                                        'bg-warn/10 text-warn' => $cuota->estado->value === 'pendiente' && ! $cuota->estaVencida(),
                                        'bg-danger/10 text-danger' => $cuota->estaVencida(),
                                        'bg-surface-2 text-ink-faint' => $cuota->estado->value === 'exonerado',
                                    ])>
                                        {{ $cuota->estaVencida() ? 'Vencida' : $cuota->estado->label() }}
                                    </span>
                                </div>
                            </div>

                            @if ($cuota->estado->value === 'pendiente')
                                <form wire:submit="subirComprobante({{ $cuota->id }})" class="mt-2 flex flex-wrap items-center gap-2">
                                    <x-select-input
                                        wire:model="metodoPorCuota.{{ $cuota->id }}"
                                        placeholder="Método…"
                                        class="text-xs"
                                        :options="collect(\App\Modules\Pagos\Enums\MetodoPagoEnum::cases())->mapWithKeys(fn ($metodoOpcion) => [$metodoOpcion->value => $metodoOpcion->label()])"
                                    />
                                    <input wire:model="comprobantePorCuota.{{ $cuota->id }}" type="file" class="text-xs text-ink-dim file:mr-2 file:rounded-md file:border-0 file:bg-surface-2 file:px-2 file:py-1 file:text-xs file:text-ink">
                                    <button type="submit" class="text-xs font-medium text-accent hover:underline">Enviar comprobante</button>
                                </form>
                                <x-input-error :messages="$errors->get('metodoPorCuota.'.$cuota->id)" class="mt-1" />
                                <x-input-error :messages="$errors->get('comprobantePorCuota.'.$cuota->id)" class="mt-1" />
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    @endforeach

    <div class="rounded-lg border border-border bg-surface p-6">
        <h2 class="text-sm font-semibold text-ink">Historial de pagos</h2>
        <div class="mt-4 divide-y divide-border">
            @forelse ($misPagos as $pago)
                <div class="flex items-center justify-between py-3 text-sm">
                    <div>
                        <p class="text-ink">{{ $pago->concepto->nombre }}</p>
                        <p class="text-xs text-ink-faint">{{ $pago->fecha_pago->format('d/m/Y') }} · {{ $pago->metodo->label() }}</p>
                    </div>
                    <div class="text-right">
                        <p class="text-ink">S/ {{ number_format((float) $pago->monto, 2) }}</p>
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
                <p class="py-4 text-sm text-ink-faint">Todavía no registras pagos.</p>
            @endforelse
        </div>
    </div>

    <div class="rounded-lg border border-border bg-surface p-6">
        <h2 class="text-sm font-semibold text-ink">Cuentas para pagar</h2>
        <div class="mt-4 space-y-3">
            @forelse ($cuentasBancarias as $cuenta)
                <div class="flex items-center justify-between text-sm">
                    <div>
                        @if ($cuenta->medio === \App\Modules\Pagos\Enums\MedioCuentaEnum::BANCO)
                            <p class="text-ink">{{ $cuenta->banco }} — {{ $cuenta->numero_cuenta }}</p>
                            <p class="text-xs text-ink-faint">{{ $cuenta->titular }}@if ($cuenta->cci) · CCI {{ $cuenta->cci }} @endif</p>
                        @else
                            <p class="text-ink">{{ $cuenta->tipo_billetera?->label() }} — {{ $cuenta->celular }}</p>
                            <p class="text-xs text-ink-faint">{{ $cuenta->titular }}</p>
                        @endif
                    </div>
                    @if ($cuenta->getFirstMedia('qr'))
                        <a href="{{ $cuenta->getFirstMediaUrl('qr') }}" target="_blank" class="text-xs font-medium text-accent hover:underline">Ver QR</a>
                    @endif
                </div>
            @empty
                <p class="text-sm text-ink-faint">No hay cuentas registradas todavía.</p>
            @endforelse
        </div>
    </div>
</div>
