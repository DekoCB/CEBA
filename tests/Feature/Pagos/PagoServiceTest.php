<?php

namespace Tests\Feature\Pagos;

use App\Models\User;
use App\Modules\Matricula\Models\Estudiante;
use App\Modules\Pagos\Enums\EstadoPagoEnum;
use App\Modules\Pagos\Enums\MetodoPagoEnum;
use App\Modules\Pagos\Enums\SerieReciboEnum;
use App\Modules\Pagos\Models\CargoAdicional;
use App\Modules\Pagos\Models\ConceptoPago;
use App\Modules\Pagos\Models\Cuota;
use App\Modules\Pagos\Models\Pago;
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

    /**
     * @return array{concepto_id: int, cuota_id: ?int, cargo_adicional_id: ?int, monto: float, detalle?: ?string}
     */
    private function destino(ConceptoPago $concepto, float $monto, ?Cuota $cuota = null, ?CargoAdicional $cargoAdicional = null, ?string $detalle = null): array
    {
        return [
            'concepto_id' => $concepto->id,
            'cuota_id' => $cuota?->id,
            'cargo_adicional_id' => $cargoAdicional?->id,
            'monto' => $monto,
            'detalle' => $detalle,
        ];
    }

    public function test_registrar_un_pago_queda_en_estado_pendiente(): void
    {
        $estudiante = Estudiante::factory()->create();
        $concepto = ConceptoPago::factory()->create();

        $pago = $this->service()->registrar($estudiante, [$this->destino($concepto, 100.0)], [['monto' => 100.0, 'metodo' => 'yape']], null, null);

        $this->assertSame(EstadoPagoEnum::PENDIENTE, $pago->estado);
        $this->assertSame(MetodoPagoEnum::YAPE, $pago->metodo);
        $this->assertSame('100.00', $pago->monto);
    }

    public function test_no_permite_registrar_dos_pagos_pendientes_para_la_misma_cuota(): void
    {
        $estudiante = Estudiante::factory()->create();
        $concepto = ConceptoPago::factory()->create();
        $cuota = Cuota::factory()->create();

        $this->service()->registrar($estudiante, [$this->destino($concepto, 100.0, cuota: $cuota)], [['monto' => 100.0, 'metodo' => 'yape']], null, null);

        $this->expectException(ValidationException::class);

        $this->service()->registrar($estudiante, [$this->destino($concepto, 100.0, cuota: $cuota)], [['monto' => 100.0, 'metodo' => 'transferencia']], null, null);
    }

    public function test_no_permite_registrar_un_pago_sin_ninguna_parte(): void
    {
        $estudiante = Estudiante::factory()->create();
        $concepto = ConceptoPago::factory()->create();

        $this->expectException(InvalidArgumentException::class);

        $this->service()->registrar($estudiante, [$this->destino($concepto, 100.0)], [], null, null);
    }

    public function test_no_permite_registrar_un_pago_sin_ningun_destino(): void
    {
        $estudiante = Estudiante::factory()->create();

        $this->expectException(InvalidArgumentException::class);

        $this->service()->registrar($estudiante, [], [['monto' => 100.0, 'metodo' => 'yape']], null, null);
    }

    public function test_registrar_un_pago_en_varias_partes_suma_el_monto_total_y_marca_metodo_mixto(): void
    {
        $estudiante = Estudiante::factory()->create();
        $concepto = ConceptoPago::factory()->create();

        $pago = $this->service()->registrar($estudiante, [$this->destino($concepto, 100.0)], [
            ['monto' => 60.0, 'metodo' => 'efectivo'],
            ['monto' => 40.0, 'metodo' => 'yape'],
        ], null, null);

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

        $pago = $this->service()->registrar($estudiante, [$this->destino($concepto, 100.0)], [
            ['monto' => 60.0, 'metodo' => 'efectivo'],
            ['monto' => 40.0, 'metodo' => 'efectivo'],
        ], null, null);

        $this->assertSame('100.00', $pago->monto);
        $this->assertSame(MetodoPagoEnum::EFECTIVO, $pago->metodo);
        $this->assertCount(2, $pago->partes);
    }

    public function test_aprobar_un_pago_marca_la_cuota_como_pagada_y_genera_recibo(): void
    {
        $estudiante = Estudiante::factory()->create();
        $concepto = ConceptoPago::factory()->create();
        $cuota = Cuota::factory()->create();

        $pago = $this->service()->registrar($estudiante, [$this->destino($concepto, (float) $cuota->monto, cuota: $cuota)], [['monto' => (float) $cuota->monto, 'metodo' => 'yape']], null, null);

        $aprobado = $this->service()->aprobar($pago, $this->aprobador(), SerieReciboEnum::ORIGINAL);

        $this->assertSame(EstadoPagoEnum::APROBADO, $aprobado->estado);
        $this->assertSame('pagado', $cuota->fresh()->estado->value);
        $this->assertNotNull($aprobado->recibo);
        $this->assertSame(SerieReciboEnum::ORIGINAL, $aprobado->recibo->serie);
        $this->assertNotNull($aprobado->recibo->getFirstMedia('pdf'));
    }

    public function test_un_pago_parcial_no_marca_la_cuota_como_pagada_y_el_saldo_se_va_descontando(): void
    {
        $estudiante = Estudiante::factory()->create();
        $concepto = ConceptoPago::factory()->create();
        $cuota = Cuota::factory()->create(['monto' => 80]);

        $primerPago = $this->service()->registrar($estudiante, [$this->destino($concepto, 40.0, cuota: $cuota)], [['monto' => 40.0, 'metodo' => 'yape']], null, null);
        $this->service()->aprobar($primerPago, $this->aprobador(), SerieReciboEnum::ORIGINAL);

        $cuota->refresh();
        $this->assertSame('pendiente', $cuota->estado->value);
        $this->assertSame(40.0, $cuota->saldoPendiente());

        $segundoPago = $this->service()->registrar($estudiante, [$this->destino($concepto, 40.0, cuota: $cuota)], [['monto' => 40.0, 'metodo' => 'efectivo']], null, null);
        $this->service()->aprobar($segundoPago, $this->aprobador(), SerieReciboEnum::ORIGINAL);

        $cuota->refresh();
        $this->assertSame('pagado', $cuota->estado->value);
        $this->assertSame(0.0, $cuota->saldoPendiente());
    }

    /**
     * Pedido del cliente: un pago puede cubrir varias cuotas a la vez, ej.
     * una completa y otra parcial, en un solo registro.
     */
    public function test_un_pago_cubre_dos_cuotas_a_la_vez_una_completa_y_otra_parcial(): void
    {
        $estudiante = Estudiante::factory()->create();
        $concepto = ConceptoPago::factory()->create();
        $cuota1 = Cuota::factory()->create(['monto' => 70]);
        $cuota2 = Cuota::factory()->create(['monto' => 80]);

        $pago = $this->service()->registrar(
            $estudiante,
            [$this->destino($concepto, 70.0, cuota: $cuota1), $this->destino($concepto, 50.0, cuota: $cuota2)],
            [['monto' => 120.0, 'metodo' => 'yape']],
            null,
            null,
        );

        $this->assertCount(2, $pago->destinos);
        $this->assertSame('120.00', $pago->monto);

        $this->service()->aprobar($pago, $this->aprobador(), SerieReciboEnum::ORIGINAL);

        $this->assertSame('pagado', $cuota1->fresh()->estado->value);
        $this->assertSame(0.0, $cuota1->fresh()->saldoPendiente());
        $this->assertSame('pendiente', $cuota2->fresh()->estado->value);
        $this->assertSame(30.0, $cuota2->fresh()->saldoPendiente());
    }

    public function test_un_pago_cubre_una_cuota_y_un_cargo_adicional_a_la_vez(): void
    {
        $estudiante = Estudiante::factory()->create();
        $concepto = ConceptoPago::factory()->create();
        $cuota = Cuota::factory()->create(['monto' => 70]);
        $cargo = CargoAdicional::factory()->create(['estudiante_id' => $estudiante->id, 'monto' => 50]);

        $pago = $this->service()->registrar(
            $estudiante,
            [$this->destino($concepto, 70.0, cuota: $cuota), $this->destino($concepto, 50.0, cargoAdicional: $cargo)],
            [['monto' => 120.0, 'metodo' => 'yape']],
            null,
            null,
        );

        $this->service()->aprobar($pago, $this->aprobador(), SerieReciboEnum::ORIGINAL);

        $this->assertSame('pagado', $cuota->fresh()->estado->value);
        $this->assertSame('pagado', $cargo->fresh()->estado->value);
    }

    public function test_rechaza_destinos_cuya_suma_no_coincide_con_la_suma_de_partes(): void
    {
        $estudiante = Estudiante::factory()->create();
        $concepto = ConceptoPago::factory()->create();

        $this->expectException(ValidationException::class);

        $this->service()->registrar($estudiante, [$this->destino($concepto, 100.0)], [['monto' => 90.0, 'metodo' => 'yape']], null, null);
    }

    public function test_rechaza_la_misma_cuota_repetida_dentro_de_un_solo_pago(): void
    {
        $estudiante = Estudiante::factory()->create();
        $concepto = ConceptoPago::factory()->create();
        $cuota = Cuota::factory()->create(['monto' => 70]);

        $this->expectException(InvalidArgumentException::class);

        $this->service()->registrar(
            $estudiante,
            [$this->destino($concepto, 35.0, cuota: $cuota), $this->destino($concepto, 35.0, cuota: $cuota)],
            [['monto' => 70.0, 'metodo' => 'yape']],
            null,
            null,
        );
    }

    public function test_no_permite_registrar_dos_pagos_pendientes_para_el_mismo_cargo_adicional(): void
    {
        $estudiante = Estudiante::factory()->create();
        $concepto = ConceptoPago::factory()->create();
        $cargo = CargoAdicional::factory()->create();

        $this->service()->registrar($estudiante, [$this->destino($concepto, 50.0, cargoAdicional: $cargo)], [['monto' => 50.0, 'metodo' => 'yape']], null, null);

        $this->expectException(ValidationException::class);

        $this->service()->registrar($estudiante, [$this->destino($concepto, 50.0, cargoAdicional: $cargo)], [['monto' => 50.0, 'metodo' => 'transferencia']], null, null);
    }

    public function test_un_pago_parcial_no_marca_el_cargo_adicional_como_pagado_y_el_saldo_se_va_descontando(): void
    {
        $estudiante = Estudiante::factory()->create();
        $concepto = ConceptoPago::factory()->create();
        $cargo = CargoAdicional::factory()->create(['monto' => 80]);

        $primerPago = $this->service()->registrar($estudiante, [$this->destino($concepto, 40.0, cargoAdicional: $cargo)], [['monto' => 40.0, 'metodo' => 'yape']], null, null);
        $this->service()->aprobar($primerPago, $this->aprobador(), SerieReciboEnum::ORIGINAL);

        $cargo->refresh();
        $this->assertSame('pendiente', $cargo->estado->value);
        $this->assertSame(40.0, $cargo->saldoPendiente());

        $segundoPago = $this->service()->registrar($estudiante, [$this->destino($concepto, 40.0, cargoAdicional: $cargo)], [['monto' => 40.0, 'metodo' => 'efectivo']], null, null);
        $this->service()->aprobar($segundoPago, $this->aprobador(), SerieReciboEnum::ORIGINAL);

        $cargo->refresh();
        $this->assertSame('pagado', $cargo->estado->value);
        $this->assertSame(0.0, $cargo->saldoPendiente());
    }

    public function test_editar_monto_de_cargo_adicional_actualiza_el_monto(): void
    {
        $cargo = CargoAdicional::factory()->create(['monto' => 50]);

        $actualizado = $this->service()->editarMontoCargoAdicional($cargo, 80.0);

        $this->assertSame('80.00', $actualizado->monto);
    }

    public function test_editar_monto_de_cargo_adicional_a_cero_o_negativo_lanza_excepcion(): void
    {
        $cargo = CargoAdicional::factory()->create(['monto' => 50]);

        $this->expectException(ValidationException::class);

        $this->service()->editarMontoCargoAdicional($cargo, 0.0);
    }

    public function test_editar_monto_de_cargo_adicional_menor_a_lo_ya_pagado_lanza_excepcion(): void
    {
        $estudiante = Estudiante::factory()->create();
        $concepto = ConceptoPago::factory()->create();
        $cargo = CargoAdicional::factory()->create(['estudiante_id' => $estudiante->id, 'monto' => 80]);

        $pago = $this->service()->registrar($estudiante, [$this->destino($concepto, 40.0, cargoAdicional: $cargo)], [['monto' => 40.0, 'metodo' => 'yape']], null, null);
        $this->service()->aprobar($pago, $this->aprobador(), SerieReciboEnum::ORIGINAL);

        $this->expectException(ValidationException::class);

        $this->service()->editarMontoCargoAdicional($cargo, 30.0);
    }

    /**
     * Subir el monto de un cargo ya pagado del todo debe reabrirlo: el
     * estado siempre tiene que reflejar el saldo real, no quedarse en
     * "pagado" con un saldo pendiente mayor a cero -- mismo requisito de
     * "sin huecos" que el resto de la funcionalidad.
     */
    public function test_editar_monto_de_cargo_adicional_ya_pagado_lo_vuelve_a_pendiente_si_deja_saldo(): void
    {
        $estudiante = Estudiante::factory()->create();
        $concepto = ConceptoPago::factory()->create();
        $cargo = CargoAdicional::factory()->create(['estudiante_id' => $estudiante->id, 'monto' => 50]);

        $pago = $this->service()->registrar($estudiante, [$this->destino($concepto, 50.0, cargoAdicional: $cargo)], [['monto' => 50.0, 'metodo' => 'yape']], null, null);
        $this->service()->aprobar($pago, $this->aprobador(), SerieReciboEnum::ORIGINAL);

        $cargo->refresh();
        $this->assertSame('pagado', $cargo->estado->value);

        $actualizado = $this->service()->editarMontoCargoAdicional($cargo, 70.0);

        $this->assertSame('pendiente', $actualizado->estado->value);
        $this->assertSame(20.0, $actualizado->saldoPendiente());
    }

    public function test_registrar_compromiso_cargo_adicional_guarda_fecha_y_monto(): void
    {
        $cargo = CargoAdicional::factory()->create();

        $actualizado = $this->service()->registrarCompromisoCargoAdicional($cargo, '2026-11-01', 30.0);

        $this->assertSame('2026-11-01', $actualizado->fecha_compromiso->format('Y-m-d'));
        $this->assertSame('30.00', $actualizado->monto_compromiso);
    }

    public function test_registrar_compromiso_cargo_adicional_sin_monto_lo_deja_nulo(): void
    {
        $cargo = CargoAdicional::factory()->create();

        $actualizado = $this->service()->registrarCompromisoCargoAdicional($cargo, '2026-11-01');

        $this->assertNull($actualizado->monto_compromiso);
    }

    public function test_registrar_compromiso_cargo_adicional_que_ya_no_esta_pendiente_lanza_excepcion(): void
    {
        $cargo = CargoAdicional::factory()->create(['estado' => 'pagado']);

        $this->expectException(ValidationException::class);

        $this->service()->registrarCompromisoCargoAdicional($cargo, '2026-11-01');
    }

    public function test_nombre_concepto_usa_el_concepto_del_cargo_adicional_cuando_corresponde(): void
    {
        $estudiante = Estudiante::factory()->create();
        $concepto = ConceptoPago::factory()->create(['nombre' => 'Otro']);
        $cargo = CargoAdicional::factory()->create(['concepto' => 'Convalidación']);

        $pago = $this->service()->registrar($estudiante, [$this->destino($concepto, 50.0, cargoAdicional: $cargo)], [['monto' => 50.0, 'metodo' => 'yape']], null, null);

        $this->assertSame('Convalidación', $pago->nombreConcepto());
    }

    public function test_nombre_concepto_con_varios_destinos_devuelve_un_resumen_unido(): void
    {
        $estudiante = Estudiante::factory()->create();
        $conceptoA = ConceptoPago::factory()->create(['nombre' => 'Mensualidad']);
        $conceptoB = ConceptoPago::factory()->create(['nombre' => 'Matrícula']);

        $pago = $this->service()->registrar(
            $estudiante,
            [$this->destino($conceptoA, 70.0), $this->destino($conceptoB, 50.0)],
            [['monto' => 120.0, 'metodo' => 'yape']],
            null,
            null,
        );

        $this->assertSame('Mensualidad, Matrícula', $pago->nombreConcepto());
    }

    public function test_registrar_guarda_la_nota_de_una_parte_cuando_se_indica(): void
    {
        $estudiante = Estudiante::factory()->create();
        $concepto = ConceptoPago::factory()->create();

        $pago = $this->service()->registrar($estudiante, [$this->destino($concepto, 100.0)], [
            ['monto' => 100.0, 'metodo' => 'yape', 'nota' => 'Director'],
        ], null, null);

        $this->assertSame('Director', $pago->partes->first()->nota);
        $this->assertSame('Yape Director', $pago->partes->first()->metodoConNota());
        $this->assertSame('Yape Director', $pago->medioPagoResumen());
    }

    public function test_registrar_sin_nota_la_deja_en_null_y_el_metodo_se_muestra_solo(): void
    {
        $estudiante = Estudiante::factory()->create();
        $concepto = ConceptoPago::factory()->create();

        $pago = $this->service()->registrar($estudiante, [$this->destino($concepto, 100.0)], [['monto' => 100.0, 'metodo' => 'yape']], null, null);

        $this->assertNull($pago->partes->first()->nota);
        $this->assertSame('Yape', $pago->partes->first()->metodoConNota());
    }

    /**
     * Pedido del cliente: la fecha de pago (a diferencia de la fecha de
     * emisión del recibo, que siempre es la de hoy y no se toca) debe
     * poder editarse, ej. para un cobro en efectivo recibido unos días
     * antes de que recién se registre en el sistema.
     */
    public function test_registrar_con_fecha_de_pago_explicita_la_guarda_en_vez_de_hoy(): void
    {
        $estudiante = Estudiante::factory()->create();
        $concepto = ConceptoPago::factory()->create();

        $pago = $this->service()->registrar(
            $estudiante,
            [$this->destino($concepto, 100.0)],
            [['monto' => 100.0, 'metodo' => 'efectivo']],
            null,
            null,
            null,
            '2026-09-01',
        );

        $this->assertSame('2026-09-01', $pago->fecha_pago->format('Y-m-d'));
    }

    public function test_registrar_sin_fecha_de_pago_usa_hoy(): void
    {
        $estudiante = Estudiante::factory()->create();
        $concepto = ConceptoPago::factory()->create();

        $pago = $this->service()->registrar($estudiante, [$this->destino($concepto, 100.0)], [['monto' => 100.0, 'metodo' => 'efectivo']], null, null);

        $this->assertSame(now()->format('Y-m-d'), $pago->fecha_pago->format('Y-m-d'));
    }

    public function test_aprobar_respeta_la_serie_elegida_a_mano(): void
    {
        $estudiante = Estudiante::factory()->create();
        $concepto = ConceptoPago::factory()->create();
        $pago = $this->service()->registrar($estudiante, [$this->destino($concepto, 100.0)], [['monto' => 100.0, 'metodo' => 'efectivo']], null, null);

        $aprobado = $this->service()->aprobar($pago, $this->aprobador(), SerieReciboEnum::COPIA);

        $this->assertSame(SerieReciboEnum::COPIA, $aprobado->recibo->serie);
    }

    public function test_rechazar_un_pago_registra_el_motivo(): void
    {
        $estudiante = Estudiante::factory()->create();
        $concepto = ConceptoPago::factory()->create();

        $pago = $this->service()->registrar($estudiante, [$this->destino($concepto, 100.0)], [['monto' => 100.0, 'metodo' => 'efectivo']], null, null);

        $rechazado = $this->service()->rechazar($pago, $this->aprobador(), 'Comprobante ilegible');

        $this->assertSame(EstadoPagoEnum::RECHAZADO, $rechazado->estado);
        $this->assertSame('Comprobante ilegible', $rechazado->motivo_rechazo);
    }

    public function test_no_permite_aprobar_un_pago_ya_procesado(): void
    {
        $estudiante = Estudiante::factory()->create();
        $concepto = ConceptoPago::factory()->create();
        $pago = $this->service()->registrar($estudiante, [$this->destino($concepto, 100.0)], [['monto' => 100.0, 'metodo' => 'efectivo']], null, null);
        $this->service()->aprobar($pago, $this->aprobador(), SerieReciboEnum::ORIGINAL);

        $this->expectException(ValidationException::class);

        $this->service()->aprobar($pago, $this->aprobador(), SerieReciboEnum::ORIGINAL);
    }

    public function test_pendientes_de_aprobacion_solo_incluye_pagos_en_estado_pendiente(): void
    {
        $estudiante = Estudiante::factory()->create();
        $concepto = ConceptoPago::factory()->create();

        $pendiente = $this->service()->registrar($estudiante, [$this->destino($concepto, 100.0)], [['monto' => 100.0, 'metodo' => 'efectivo']], null, null);
        $aprobado = $this->service()->registrar($estudiante, [$this->destino($concepto, 100.0)], [['monto' => 100.0, 'metodo' => 'efectivo']], null, null);
        $this->service()->aprobar($aprobado, $this->aprobador(), SerieReciboEnum::ORIGINAL);

        $cola = $this->service()->pendientesDeAprobacion();

        $this->assertTrue($cola->contains('id', $pendiente->id));
        $this->assertFalse($cola->contains('id', $aprobado->id));
    }

    /**
     * Varios tests fuera de esta carpeta (HistorialEstudianteServiceTest,
     * CobranzaServiceTest, MatriculaPermisosTest) crean `Pago::factory()`
     * directo sin pasar por el servicio -- el hook de la factory debe
     * seguir creando su PagoDestino correspondiente para que montoPagado()
     * siga viendo esos pagos.
     */
    public function test_la_factory_de_pago_crea_automaticamente_su_destino_correspondiente(): void
    {
        $cuota = Cuota::factory()->create();

        $pago = Pago::factory()->create(['cuota_id' => $cuota->id, 'cargo_adicional_id' => null, 'monto' => 50]);

        $this->assertDatabaseHas('pago_destinos', [
            'pago_id' => $pago->id,
            'cuota_id' => $cuota->id,
            'monto' => 50,
        ]);
    }
}
