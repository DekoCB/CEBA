<?php

namespace Tests\Feature\Reportes;

use App\Models\User;
use App\Modules\Academico\Models\Horario;
use App\Modules\Asistencia\Models\Asistencia;
use App\Modules\Evaluaciones\Models\Calificacion;
use App\Modules\Evaluaciones\Models\Evaluacion;
use App\Modules\Matricula\Models\Estudiante;
use App\Modules\Matricula\Models\Matricula;
use App\Modules\Pagos\Models\Cuota;
use App\Modules\Pagos\Models\Pago;
use App\Modules\Pagos\Models\PlanPago;
use App\Modules\Reportes\Services\ReporteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReporteServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_reporte_de_matricula_lista_las_matriculas_con_sus_columnas(): void
    {
        $estudiante = Estudiante::factory()->create(['nombres' => 'Ana', 'apellidos' => 'Torres']);
        Matricula::factory()->create(['estudiante_id' => $estudiante->id, 'fecha_matricula' => now()]);

        $reporte = app(ReporteService::class)->matricula(null, null);

        $this->assertSame(['Estudiante', 'DNI', 'Grado', 'Ciclo', 'Estado', 'Fecha de matrícula'], $reporte['columnas']);
        $this->assertCount(1, $reporte['filas']);
        $this->assertStringContainsString('Ana', $reporte['filas'][0][0]);
    }

    public function test_reporte_de_matricula_respeta_el_filtro_de_fechas(): void
    {
        $estudiante = Estudiante::factory()->create();
        Matricula::factory()->create(['estudiante_id' => $estudiante->id, 'fecha_matricula' => now()->subYear()]);

        $reporte = app(ReporteService::class)->matricula(now()->subDays(7)->toDateString(), now()->toDateString());

        $this->assertCount(0, $reporte['filas']);
    }

    public function test_reporte_de_matricula_no_falla_si_el_estudiante_fue_eliminado(): void
    {
        $estudiante = Estudiante::factory()->create();
        Matricula::factory()->create(['estudiante_id' => $estudiante->id, 'fecha_matricula' => now()]);
        $estudiante->delete();

        $reporte = app(ReporteService::class)->matricula(null, null);

        $this->assertCount(1, $reporte['filas']);
        $this->assertSame('—', $reporte['filas'][0][0]);
        $this->assertSame('—', $reporte['filas'][0][1]);
    }

    public function test_reporte_academico_marca_aprobado_desde_once(): void
    {
        $evaluacion = Evaluacion::factory()->create(['fecha' => now()]);
        Calificacion::factory()->create(['evaluacion_id' => $evaluacion->id, 'nota_numerica' => 15]);

        $reporte = app(ReporteService::class)->academico(null, null);

        $this->assertCount(1, $reporte['filas']);
        $this->assertSame('Aprobado', $reporte['filas'][0][5]);
    }

    public function test_reporte_financiero_lista_pagos(): void
    {
        Pago::factory()->aprobado()->create(['fecha_pago' => now()]);

        $reporte = app(ReporteService::class)->financiero(null, null);

        $this->assertSame(['Estudiante', 'Concepto', 'Monto', 'Método', 'Estado', 'Fecha de pago'], $reporte['columnas']);
        $this->assertCount(1, $reporte['filas']);
    }

    public function test_reporte_de_morosos_agrupa_cuotas_vencidas_por_estudiante(): void
    {
        $estudiante = Estudiante::factory()->create(['nombres' => 'Rosa', 'apellidos' => 'Delgado']);
        $matricula = Matricula::factory()->create(['estudiante_id' => $estudiante->id]);
        $plan = PlanPago::factory()->create(['matricula_id' => $matricula->id]);

        Cuota::factory()->vencida()->create([
            'plan_pago_id' => $plan->id,
            'numero' => 1,
            'monto' => 100,
            'fecha_vencimiento' => now()->subMonths(2)->format('Y-m-d'),
        ]);
        Cuota::factory()->vencida()->create([
            'plan_pago_id' => $plan->id,
            'numero' => 2,
            'monto' => 150,
            'fecha_vencimiento' => now()->subMonth()->format('Y-m-d'),
        ]);
        // No debería contar: todavía no vence.
        Cuota::factory()->create([
            'plan_pago_id' => $plan->id,
            'numero' => 3,
            'fecha_vencimiento' => now()->addMonth()->format('Y-m-d'),
        ]);
        // No debería contar: ya está pagada, aunque la fecha haya pasado.
        Cuota::factory()->vencida()->pagada()->create(['plan_pago_id' => $plan->id, 'numero' => 4]);

        $reporte = app(ReporteService::class)->morosos(null, null);

        $this->assertSame(['Estudiante', 'DNI', 'Grado', 'Cuotas vencidas', 'Monto adeudado', 'Vencida desde'], $reporte['columnas']);
        $this->assertCount(1, $reporte['filas']);
        $this->assertStringContainsString('Rosa', $reporte['filas'][0][0]);
        $this->assertSame(2, $reporte['filas'][0][3]);
        $this->assertSame('250.00', $reporte['filas'][0][4]);
        $this->assertSame(now()->subMonths(2)->format('d/m/Y'), $reporte['filas'][0][5]);
    }

    public function test_reporte_de_morosos_no_incluye_estudiantes_al_dia(): void
    {
        $matricula = Matricula::factory()->create();
        $plan = PlanPago::factory()->create(['matricula_id' => $matricula->id]);
        Cuota::factory()->pagada()->create(['plan_pago_id' => $plan->id]);

        $reporte = app(ReporteService::class)->morosos(null, null);

        $this->assertSame([], $reporte['filas']);
    }

    public function test_reporte_de_certificados_esta_vacio_sin_datos(): void
    {
        $reporte = app(ReporteService::class)->certificados(null, null);

        $this->assertSame([], $reporte['filas']);
    }

    public function test_reporte_academico_filtra_por_horario(): void
    {
        $horarioA = Horario::factory()->create();
        $horarioB = Horario::factory()->create();
        Calificacion::factory()->create(['evaluacion_id' => Evaluacion::factory()->create(['horario_id' => $horarioA->id])->id]);
        Calificacion::factory()->create(['evaluacion_id' => Evaluacion::factory()->create(['horario_id' => $horarioB->id])->id]);

        $reporte = app(ReporteService::class)->academico(null, null, $horarioA->id);

        $this->assertCount(1, $reporte['filas']);
    }

    public function test_reporte_operativo_filtra_por_horario(): void
    {
        $horarioA = Horario::factory()->create();
        $horarioB = Horario::factory()->create();
        Asistencia::factory()->create(['horario_id' => $horarioA->id]);
        Asistencia::factory()->create(['horario_id' => $horarioB->id]);

        $reporte = app(ReporteService::class)->operativo(null, null, $horarioA->id);

        $this->assertCount(1, $reporte['filas']);
    }

    public function test_reporte_operativo_omite_asistencias_de_un_estudiante_eliminado(): void
    {
        $horario = Horario::factory()->create();
        $estudianteVigente = Estudiante::factory()->create();
        $estudianteEliminado = Estudiante::factory()->create();
        Asistencia::factory()->create(['horario_id' => $horario->id, 'estudiante_id' => $estudianteVigente->id]);
        Asistencia::factory()->create(['horario_id' => $horario->id, 'estudiante_id' => $estudianteEliminado->id]);
        $estudianteEliminado->delete();

        $reporte = app(ReporteService::class)->operativo(null, null, $horario->id);

        $this->assertCount(1, $reporte['filas']);
    }

    public function test_reporte_propio_solo_incluye_horarios_del_docente_aunque_se_filtre_por_otro(): void
    {
        $docente = User::factory()->create();
        $horarioPropio = Horario::factory()->create(['docente_id' => $docente->id]);
        $horarioAjeno = Horario::factory()->create();
        Evaluacion::factory()->create(['horario_id' => $horarioPropio->id]);
        Evaluacion::factory()->create(['horario_id' => $horarioAjeno->id]);

        $reporteSinFiltro = app(ReporteService::class)->propio($docente, null, null);
        $this->assertCount(1, $reporteSinFiltro['filas']);

        $reporteConHorarioAjeno = app(ReporteService::class)->propio($docente, null, null, $horarioAjeno->id);
        $this->assertCount(0, $reporteConHorarioAjeno['filas']);

        $reporteConHorarioPropio = app(ReporteService::class)->propio($docente, null, null, $horarioPropio->id);
        $this->assertCount(1, $reporteConHorarioPropio['filas']);
    }
}
