<?php

namespace Tests\Feature\Reportes;

use App\Modules\Academico\Models\Horario;
use App\Modules\Certificados\Models\Certificado;
use App\Modules\Evaluaciones\Models\Calificacion;
use App\Modules\Evaluaciones\Models\Evaluacion;
use App\Modules\Evaluaciones\Models\Libreta;
use App\Modules\Matricula\Models\DocumentoEstudiante;
use App\Modules\Matricula\Models\Estudiante;
use App\Modules\Matricula\Models\Matricula;
use App\Modules\Pagos\Enums\EstadoCuotaEnum;
use App\Modules\Pagos\Models\Cuota;
use App\Modules\Pagos\Models\PlanPago;
use App\Modules\Reportes\Services\HistorialEstudianteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HistorialEstudianteServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): HistorialEstudianteService
    {
        return $this->app->make(HistorialEstudianteService::class);
    }

    public function test_dni_inexistente_devuelve_null(): void
    {
        $this->assertNull($this->service()->porDni('00000000'));
    }

    public function test_las_matriculas_quedan_en_orden_cronologico(): void
    {
        $estudiante = Estudiante::factory()->create(['dni' => '11111111']);
        Matricula::factory()->create(['estudiante_id' => $estudiante->id, 'fecha_matricula' => now()->subMonths(6)]);
        Matricula::factory()->create(['estudiante_id' => $estudiante->id, 'fecha_matricula' => now()->subYear()]);
        Matricula::factory()->create(['estudiante_id' => $estudiante->id, 'fecha_matricula' => now()]);

        $historial = $this->service()->porDni('11111111');

        $this->assertCount(3, $historial['matriculas']);
        $fechas = $historial['matriculas']->pluck('fecha_matricula')->map(fn ($fecha) => $fecha->format('Y-m-d'))->all();
        $this->assertSame($fechas, collect($fechas)->sort()->values()->all());
    }

    public function test_el_resumen_de_pagos_suma_a_traves_de_varias_matriculas(): void
    {
        $estudiante = Estudiante::factory()->create(['dni' => '22222222']);

        $matriculaUno = Matricula::factory()->create(['estudiante_id' => $estudiante->id]);
        $planUno = PlanPago::factory()->create(['matricula_id' => $matriculaUno->id]);
        Cuota::factory()->pagada()->create(['plan_pago_id' => $planUno->id, 'numero' => 1, 'monto' => 100]);
        Cuota::factory()->vencida()->create(['plan_pago_id' => $planUno->id, 'numero' => 2, 'monto' => 150]);

        $matriculaDos = Matricula::factory()->create(['estudiante_id' => $estudiante->id]);
        $planDos = PlanPago::factory()->create(['matricula_id' => $matriculaDos->id]);
        Cuota::factory()->pagada()->create(['plan_pago_id' => $planDos->id, 'numero' => 1, 'monto' => 200]);
        Cuota::factory()->create(['plan_pago_id' => $planDos->id, 'numero' => 2, 'monto' => 50, 'estado' => EstadoCuotaEnum::EXONERADO]);

        $historial = $this->service()->porDni('22222222');

        $this->assertSame(300.0, $historial['resumenPagos']['totalPagado']);
        $this->assertSame(50.0, $historial['resumenPagos']['totalExonerado']);
        $this->assertCount(1, $historial['resumenPagos']['cuotasVencidas']);
        $this->assertSame(150.0, (float) $historial['resumenPagos']['cuotasVencidas']->first()->monto);
    }

    public function test_los_documentos_de_las_3_fuentes_aparecen_separados(): void
    {
        $estudiante = Estudiante::factory()->create(['dni' => '33333333']);
        DocumentoEstudiante::factory()->create(['estudiante_id' => $estudiante->id]);
        Certificado::factory()->create(['estudiante_id' => $estudiante->id]);
        Libreta::factory()->create(['estudiante_id' => $estudiante->id]);

        $historial = $this->service()->porDni('33333333');

        $this->assertCount(1, $historial['documentosSubidos']);
        $this->assertCount(1, $historial['documentosEmitidos']);
        $this->assertCount(1, $historial['libretas']);
    }

    public function test_las_notas_por_ciclo_solo_cuentan_matriculas_aprobadas(): void
    {
        $estudiante = Estudiante::factory()->create(['dni' => '44444444']);

        $matriculaAprobada = Matricula::factory()->create(['estudiante_id' => $estudiante->id, 'estado' => 'aprobada']);
        $horario = Horario::factory()->create(['grado_id' => $matriculaAprobada->grado_id, 'ciclo_id' => $matriculaAprobada->ciclo_id]);
        $evaluacion = Evaluacion::factory()->create(['horario_id' => $horario->id]);
        Calificacion::factory()->create(['evaluacion_id' => $evaluacion->id, 'estudiante_id' => $estudiante->id, 'nota_numerica' => 16]);
        $evaluacion->update(['estado' => 'publicada']);

        Matricula::factory()->create(['estudiante_id' => $estudiante->id, 'estado' => 'pendiente']);

        $historial = $this->service()->porDni('44444444');

        $this->assertCount(1, $historial['notasPorCiclo']);
        $this->assertSame($matriculaAprobada->ciclo_id, $historial['notasPorCiclo']->first()['ciclo']->id);
    }
}
