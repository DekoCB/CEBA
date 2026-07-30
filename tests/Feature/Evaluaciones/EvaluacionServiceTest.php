<?php

namespace Tests\Feature\Evaluaciones;

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

    public function test_promedio_es_null_sin_calificaciones_publicadas(): void
    {
        $horario = Horario::factory()->create();
        $estudiante = Estudiante::factory()->create();

        $this->assertNull($this->service()->promedioDelEstudiante($estudiante, $horario));
    }
}
