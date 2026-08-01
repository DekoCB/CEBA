<?php

namespace Tests\Feature\Asistencia;

use App\Modules\Academico\Models\Horario;
use App\Modules\Asistencia\Enums\EstadoAsistenciaEnum;
use App\Modules\Asistencia\Models\Asistencia;
use App\Modules\Asistencia\Services\AsistenciaService;
use App\Modules\Matricula\Models\Estudiante;
use App\Modules\Matricula\Models\Matricula;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AsistenciaServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): AsistenciaService
    {
        return $this->app->make(AsistenciaService::class);
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

    public function test_registrar_crea_un_registro_de_asistencia_por_estudiante(): void
    {
        $horario = Horario::factory()->create();
        $estudiante = Estudiante::factory()->create();

        $this->service()->registrar($horario, '2026-09-01', [
            $estudiante->id => EstadoAsistenciaEnum::PRESENTE->value,
        ]);

        $this->assertDatabaseHas('asistencias', [
            'horario_id' => $horario->id,
            'estudiante_id' => $estudiante->id,
            'fecha' => '2026-09-01',
            'estado' => 'presente',
        ]);
    }

    public function test_registrar_dos_veces_la_misma_fecha_actualiza_en_vez_de_duplicar(): void
    {
        $horario = Horario::factory()->create();
        $estudiante = Estudiante::factory()->create();

        $this->service()->registrar($horario, '2026-09-01', [
            $estudiante->id => EstadoAsistenciaEnum::PRESENTE->value,
        ]);
        $this->service()->registrar($horario, '2026-09-01', [
            $estudiante->id => EstadoAsistenciaEnum::TARDANZA->value,
        ]);

        $this->assertDatabaseCount('asistencias', 1);
        $this->assertDatabaseHas('asistencias', ['estado' => 'tardanza']);
    }

    public function test_registrar_guarda_la_observacion_y_el_documento_de_una_falta_justificada(): void
    {
        Storage::fake('public');

        $horario = Horario::factory()->create();
        $estudiante = Estudiante::factory()->create();

        $this->service()->registrar(
            $horario,
            '2026-09-01',
            [$estudiante->id => EstadoAsistenciaEnum::JUSTIFICADO->value],
            [$estudiante->id => 'Cita médica'],
            [$estudiante->id => UploadedFile::fake()->create('constancia.pdf', 100, 'application/pdf')],
        );

        $this->assertDatabaseHas('asistencias', [
            'horario_id' => $horario->id,
            'estudiante_id' => $estudiante->id,
            'estado' => 'justificado',
            'observacion' => 'Cita médica',
        ]);

        $asistencia = Asistencia::query()->where('estudiante_id', $estudiante->id)->firstOrFail();
        $this->assertNotNull($asistencia->getFirstMedia('justificante'));
    }

    public function test_registrar_sin_documento_no_borra_el_justificante_ya_subido(): void
    {
        Storage::fake('public');

        $horario = Horario::factory()->create();
        $estudiante = Estudiante::factory()->create();
        $service = $this->service();

        $service->registrar(
            $horario,
            '2026-09-01',
            [$estudiante->id => EstadoAsistenciaEnum::JUSTIFICADO->value],
            [$estudiante->id => 'Cita médica'],
            [$estudiante->id => UploadedFile::fake()->create('constancia.pdf', 100, 'application/pdf')],
        );

        $service->registrar(
            $horario,
            '2026-09-01',
            [$estudiante->id => EstadoAsistenciaEnum::JUSTIFICADO->value],
            [$estudiante->id => 'Cita médica reprogramada'],
        );

        $asistencia = Asistencia::query()->where('estudiante_id', $estudiante->id)->firstOrFail();
        $this->assertSame('Cita médica reprogramada', $asistencia->observacion);
        $this->assertNotNull($asistencia->getFirstMedia('justificante'));
    }

    public function test_resumen_estudiante_calcula_el_porcentaje_de_asistencia(): void
    {
        $horario = Horario::factory()->create();
        $estudiante = Estudiante::factory()->create();
        $service = $this->service();

        $service->registrar($horario, '2026-09-01', [$estudiante->id => EstadoAsistenciaEnum::PRESENTE->value]);
        $service->registrar($horario, '2026-09-02', [$estudiante->id => EstadoAsistenciaEnum::FALTA->value]);
        $service->registrar($horario, '2026-09-03', [$estudiante->id => EstadoAsistenciaEnum::TARDANZA->value]);
        $service->registrar($horario, '2026-09-04', [$estudiante->id => EstadoAsistenciaEnum::PRESENTE->value]);

        $resumen = $service->resumenEstudiante($estudiante, $horario);

        $this->assertSame(4, $resumen['total']);
        $this->assertSame(3, $resumen['asistio']);
        $this->assertSame(75.0, $resumen['porcentaje']);
    }

    public function test_fechas_registradas_devuelve_las_fechas_unicas_ordenadas_descendente(): void
    {
        $horario = Horario::factory()->create();
        $estudianteUno = Estudiante::factory()->create();
        $estudianteDos = Estudiante::factory()->create();
        $service = $this->service();

        $service->registrar($horario, '2026-09-01', [
            $estudianteUno->id => EstadoAsistenciaEnum::PRESENTE->value,
            $estudianteDos->id => EstadoAsistenciaEnum::PRESENTE->value,
        ]);
        $service->registrar($horario, '2026-09-03', [
            $estudianteUno->id => EstadoAsistenciaEnum::PRESENTE->value,
        ]);

        $fechas = $service->fechasRegistradas($horario);

        $this->assertSame(['2026-09-03', '2026-09-01'], $fechas->all());
    }
}
