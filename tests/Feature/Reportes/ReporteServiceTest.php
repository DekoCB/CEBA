<?php

namespace Tests\Feature\Reportes;

use App\Models\User;
use App\Modules\Academico\Enums\FranjaHorarioEnum;
use App\Modules\Academico\Enums\TipoSiagieEnum;
use App\Modules\Academico\Models\Curso;
use App\Modules\Academico\Models\Horario;
use App\Modules\Academico\Models\Siagie;
use App\Modules\Asistencia\Models\Asistencia;
use App\Modules\Certificados\Models\Certificado;
use App\Modules\Evaluaciones\Models\Calificacion;
use App\Modules\Evaluaciones\Models\Evaluacion;
use App\Modules\Matricula\Enums\TipoDocumentoEnum;
use App\Modules\Matricula\Models\DocumentoEstudiante;
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

    /**
     * @param  array<string, mixed>  $atributos
     */
    private function horarioConFranja(FranjaHorarioEnum $franja, array $atributos = []): Horario
    {
        $horario = Horario::factory()->create($atributos);
        $horario->dias()->delete();

        foreach ($franja->dias() as $dia) {
            $horario->dias()->create([
                'dia_semana' => $dia,
                'hora_inicio' => '18:00:00',
                'hora_fin' => '20:00:00',
            ]);
        }

        return $horario->fresh(['dias']);
    }

    public function test_reporte_de_matricula_lista_las_matriculas_con_sus_columnas(): void
    {
        $estudiante = Estudiante::factory()->create(['nombres' => 'Ana', 'apellidos' => 'Torres']);
        Matricula::factory()->create(['estudiante_id' => $estudiante->id, 'fecha_matricula' => now()]);

        $reporte = app(ReporteService::class)->matricula(null, null, null);

        $this->assertSame(['Estudiante', 'DNI', 'Grado', 'Ciclo', 'Estado', 'Fecha de matrícula'], $reporte['columnas']);
        $this->assertCount(1, $reporte['filas']);
        $this->assertStringContainsString('Ana', $reporte['filas'][0][0]);
    }

    public function test_reporte_de_matricula_filtra_por_grupo_y_grado(): void
    {
        $horarioA = Horario::factory()->create();
        $horarioB = Horario::factory()->create();
        Matricula::factory()->create(['grado_id' => $horarioA->grado_id, 'ciclo_id' => $horarioA->ciclo_id]);
        Matricula::factory()->create(['grado_id' => $horarioB->grado_id, 'ciclo_id' => $horarioB->ciclo_id]);

        $reporte = app(ReporteService::class)->matricula($horarioA->ciclo_id, $horarioA->grado_id, null);

        $this->assertCount(1, $reporte['filas']);
    }

    public function test_reporte_de_matricula_filtra_por_curso_sin_paralelos_incluye_a_todo_el_grado(): void
    {
        $horario = Horario::factory()->create();
        $matriculaA = Matricula::factory()->create(['grado_id' => $horario->grado_id, 'ciclo_id' => $horario->ciclo_id]);
        $matriculaB = Matricula::factory()->create(['grado_id' => $horario->grado_id, 'ciclo_id' => $horario->ciclo_id]);

        $reporte = app(ReporteService::class)->matricula($horario->ciclo_id, $horario->grado_id, $horario->curso_id);

        // Sin secciones paralelas, todos los matriculados de ese grado+ciclo llevan el curso automáticamente.
        $this->assertCount(2, $reporte['filas']);
    }

    public function test_reporte_de_matricula_filtra_por_curso_con_paralelos_solo_incluye_a_los_asignados(): void
    {
        $curso = Curso::factory()->create();
        $horarioA = Horario::factory()->create(['curso_id' => $curso->id]);
        $horarioB = Horario::factory()->create([
            'curso_id' => $curso->id,
            'grado_id' => $horarioA->grado_id,
            'ciclo_id' => $horarioA->ciclo_id,
        ]);

        $matriculaAsignada = Matricula::factory()->create(['grado_id' => $horarioA->grado_id, 'ciclo_id' => $horarioA->ciclo_id]);
        $matriculaAsignada->horarios()->attach($horarioA->id);

        // Matriculado en el mismo grado+ciclo, pero sin asignación a ninguno de los horarios de este curso.
        Matricula::factory()->create(['grado_id' => $horarioA->grado_id, 'ciclo_id' => $horarioA->ciclo_id]);

        $reporte = app(ReporteService::class)->matricula($horarioA->ciclo_id, $horarioA->grado_id, $curso->id);

        $this->assertCount(1, $reporte['filas']);
    }

    public function test_reporte_de_matricula_no_falla_si_el_estudiante_fue_eliminado(): void
    {
        $estudiante = Estudiante::factory()->create();
        Matricula::factory()->create(['estudiante_id' => $estudiante->id, 'fecha_matricula' => now()]);
        $estudiante->delete();

        $reporte = app(ReporteService::class)->matricula(null, null, null);

        $this->assertCount(1, $reporte['filas']);
        $this->assertSame('—', $reporte['filas'][0][0]);
        $this->assertSame('—', $reporte['filas'][0][1]);
    }

    public function test_reporte_de_matricula_filtra_por_siagie(): void
    {
        $siagieA = Siagie::factory()->create(['tipo' => TipoSiagieEnum::PRIMERO, 'anio' => 2026]);
        $siagieB = Siagie::factory()->create(['tipo' => TipoSiagieEnum::SEGUNDO, 'anio' => 2026]);
        Matricula::factory()->create(['siagie_id' => $siagieA->id, 'fecha_matricula' => now()]);
        Matricula::factory()->create(['siagie_id' => $siagieB->id, 'fecha_matricula' => now()]);
        Matricula::factory()->create(['siagie_id' => null, 'fecha_matricula' => now()]);

        $reporte = app(ReporteService::class)->matricula(null, null, null, null, $siagieA->id);

        $this->assertCount(1, $reporte['filas']);
    }

    public function test_reporte_academico_filtra_por_siagie(): void
    {
        $siagie = Siagie::factory()->create(['tipo' => TipoSiagieEnum::PRIMERO, 'anio' => 2026]);
        $estudianteConSiagie = Estudiante::factory()->create();
        $estudianteSinSiagie = Estudiante::factory()->create();
        Matricula::factory()->create(['estudiante_id' => $estudianteConSiagie->id, 'siagie_id' => $siagie->id]);
        Matricula::factory()->create(['estudiante_id' => $estudianteSinSiagie->id, 'siagie_id' => null]);
        Calificacion::factory()->create(['estudiante_id' => $estudianteConSiagie->id]);
        Calificacion::factory()->create(['estudiante_id' => $estudianteSinSiagie->id]);

        $reporte = app(ReporteService::class)->academico(null, null, null, null, $siagie->id);

        $this->assertCount(1, $reporte['filas']);
    }

    public function test_reporte_academico_marca_aprobado_desde_once(): void
    {
        $evaluacion = Evaluacion::factory()->create(['fecha' => now()]);
        Calificacion::factory()->create(['evaluacion_id' => $evaluacion->id, 'nota_numerica' => 15]);

        $reporte = app(ReporteService::class)->academico(null, null, null);

        $this->assertCount(1, $reporte['filas']);
        $this->assertSame('Aprobado', $reporte['filas'][0][5]);
    }

    public function test_reporte_financiero_lista_pagos(): void
    {
        $pago = Pago::factory()->aprobado()->create(['fecha_pago' => now()]);

        $reporte = app(ReporteService::class)->financiero(null, null, null);

        $this->assertSame(['Estudiante', 'DNI', 'Concepto', 'Monto', 'Método', 'Estado', 'Fecha de pago', 'N.° de operación'], $reporte['columnas']);
        $this->assertCount(1, $reporte['filas']);
        $this->assertSame($pago->estudiante->dni, $reporte['filas'][0][1]);
    }

    public function test_reporte_financiero_muestra_el_numero_de_operacion(): void
    {
        Pago::factory()->aprobado()->create(['fecha_pago' => now(), 'numero_operacion' => 'OP-12345']);

        $reporte = app(ReporteService::class)->financiero(null, null, null);

        $this->assertSame('OP-12345', $reporte['filas'][0][7]);
    }

    /**
     * Pedido del cliente: el reporte financiero debe mostrar el medio de
     * pago igual que sale impreso en el recibo (con la nota, que es donde
     * se escribe de quién es la cuenta -- ej. "Yape Walter"), no solo el
     * método genérico.
     */
    public function test_reporte_financiero_muestra_el_medio_de_pago_con_la_nota_de_la_cuenta(): void
    {
        $pago = Pago::factory()->aprobado()->create(['fecha_pago' => now(), 'metodo' => 'yape']);
        $pago->partes()->create(['monto' => $pago->monto, 'metodo' => 'yape', 'nota' => 'Walter']);

        $reporte = app(ReporteService::class)->financiero(null, null, null);

        $this->assertSame('Yape Walter', $reporte['filas'][0][4]);
    }

    public function test_reporte_de_matricula_filtra_por_franja(): void
    {
        $horarioA = $this->horarioConFranja(FranjaHorarioEnum::LUN_MIE);
        $horarioB = $this->horarioConFranja(FranjaHorarioEnum::MAR_JUE);
        Matricula::factory()->create(['grado_id' => $horarioA->grado_id, 'ciclo_id' => $horarioA->ciclo_id, 'fecha_matricula' => now()]);
        Matricula::factory()->create(['grado_id' => $horarioB->grado_id, 'ciclo_id' => $horarioB->ciclo_id, 'fecha_matricula' => now()]);

        $reporte = app(ReporteService::class)->matricula(null, null, null, FranjaHorarioEnum::LUN_MIE->value);

        $this->assertCount(1, $reporte['filas']);
    }

    public function test_reporte_financiero_filtra_por_franja(): void
    {
        $horarioA = $this->horarioConFranja(FranjaHorarioEnum::LUN_MIE);
        $horarioB = $this->horarioConFranja(FranjaHorarioEnum::MAR_JUE);
        $estudianteA = Estudiante::factory()->create();
        $estudianteB = Estudiante::factory()->create();
        Matricula::factory()->create(['estudiante_id' => $estudianteA->id, 'grado_id' => $horarioA->grado_id, 'ciclo_id' => $horarioA->ciclo_id]);
        Matricula::factory()->create(['estudiante_id' => $estudianteB->id, 'grado_id' => $horarioB->grado_id, 'ciclo_id' => $horarioB->ciclo_id]);
        Pago::factory()->aprobado()->create(['estudiante_id' => $estudianteA->id, 'fecha_pago' => now()]);
        Pago::factory()->aprobado()->create(['estudiante_id' => $estudianteB->id, 'fecha_pago' => now()]);

        $reporte = app(ReporteService::class)->financiero(null, null, null, FranjaHorarioEnum::LUN_MIE->value);

        $this->assertCount(1, $reporte['filas']);
    }

    public function test_reporte_de_certificados_filtra_por_franja(): void
    {
        $horarioA = $this->horarioConFranja(FranjaHorarioEnum::LUN_MIE);
        $horarioB = $this->horarioConFranja(FranjaHorarioEnum::MAR_JUE);
        $matriculaA = Matricula::factory()->create(['grado_id' => $horarioA->grado_id, 'ciclo_id' => $horarioA->ciclo_id]);
        $matriculaB = Matricula::factory()->create(['grado_id' => $horarioB->grado_id, 'ciclo_id' => $horarioB->ciclo_id]);
        Certificado::factory()->create(['matricula_id' => $matriculaA->id, 'fecha_emision' => now()]);
        Certificado::factory()->create(['matricula_id' => $matriculaB->id, 'fecha_emision' => now()]);

        $reporte = app(ReporteService::class)->certificados(null, null, null, FranjaHorarioEnum::LUN_MIE->value);

        $this->assertCount(1, $reporte['filas']);
    }

    public function test_reporte_de_morosos_filtra_por_franja(): void
    {
        $horarioA = $this->horarioConFranja(FranjaHorarioEnum::LUN_MIE);
        $horarioB = $this->horarioConFranja(FranjaHorarioEnum::MAR_JUE);
        $matriculaA = Matricula::factory()->create(['grado_id' => $horarioA->grado_id, 'ciclo_id' => $horarioA->ciclo_id]);
        $matriculaB = Matricula::factory()->create(['grado_id' => $horarioB->grado_id, 'ciclo_id' => $horarioB->ciclo_id]);
        $planA = PlanPago::factory()->create(['matricula_id' => $matriculaA->id]);
        $planB = PlanPago::factory()->create(['matricula_id' => $matriculaB->id]);
        Cuota::factory()->vencida()->create(['plan_pago_id' => $planA->id, 'numero' => 1]);
        Cuota::factory()->vencida()->create(['plan_pago_id' => $planB->id, 'numero' => 1]);

        $reporte = app(ReporteService::class)->morosos(null, null, null, FranjaHorarioEnum::LUN_MIE->value);

        $this->assertCount(1, $reporte['filas']);
    }

    /**
     * Pedido del cliente: una fila POR CUOTA vencida (no un resumen por
     * estudiante) -- para gestionar la cobranza cuota por cuota.
     */
    public function test_reporte_de_morosos_tiene_una_fila_por_cuota_vencida(): void
    {
        $estudiante = Estudiante::factory()->create(['nombres' => 'Rosa', 'apellidos' => 'Delgado', 'celular' => '987654321']);
        $matricula = Matricula::factory()->create(['estudiante_id' => $estudiante->id, 'vendedor' => 'Juan Pérez', 'fecha_fin_estudio' => '2026-12-15']);
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

        $reporte = app(ReporteService::class)->morosos(null, null, null);

        $this->assertSame(['Estudiante', 'DNI', 'Grupo', 'Grado', 'Celulares', 'Vendedor', 'Cuota N°', 'Monto cuota', 'Vencida desde', 'Compromiso de pago', 'Fin de clase'], $reporte['columnas']);
        $this->assertCount(2, $reporte['filas']);
        $this->assertStringContainsString('Rosa', $reporte['filas'][0][0]);
        $this->assertStringContainsString('987654321', $reporte['filas'][0][4]);
        $this->assertSame('Juan Pérez', $reporte['filas'][0][5]);
        $this->assertSame(1, $reporte['filas'][0][6]);
        $this->assertSame('100.00', $reporte['filas'][0][7]);
        $this->assertSame(now()->subMonths(2)->format('d/m/Y'), $reporte['filas'][0][8]);
        $this->assertSame('—', $reporte['filas'][0][9]);
        $this->assertSame('15/12/2026', $reporte['filas'][0][10]);
        $this->assertSame(2, $reporte['filas'][1][6]);
        $this->assertSame('150.00', $reporte['filas'][1][7]);
    }

    public function test_reporte_de_morosos_muestra_guion_si_la_matricula_no_tiene_vendedor(): void
    {
        $matricula = Matricula::factory()->create(['vendedor' => null]);
        $plan = PlanPago::factory()->create(['matricula_id' => $matricula->id]);
        Cuota::factory()->vencida()->create(['plan_pago_id' => $plan->id, 'numero' => 1]);

        $reporte = app(ReporteService::class)->morosos(null, null, null);

        $this->assertSame('—', $reporte['filas'][0][5]);
    }

    /**
     * Pedido del cliente: ver de un vistazo, en el reporte de deudores,
     * hasta cuándo se comprometió a pagar cada cuota.
     */
    public function test_reporte_de_morosos_muestra_el_compromiso_de_pago_de_cada_cuota(): void
    {
        $estudiante = Estudiante::factory()->create();
        $matricula = Matricula::factory()->create(['estudiante_id' => $estudiante->id]);
        $plan = PlanPago::factory()->create(['matricula_id' => $matricula->id]);

        Cuota::factory()->vencida()->create([
            'plan_pago_id' => $plan->id,
            'numero' => 1,
            'fecha_vencimiento' => now()->subMonths(2)->format('Y-m-d'),
            'fecha_compromiso' => now()->subDays(10)->format('Y-m-d'),
        ]);
        Cuota::factory()->vencida()->create([
            'plan_pago_id' => $plan->id,
            'numero' => 2,
            'fecha_vencimiento' => now()->subMonth()->format('Y-m-d'),
            'fecha_compromiso' => now()->subDays(3)->format('Y-m-d'),
        ]);

        $reporte = app(ReporteService::class)->morosos(null, null, null);

        $this->assertSame(now()->subDays(10)->format('d/m/Y'), $reporte['filas'][0][9]);
        $this->assertSame(now()->subDays(3)->format('d/m/Y'), $reporte['filas'][1][9]);
    }

    public function test_reporte_de_morosos_no_incluye_estudiantes_al_dia(): void
    {
        $matricula = Matricula::factory()->create();
        $plan = PlanPago::factory()->create(['matricula_id' => $matricula->id]);
        Cuota::factory()->pagada()->create(['plan_pago_id' => $plan->id]);

        $reporte = app(ReporteService::class)->morosos(null, null, null);

        $this->assertSame([], $reporte['filas']);
    }

    /**
     * Pedido del cliente: un reporte para ver de un vistazo si el
     * Certificado de estudios, las fotos de DNI (estudiante y apoderado)
     * y los demás documentos ya fueron verificados, están pendientes o
     * todavía no se subieron -- mismo vocabulario que ya usa
     * ficha-estudiante.blade.php.
     */
    public function test_reporte_de_documentos_muestra_el_estado_de_cada_tipo(): void
    {
        $estudiante = Estudiante::factory()->create(['nombres' => 'Luz', 'apellidos' => 'Fernández', 'es_menor_edad' => false]);
        $matricula = Matricula::factory()->create(['estudiante_id' => $estudiante->id]);

        DocumentoEstudiante::factory()->create(['estudiante_id' => $estudiante->id, 'tipo' => TipoDocumentoEnum::CERTIFICADO_ESTUDIOS, 'verificado' => true]);
        DocumentoEstudiante::factory()->create(['estudiante_id' => $estudiante->id, 'tipo' => TipoDocumentoEnum::CONSTANCIA, 'verificado' => false]);
        // DNI_ESTUDIANTE y OTRO quedan sin ninguna fila -- "Falta subir".

        $reporte = app(ReporteService::class)->documentos(null, null, null);

        $this->assertSame(['Estudiante', 'DNI', 'Grado', 'DNI estudiante', 'DNI apoderado', 'Certificado de estudios', 'Constancia', 'Otro documento'], $reporte['columnas']);
        $this->assertCount(1, $reporte['filas']);
        $this->assertStringContainsString('Luz', $reporte['filas'][0][0]);
        $this->assertSame($matricula->grado->nombre, $reporte['filas'][0][2]);
        $this->assertSame('Falta subir', $reporte['filas'][0][3]);
        $this->assertSame('No aplica', $reporte['filas'][0][4]);
        $this->assertSame('Verificado', $reporte['filas'][0][5]);
        $this->assertSame('Pendiente', $reporte['filas'][0][6]);
        $this->assertSame('Falta subir', $reporte['filas'][0][7]);
    }

    public function test_reporte_de_documentos_exige_dni_del_apoderado_solo_si_es_menor_de_edad(): void
    {
        $estudiante = Estudiante::factory()->create(['es_menor_edad' => true]);
        Matricula::factory()->create(['estudiante_id' => $estudiante->id]);
        DocumentoEstudiante::factory()->create(['estudiante_id' => $estudiante->id, 'tipo' => TipoDocumentoEnum::DNI_APODERADO, 'verificado' => true]);

        $reporte = app(ReporteService::class)->documentos(null, null, null);

        $this->assertSame('Verificado', $reporte['filas'][0][4]);
    }

    public function test_reporte_de_certificados_esta_vacio_sin_datos(): void
    {
        $reporte = app(ReporteService::class)->certificados(null, null, null);

        $this->assertSame([], $reporte['filas']);
    }

    public function test_reporte_academico_filtra_por_franja(): void
    {
        $horarioA = $this->horarioConFranja(FranjaHorarioEnum::LUN_MIE);
        $horarioB = $this->horarioConFranja(FranjaHorarioEnum::MAR_JUE);
        Calificacion::factory()->create(['evaluacion_id' => Evaluacion::factory()->create(['horario_id' => $horarioA->id])->id]);
        Calificacion::factory()->create(['evaluacion_id' => Evaluacion::factory()->create(['horario_id' => $horarioB->id])->id]);

        $reporte = app(ReporteService::class)->academico(null, null, null, FranjaHorarioEnum::LUN_MIE->value);

        $this->assertCount(1, $reporte['filas']);
    }

    public function test_reporte_academico_filtra_por_grupo_grado_y_curso(): void
    {
        $horarioA = Horario::factory()->create();
        $horarioB = Horario::factory()->create();
        Calificacion::factory()->create(['evaluacion_id' => Evaluacion::factory()->create(['horario_id' => $horarioA->id])->id]);
        Calificacion::factory()->create(['evaluacion_id' => Evaluacion::factory()->create(['horario_id' => $horarioB->id])->id]);

        $reporte = app(ReporteService::class)->academico($horarioA->ciclo_id, $horarioA->grado_id, $horarioA->curso_id);

        $this->assertCount(1, $reporte['filas']);
    }

    public function test_reporte_operativo_filtra_por_franja(): void
    {
        $horarioA = $this->horarioConFranja(FranjaHorarioEnum::LUN_MIE);
        $horarioB = $this->horarioConFranja(FranjaHorarioEnum::MAR_JUE);
        Asistencia::factory()->create(['horario_id' => $horarioA->id]);
        Asistencia::factory()->create(['horario_id' => $horarioB->id]);

        $reporte = app(ReporteService::class)->operativo(null, null, null, FranjaHorarioEnum::LUN_MIE->value);

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

        $reporte = app(ReporteService::class)->operativo(null, null, null);

        $this->assertCount(1, $reporte['filas']);
    }

    public function test_reporte_propio_solo_incluye_horarios_del_docente_aunque_se_filtre_por_otra_franja(): void
    {
        $docente = User::factory()->create();
        $horarioPropio = $this->horarioConFranja(FranjaHorarioEnum::LUN_MIE, ['docente_id' => $docente->id]);
        $horarioAjeno = $this->horarioConFranja(FranjaHorarioEnum::MAR_JUE);
        Evaluacion::factory()->create(['horario_id' => $horarioPropio->id]);
        Evaluacion::factory()->create(['horario_id' => $horarioAjeno->id]);

        $reporteSinFiltro = app(ReporteService::class)->propio($docente, null, null, null);
        $this->assertCount(1, $reporteSinFiltro['filas']);

        $reporteConFranjaAjena = app(ReporteService::class)->propio($docente, null, null, null, FranjaHorarioEnum::MAR_JUE->value);
        $this->assertCount(0, $reporteConFranjaAjena['filas']);

        $reporteConFranjaPropia = app(ReporteService::class)->propio($docente, null, null, null, FranjaHorarioEnum::LUN_MIE->value);
        $this->assertCount(1, $reporteConFranjaPropia['filas']);
    }
}
