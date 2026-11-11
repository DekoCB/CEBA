<?php

namespace Tests\Feature\FlujoCaja;

use App\Models\User;
use App\Modules\FlujoCaja\Enums\CategoriaEgresoEnum;
use App\Modules\FlujoCaja\Enums\SedeEnum;
use App\Modules\FlujoCaja\Models\Egreso;
use App\Modules\FlujoCaja\Services\FlujoCajaService;
use App\Modules\Pagos\Enums\EstadoPagoEnum;
use App\Modules\Pagos\Enums\MetodoPagoEnum;
use App\Modules\Pagos\Enums\SerieReciboEnum;
use App\Modules\Pagos\Models\Pago;
use App\Modules\Pagos\Models\Recibo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FlujoCajaServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): FlujoCajaService
    {
        return $this->app->make(FlujoCajaService::class);
    }

    public function test_registrar_egreso_crea_el_registro_y_adjunta_el_comprobante(): void
    {
        Storage::fake('public');
        $usuario = User::factory()->create();

        $egreso = $this->service()->registrarEgreso([
            'categoria' => CategoriaEgresoEnum::SERVICIOS,
            'descripcion' => 'Recibo de luz de setiembre',
            'monto' => 150.5,
            'metodo' => MetodoPagoEnum::TRANSFERENCIA,
            'fecha_egreso' => '2026-09-05',
        ], UploadedFile::fake()->create('recibo.pdf', 100, 'application/pdf'), $usuario->id);

        $this->assertDatabaseHas('egresos', [
            'id' => $egreso->id,
            'categoria' => 'servicios',
            'monto' => '150.50',
            'metodo' => 'transferencia',
            'registrado_por' => $usuario->id,
        ]);
        $this->assertNotNull($egreso->getFirstMedia('comprobante'));
    }

    public function test_registrar_egreso_sin_comprobante_no_falla(): void
    {
        $egreso = $this->service()->registrarEgreso([
            'categoria' => CategoriaEgresoEnum::OTRO,
            'descripcion' => null,
            'monto' => 30,
            'metodo' => MetodoPagoEnum::EFECTIVO,
            'fecha_egreso' => now()->format('Y-m-d'),
        ], null, null);

        $this->assertDatabaseHas('egresos', ['id' => $egreso->id]);
        $this->assertNull($egreso->getFirstMedia('comprobante'));
    }

    private function pagoAprobadoConRecibo(float $monto, string $fechaAprobacion, SerieReciboEnum $serie = SerieReciboEnum::ORIGINAL): Pago
    {
        $pago = Pago::factory()->aprobado()->create(['monto' => $monto, 'fecha_aprobacion' => $fechaAprobacion]);
        Recibo::factory()->create(['pago_id' => $pago->id, 'serie' => $serie]);

        return $pago;
    }

    public function test_ingresos_del_periodo_suma_solo_pagos_aprobados_en_el_rango_y_la_sede(): void
    {
        $this->pagoAprobadoConRecibo(100, '2026-09-10', SerieReciboEnum::ORIGINAL);
        $this->pagoAprobadoConRecibo(50, '2026-08-15', SerieReciboEnum::ORIGINAL);
        // No debería contar: sigue pendiente.
        Pago::factory()->create(['estado' => EstadoPagoEnum::PENDIENTE, 'monto' => 999]);
        // No debería contar: es de la otra sede (serie CA).
        $this->pagoAprobadoConRecibo(999, '2026-09-12', SerieReciboEnum::COPIA);

        $total = $this->service()->ingresosDelPeriodo(Carbon::parse('2026-09-01'), Carbon::parse('2026-09-30'), SedeEnum::PB);

        $this->assertSame(100.0, $total);
    }

    public function test_ingresos_del_periodo_de_la_sede_ca_no_se_mezcla_con_pb(): void
    {
        $this->pagoAprobadoConRecibo(100, '2026-09-10', SerieReciboEnum::ORIGINAL);
        $this->pagoAprobadoConRecibo(70, '2026-09-11', SerieReciboEnum::COPIA);

        $totalCa = $this->service()->ingresosDelPeriodo(Carbon::parse('2026-09-01'), Carbon::parse('2026-09-30'), SedeEnum::CA);

        $this->assertSame(70.0, $totalCa);
    }

    public function test_egresos_del_periodo_suma_los_egresos_del_rango_y_la_sede(): void
    {
        Egreso::factory()->create(['monto' => 40, 'fecha_egreso' => '2026-09-05', 'sede' => SedeEnum::PB]);
        Egreso::factory()->create(['monto' => 60, 'fecha_egreso' => '2026-09-20', 'sede' => SedeEnum::PB]);
        Egreso::factory()->create(['monto' => 999, 'fecha_egreso' => '2026-08-01', 'sede' => SedeEnum::PB]);
        // No debería contar: es de la otra sede.
        Egreso::factory()->create(['monto' => 999, 'fecha_egreso' => '2026-09-10', 'sede' => SedeEnum::CA]);

        $total = $this->service()->egresosDelPeriodo(Carbon::parse('2026-09-01'), Carbon::parse('2026-09-30'), SedeEnum::PB);

        $this->assertSame(100.0, $total);
    }

    public function test_un_egreso_sin_sede_explicita_queda_pb_por_defecto(): void
    {
        $this->service()->registrarEgreso([
            'categoria' => CategoriaEgresoEnum::OTRO,
            'descripcion' => null,
            'monto' => 25,
            'metodo' => MetodoPagoEnum::EFECTIVO,
            'fecha_egreso' => '2026-09-05',
        ], null, null);

        $total = $this->service()->egresosDelPeriodo(Carbon::parse('2026-09-01'), Carbon::parse('2026-09-30'), SedeEnum::PB);

        $this->assertSame(25.0, $total);
    }

    public function test_movimientos_del_periodo_mezcla_ingresos_y_egresos_ordenados_por_fecha(): void
    {
        $this->pagoAprobadoConRecibo(100, '2026-09-10');
        Egreso::factory()->create(['monto' => 40, 'fecha_egreso' => '2026-09-20', 'sede' => SedeEnum::PB]);

        $movimientos = $this->service()->movimientosDelPeriodo(Carbon::parse('2026-09-01'), Carbon::parse('2026-09-30'), SedeEnum::PB);

        $this->assertCount(2, $movimientos);
        $this->assertSame('egreso', $movimientos->first()['tipo']);
        $this->assertSame('ingreso', $movimientos->last()['tipo']);
    }
}
