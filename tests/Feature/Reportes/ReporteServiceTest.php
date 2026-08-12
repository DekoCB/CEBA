<?php

namespace Tests\Feature\Reportes;

use App\Models\User;
use App\Modules\Academico\Models\Horario;
use App\Modules\Asistencia\Models\Asistencia;
use App\Modules\Evaluaciones\Models\Calificacion;
use App\Modules\Evaluaciones\Models\Evaluacion;
use App\Modules\Matricula\Models\Estudiante;
use App\Modules\Matricula\Models\Matricula;
use App\Modules\Pagos\Models\Pago;
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
