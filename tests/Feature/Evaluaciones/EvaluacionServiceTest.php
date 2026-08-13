<?php

namespace Tests\Feature\Evaluaciones;

use App\Modules\Academico\Models\Ciclo;
use App\Modules\Academico\Models\Grado;
use App\Modules\Academico\Models\Horario;
use App\Modules\Evaluaciones\Enums\EstadoEvaluacionEnum;
use App\Modules\Evaluaciones\Services\EvaluacionService;
use App\Modules\Matricula\Models\Estudiante;
use App\Modules\Matricula\Models\Matricula;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EvaluacionServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): EvaluacionService
    {
        return $this->app->make(EvaluacionService::class);
    }

    public function test_crear_una_evaluacion_queda_en_estado_borrador(): void
    {
        $horario = Horario::factory()->create();

        $evaluacion = $this->service()->crear($horario, 'Evaluación mensual — julio', '2026-07-15');

        $this->assertSame(EstadoEvaluacionEnum::BORRADOR, $evaluacion->estado);
        $this->assertDatabaseHas('evaluaciones', ['nombre' => 'Evaluación mensual — julio', 'estado' => 'borrador']);
    }

    public function test_estudiantes_del_horario_solo_incluye_matriculados_aprobados(): void
    {
        $horario = Horario::factory()->create();

        $matriculado = Estudiante::factory()->create();
        Matricula::factory()->create([
            'estudiante_id' => $matriculado->id,
            'grado_id' => $horario->grado_id,
            'ciclo_id' => $horario->ciclo_id,
        ]);

        $noMatriculado = Estudiante::factory()->create();

        $estudiantes = $this->service()->estudiantesDelHorario($horario);

        $this->assertTrue($estudiantes->contains('id', $matriculado->id));
        $this->assertFalse($estudiantes->contains('id', $noMatriculado->id));
    }

    public function test_estudiantes_del_horario_no_mezcla_secciones_distintas_del_mismo_grado(): void
    {
        $grado = Grado::factory()->create();
        $ciclo = Ciclo::factory()->create();
        $horarioA = Horario::factory()->create(['grado_id' => $grado->id, 'ciclo_id' => $ciclo->id, 'seccion' => 'A']);
        $horarioB = Horario::factory()->create(['grado_id' => $grado->id, 'ciclo_id' => $ciclo->id, 'seccion' => 'B']);

        $estudianteA = Estudiante::factory()->create();
        Matricula::factory()->create([
            'estudiante_id' => $estudianteA->id,
            'grado_id' => $grado->id,
            'ciclo_id' => $ciclo->id,
            'horario_id' => $horarioA->id,
        ]);

        $estudianteB = Estudiante::factory()->create();
        Matricula::factory()->create([
            'estudiante_id' => $estudianteB->id,
            'grado_id' => $grado->id,
            'ciclo_id' => $ciclo->id,
            'horario_id' => $horarioB->id,
        ]);

        $estudiantesDeA = $this->service()->estudiantesDelHorario($horarioA);

        $this->assertTrue($estudiantesDeA->contains('id', $estudianteA->id));
        $this->assertFalse($estudiantesDeA->contains('id', $estudianteB->id));
    }

    public function test_estudiantes_del_horario_incluye_matriculas_sin_horario_id_asignado(): void
    {
        $grado = Grado::factory()->create();
        $ciclo = Ciclo::factory()->create();
        $horarioA = Horario::factory()->create(['grado_id' => $grado->id, 'ciclo_id' => $ciclo->id, 'seccion' => 'A']);
        Horario::factory()->create(['grado_id' => $grado->id, 'ciclo_id' => $ciclo->id, 'seccion' => 'B']);

        $estudianteSinSeccion = Estudiante::factory()->create();
        Matricula::factory()->create([
            'estudiante_id' => $estudianteSinSeccion->id,
            'grado_id' => $grado->id,
            'ciclo_id' => $ciclo->id,
            'horario_id' => null,
        ]);

        $estudiantesDeA = $this->service()->estudiantesDelHorario($horarioA);

        $this->assertTrue($estudiantesDeA->contains('id', $estudianteSinSeccion->id));
    }

    public function test_calificar_dos_veces_al_mismo_estudiante_actualiza_en_vez_de_duplicar(): void
    {
        $horario = Horario::factory()->create();
        $estudiante = Estudiante::factory()->create();
        $service = $this->service();
        $evaluacion = $service->crear($horario, 'Evaluación', '2026-07-15');

        $service->calificar($evaluacion, $estudiante, 15.5, 'Primer intento', null);
        $service->calificar($evaluacion, $estudiante, 18.0, 'Segundo intento', null);

        $this->assertDatabaseCount('calificaciones', 1);
        $this->assertDatabaseHas('calificaciones', ['nota_numerica' => 18.0, 'observaciones' => 'Segundo intento']);
    }

    public function test_un_estudiante_no_ve_calificaciones_de_una_evaluacion_en_borrador(): void
    {
        $horario = Horario::factory()->create();
        $estudiante = Estudiante::factory()->create();
        $service = $this->service();
        $evaluacion = $service->crear($horario, 'Evaluación', '2026-07-15');
        $service->calificar($evaluacion, $estudiante, 17.0, null, null);

        $this->assertTrue($service->misCalificaciones($estudiante, $horario)->isEmpty());

        $service->publicar($evaluacion);

        $this->assertCount(1, $service->misCalificaciones($estudiante, $horario));
    }

    public function test_promedio_del_estudiante_solo_considera_evaluaciones_publicadas(): void
    {
        $horario = Horario::factory()->create();
        $estudiante = Estudiante::factory()->create();
        $service = $this->service();

        $evaluacionUno = $service->crear($horario, 'Evaluación 1', '2026-07-01');
        $service->calificar($evaluacionUno, $estudiante, 14.0, null, null);
        $service->publicar($evaluacionUno);

        $evaluacionDos = $service->crear($horario, 'Evaluación 2', '2026-07-15');
        $service->calificar($evaluacionDos, $estudiante, 20.0, null, null);
        $service->publicar($evaluacionDos);

        $evaluacionSinPublicar = $service->crear($horario, 'Evaluación 3', '2026-07-20');
        $service->calificar($evaluacionSinPublicar, $estudiante, 0.0, null, null);

        $this->assertSame(17.0, $service->promedioDelEstudiante($estudiante, $horario));
    }

    public function test_horarios_del_estudiante_solo_incluye_la_seccion_asignada(): void
    {
        $grado = Grado::factory()->create();
        $ciclo = Ciclo::factory()->create();
        $horarioA = Horario::factory()->create(['grado_id' => $grado->id, 'ciclo_id' => $ciclo->id, 'seccion' => 'A']);
        $horarioB = Horario::factory()->create(['grado_id' => $grado->id, 'ciclo_id' => $ciclo->id, 'seccion' => 'B']);

        $estudiante = Estudiante::factory()->create();
        Matricula::factory()->create([
            'estudiante_id' => $estudiante->id,
            'grado_id' => $grado->id,
            'ciclo_id' => $ciclo->id,
            'horario_id' => $horarioA->id,
        ]);

        $horarios = $this->service()->horariosDelEstudiante($estudiante);

        $this->assertTrue($horarios->contains('id', $horarioA->id));
        $this->assertFalse($horarios->contains('id', $horarioB->id));
    }

    public function test_horarios_del_estudiante_sin_horario_id_incluye_todas_las_secciones_del_grado(): void
    {
        $grado = Grado::factory()->create();
        $ciclo = Ciclo::factory()->create();
        $horarioA = Horario::factory()->create(['grado_id' => $grado->id, 'ciclo_id' => $ciclo->id, 'seccion' => 'A']);
        $horarioB = Horario::factory()->create(['grado_id' => $grado->id, 'ciclo_id' => $ciclo->id, 'seccion' => 'B']);

        $estudiante = Estudiante::factory()->create();
        Matricula::factory()->create([
            'estudiante_id' => $estudiante->id,
            'grado_id' => $grado->id,
            'ciclo_id' => $ciclo->id,
            'horario_id' => null,
        ]);

        $horarios = $this->service()->horariosDelEstudiante($estudiante);

        $this->assertTrue($horarios->contains('id', $horarioA->id));
        $this->assertTrue($horarios->contains('id', $horarioB->id));
    }

    public function test_promedio_es_null_sin_calificaciones_publicadas(): void
    {
        $horario = Horario::factory()->create();
        $estudiante = Estudiante::factory()->create();

        $this->assertNull($this->service()->promedioDelEstudiante($estudiante, $horario));
    }

    public function test_crear_una_evaluacion_con_enlace_lo_persiste(): void
    {
        $horario = Horario::factory()->create();

        $evaluacion = $this->service()->crear($horario, 'Evaluación', '2026-07-15', 'https://forms.test/examen');

        $this->assertSame('https://forms.test/examen', $evaluacion->enlace_externo);
        $this->assertTrue($evaluacion->tieneEnlaceExterno());
    }

    public function test_crear_una_evaluacion_sin_enlace_lo_deja_nulo(): void
    {
        $horario = Horario::factory()->create();

        $evaluacion = $this->service()->crear($horario, 'Evaluación', '2026-07-15');

        $this->assertNull($evaluacion->enlace_externo);
        $this->assertFalse($evaluacion->tieneEnlaceExterno());
    }

    public function test_actualizar_enlace_modifica_el_enlace_de_una_evaluacion_existente(): void
    {
        $horario = Horario::factory()->create();
        $evaluacion = $this->service()->crear($horario, 'Evaluación', '2026-07-15');

        $this->service()->actualizarEnlace($evaluacion, 'https://forms.test/nuevo');

        $this->assertSame('https://forms.test/nuevo', $evaluacion->refresh()->enlace_externo);
    }

    public function test_evaluaciones_con_enlace_del_horario_solo_incluye_las_que_tienen_enlace(): void
    {
        $horario = Horario::factory()->create();
        $service = $this->service();

        $conEnlace = $service->crear($horario, 'Con enlace', '2026-07-15', 'https://forms.test/examen');
        $service->publicar($conEnlace);
        $sinEnlace = $service->crear($horario, 'Sin enlace', '2026-07-16');
        $service->publicar($sinEnlace);

        $resultado = $service->evaluacionesConEnlaceDelHorario($horario);

        $this->assertTrue($resultado->contains('id', $conEnlace->id));
        $this->assertFalse($resultado->contains('id', $sinEnlace->id));
    }

    public function test_evaluaciones_con_enlace_del_horario_excluye_evaluaciones_en_borrador(): void
    {
        $horario = Horario::factory()->create();
        $evaluacion = $this->service()->crear($horario, 'Con enlace', '2026-07-15', 'https://forms.test/examen');

        $this->assertSame(EstadoEvaluacionEnum::BORRADOR, $evaluacion->estado);
        $this->assertFalse($this->service()->evaluacionesConEnlaceDelHorario($horario)->contains('id', $evaluacion->id));
    }

    public function test_evaluaciones_con_enlace_del_horario_excluye_las_vencidas(): void
    {
        $horario = Horario::factory()->create();
        $service = $this->service();

        $vigente = $service->crear($horario, 'Vigente', '2026-07-15', 'https://forms.test/vigente', now()->addDay()->format('Y-m-d H:i:s'));
        $service->publicar($vigente);

        $vencida = $service->crear($horario, 'Vencida', '2026-07-14', 'https://forms.test/vencida', now()->subDay()->format('Y-m-d H:i:s'));
        $service->publicar($vencida);

        $resultado = $service->evaluacionesConEnlaceDelHorario($horario);

        $this->assertTrue($resultado->contains('id', $vigente->id));
        $this->assertFalse($resultado->contains('id', $vencida->id));
    }

    public function test_crear_una_evaluacion_con_disponible_hasta_lo_persiste(): void
    {
        $horario = Horario::factory()->create();
        $fecha = now()->addWeek();

        $evaluacion = $this->service()->crear($horario, 'Evaluación', '2026-07-15', 'https://forms.test/examen', $fecha->format('Y-m-d H:i:s'));

        $this->assertSame($fecha->format('Y-m-d H:i'), $evaluacion->disponible_hasta->format('Y-m-d H:i'));
    }

    public function test_actualizar_enlace_tambien_actualiza_disponible_hasta(): void
    {
        $horario = Horario::factory()->create();
        $evaluacion = $this->service()->crear($horario, 'Evaluación', '2026-07-15', 'https://forms.test/examen');
        $fecha = now()->addWeek();

        $this->service()->actualizarEnlace($evaluacion, 'https://forms.test/nuevo', $fecha->format('Y-m-d H:i:s'));

        $evaluacion->refresh();
        $this->assertSame('https://forms.test/nuevo', $evaluacion->enlace_externo);
        $this->assertSame($fecha->format('Y-m-d H:i'), $evaluacion->disponible_hasta->format('Y-m-d H:i'));
    }

    public function test_enlace_disponible_es_falso_si_no_esta_publicada(): void
    {
        $horario = Horario::factory()->create();
        $evaluacion = $this->service()->crear($horario, 'Evaluación', '2026-07-15', 'https://forms.test/examen');

        $this->assertFalse($evaluacion->enlaceDisponible());
    }

    public function test_enlace_disponible_es_verdadero_publicada_sin_fecha_limite(): void
    {
        $horario = Horario::factory()->create();
        $service = $this->service();
        $evaluacion = $service->crear($horario, 'Evaluación', '2026-07-15', 'https://forms.test/examen');
        $service->publicar($evaluacion);

        $this->assertTrue($evaluacion->refresh()->enlaceDisponible());
    }

    public function test_enlace_disponible_es_falso_publicada_pero_vencida(): void
    {
        $horario = Horario::factory()->create();
        $service = $this->service();
        $evaluacion = $service->crear($horario, 'Evaluación', '2026-07-15', 'https://forms.test/examen', now()->subDay()->format('Y-m-d H:i:s'));
        $service->publicar($evaluacion);

        $this->assertFalse($evaluacion->refresh()->enlaceDisponible());
    }
}
