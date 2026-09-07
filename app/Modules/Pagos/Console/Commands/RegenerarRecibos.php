<?php

declare(strict_types=1);

namespace App\Modules\Pagos\Console\Commands;

use App\Modules\Pagos\Models\Recibo;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Console\Command;

/**
 * Migra los recibos existentes (creados antes de las dos series) al nuevo
 * formato: reasigna un correlativo continuo en orden de creación y
 * regenera el PDF de cada uno con ambas series (001 y 002).
 */
class RegenerarRecibos extends Command
{
    protected $signature = 'recibos:regenerar';

    protected $description = 'Reasigna el correlativo de cada recibo existente y regenera su PDF con el formato de dos series';

    public function handle(): int
    {
        $recibos = Recibo::query()
            ->with(['pago.estudiante', 'pago.concepto', 'pago.partes', 'pago.cuota.planPago.matricula.ciclo'])
            ->orderBy('id')
            ->get();

        // Primero se pasan todos a un valor temporal único: si se reasignara
        // el correlativo definitivo fila por fila, una fila podría chocar
        // contra el numero_recibo (todavía sin actualizar) de otra -- el
        // unique() de la columna lo rechazaría a mitad de camino.
        foreach ($recibos as $recibo) {
            $recibo->update(['numero_recibo' => "tmp-{$recibo->id}"]);
        }

        $correlativo = 0;

        foreach ($recibos as $recibo) {
            $correlativo++;
            $numero = sprintf('%06d', $correlativo);

            $recibo->update(['numero_recibo' => $numero]);

            $pdf = Pdf::loadView('pdf.recibo', ['pago' => $recibo->pago, 'recibo' => $recibo]);

            $recibo->addMediaFromString($pdf->output())
                ->usingFileName("{$numero}.pdf")
                ->toMediaCollection('pdf');
        }

        $this->info("Recibos regenerados: {$recibos->count()}");

        return self::SUCCESS;
    }
}
