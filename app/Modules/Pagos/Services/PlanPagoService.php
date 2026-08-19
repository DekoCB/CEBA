<?php

declare(strict_types=1);

namespace App\Modules\Pagos\Services;

use App\Modules\Matricula\Models\Matricula;
use App\Modules\Pagos\Enums\NumeroCuotasEnum;
use App\Modules\Pagos\Models\Cuota;
use App\Modules\Pagos\Models\PlanPago;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PlanPagoService
{
    /**
     * Si no se pasa $cuotasPersonalizadas, reparte $montoTotal en partes
     * iguales entre $numeroCuotas (la última absorbe el redondeo) con
     * vencimientos mensuales desde la fecha de matrícula -- el
     * comportamiento de siempre. Si se pasa, se usa tal cual: debe traer
     * exactamente $numeroCuotas->value elementos, uno por cada cuota, en
     * orden (monto y fecha de vencimiento propios de cada una).
     *
     * @param  list<array{monto: float, fecha_vencimiento: string}>|null  $cuotasPersonalizadas
     */
    public function crear(Matricula $matricula, NumeroCuotasEnum $numeroCuotas, float $montoTotal, ?array $cuotasPersonalizadas = null): PlanPago
    {
        if (PlanPago::query()->where('matricula_id', $matricula->id)->exists()) {
            throw ValidationException::withMessages([
                'matricula' => 'Este estudiante ya tiene un plan de pago para esta matrícula.',
            ]);
        }

        return DB::transaction(function () use ($matricula, $numeroCuotas, $montoTotal, $cuotasPersonalizadas) {
            $plan = PlanPago::query()->create([
                'matricula_id' => $matricula->id,
                'numero_cuotas' => $numeroCuotas->value,
                'monto_total' => $montoTotal,
            ]);

            if ($cuotasPersonalizadas !== null) {
                foreach ($cuotasPersonalizadas as $indice => $cuota) {
                    Cuota::query()->create([
                        'plan_pago_id' => $plan->id,
                        'numero' => $indice + 1,
                        'monto' => $cuota['monto'],
                        'fecha_vencimiento' => $cuota['fecha_vencimiento'],
                    ]);
                }

                return $plan;
            }

            $montoPorCuota = round($montoTotal / $numeroCuotas->value, 2);
            $montoAcumulado = 0.0;

            for ($numero = 1; $numero <= $numeroCuotas->value; $numero++) {
                $esUltima = $numero === $numeroCuotas->value;
                $monto = $esUltima ? round($montoTotal - $montoAcumulado, 2) : $montoPorCuota;
                $montoAcumulado += $monto;

                Cuota::query()->create([
                    'plan_pago_id' => $plan->id,
                    'numero' => $numero,
                    'monto' => $monto,
                    'fecha_vencimiento' => $matricula->fecha_matricula->copy()->addMonths($numero),
                ]);
            }

            return $plan;
        });
    }

    public function planDe(Matricula $matricula): ?PlanPago
    {
        return PlanPago::query()->where('matricula_id', $matricula->id)->with('cuotas')->first();
    }
}
