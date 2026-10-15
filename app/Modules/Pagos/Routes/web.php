<?php

declare(strict_types=1);

use App\Modules\Pagos\Enums\EstadoPagoEnum;
use App\Modules\Pagos\Enums\SerieReciboEnum;
use App\Modules\Pagos\Models\Pago;
use App\Modules\Pagos\Models\Recibo;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::middleware(['auth'])->prefix('pagos')->name('pagos.')->group(function () {
    Volt::route('/', 'pagos.index')->name('index');

    Volt::route('mi-cuenta', 'pagos.mi-cuenta')->name('mi-cuenta');

    Volt::route('conceptos', 'pagos.conceptos')->name('conceptos');

    Volt::route('cuentas-bancarias', 'pagos.cuentas-bancarias')->name('cuentas-bancarias');

    // Vista previa del recibo antes de aprobar, desde la Cola de
    // aprobación -- no crea ni guarda ningún Recibo real, reutiliza la
    // misma plantilla que ReciboService::emitir() para que lo que se vea
    // acá sea idéntico en estructura a lo que se genera al aprobar.
    Route::get('{pago}/vista-previa', function (Pago $pago) {
        Gate::authorize('pagos.aprobar');
        abort_unless($pago->estado === EstadoPagoEnum::PENDIENTE, 404);

        $serie = SerieReciboEnum::tryFrom((string) request('serie')) ?? SerieReciboEnum::ORIGINAL;
        $recibo = new Recibo(['numero_recibo' => 'VISTA PREVIA', 'emitido_en' => now()]);

        return Pdf::loadView('pdf.recibo', [
            'pago' => $pago->load(['estudiante', 'destinos.concepto', 'destinos.cuota.planPago', 'destinos.cargoAdicional', 'partes']),
            'recibo' => $recibo,
            'serie' => $serie,
        ])->stream('vista-previa.pdf');
    })->name('vista-previa');
});
