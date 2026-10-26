<?php

declare(strict_types=1);

namespace App\Modules\Pagos\Console\Commands;

use App\Modules\Matricula\Models\Estudiante;
use App\Modules\Pagos\Models\BloqueoAcceso;
use App\Modules\Pagos\Models\Cuota;
use App\Modules\Pagos\Services\BloqueoAccesoService;
use Illuminate\Console\Command;

/**
 * BloqueoAccesoService::evaluarYDesbloquear() solo se dispara desde
 * acciones reales (aprobar un pago, registrar un compromiso) -- nada lo
 * vuelve a correr cuando el tiempo pasa solo, así que un compromiso de
 * pago que venció sin que el estudiante pagara dejaría el bloqueo general
 * (el que lee el flag cacheado BloqueoAcceso.activo) desactualizado
 * indefinidamente. Este comando recorre, una vez al día, a los
 * estudiantes que podrían necesitar recalcularse -- los que ya tienen un
 * bloqueo activo (por si corresponde levantarlo) o los que tienen alguna
 * cuota vencida (por si corresponde activarlo) -- y fuerza el recálculo.
 */
class ReevaluarBloqueosAcceso extends Command
{
    protected $signature = 'bloqueos:reevaluar';

    protected $description = 'Recalcula el bloqueo de acceso de estudiantes con cuotas vencidas o un bloqueo activo';

    public function handle(BloqueoAccesoService $bloqueos): int
    {
        $estudianteIds = BloqueoAcceso::query()
            ->where('activo', true)
            ->pluck('estudiante_id')
            ->merge(
                Cuota::query()
                    ->where('estado', 'pendiente')
                    ->where('fecha_vencimiento', '<', now()->toDateString())
                    ->with('planPago.matricula')
                    ->get()
                    ->pluck('planPago.matricula.estudiante_id')
                    ->filter(),
            )
            ->unique();

        $estudiantes = Estudiante::query()->whereIn('id', $estudianteIds)->get();

        foreach ($estudiantes as $estudiante) {
            $bloqueos->evaluarYDesbloquear($estudiante);
        }

        $this->info("Bloqueos reevaluados: {$estudiantes->count()} estudiantes.");

        return self::SUCCESS;
    }
}
