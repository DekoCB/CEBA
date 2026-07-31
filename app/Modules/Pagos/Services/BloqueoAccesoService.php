<?php

declare(strict_types=1);

namespace App\Modules\Pagos\Services;

use App\Modules\Matricula\Models\Estudiante;
use App\Modules\Pagos\Models\BloqueoAcceso;
use App\Modules\Pagos\Models\Cuota;
use Illuminate\Database\Eloquent\Collection;

class BloqueoAccesoService
{
    /**
     * Umbral institucional: dos o más cuotas vencidas sin pagar activan el
     * bloqueo de acceso a notas.
     */
    private const CUOTAS_VENCIDAS_PARA_BLOQUEAR = 2;

    /**
     * @return Collection<int, Cuota>
     */
    public function cuotasVencidasDe(Estudiante $estudiante): Collection
    {
        return Cuota::query()
            ->where('estado', 'pendiente')
            ->where('fecha_vencimiento', '<', now()->toDateString())
            ->whereHas('planPago', function ($query) use ($estudiante) {
                $query->whereHas('matricula', fn ($query) => $query->where('estudiante_id', $estudiante->id));
            })
            ->with('planPago')
            ->get();
    }

    public function estaBloqueado(Estudiante $estudiante): bool
    {
        return BloqueoAcceso::query()
            ->where('estudiante_id', $estudiante->id)
            ->where('activo', true)
            ->exists();
    }

    public function bloqueoActivoDe(Estudiante $estudiante): ?BloqueoAcceso
    {
        return BloqueoAcceso::query()
            ->where('estudiante_id', $estudiante->id)
            ->where('activo', true)
            ->latest('fecha_bloqueo')
            ->first();
    }

    /**
     * Recalcula si el estudiante debe estar bloqueado según sus cuotas
     * vencidas y crea o levanta el bloqueo según corresponda. Se llama tras
     * cada aprobación/rechazo de pago y tras crear un plan de pago.
     */
    public function evaluarYDesbloquear(Estudiante $estudiante): void
    {
        $cuotasVencidas = $this->cuotasVencidasDe($estudiante)->count();
        $bloqueoActivo = $this->bloqueoActivoDe($estudiante);

        if ($cuotasVencidas >= self::CUOTAS_VENCIDAS_PARA_BLOQUEAR) {
            if (! $bloqueoActivo) {
                BloqueoAcceso::query()->create([
                    'estudiante_id' => $estudiante->id,
                    'motivo' => "{$cuotasVencidas} cuotas vencidas sin pagar",
                    'fecha_bloqueo' => now(),
                    'activo' => true,
                ]);
            }

            return;
        }

        if ($bloqueoActivo) {
            $bloqueoActivo->update([
                'fecha_desbloqueo' => now(),
                'activo' => false,
            ]);
        }
    }
}
