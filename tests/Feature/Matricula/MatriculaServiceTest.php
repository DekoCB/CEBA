<?php

namespace Tests\Feature\Matricula;

use App\Models\User;
use App\Modules\Academico\Enums\TipoSiagieEnum;
use App\Modules\Academico\Models\Ciclo;
use App\Modules\Academico\Models\Curso;
use App\Modules\Academico\Models\Grado;
use App\Modules\Academico\Models\Horario;
use App\Modules\Academico\Models\Siagie;
use App\Modules\Identidad\Database\Seeders\RolesAndPermissionsSeeder;
use App\Modules\Matricula\DTOs\RegistrarApoderadoData;
use App\Modules\Matricula\DTOs\RegistrarEstudianteData;
use App\Modules\Matricula\DTOs\RegistrarMatriculaData;
use App\Modules\Matricula\Events\EstudianteMatriculado;
use App\Modules\Matricula\Services\MatriculaService;
use App\Modules\Pagos\Models\CargoAdicional;
use App\Modules\Pagos\Models\Cuota;
use App\Modules\Pagos\Models\Pago;
use App\Modules\Pagos\Models\PlanPago;
use App\Modules\Pagos\Models\Recibo;
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

    private function datosEstudianteMenor(): RegistrarEstudianteData
    {
        return new RegistrarEstudianteData(
            nombres: 'Diego',
            apellidos: 'Torres Huamán',
            dni: new Dni('78912345'),
            fechaNacimiento: now()->subYears(15)->format('Y-m-d'),
            estadoCivil: null,
            direccion: 'Av. Las Flores 512',
            celular: null,
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

    /**
     * Pedido del cliente: la mayoría/minoría de edad se decide según la
     * fecha de matrícula, no según hoy -- relevante si el registro se hace
     * días después de la matrícula real, o con fecha pasada a propósito.
     */
    public function test_es_menor_de_edad_usa_la_fecha_de_referencia_indicada_en_vez_de_hoy(): void
    {
        $fechaNacimiento = now()->subYears(18)->subDays(10)->format('Y-m-d');

        // Hoy ya es mayor de edad (los 18 años se cumplieron hace 10 días)...
        $this->assertFalse(MatriculaService::esMenorDeEdad($fechaNacimiento));

        // ...pero contra una fecha de matrícula de hace 20 días, todavía era menor.
        $this->assertTrue(MatriculaService::esMenorDeEdad($fechaNacimiento, now()->subDays(20)->format('Y-m-d')));
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

    public function test_no_permite_matricular_sin_periodo_de_matricula_abierto(): void
    {
        $estudiante = $this->service()->registrarEstudiante($this->datosEstudianteMayor());
        $ciclo = Ciclo::factory()->activo()->create();
        $grado = Grado::factory()->create();

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
        $grado = Grado::factory()->create();

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
        $grado = Grado::factory()->create();

        $this->service()->matricular($estudiante, new RegistrarMatriculaData($ciclo->id, $grado->id, null, null));

        Event::assertDispatched(EstudianteMatriculado::class);
    }

    public function test_matricular_guarda_el_siagie_elegido_a_mano(): void
    {
        $estudiante = $this->service()->registrarEstudiante($this->datosEstudianteMayor());
        $ciclo = $this->cicloConPeriodoAbierto();
        $grado = Grado::factory()->create();
        $siagie = Siagie::factory()->create(['tipo' => TipoSiagieEnum::SEGUNDO, 'anio' => $ciclo->anio]);

        $matricula = $this->service()->matricular($estudiante, new RegistrarMatriculaData(
            cicloId: $ciclo->id,
            gradoId: $grado->id,
            observaciones: null,
            registradoPor: null,
            siagieId: $siagie->id,
        ));

        $this->assertSame($siagie->id, $matricula->fresh()->siagie_id);
        $this->assertSame("{$ciclo->anio}-2", $matricula->siagieCompleto());
    }

    public function test_matricular_sin_elegir_siagie_lo_deja_nulo(): void
    {
        $estudiante = $this->service()->registrarEstudiante($this->datosEstudianteMayor());
        $ciclo = $this->cicloConPeriodoAbierto();
        $grado = Grado::factory()->create();

        $matricula = $this->service()->matricular($estudiante, new RegistrarMatriculaData($ciclo->id, $grado->id, null, null));

        $this->assertNull($matricula->siagie_id);
        $this->assertNull($matricula->siagieCompleto());
    }

    public function test_matricular_actualiza_el_grado_actual_del_estudiante(): void
    {
        $estudiante = $this->service()->registrarEstudiante($this->datosEstudianteMayor());
        $ciclo = $this->cicloConPeriodoAbierto();
        $grado = Grado::factory()->create();

        $this->service()->matricular($estudiante, new RegistrarMatriculaData($ciclo->id, $grado->id, null, null));

        $this->assertSame($grado->id, $estudiante->fresh()->grado_actual_id);
    }

    public function test_matricular_en_un_grado_sin_horarios_deja_la_matricula_sin_horarios_asignados(): void
    {
        $estudiante = $this->service()->registrarEstudiante($this->datosEstudianteMayor());
        $ciclo = $this->cicloConPeriodoAbierto();
        $grado = Grado::factory()->create();

        $matricula = $this->service()->matricular($estudiante, new RegistrarMatriculaData($ciclo->id, $grado->id, null, null));

        $this->assertTrue($matricula->horarios->isEmpty());
    }

    public function test_matricular_calcula_fecha_fin_estudio_a_seis_meses_para_mayor_de_edad(): void
    {
        $estudiante = $this->service()->registrarEstudiante($this->datosEstudianteMayor());
        $ciclo = $this->cicloConPeriodoAbierto();
        $grado = Grado::factory()->create();

        $matricula = $this->service()->matricular($estudiante, new RegistrarMatriculaData($ciclo->id, $grado->id, null, null));

        $this->assertTrue($matricula->fecha_matricula->addMonths(6)->isSameDay($matricula->fecha_fin_estudio));
    }

    public function test_matricular_calcula_fecha_fin_estudio_a_ocho_meses_para_menor_de_edad(): void
    {
        $estudiante = $this->service()->registrarEstudiante($this->datosEstudianteMenor());
        $ciclo = $this->cicloConPeriodoAbierto();
        $grado = Grado::factory()->create();

        $matricula = $this->service()->matricular($estudiante, new RegistrarMatriculaData($ciclo->id, $grado->id, null, null));

        $this->assertTrue($matricula->fecha_matricula->addMonths(8)->isSameDay($matricula->fecha_fin_estudio));
    }

    public function test_matricular_en_un_ciclo_anual_no_exige_periodo_de_matricula_abierto(): void
    {
        // A diferencia de los Grupos de 6 meses, SIAGIE anual no depende de
        // un PeriodoMatricula: este ciclo no tiene ninguno y aun así debe
        // poder matricularse.
        $estudiante = $this->service()->registrarEstudiante($this->datosEstudianteMayor());
        $ciclo = Ciclo::factory()->anual()->activo()->create();
        $grado = Grado::factory()->create();

        $matricula = $this->service()->matricular($estudiante, new RegistrarMatriculaData($ciclo->id, $grado->id, null, null));

        $this->assertTrue($ciclo->fecha_fin->isSameDay($matricula->fecha_fin_estudio));
    }

    public function test_reasignar_fecha_fin_estudio_la_actualiza(): void
    {
        $estudiante = $this->service()->registrarEstudiante($this->datosEstudianteMayor());
        $ciclo = $this->cicloConPeriodoAbierto();
        $grado = Grado::factory()->create();

        $matricula = $this->service()->matricular($estudiante, new RegistrarMatriculaData($ciclo->id, $grado->id, null, null));

        $nuevaFecha = now()->addMonths(9)->format('Y-m-d');
        $matricula = $this->service()->reasignarFechaFinEstudio($matricula, $nuevaFecha);

        $this->assertSame($nuevaFecha, $matricula->fecha_fin_estudio->format('Y-m-d'));
        $this->assertSame($nuevaFecha, $matricula->fresh()->fecha_fin_estudio->format('Y-m-d'));
    }

    public function test_asignar_horario_de_curso_agrega_el_horario_a_la_matricula(): void
    {
        $estudiante = $this->service()->registrarEstudiante($this->datosEstudianteMayor());
        $ciclo = $this->cicloConPeriodoAbierto();
        $grado = Grado::factory()->create();
        Horario::factory()->create(['grado_id' => $grado->id, 'ciclo_id' => $ciclo->id]);
        $horarioDos = Horario::factory()->create(['grado_id' => $grado->id, 'ciclo_id' => $ciclo->id]);

        $matricula = $this->service()->matricular($estudiante, new RegistrarMatriculaData($ciclo->id, $grado->id, null, null));

        $matricula = $this->service()->asignarHorarioDeCurso($matricula, $horarioDos->curso_id, $horarioDos->id);

        $this->assertTrue($matricula->horarios->pluck('id')->contains($horarioDos->id));
        $this->assertTrue($matricula->fresh()->horarios->pluck('id')->contains($horarioDos->id));
    }

    public function test_asignar_horario_de_curso_reemplaza_la_asignacion_previa_del_mismo_curso(): void
    {
        $estudiante = $this->service()->registrarEstudiante($this->datosEstudianteMayor());
        $ciclo = $this->cicloConPeriodoAbierto();
        $grado = Grado::factory()->create();
        $curso = Curso::factory()->create(['grado_id' => $grado->id]);
        $seccionUno = Horario::factory()->create(['curso_id' => $curso->id, 'grado_id' => $grado->id, 'ciclo_id' => $ciclo->id]);
        $seccionDos = Horario::factory()->create(['curso_id' => $curso->id, 'grado_id' => $grado->id, 'ciclo_id' => $ciclo->id]);

        $matricula = $this->service()->matricular($estudiante, new RegistrarMatriculaData($ciclo->id, $grado->id, null, null));
        $this->service()->asignarHorarioDeCurso($matricula, $curso->id, $seccionUno->id);

        $matricula = $this->service()->asignarHorarioDeCurso($matricula, $curso->id, $seccionDos->id);

        $this->assertSame([$seccionDos->id], $matricula->fresh()->horarios->pluck('id')->all());
    }

    public function test_asignar_horario_de_curso_a_null_lo_deja_sin_asignar(): void
    {
        $estudiante = $this->service()->registrarEstudiante($this->datosEstudianteMayor());
        $ciclo = $this->cicloConPeriodoAbierto();
        $grado = Grado::factory()->create();
        $horario = Horario::factory()->create(['grado_id' => $grado->id, 'ciclo_id' => $ciclo->id]);

        $matricula = $this->service()->matricular($estudiante, new RegistrarMatriculaData($ciclo->id, $grado->id, null, null));
        $matricula = $this->service()->asignarHorarioDeCurso($matricula, $horario->curso_id, $horario->id);

        $matricula = $this->service()->asignarHorarioDeCurso($matricula, $horario->curso_id, null);

        $this->assertTrue($matricula->fresh()->horarios->isEmpty());
    }

    public function test_asignar_horario_de_curso_con_uno_ajeno_al_grado_lanza_excepcion(): void
    {
        $estudiante = $this->service()->registrarEstudiante($this->datosEstudianteMayor());
        $ciclo = $this->cicloConPeriodoAbierto();
        $grado = Grado::factory()->create();
        $horarioAjeno = Horario::factory()->create();

        $matricula = $this->service()->matricular($estudiante, new RegistrarMatriculaData($ciclo->id, $grado->id, null, null));

        $this->expectException(ValidationException::class);

        $this->service()->asignarHorarioDeCurso($matricula, $horarioAjeno->curso_id, $horarioAjeno->id);
    }

    /**
     * Pedido del cliente: poder borrar toda la ficha de un estudiante
     * (matrícula, apoderado, documentos, celulares, cargos) si todavía no
     * se le emitió ningún recibo de pago -- un pago pendiente o rechazado
     * sin recibo no es obstáculo.
     */
    public function test_eliminar_estudiante_completo_borra_todos_los_datos_relacionados(): void
    {
        $estudiante = $this->service()->registrarEstudiante($this->datosEstudianteMenor());
        $this->service()->registrarApoderado($estudiante, new RegistrarApoderadoData(
            nombres: 'José Torres',
            dni: new Dni('44556677'),
            celular: new Telefono('987654321'),
            correo: null,
            direccion: null,
            parentesco: 'Padre',
        ));
        $estudiante->telefonos()->create(['numero' => '912345678']);

        $ciclo = $this->cicloConPeriodoAbierto();
        $grado = Grado::factory()->create();
        $matricula = $this->service()->matricular($estudiante, new RegistrarMatriculaData($ciclo->id, $grado->id, null, null));

        $plan = PlanPago::factory()->create(['matricula_id' => $matricula->id]);
        Cuota::factory()->create(['plan_pago_id' => $plan->id, 'numero' => 1]);
        CargoAdicional::factory()->create(['estudiante_id' => $estudiante->id]);
        Pago::factory()->create(['estudiante_id' => $estudiante->id, 'estado' => 'rechazado']);

        $estudianteId = $estudiante->id;
        $apoderadoId = $estudiante->apoderado->id;

        $dni = $estudiante->dni;

        $this->service()->eliminarEstudianteCompleto($estudiante);

        // forceDelete(), no borrado lógico: una fila "blanda" seguiría
        // ocupando el DNI contra el índice único de la columna.
        $this->assertDatabaseMissing('estudiantes', ['id' => $estudianteId]);
        $this->assertDatabaseMissing('matriculas', ['id' => $matricula->id]);
        $this->assertDatabaseMissing('apoderados', ['id' => $apoderadoId]);
        $this->assertDatabaseMissing('planes_pago', ['id' => $plan->id]);
        $this->assertDatabaseMissing('pagos', ['estudiante_id' => $estudianteId]);
        $this->assertDatabaseMissing('cargos_adicionales', ['estudiante_id' => $estudianteId]);
        $this->assertDatabaseMissing('estudiante_telefonos', ['estudiante_id' => $estudianteId]);

        $this->assertTrue($this->service()->dniDisponible($dni));
    }

    /**
     * Bug real visto en producción: después de eliminar un estudiante,
     * volver a registrar a alguien con el mismo DNI reventaba con un 500
     * crudo (Duplicate entry ... for key estudiantes_dni_unique) porque el
     * borrado era lógico y la fila vieja seguía ocupando el DNI. Cubre el
     * caso feliz (forceDelete libera el DNI de verdad) además del test de
     * arriba.
     */
    public function test_el_dni_de_un_estudiante_eliminado_queda_disponible_para_un_registro_nuevo(): void
    {
        $datos = $this->datosEstudianteMayor();
        $estudiante = $this->service()->registrarEstudiante($datos);
        $dni = $estudiante->dni;

        $this->service()->eliminarEstudianteCompleto($estudiante);

        $nuevo = $this->service()->registrarEstudiante($datos);

        $this->assertSame($dni, $nuevo->dni);
        $this->assertNotSame($estudiante->id, $nuevo->id);
    }

    /**
     * Defensa adicional: si por cualquier motivo quedara una fila "resto"
     * con un DNI fuera del alcance normal de Eloquent (ej. de antes de este
     * fix), registrar ya no debe reventar con un 500 crudo -- debe quedar
     * como un error de formulario claro.
     */
    public function test_registrar_estudiante_con_un_dni_en_conflicto_fuera_del_alcance_normal_lanza_error_de_formulario(): void
    {
        $datos = $this->datosEstudianteMayor();
        $estudiante = $this->service()->registrarEstudiante($datos);
        $estudiante->delete(); // borrado lógico a propósito, para simular el resto

        $this->expectException(ValidationException::class);

        $this->service()->registrarEstudiante($datos);
    }

    public function test_no_permite_eliminar_estudiante_completo_si_ya_se_emitio_un_recibo(): void
    {
        $estudiante = $this->service()->registrarEstudiante($this->datosEstudianteMayor());
        $pago = Pago::factory()->aprobado()->create(['estudiante_id' => $estudiante->id]);
        Recibo::factory()->create(['pago_id' => $pago->id]);

        $this->expectException(ValidationException::class);

        $this->service()->eliminarEstudianteCompleto($estudiante);
    }

    public function test_esta_eliminable_completo_es_falso_si_ya_se_emitio_un_recibo(): void
    {
        $estudiante = $this->service()->registrarEstudiante($this->datosEstudianteMayor());
        $pago = Pago::factory()->aprobado()->create(['estudiante_id' => $estudiante->id]);
        Recibo::factory()->create(['pago_id' => $pago->id]);

        $this->assertFalse($this->service()->esEliminableCompleto($estudiante));
    }
}
