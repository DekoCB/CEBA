<?php

namespace Tests\Feature\Pagos;

use App\Models\User;
use App\Modules\Matricula\Models\Estudiante;
use App\Modules\Pagos\Enums\EstadoPagoEnum;
use App\Modules\Pagos\Models\ConceptoPago;
use App\Modules\Pagos\Models\Cuota;
use App\Modules\Pagos\Services\PagoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PagoServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): PagoService
    {
        return $this->app->make(PagoService::class);
    }

    private function aprobador(): int
    {
        return User::factory()->create()->id;
    }

    public function test_registrar_un_pago_queda_en_estado_pendiente(): void
    {
        $estudiante = Estudiante::factory()->create();
        $concepto = ConceptoPago::factory()->create();

        $pago = $this->service()->registrar($estudiante, $concepto, 100.0, 'yape', null, null, null);

        $this->assertSame(EstadoPagoEnum::PENDIENTE, $pago->estado);
    }

    public function test_no_permite_registrar_dos_pagos_pendientes_para_la_misma_cuota(): void
    {
        $estudiante = Estudiante::factory()->create();
        $concepto = ConceptoPago::factory()->create();
        $cuota = Cuota::factory()->create();

        $this->service()->registrar($estudiante, $concepto, 100.0, 'yape', $cuota, null, null);

        $this->expectException(ValidationException::class);

        $this->service()->registrar($estudiante, $concepto, 100.0, 'transferencia', $cuota, null, null);
    }

    public function test_aprobar_un_pago_marca_la_cuota_como_pagada_y_genera_recibo(): void
    {
        $estudiante = Estudiante::factory()->create();
        $concepto = ConceptoPago::factory()->create();
        $cuota = Cuota::factory()->create();

        $pago = $this->service()->registrar($estudiante, $concepto, (float) $cuota->monto, 'yape', $cuota, null, null);

        $aprobado = $this->service()->aprobar($pago, $this->aprobador());

        $this->assertSame(EstadoPagoEnum::APROBADO, $aprobado->estado);
        $this->assertSame('pagado', $cuota->fresh()->estado->value);
        $this->assertNotNull($aprobado->recibo);
        $this->assertNotNull($aprobado->recibo->getFirstMedia('pdf'));
    }

    public function test_rechazar_un_pago_registra_el_motivo(): void
    {
        $estudiante = Estudiante::factory()->create();
        $concepto = ConceptoPago::factory()->create();

        $pago = $this->service()->registrar($estudiante, $concepto, 100.0, 'efectivo', null, null, null);

        $rechazado = $this->service()->rechazar($pago, $this->aprobador(), 'Comprobante ilegible');

        $this->assertSame(EstadoPagoEnum::RECHAZADO, $rechazado->estado);
        $this->assertSame('Comprobante ilegible', $rechazado->motivo_rechazo);
    }

    public function test_no_permite_aprobar_un_pago_ya_procesado(): void
    {
        $estudiante = Estudiante::factory()->create();
        $concepto = ConceptoPago::factory()->create();
        $pago = $this->service()->registrar($estudiante, $concepto, 100.0, 'efectivo', null, null, null);
        $this->service()->aprobar($pago, $this->aprobador());

        $this->expectException(ValidationException::class);

        $this->service()->aprobar($pago, $this->aprobador());
    }

    public function test_pendientes_de_aprobacion_solo_incluye_pagos_en_estado_pendiente(): void
    {
        $estudiante = Estudiante::factory()->create();
        $concepto = ConceptoPago::factory()->create();

        $pendiente = $this->service()->registrar($estudiante, $concepto, 100.0, 'efectivo', null, null, null);
        $aprobado = $this->service()->registrar($estudiante, $concepto, 100.0, 'efectivo', null, null, null);
        $this->service()->aprobar($aprobado, $this->aprobador());

        $cola = $this->service()->pendientesDeAprobacion();

        $this->assertTrue($cola->contains('id', $pendiente->id));
        $this->assertFalse($cola->contains('id', $aprobado->id));
    }
}
