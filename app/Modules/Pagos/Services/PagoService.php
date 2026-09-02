<?php

declare(strict_types=1);

namespace App\Modules\Pagos\Services;

use App\Modules\Matricula\Models\Estudiante;
use App\Modules\Pagos\Enums\EstadoPagoEnum;
use App\Modules\Pagos\Enums\MetodoPagoEnum;
use App\Modules\Pagos\Models\ConceptoPago;
use App\Modules\Pagos\Models\Cuota;
use App\Modules\Pagos\Models\Pago;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class PagoService
{
    public function __construct(
        private readonly ReciboService $recibos,
        private readonly BloqueoAccesoService $bloqueos,
    ) {}

    /**
     * Un pago puede cubrirse con más de una parte a la vez (ej. una mitad
     * en efectivo y la otra por Yape), pero queda como un solo registro:
     * cada elemento de $partes es un monto+método independiente, y su suma
     * es lo que se guarda como Pago::$monto. Pago::$metodo queda como el
     * método único si todas las partes usan el mismo, o "mixto" si no --
     * es solo un resumen para listados; el detalle real vive en partes().
     *
     * @param  list<array{monto: float, metodo: string}>  $partes
     */
    public function registrar(
        Estudiante $estudiante,
        ConceptoPago $concepto,
        array $partes,
        ?Cuota $cuota,
        ?UploadedFile $comprobante,
        ?int $registradoPor,
        ?string $detalle = null,
    ): Pago {
        if ($partes === []) {
            throw new InvalidArgumentException('Un pago necesita al menos una parte (monto y método).');
        }

        if ($cuota && Pago::query()->where('cuota_id', $cuota->id)->where('estado', EstadoPagoEnum::PENDIENTE)->exists()) {
            throw ValidationException::withMessages([
                'cuota' => 'Ya existe un pago pendiente de aprobación para esta cuota.',
            ]);
        }

        $montoTotal = array_sum(array_column($partes, 'monto'));
        $metodosUnicos = collect($partes)->pluck('metodo')->unique();
        $metodo = $metodosUnicos->count() === 1 ? $metodosUnicos->first() : MetodoPagoEnum::MIXTO->value;

        return DB::transaction(function () use ($estudiante, $concepto, $detalle, $cuota, $montoTotal, $metodo, $registradoPor, $comprobante, $partes) {
            /** @var Pago $pago */
            $pago = Pago::query()->create([
                'estudiante_id' => $estudiante->id,
                'concepto_id' => $concepto->id,
                'detalle' => $detalle,
                'cuota_id' => $cuota?->id,
                'monto' => $montoTotal,
                'metodo' => $metodo,
                'estado' => EstadoPagoEnum::PENDIENTE,
                'registrado_por' => $registradoPor,
                'fecha_pago' => now(),
            ]);

            foreach ($partes as $parte) {
                $pago->partes()->create([
                    'monto' => $parte['monto'],
                    'metodo' => $parte['metodo'],
                ]);
            }

            if ($comprobante) {
                $pago->addMedia($comprobante)->toMediaCollection('comprobante');
            }

            return $pago;
        });
    }

    public function aprobar(Pago $pago, int $aprobadoPor): Pago
    {
        $this->validarPendiente($pago);

        DB::transaction(function () use ($pago, $aprobadoPor) {
            $pago->update([
                'estado' => EstadoPagoEnum::APROBADO,
                'aprobado_por' => $aprobadoPor,
                'fecha_aprobacion' => now(),
            ]);

            if ($pago->cuota) {
                $pago->cuota->update(['estado' => 'pagado']);
            }

            $this->recibos->emitir($pago);
            $this->bloqueos->evaluarYDesbloquear($pago->estudiante);
        });

        return $pago->refresh();
    }

    public function rechazar(Pago $pago, int $aprobadoPor, string $motivo): Pago
    {
        $this->validarPendiente($pago);

        $pago->update([
            'estado' => EstadoPagoEnum::RECHAZADO,
            'aprobado_por' => $aprobadoPor,
            'fecha_aprobacion' => now(),
            'motivo_rechazo' => $motivo,
        ]);

        return $pago;
    }

    /**
     * @return Collection<int, Pago>
     */
    public function misPagos(Estudiante $estudiante): Collection
    {
        return Pago::query()
            ->where('estudiante_id', $estudiante->id)
            ->with(['concepto', 'cuota', 'recibo', 'partes'])
            ->latest('fecha_pago')
            ->get();
    }

    /**
     * @return Collection<int, Pago>
     */
    public function pendientesDeAprobacion(): Collection
    {
        return Pago::query()
            ->where('estado', EstadoPagoEnum::PENDIENTE)
            ->with(['estudiante', 'concepto', 'cuota', 'partes'])
            ->oldest('fecha_pago')
            ->get();
    }

    /**
     * @return Collection<int, Pago>
     */
    public function todos(): Collection
    {
        return Pago::query()
            ->with(['estudiante', 'concepto', 'cuota', 'recibo', 'partes'])
            ->latest('fecha_pago')
            ->get();
    }

    private function validarPendiente(Pago $pago): void
    {
        if ($pago->estado !== EstadoPagoEnum::PENDIENTE) {
            throw ValidationException::withMessages([
                'estado' => 'Este pago ya fue procesado y no puede modificarse.',
            ]);
        }
    }
}
