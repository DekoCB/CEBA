<?php

declare(strict_types=1);

namespace App\Modules\Pagos\Services;

use App\Modules\Matricula\Models\Estudiante;
use App\Modules\Pagos\Enums\EstadoPagoEnum;
use App\Modules\Pagos\Models\ConceptoPago;
use App\Modules\Pagos\Models\Cuota;
use App\Modules\Pagos\Models\Pago;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PagoService
{
    public function __construct(
        private readonly ReciboService $recibos,
        private readonly BloqueoAccesoService $bloqueos,
    ) {}

    public function registrar(
        Estudiante $estudiante,
        ConceptoPago $concepto,
        float $monto,
        string $metodo,
        ?Cuota $cuota,
        ?UploadedFile $comprobante,
        ?int $registradoPor,
        ?string $detalle = null,
    ): Pago {
        if ($cuota && Pago::query()->where('cuota_id', $cuota->id)->where('estado', EstadoPagoEnum::PENDIENTE)->exists()) {
            throw ValidationException::withMessages([
                'cuota' => 'Ya existe un pago pendiente de aprobación para esta cuota.',
            ]);
        }

        /** @var Pago $pago */
        $pago = Pago::query()->create([
            'estudiante_id' => $estudiante->id,
            'concepto_id' => $concepto->id,
            'detalle' => $detalle,
            'cuota_id' => $cuota?->id,
            'monto' => $monto,
            'metodo' => $metodo,
            'estado' => EstadoPagoEnum::PENDIENTE,
            'registrado_por' => $registradoPor,
            'fecha_pago' => now(),
        ]);

        if ($comprobante) {
            $pago->addMedia($comprobante)->toMediaCollection('comprobante');
        }

        return $pago;
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
            ->with(['concepto', 'cuota', 'recibo'])
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
            ->with(['estudiante', 'concepto', 'cuota'])
            ->oldest('fecha_pago')
            ->get();
    }

    /**
     * @return Collection<int, Pago>
     */
    public function todos(): Collection
    {
        return Pago::query()
            ->with(['estudiante', 'concepto', 'cuota', 'recibo'])
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
