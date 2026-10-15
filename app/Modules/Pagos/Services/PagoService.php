<?php

declare(strict_types=1);

namespace App\Modules\Pagos\Services;

use App\Modules\Matricula\Models\Estudiante;
use App\Modules\Pagos\Enums\EstadoCuotaEnum;
use App\Modules\Pagos\Enums\EstadoPagoEnum;
use App\Modules\Pagos\Enums\MetodoPagoEnum;
use App\Modules\Pagos\Enums\SerieReciboEnum;
use App\Modules\Pagos\Models\CargoAdicional;
use App\Modules\Pagos\Models\Pago;
use App\Modules\Pagos\Models\PagoDestino;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
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
     * Un pago puede cubrir varios destinos a la vez (ej. una cuota completa
     * + parte de la siguiente, o una cuota + un cargo adicional, en un solo
     * registro): cada elemento de $destinos es un concepto+monto
     * independiente (con su cuota_id o cargo_adicional_id si corresponde),
     * y su suma debe coincidir con la suma de $partes. También puede
     * cubrirse con más de un medio de pago a la vez (ej. una mitad en
     * efectivo y la otra por Yape): cada elemento de $partes es un
     * monto+método independiente. Ambas cosas quedan como un solo registro
     * de Pago -- Pago::$metodo/concepto_id/cuota_id/cargo_adicional_id/
     * detalle quedan como un resumen (el único destino, si solo hay uno; el
     * primero, si hay varios) para listados que no necesitan el detalle
     * completo; el detalle real vive en destinos()/partes().
     *
     * @param  list<array{concepto_id: int, cuota_id: ?int, cargo_adicional_id: ?int, monto: float, detalle?: ?string}>  $destinos
     * @param  list<array{monto: float, metodo: string, nota?: ?string}>  $partes
     */
    public function registrar(
        Estudiante $estudiante,
        array $destinos,
        array $partes,
        ?UploadedFile $comprobante,
        ?int $registradoPor,
        ?string $observacion = null,
        ?string $fechaPago = null,
    ): Pago {
        if ($partes === []) {
            throw new InvalidArgumentException('Un pago necesita al menos una parte (monto y método).');
        }

        if ($destinos === []) {
            throw new InvalidArgumentException('Un pago necesita al menos un destino (concepto, cuota o cargo adicional).');
        }

        $cuotaIds = array_values(array_filter(array_column($destinos, 'cuota_id')));
        $cargoIds = array_values(array_filter(array_column($destinos, 'cargo_adicional_id')));

        if (count($cuotaIds) !== count(array_unique($cuotaIds)) || count($cargoIds) !== count(array_unique($cargoIds))) {
            throw new InvalidArgumentException('No puedes repetir la misma cuota o el mismo cargo adicional en un pago.');
        }

        if ($cuotaIds !== [] && PagoDestino::query()->whereIn('cuota_id', $cuotaIds)
            ->whereHas('pago', fn ($query) => $query->where('estado', EstadoPagoEnum::PENDIENTE))
            ->exists()) {
            throw ValidationException::withMessages([
                'destinos' => 'Ya existe un pago pendiente de aprobación para una de estas cuotas.',
            ]);
        }

        if ($cargoIds !== [] && PagoDestino::query()->whereIn('cargo_adicional_id', $cargoIds)
            ->whereHas('pago', fn ($query) => $query->where('estado', EstadoPagoEnum::PENDIENTE))
            ->exists()) {
            throw ValidationException::withMessages([
                'destinos' => 'Ya existe un pago pendiente de aprobación para uno de estos cargos.',
            ]);
        }

        $montoPartes = array_sum(array_column($partes, 'monto'));
        $montoDestinos = array_sum(array_column($destinos, 'monto'));

        // Comparación en céntimos: evita falsos negativos por redondeo
        // binario de floats (no hay un precedente de tolerancia en el
        // proyecto, así que no se inventa uno nuevo).
        if ((int) round($montoDestinos * 100) !== (int) round($montoPartes * 100)) {
            throw ValidationException::withMessages([
                'destinos' => 'La suma de los destinos (S/ '.number_format($montoDestinos, 2).') debe ser igual a la suma de las partes del pago (S/ '.number_format($montoPartes, 2).').',
            ]);
        }

        $metodosUnicos = collect($partes)->pluck('metodo')->unique();
        $metodo = $metodosUnicos->count() === 1 ? $metodosUnicos->first() : MetodoPagoEnum::MIXTO->value;

        // Fecha de pago: cuándo se recibió el dinero (editable, puede ser un
        // día anterior si se cobró en efectivo y recién se registra hoy) --
        // distinta de la fecha de emisión del recibo (Recibo::emitido_en,
        // siempre la del momento en que Tesorería aprueba), que no se toca.
        $fecha = $fechaPago !== null ? Carbon::parse($fechaPago) : now();
        $unico = count($destinos) === 1 ? $destinos[0] : null;

        return DB::transaction(function () use ($estudiante, $destinos, $partes, $observacion, $montoPartes, $metodo, $registradoPor, $comprobante, $fecha, $unico) {
            /** @var Pago $pago */
            $pago = Pago::query()->create([
                'estudiante_id' => $estudiante->id,
                'concepto_id' => $unico['concepto_id'] ?? $destinos[0]['concepto_id'],
                'detalle' => $unico['detalle'] ?? null,
                'observacion' => $observacion,
                'cuota_id' => $unico['cuota_id'] ?? null,
                'cargo_adicional_id' => $unico['cargo_adicional_id'] ?? null,
                'monto' => $montoPartes,
                'metodo' => $metodo,
                'estado' => EstadoPagoEnum::PENDIENTE,
                'registrado_por' => $registradoPor,
                'fecha_pago' => $fecha,
            ]);

            foreach ($destinos as $destino) {
                $pago->destinos()->create([
                    'concepto_id' => $destino['concepto_id'],
                    'cuota_id' => $destino['cuota_id'] ?? null,
                    'cargo_adicional_id' => $destino['cargo_adicional_id'] ?? null,
                    'detalle' => $destino['detalle'] ?? null,
                    'monto' => $destino['monto'],
                ]);
            }

            foreach ($partes as $parte) {
                $pago->partes()->create([
                    'monto' => $parte['monto'],
                    'metodo' => $parte['metodo'],
                    'nota' => $parte['nota'] ?? null,
                ]);
            }

            if ($comprobante) {
                $pago->addMedia($comprobante)->toMediaCollection('comprobante');
            }

            return $pago;
        });
    }

    public function aprobar(Pago $pago, int $aprobadoPor, SerieReciboEnum $serie): Pago
    {
        $this->validarPendiente($pago);

        DB::transaction(function () use ($pago, $aprobadoPor, $serie) {
            $pago->update([
                'estado' => EstadoPagoEnum::APROBADO,
                'aprobado_por' => $aprobadoPor,
                'fecha_aprobacion' => now(),
            ]);

            // El pago recién aprobado puede ser parcial (ej. 40 de una cuota
            // de 80), y puede cubrir varios destinos a la vez: cada cuota o
            // cargo adicional vinculado solo pasa a "pagado" cuando la suma
            // de todos sus pagos aprobados cubre el monto completo, no con
            // el primer pago que se le vincule. Ver Cuota::saldoPendiente().
            foreach ($pago->destinos()->with(['cuota', 'cargoAdicional'])->get() as $destino) {
                if ($destino->cuota) {
                    $destino->cuota->update([
                        'estado' => $destino->cuota->saldoPendiente() <= 0.0 ? EstadoCuotaEnum::PAGADO : EstadoCuotaEnum::PENDIENTE,
                    ]);
                }

                if ($destino->cargoAdicional) {
                    $destino->cargoAdicional->update([
                        'estado' => $destino->cargoAdicional->saldoPendiente() <= 0.0 ? EstadoCuotaEnum::PAGADO : EstadoCuotaEnum::PENDIENTE,
                    ]);
                }
            }

            $this->recibos->emitir($pago, $serie);
            $this->bloqueos->evaluarYDesbloquear($pago->estudiante);
        });

        return $pago->refresh();
    }

    /**
     * Corrige el monto nominal de un cargo adicional (ej. se anotó mal el
     * precio de la Convalidación al matricular). No puede bajar del monto
     * ya cobrado -- mismo criterio que PlanPagoService::editarMontoTotal()
     * para cuotas. Si el cargo ya estaba "pagado" y el nuevo monto deja
     * saldo pendiente, vuelve a "pendiente": el estado siempre refleja el
     * saldo real, nunca al revés.
     */
    public function editarMontoCargoAdicional(CargoAdicional $cargoAdicional, float $nuevoMonto): CargoAdicional
    {
        if ($nuevoMonto <= 0) {
            throw ValidationException::withMessages([
                'montoCargoNuevo' => 'El monto debe ser mayor a cero.',
            ]);
        }

        $montoPagado = $cargoAdicional->montoPagado();

        if ($nuevoMonto < $montoPagado) {
            throw ValidationException::withMessages([
                'montoCargoNuevo' => 'El nuevo monto no puede ser menor a lo ya pagado (S/ '.number_format($montoPagado, 2).').',
            ]);
        }

        $cargoAdicional->update([
            'monto' => $nuevoMonto,
            'estado' => $cargoAdicional->estado === EstadoCuotaEnum::PAGADO && $nuevoMonto > $montoPagado
                ? EstadoCuotaEnum::PENDIENTE
                : $cargoAdicional->estado,
        ]);

        return $cargoAdicional->fresh();
    }

    /**
     * Registra la fecha (y opcionalmente el monto) en que el estudiante se
     * comprometió a pagar este cargo adicional -- mismo mecanismo que
     * PlanPagoService::registrarCompromiso() para cuotas, que cargos
     * adicionales no tenía hasta ahora.
     */
    public function registrarCompromisoCargoAdicional(CargoAdicional $cargoAdicional, string $fecha, ?float $monto = null): CargoAdicional
    {
        if ($cargoAdicional->estado !== EstadoCuotaEnum::PENDIENTE) {
            throw ValidationException::withMessages([
                'fechaCompromiso' => 'Solo se puede registrar un compromiso de pago en cargos pendientes.',
            ]);
        }

        $cargoAdicional->update([
            'fecha_compromiso' => $fecha,
            'monto_compromiso' => $monto,
        ]);

        return $cargoAdicional->fresh();
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
            ->with(['concepto', 'cuota', 'cargoAdicional', 'recibo', 'partes'])
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
            ->with(['estudiante', 'concepto', 'cuota', 'cargoAdicional', 'partes'])
            ->oldest('fecha_pago')
            ->get();
    }

    /**
     * @return Collection<int, Pago>
     */
    public function todos(): Collection
    {
        return Pago::query()
            ->with(['estudiante', 'concepto', 'cuota', 'cargoAdicional', 'recibo', 'partes'])
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
