<?php

declare(strict_types=1);

namespace App\Modules\Pagos\Services;

use App\Modules\Pagos\Models\Pago;
use App\Modules\Pagos\Models\Recibo;
use Barryvdh\DomPDF\Facade\Pdf;

class ReciboService
{
    public function emitir(Pago $pago): Recibo
    {
        $numeroRecibo = $this->siguienteNumero();

        /** @var Recibo $recibo */
        $recibo = Recibo::query()->create([
            'pago_id' => $pago->id,
            'numero_recibo' => $numeroRecibo,
            'emitido_en' => now(),
        ]);

        $pdf = Pdf::loadView('pdf.recibo', ['pago' => $pago, 'recibo' => $recibo]);

        $recibo->addMediaFromString($pdf->output())
            ->usingFileName("{$numeroRecibo}.pdf")
            ->toMediaCollection('pdf');

        return $recibo;
    }

    private function siguienteNumero(): string
    {
        $anio = now()->format('Y');
        $emitidosEsteAnio = Recibo::query()->where('numero_recibo', 'like', "R-{$anio}-%")->count();

        return sprintf('R-%s-%06d', $anio, $emitidosEsteAnio + 1);
    }
}
