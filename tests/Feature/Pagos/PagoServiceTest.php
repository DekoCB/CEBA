<?php

namespace Tests\Feature\Pagos;

use App\Models\User;
use App\Modules\Matricula\Models\Estudiante;
use App\Modules\Pagos\Enums\EstadoPagoEnum;
use App\Modules\Pagos\Enums\MetodoPagoEnum;
use App\Modules\Pagos\Models\ConceptoPago;
use App\Modules\Pagos\Models\Cuota;
use App\Modules\Pagos\Services\PagoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
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

        $pago = $this->service()->registrar($estudiante, $concepto, [['monto' => 100.0, 'metodo' => 'yape']], null, null, null);

        $this->assertSame(EstadoPagoEnum::PENDIENTE, $pago->estado);
        $this->assertSame(MetodoPagoEnum::YAPE, $pago->metodo);
        $this->assertSame('100.00', $pago->monto);
    }

    public function test_no_permite_registrar_dos_pagos_pendientes_para_la_misma_cuota(): void
    {
        $estudiante = Estudiante::factory()->create();
        $concepto = ConceptoPago::factory()->create();
        $cuota = Cuota::factory()->create();

        $this->service()->registrar($estudiante, $concepto, [['monto' => 100.0, 'metodo' => 'yape']], $cuota, null, null);

        $this->expectException(ValidationException::class);

        $this->service()->registrar($estudiante, $concepto, [['monto' => 100.0, 'metodo' => 'transferencia']], $cuota, null, null);
    }

    public function test_no_permite_registrar_un_pago_sin_ninguna_parte(): void
    {
        $estudiante = Estudiante::factory()->create();
        $concepto = ConceptoPago::factory()->create();

        $this->expectException(InvalidArgumentException::class);

        $this->service()->registrar($estudiante, $concepto, [], null, null, null);
    }

    public function test_registrar_un_pago_en_varias_partes_suma_el_monto_total_y_marca_metodo_mixto(): void
    {
        $estudiante = Estudiante::factory()->create();
        $concepto = ConceptoPago::factory()->create();

        $pago = $this->service()->registrar($estudiante, $concepto, [
            ['monto' => 60.0, 'metodo' => 'efectivo'],
            ['monto' => 40.0, 'metodo' => 'yape'],
        ], null, null, null);

        $this->assertSame('100.00', $pago->monto);
        $this->assertSame(MetodoPagoEnum::MIXTO, $pago->metodo);
        $this->assertCount(2, $pago->partes);
        $this->assertSame('60.00', $pago->partes->firstWhere('metodo', MetodoPagoEnum::EFECTIVO)->monto);
        $this->assertSame('40.00', $pago->partes->firstWhere('metodo', MetodoPagoEnum::YAPE)->monto);
    }

    public function test_registrar_un_pago_con_varias_partes_del_mismo_metodo_no_queda_mixto(): void
    {
        $estudiante = Estudiante::factory()->create();
        $concepto = ConceptoPago::factory()->create();

        $pago = $this->service()->registrar($estudiante, $concepto, [
            ['monto' => 60.0, 'metodo' => 'efectivo'],
            ['monto' => 40.0, 'metodo' => 'efectivo'],
        ], null, null, null);

        $this->assertSame('100.00', $pago->monto);
        $this->assertSame(MetodoPagoEnum::EFECTIVO, $pago->metodo);
        $this->assertCount(2, $pago->partes);
    }

    public function test_aprobar_un_pago_marca_la_cuota_como_pagada_y_genera_recibo(): void
    {
        $estudiante = Estudiante::factory()->create();
        $concepto = ConceptoPago::factory()->create();
        $cuota = Cuota::factory()->create();

        $pago = $this->service()->registrar($estudiante, $concepto, [['monto' => (float) $cuota->monto, 'metodo' => 'yape']], $cuota, null, null);

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

        $pago = $this->service()->registrar($estudiante, $concepto, [['monto' => 100.0, 'metodo' => 'efectivo']], null, null, null);

        $rechazado = $this->service()->rechazar($pago, $this->aprobador(), 'Comprobante ilegible');

        $this->assertSame(EstadoPagoEnum::RECHAZADO, $rechazado->estado);
        $this->assertSame('Comprobante ilegible', $rechazado->motivo_rechazo);
    }

    public function test_no_permite_aprobar_un_pago_ya_procesado(): void
    {
        $estudiante = Estudiante::factory()->create();
        $concepto = ConceptoPago::factory()->create();
        $pago = $this->service()->registrar($estudiante, $concepto, [['monto' => 100.0, 'metodo' => 'efectivo']], null, null, null);
        $this->service()->aprobar($pago, $this->aprobador());

        $this->expectException(ValidationException::class);

        $this->service()->aprobar($pago, $this->aprobador());
    }

    public function test_pendientes_de_aprobacion_solo_incluye_pagos_en_estado_pendiente(): void
    {
        $estudiante = Estudiante::factory()->create();
        $concepto = ConceptoPago::factory()->create();

        $pendiente = $this->service()->registrar($estudiante, $concepto, [['monto' => 100.0, 'metodo' => 'efectivo']], null, null, null);
        $aprobado = $this->service()->registrar($estudiante, $concepto, [['monto' => 100.0, 'metodo' => 'efectivo']], null, null, null);
        $this->service()->aprobar($aprobado, $this->aprobador());

        $cola = $this->service()->pendientesDeAprobacion();

        $this->assertTrue($cola->contains('id', $pendiente->id));
        $this->assertFalse($cola->contains('id', $aprobado->id));
    }
}
