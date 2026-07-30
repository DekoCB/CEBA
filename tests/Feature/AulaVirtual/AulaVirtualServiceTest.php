<?php

namespace Tests\Feature\AulaVirtual;

use App\Modules\Academico\Models\Horario;
use App\Modules\AulaVirtual\Enums\EstadoEntregaEnum;
use App\Modules\AulaVirtual\Enums\TipoMaterialEnum;
use App\Modules\AulaVirtual\Models\CursoVirtual;
use App\Modules\AulaVirtual\Services\CursoVirtualService;
use App\Modules\AulaVirtual\Services\MaterialService;
use App\Modules\AulaVirtual\Services\TareaService;
use App\Modules\Matricula\Models\Estudiante;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AulaVirtualServiceTest extends TestCase
{
    use RefreshDatabase;

    private function cursoVirtualService(): CursoVirtualService
    {
        return $this->app->make(CursoVirtualService::class);
    }

    public function test_activar_para_horario_es_idempotente(): void
    {
        $horario = Horario::factory()->create();

        $primero = $this->cursoVirtualService()->activarParaHorario($horario);
        $segundo = $this->cursoVirtualService()->activarParaHorario($horario);

        $this->assertSame($primero->id, $segundo->id);
        $this->assertSame(1, CursoVirtual::query()->count());
    }

    public function test_material_de_tipo_pdf_requiere_archivo(): void
    {
        $curso = CursoVirtual::factory()->create();

        $this->expectException(ValidationException::class);

        $this->app->make(MaterialService::class)->crear($curso, TipoMaterialEnum::PDF, 'Separata', null, null);
    }

    public function test_material_de_tipo_enlace_requiere_url(): void
    {
        $curso = CursoVirtual::factory()->create();

        $this->expectException(ValidationException::class);

        $this->app->make(MaterialService::class)->crear($curso, TipoMaterialEnum::ENLACE, 'Video de repaso', null, null);
    }

    public function test_crear_material_con_archivo_lo_adjunta_a_la_coleccion_archivo(): void
    {
        Storage::fake('public');

        $curso = CursoVirtual::factory()->create();
        $archivo = UploadedFile::fake()->create('separata.pdf', 500, 'application/pdf');

        $material = $this->app->make(MaterialService::class)->crear($curso, TipoMaterialEnum::PDF, 'Separata', null, $archivo);

        $this->assertNotNull($material->getFirstMedia('archivo'));
    }

    public function test_entregar_tarea_antes_de_la_fecha_limite_queda_como_entregado(): void
    {
        $curso = CursoVirtual::factory()->create();
        $tarea = $this->app->make(TareaService::class)->crear($curso, [
            'titulo' => 'Ensayo',
            'descripcion' => null,
            'fecha_limite' => now()->addDay(),
            'puntaje_max' => 20,
        ]);
        $estudiante = Estudiante::factory()->create();

        $entrega = $this->app->make(TareaService::class)->entregar($tarea, $estudiante, 'Mi respuesta', null);

        $this->assertSame(EstadoEntregaEnum::ENTREGADO, $entrega->estado);
    }

    public function test_entregar_tarea_despues_de_la_fecha_limite_queda_como_tarde(): void
    {
        $curso = CursoVirtual::factory()->create();
        $tarea = $this->app->make(TareaService::class)->crear($curso, [
            'titulo' => 'Ensayo',
            'descripcion' => null,
            'fecha_limite' => now()->subDay(),
            'puntaje_max' => 20,
        ]);
        $estudiante = Estudiante::factory()->create();

        $entrega = $this->app->make(TareaService::class)->entregar($tarea, $estudiante, null, null);

        $this->assertSame(EstadoEntregaEnum::TARDE, $entrega->estado);
    }

    public function test_calificar_una_entrega_actualiza_nota_y_estado(): void
    {
        $curso = CursoVirtual::factory()->create();
        $tarea = $this->app->make(TareaService::class)->crear($curso, [
            'titulo' => 'Ensayo',
            'descripcion' => null,
            'fecha_limite' => now()->addDay(),
            'puntaje_max' => 20,
        ]);
        $estudiante = Estudiante::factory()->create();
        $service = $this->app->make(TareaService::class);
        $entrega = $service->entregar($tarea, $estudiante, null, null);

        $calificada = $service->calificar($entrega, 18.5);

        $this->assertEquals(18.5, $calificada->nota);
        $this->assertSame(EstadoEntregaEnum::CALIFICADO, $calificada->estado);
    }

    public function test_reentregar_actualiza_la_misma_fila_en_lugar_de_duplicarla(): void
    {
        $curso = CursoVirtual::factory()->create();
        $tarea = $this->app->make(TareaService::class)->crear($curso, [
            'titulo' => 'Ensayo',
            'descripcion' => null,
            'fecha_limite' => now()->addDay(),
            'puntaje_max' => 20,
        ]);
        $estudiante = Estudiante::factory()->create();
        $service = $this->app->make(TareaService::class);

        $service->entregar($tarea, $estudiante, 'Primer intento', null);
        $service->entregar($tarea, $estudiante, 'Segundo intento', null);

        $this->assertSame(1, $tarea->entregas()->count());
        $this->assertSame('Segundo intento', $tarea->entregas()->first()->comentario);
    }
}
