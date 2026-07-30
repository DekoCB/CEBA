<?php

namespace Tests\Feature\Matricula;

use App\Modules\Academico\Enums\TipoPublicoEnum;
use App\Modules\Academico\Models\Ciclo;
use App\Modules\Academico\Models\Grado;
use App\Modules\Matricula\DTOs\RegistrarApoderadoData;
use App\Modules\Matricula\DTOs\RegistrarEstudianteData;
use App\Modules\Matricula\DTOs\RegistrarMatriculaData;
use App\Modules\Matricula\Events\EstudianteMatriculado;
use App\Modules\Matricula\Services\MatriculaService;
use App\Shared\ValueObjects\Dni;
use App\Shared\ValueObjects\Telefono;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class MatriculaServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): MatriculaService
    {
        return $this->app->make(MatriculaService::class);
    }

    private function datosEstudianteMayor(): RegistrarEstudianteData
    {
        return new RegistrarEstudianteData(
            nombres: 'Ana',
            apellidos: 'García Pérez',
            dni: new Dni('12345678'),
            fechaNacimiento: now()->subYears(30)->format('Y-m-d'),
            estadoCivil: null,
            direccion: 'Av. Siempre Viva 123',
            celular: new Telefono('987654321'),
            email: 'ana@example.com',
            observaciones: null,
        );
    }

    private function cicloConPeriodoAbierto(): Ciclo
    {
        $ciclo = Ciclo::factory()->activo()->create([
            'fecha_inicio' => now()->subDays(20),
            'fecha_fin' => now()->addMonths(5),
        ]);

        $ciclo->periodosMatricula()->create([
            'fecha_inicio' => now()->subDays(10),
            'fecha_fin' => now()->addDays(10),
        ]);

        return $ciclo;
    }

    public function test_calcula_correctamente_si_es_menor_de_edad(): void
    {
        $this->assertTrue(MatriculaService::esMenorDeEdad(now()->subYears(15)->format('Y-m-d')));
        $this->assertFalse(MatriculaService::esMenorDeEdad(now()->subYears(25)->format('Y-m-d')));
    }

    public function test_registra_un_estudiante_correctamente(): void
    {
        $estudiante = $this->service()->registrarEstudiante($this->datosEstudianteMayor());

        $this->assertDatabaseHas('estudiantes', ['dni' => '12345678', 'es_menor_edad' => false]);
        $this->assertSame('Ana García Pérez', $estudiante->nombreCompleto());
    }

    public function test_no_permite_registrar_apoderado_para_estudiante_mayor_de_edad(): void
    {
        $estudiante = $this->service()->registrarEstudiante($this->datosEstudianteMayor());

        $this->expectException(ValidationException::class);

        $this->service()->registrarApoderado($estudiante, new RegistrarApoderadoData(
            nombres: 'Pedro García',
            dni: new Dni('87654321'),
            celular: new Telefono('912345678'),
            correo: null,
            direccion: null,
            parentesco: 'Padre',
        ));
    }

    public function test_no_permite_matricular_en_grado_incoherente_con_la_edad(): void
    {
        $estudiante = $this->service()->registrarEstudiante($this->datosEstudianteMayor());
        $ciclo = $this->cicloConPeriodoAbierto();
        $gradoMenores = Grado::factory()->create(['tipo_publico' => TipoPublicoEnum::MENOR]);

        $this->expectException(ValidationException::class);

        $this->service()->matricular($estudiante, new RegistrarMatriculaData(
            cicloId: $ciclo->id,
            gradoId: $gradoMenores->id,
            observaciones: null,
            registradoPor: null,
        ));
    }

    public function test_no_permite_matricular_sin_periodo_de_matricula_abierto(): void
    {
        $estudiante = $this->service()->registrarEstudiante($this->datosEstudianteMayor());
        $ciclo = Ciclo::factory()->activo()->create();
        $grado = Grado::factory()->create(['tipo_publico' => TipoPublicoEnum::MAYOR]);

        $this->expectException(ValidationException::class);

        $this->service()->matricular($estudiante, new RegistrarMatriculaData(
            cicloId: $ciclo->id,
            gradoId: $grado->id,
            observaciones: null,
            registradoPor: null,
        ));
    }

    public function test_no_permite_matricula_duplicada_para_el_mismo_estudiante_y_ciclo(): void
    {
        $estudiante = $this->service()->registrarEstudiante($this->datosEstudianteMayor());
        $ciclo = $this->cicloConPeriodoAbierto();
        $grado = Grado::factory()->create(['tipo_publico' => TipoPublicoEnum::MAYOR]);

        $data = new RegistrarMatriculaData($ciclo->id, $grado->id, null, null);

        $this->service()->matricular($estudiante, $data);

        $this->expectException(ValidationException::class);

        $this->service()->matricular($estudiante, $data);
    }

    public function test_matricular_dispara_el_evento_estudiante_matriculado(): void
    {
        Event::fake([EstudianteMatriculado::class]);

        $estudiante = $this->service()->registrarEstudiante($this->datosEstudianteMayor());
        $ciclo = $this->cicloConPeriodoAbierto();
        $grado = Grado::factory()->create(['tipo_publico' => TipoPublicoEnum::MAYOR]);

        $this->service()->matricular($estudiante, new RegistrarMatriculaData($ciclo->id, $grado->id, null, null));

        Event::assertDispatched(EstudianteMatriculado::class);
    }

    public function test_matricular_actualiza_el_grado_actual_del_estudiante(): void
    {
        $estudiante = $this->service()->registrarEstudiante($this->datosEstudianteMayor());
        $ciclo = $this->cicloConPeriodoAbierto();
        $grado = Grado::factory()->create(['tipo_publico' => TipoPublicoEnum::MAYOR]);

        $this->service()->matricular($estudiante, new RegistrarMatriculaData($ciclo->id, $grado->id, null, null));

        $this->assertSame($grado->id, $estudiante->fresh()->grado_actual_id);
    }
}
