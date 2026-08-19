<?php

namespace Tests\Feature\Pagos;

use App\Modules\Matricula\Models\Matricula;
use App\Modules\Pagos\Enums\NumeroCuotasEnum;
use App\Modules\Pagos\Services\PlanPagoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PlanPagoServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): PlanPagoService
    {
        return $this->app->make(PlanPagoService::class);
    }

    public function test_crear_un_plan_genera_las_cuotas_indicadas(): void
    {
        $matricula = Matricula::factory()->create(['fecha_matricula' => now()]);

        $plan = $this->service()->crear($matricula, NumeroCuotasEnum::SEIS, 600.0);

        $this->assertSame(6, $plan->cuotas()->count());
        $this->assertEquals(600.0, (float) $plan->cuotas()->sum('monto'));
    }

    public function test_la_ultima_cuota_absorbe_el_redondeo(): void
    {
        $matricula = Matricula::factory()->create(['fecha_matricula' => now()]);

        $plan = $this->service()->crear($matricula, NumeroCuotasEnum::OCHO, 100.0);

        $cuotas = $plan->cuotas()->orderBy('numero')->get();

        $this->assertEquals(100.0, (float) $cuotas->sum('monto'));
        $this->assertEqualsWithDelta(12.5, (float) $cuotas->first()->monto, 0.01);
    }

    public function test_las_fechas_de_vencimiento_son_mensuales_desde_la_matricula(): void
    {
        $matricula = Matricula::factory()->create(['fecha_matricula' => '2026-03-01']);

        $plan = $this->service()->crear($matricula, NumeroCuotasEnum::SEIS, 600.0);

        $cuotas = $plan->cuotas()->orderBy('numero')->get();

        $this->assertSame('2026-04-01', $cuotas[0]->fecha_vencimiento->format('Y-m-d'));
        $this->assertSame('2026-09-01', $cuotas[5]->fecha_vencimiento->format('Y-m-d'));
    }

    public function test_crear_con_cuotas_personalizadas_usa_los_montos_y_fechas_dados(): void
    {
        $matricula = Matricula::factory()->create(['fecha_matricula' => now()]);

        $plan = $this->service()->crear($matricula, NumeroCuotasEnum::UNA, 350.0, [
            ['monto' => 350.0, 'fecha_vencimiento' => '2026-05-15'],
        ]);

        $cuota = $plan->cuotas()->firstOrFail();
        $this->assertSame(1, $cuota->numero);
        $this->assertSame('350.00', $cuota->monto);
        $this->assertSame('2026-05-15', $cuota->fecha_vencimiento->format('Y-m-d'));
    }

    public function test_no_permite_crear_dos_planes_para_la_misma_matricula(): void
    {
        $matricula = Matricula::factory()->create(['fecha_matricula' => now()]);
        $this->service()->crear($matricula, NumeroCuotasEnum::UNA, 100.0);

        $this->expectException(ValidationException::class);

        $this->service()->crear($matricula, NumeroCuotasEnum::SEIS, 600.0);
    }
}
