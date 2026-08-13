<?php

namespace Tests\Feature\Matricula;

use App\Models\User;
use App\Modules\Academico\Enums\TipoPublicoEnum;
use App\Modules\Academico\Models\Ciclo;
use App\Modules\Academico\Models\Grado;
use App\Modules\Academico\Models\Horario;
use App\Modules\Identidad\Database\Seeders\RolesAndPermissionsSeeder;
use App\Modules\Matricula\DTOs\RegistrarApoderadoData;
use App\Modules\Matricula\DTOs\RegistrarEstudianteData;
use App\Modules\Matricula\DTOs\RegistrarMatriculaData;
use App\Modules\Matricula\Events\EstudianteMatriculado;
use App\Modules\Matricula\Services\MatriculaService;
use App\Shared\Enums\RolEnum;
use App\Shared\ValueObjects\Dni;
use App\Shared\ValueObjects\Telefono;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class MatriculaServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

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

    public function test_registrar_estudiante_le_crea_su_cuenta_de_acceso_con_correo_y_password_autoasignados(): void
    {
        $estudiante = $this->service()->registrarEstudiante($this->datosEstudianteMayor());

        $this->assertSame('12345678@ceba.test', $estudiante->email);
        $this->assertNotNull($estudiante->user_id);

        $usuario = $estudiante->user;
        $this->assertSame('12345678@ceba.test', $usuario->email);
        $this->assertSame('12345678', $usuario->dni);
        $this->assertTrue($usuario->hasRole(RolEnum::ESTUDIANTE->value));
        $this->assertTrue(Hash::check('12345678', $usuario->password));
    }

    public function test_registrar_estudiante_reutiliza_una_cuenta_existente_con_el_mismo_dni(): void
    {
        $usuarioExistente = User::factory()->create(['dni' => '12345678']);

        $estudiante = $this->service()->registrarEstudiante($this->datosEstudianteMayor());

        $this->assertSame($usuarioExistente->id, $estudiante->user_id);
        $this->assertTrue($usuarioExistente->fresh()->hasRole(RolEnum::ESTUDIANTE->value));
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
            horarioId: null,
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
            horarioId: null,
            observaciones: null,
            registradoPor: null,
        ));
    }

    public function test_no_permite_matricula_duplicada_para_el_mismo_estudiante_y_ciclo(): void
    {
        $estudiante = $this->service()->registrarEstudiante($this->datosEstudianteMayor());
        $ciclo = $this->cicloConPeriodoAbierto();
        $grado = Grado::factory()->create(['tipo_publico' => TipoPublicoEnum::MAYOR]);

        $data = new RegistrarMatriculaData($ciclo->id, $grado->id, null, null, null);

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

        $this->service()->matricular($estudiante, new RegistrarMatriculaData($ciclo->id, $grado->id, null, null, null));

        Event::assertDispatched(EstudianteMatriculado::class);
    }

    public function test_matricular_actualiza_el_grado_actual_del_estudiante(): void
    {
        $estudiante = $this->service()->registrarEstudiante($this->datosEstudianteMayor());
        $ciclo = $this->cicloConPeriodoAbierto();
        $grado = Grado::factory()->create(['tipo_publico' => TipoPublicoEnum::MAYOR]);

        $this->service()->matricular($estudiante, new RegistrarMatriculaData($ciclo->id, $grado->id, null, null, null));

        $this->assertSame($grado->id, $estudiante->fresh()->grado_actual_id);
    }

    public function test_matricular_en_un_grado_sin_horarios_deja_la_matricula_sin_horario_id(): void
    {
        $estudiante = $this->service()->registrarEstudiante($this->datosEstudianteMayor());
        $ciclo = $this->cicloConPeriodoAbierto();
        $grado = Grado::factory()->create(['tipo_publico' => TipoPublicoEnum::MAYOR]);

        $matricula = $this->service()->matricular($estudiante, new RegistrarMatriculaData($ciclo->id, $grado->id, null, null, null));

        $this->assertNull($matricula->horario_id);
    }

    public function test_matricular_en_un_grado_con_un_solo_horario_lo_asigna_automaticamente(): void
    {
        $estudiante = $this->service()->registrarEstudiante($this->datosEstudianteMayor());
        $ciclo = $this->cicloConPeriodoAbierto();
        $grado = Grado::factory()->create(['tipo_publico' => TipoPublicoEnum::MAYOR]);
        $horario = Horario::factory()->create(['grado_id' => $grado->id, 'ciclo_id' => $ciclo->id]);

        $matricula = $this->service()->matricular($estudiante, new RegistrarMatriculaData($ciclo->id, $grado->id, null, null, null));

        $this->assertSame($horario->id, $matricula->horario_id);
    }

    public function test_matricular_en_un_grado_con_varias_secciones_sin_elegir_una_lanza_excepcion(): void
    {
        $estudiante = $this->service()->registrarEstudiante($this->datosEstudianteMayor());
        $ciclo = $this->cicloConPeriodoAbierto();
        $grado = Grado::factory()->create(['tipo_publico' => TipoPublicoEnum::MAYOR]);
        Horario::factory()->create(['grado_id' => $grado->id, 'ciclo_id' => $ciclo->id, 'seccion' => 'A']);
        Horario::factory()->create(['grado_id' => $grado->id, 'ciclo_id' => $ciclo->id, 'seccion' => 'B']);

        $this->expectException(ValidationException::class);

        $this->service()->matricular($estudiante, new RegistrarMatriculaData($ciclo->id, $grado->id, null, null, null));
    }

    public function test_matricular_en_un_grado_con_varias_secciones_eligiendo_una_la_asigna(): void
    {
        $estudiante = $this->service()->registrarEstudiante($this->datosEstudianteMayor());
        $ciclo = $this->cicloConPeriodoAbierto();
        $grado = Grado::factory()->create(['tipo_publico' => TipoPublicoEnum::MAYOR]);
        Horario::factory()->create(['grado_id' => $grado->id, 'ciclo_id' => $ciclo->id, 'seccion' => 'A']);
        $horarioB = Horario::factory()->create(['grado_id' => $grado->id, 'ciclo_id' => $ciclo->id, 'seccion' => 'B']);

        $matricula = $this->service()->matricular($estudiante, new RegistrarMatriculaData($ciclo->id, $grado->id, $horarioB->id, null, null));

        $this->assertSame($horarioB->id, $matricula->horario_id);
    }

    public function test_matricular_con_un_horario_que_no_pertenece_al_grado_lanza_excepcion(): void
    {
        $estudiante = $this->service()->registrarEstudiante($this->datosEstudianteMayor());
        $ciclo = $this->cicloConPeriodoAbierto();
        $grado = Grado::factory()->create(['tipo_publico' => TipoPublicoEnum::MAYOR]);
        Horario::factory()->create(['grado_id' => $grado->id, 'ciclo_id' => $ciclo->id, 'seccion' => 'A']);
        Horario::factory()->create(['grado_id' => $grado->id, 'ciclo_id' => $ciclo->id, 'seccion' => 'B']);
        $horarioAjeno = Horario::factory()->create();

        $this->expectException(ValidationException::class);

        $this->service()->matricular($estudiante, new RegistrarMatriculaData($ciclo->id, $grado->id, $horarioAjeno->id, null, null));
    }
}
