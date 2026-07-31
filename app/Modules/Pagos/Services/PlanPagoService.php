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
    public function crear(Matricula $matricula, NumeroCuotasEnum $numeroCuotas, float $montoTotal): PlanPago
    {
        if (PlanPago::query()->where('matricula_id', $matricula->id)->exists()) {
            throw ValidationException::withMessages([
                'matricula' => 'Este estudiante ya tiene un plan de pago para esta matrícula.',
            ]);
        }

        return DB::transaction(function () use ($matricula, $numeroCuotas, $montoTotal) {
            $plan = PlanPago::query()->create([
                'matricula_id' => $matricula->id,
                'numero_cuotas' => $numeroCuotas->value,
                'monto_total' => $montoTotal,
            ]);

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
