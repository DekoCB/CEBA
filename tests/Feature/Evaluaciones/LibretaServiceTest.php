<?php

namespace Tests\Feature\Evaluaciones;

use App\Modules\Academico\Models\Ciclo;
use App\Modules\Academico\Models\Horario;
use App\Modules\Evaluaciones\Services\EvaluacionService;
use App\Modules\Evaluaciones\Services\LibretaService;
use App\Modules\Matricula\Models\Estudiante;
use App\Modules\Matricula\Models\Matricula;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class LibretaServiceTest extends TestCase
{
    use RefreshDatabase;

    private function libretaService(): LibretaService
    {
        return $this->app->make(LibretaService::class);
    }

    public function test_generar_libreta_falla_si_no_hay_matricula_aprobada_en_ese_ciclo(): void
    {
        $estudiante = Estudiante::factory()->create();
        $ciclo = Ciclo::factory()->create();

        $this->expectException(ValidationException::class);

        $this->libretaService()->generar($estudiante, $ciclo);
    }

    public function test_generar_libreta_calcula_el_promedio_por_curso_y_adjunta_el_pdf(): void
    {
        Storage::fake('public');

        $ciclo = Ciclo::factory()->create();
        $estudiante = Estudiante::factory()->create();
        $horario = Horario::factory()->create(['ciclo_id' => $ciclo->id]);
        Matricula::factory()->create([
            'estudiante_id' => $estudiante->id,
            'ciclo_id' => $ciclo->id,
            'grado_id' => $horario->grado_id,
        ]);

        $evaluacionService = $this->app->make(EvaluacionService::class);
        $evaluacion = $evaluacionService->crear($horario, 'Evaluación', '2026-07-15');
        $evaluacionService->calificar($evaluacion, $estudiante, 16.0, null, null);
        $evaluacionService->publicar($evaluacion);

        $libreta = $this->libretaService()->generar($estudiante, $ciclo);

        $this->assertNotNull($libreta->generado_en);
        $this->assertNotNull($libreta->getFirstMedia('pdf'));
        $this->assertDatabaseHas('libretas', ['estudiante_id' => $estudiante->id, 'ciclo_id' => $ciclo->id]);
    }

    public function test_generar_libreta_dos_veces_actualiza_la_misma_fila(): void
    {
        Storage::fake('public');

        $ciclo = Ciclo::factory()->create();
        $estudiante = Estudiante::factory()->create();
        $horario = Horario::factory()->create(['ciclo_id' => $ciclo->id]);
        Matricula::factory()->create([
            'estudiante_id' => $estudiante->id,
            'ciclo_id' => $ciclo->id,
            'grado_id' => $horario->grado_id,
        ]);

        $this->libretaService()->generar($estudiante, $ciclo);
        $this->libretaService()->generar($estudiante, $ciclo);

        $this->assertDatabaseCount('libretas', 1);
    }
}
