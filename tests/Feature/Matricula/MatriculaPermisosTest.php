<?php

namespace Tests\Feature\Matricula;

use App\Models\User;
use App\Modules\Academico\Models\Ciclo;
use App\Modules\Academico\Models\Curso;
use App\Modules\Academico\Models\Grado;
use App\Modules\Academico\Models\Horario;
use App\Modules\Identidad\Database\Seeders\RolesAndPermissionsSeeder;
use App\Modules\Matricula\Models\Estudiante;
use App\Modules\Matricula\Models\Matricula;
use App\Modules\Pagos\Models\PlanPago;
use App\Shared\Enums\RolEnum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Volt\Volt;
use Tests\TestCase;

class MatriculaPermisosTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_rol_coordinador_puede_ver_el_listado(): void
    {
        $usuario = User::factory()->create();
        $usuario->assignRole(RolEnum::COORDINADOR->value);

        $this->actingAs($usuario)
            ->get('/matricula')
            ->assertOk();
    }

    public function test_un_docente_no_puede_ver_matricula(): void
    {
        $docente = User::factory()->create();
        $docente->assignRole(RolEnum::DOCENTE->value);

        $this->actingAs($docente)
            ->get('/matricula')
            ->assertForbidden();
    }

    public function test_completar_el_wizard_registra_estudiante_y_matricula(): void
    {
        Storage::fake('public');

        $usuario = User::factory()->create();
        $usuario->assignRole(RolEnum::COORDINADOR->value);

        $ciclo = Ciclo::factory()->activo()->create([
            'fecha_inicio' => now()->subDays(20),
            'fecha_fin' => now()->addMonths(5),
        ]);
        $ciclo->periodosMatricula()->create([
            'fecha_inicio' => now()->subDays(10),
            'fecha_fin' => now()->addDays(10),
        ]);
        $grado = Grado::factory()->create();

        $this->actingAs($usuario);

        Volt::test('matricula.wizard')
            ->set('nombres', 'Luis')
            ->set('apellidos', 'Fernández Ruiz')
            ->set('dni', '55667788')
            ->set('fechaNacimiento', now()->subYears(28)->format('Y-m-d'))
            ->set('observacionesEstudiante', 'Pendiente entregar certificado de estudios del colegio anterior.')
            ->call('avanzar')
            ->assertHasNoErrors()
            ->assertSet('paso', 3)
            ->set('dniEstudianteArchivo', UploadedFile::fake()->create('dni.pdf', 100, 'application/pdf'))
            ->call('avanzar')
            ->assertHasNoErrors()
            ->assertSet('paso', 4)
            ->call('avanzar')
            ->assertSet('paso', 5)
            ->set('cicloId', (string) $ciclo->id)
            ->set('gradoId', (string) $grado->id)
            ->call('confirmar')
            ->assertHasNoErrors()
            ->assertDispatched('matricula-registrada');

        $this->assertDatabaseHas('estudiantes', ['dni' => '55667788']);
        $estudiante = Estudiante::query()->where('dni', '55667788')->firstOrFail();
        $this->assertDatabaseHas('matriculas', ['estudiante_id' => $estudiante->id, 'ciclo_id' => $ciclo->id]);
        $this->assertSame('Pendiente entregar certificado de estudios del colegio anterior.', $estudiante->observaciones);
    }

    public function test_elegir_modalidad_anual_en_el_wizard_autoselecciona_el_ciclo_vigente(): void
    {
        Storage::fake('public');

        $usuario = User::factory()->create();
        $usuario->assignRole(RolEnum::COORDINADOR->value);

        // Sin periodo de matrícula: a diferencia de los Grupos de 6 meses,
        // SIAGE anual no lo necesita para poder matricularse.
        $cicloAnual = Ciclo::factory()->anual()->activo()->create();
        $grado = Grado::factory()->create();

        $this->actingAs($usuario);

        Volt::test('matricula.wizard')
            ->set('nombres', 'Marisol')
            ->set('apellidos', 'Peña Ríos')
            ->set('dni', '55667950')
            ->set('fechaNacimiento', now()->subYears(28)->format('Y-m-d'))
            ->call('avanzar')
            ->assertSet('paso', 3)
            ->set('dniEstudianteArchivo', UploadedFile::fake()->create('dni.pdf', 100, 'application/pdf'))
            ->call('avanzar')
            ->assertSet('paso', 4)
            ->call('avanzar')
            ->assertSet('paso', 5)
            ->set('modalidadCiclo', 'anual')
            ->assertSet('cicloId', (string) $cicloAnual->id)
            ->set('gradoId', (string) $grado->id)
            ->call('confirmar')
            ->assertHasNoErrors()
            ->assertDispatched('matricula-registrada');

        $estudiante = Estudiante::query()->where('dni', '55667950')->firstOrFail();
        $this->assertDatabaseHas('matriculas', ['estudiante_id' => $estudiante->id, 'ciclo_id' => $cicloAnual->id]);
    }

    public function test_el_wizard_detecta_un_dni_existente_y_permite_rematricular_sin_repetir_los_datos(): void
    {
        $usuario = User::factory()->create();
        $usuario->assignRole(RolEnum::COORDINADOR->value);

        $ciclo = Ciclo::factory()->activo()->create([
            'fecha_inicio' => now()->subDays(20),
            'fecha_fin' => now()->addMonths(5),
        ]);
        $ciclo->periodosMatricula()->create([
            'fecha_inicio' => now()->subDays(10),
            'fecha_fin' => now()->addDays(10),
        ]);
        $grado = Grado::factory()->create();

        $estudiante = Estudiante::factory()->create(['dni' => '55667890']);

        $this->actingAs($usuario);

        Volt::test('matricula.wizard')
            ->set('dni', '55667890')
            ->assertSet('estudianteEncontradoId', $estudiante->id)
            ->call('continuarComoRematricula')
            ->assertSet('esRematricula', true)
            ->assertSet('paso', 5)
            ->set('cicloId', (string) $ciclo->id)
            ->set('gradoId', (string) $grado->id)
            ->call('confirmar')
            ->assertHasNoErrors()
            ->assertDispatched('matricula-registrada');

        // No se crea una ficha nueva: sigue siendo el mismo estudiante, solo se le agrega la matrícula.
        $this->assertDatabaseCount('estudiantes', 1);
        $this->assertDatabaseHas('matriculas', [
            'estudiante_id' => $estudiante->id,
            'ciclo_id' => $ciclo->id,
            'grado_id' => $grado->id,
        ]);
    }

    public function test_el_wizard_sigue_bloqueando_avanzar_con_un_dni_ya_registrado_si_no_se_elige_rematricular(): void
    {
        $usuario = User::factory()->create();
        $usuario->assignRole(RolEnum::COORDINADOR->value);

        Estudiante::factory()->create(['dni' => '55667891']);

        $this->actingAs($usuario);

        Volt::test('matricula.wizard')
            ->set('nombres', 'Otro')
            ->set('apellidos', 'Nombre')
            ->set('dni', '55667891')
            ->set('fechaNacimiento', now()->subYears(25)->format('Y-m-d'))
            ->call('avanzar')
            ->assertSet('paso', 1)
            ->assertHasErrors('dni');

        $this->assertDatabaseCount('estudiantes', 1);
    }

    public function test_el_wizard_configura_un_cronograma_de_pagos_personalizado_al_matricular(): void
    {
        Storage::fake('public');

        $usuario = User::factory()->create();
        $usuario->assignRole(RolEnum::COORDINADOR->value);

        $ciclo = Ciclo::factory()->activo()->create([
            'fecha_inicio' => now()->subDays(20),
            'fecha_fin' => now()->addMonths(5),
        ]);
        $ciclo->periodosMatricula()->create([
            'fecha_inicio' => now()->subDays(10),
            'fecha_fin' => now()->addDays(10),
        ]);
        $grado = Grado::factory()->create();

        $this->actingAs($usuario);

        $fechaCuotaAjustada = now()->addDays(5)->format('Y-m-d');

        Volt::test('matricula.wizard')
            ->set('nombres', 'Marisol')
            ->set('apellidos', 'Peña Ríos')
            ->set('dni', '55667799')
            ->set('fechaNacimiento', now()->subYears(30)->format('Y-m-d'))
            ->call('avanzar')
            ->assertSet('paso', 3)
            ->set('dniEstudianteArchivo', UploadedFile::fake()->create('dni.pdf', 100, 'application/pdf'))
            ->call('avanzar')
            ->assertSet('paso', 4)
            ->call('avanzar')
            ->assertSet('paso', 5)
            ->set('cicloId', (string) $ciclo->id)
            ->set('gradoId', (string) $grado->id)
            ->call('avanzar')
            ->assertHasNoErrors()
            ->assertSet('paso', 6)
            ->set('configurarCronograma', true)
            ->set('numeroCuotasCronograma', '6')
            ->set('montoTotalCronograma', '600')
            ->call('generarCronogramaAutomatico')
            ->assertHasNoErrors()
            ->assertSet('cuotaMontos.1', '100.00')
            // Ajusta la primera cuota a mano: distinta del reparto parejo automático.
            ->set('cuotaMontos.1', '150')
            ->set('cuotaFechas.1', $fechaCuotaAjustada)
            ->call('confirmar')
            ->assertHasNoErrors()
            ->assertDispatched('matricula-registrada');

        $estudiante = Estudiante::query()->where('dni', '55667799')->firstOrFail();
        $matricula = $estudiante->matriculas()->firstOrFail();
        $plan = PlanPago::query()->where('matricula_id', $matricula->id)->with('cuotas')->firstOrFail();

        $this->assertCount(6, $plan->cuotas);
        $primeraCuota = $plan->cuotas->firstWhere('numero', 1);
        $this->assertSame('150.00', $primeraCuota->monto);
        $this->assertSame($fechaCuotaAjustada, $primeraCuota->fecha_vencimiento->format('Y-m-d'));
        // El total del plan refleja la suma real de las cuotas (con el ajuste), no el monto tipeado originalmente.
        $this->assertSame('650.00', $plan->monto_total);
    }

    public function test_el_wizard_no_crea_plan_de_pago_si_no_se_configura_cronograma(): void
    {
        Storage::fake('public');

        $usuario = User::factory()->create();
        $usuario->assignRole(RolEnum::COORDINADOR->value);

        $ciclo = Ciclo::factory()->activo()->create([
            'fecha_inicio' => now()->subDays(20),
            'fecha_fin' => now()->addMonths(5),
        ]);
        $ciclo->periodosMatricula()->create([
            'fecha_inicio' => now()->subDays(10),
            'fecha_fin' => now()->addDays(10),
        ]);
        $grado = Grado::factory()->create();

        $this->actingAs($usuario);

        Volt::test('matricula.wizard')
            ->set('nombres', 'Renzo')
            ->set('apellidos', 'Villar Soto')
            ->set('dni', '55667700')
            ->set('fechaNacimiento', now()->subYears(30)->format('Y-m-d'))
            ->call('avanzar')
            ->assertSet('paso', 3)
            ->set('dniEstudianteArchivo', UploadedFile::fake()->create('dni.pdf', 100, 'application/pdf'))
            ->call('avanzar')
            ->assertSet('paso', 4)
            ->call('avanzar')
            ->assertSet('paso', 5)
            ->set('cicloId', (string) $ciclo->id)
            ->set('gradoId', (string) $grado->id)
            ->call('avanzar')
            ->assertSet('paso', 6)
            ->call('confirmar')
            ->assertHasNoErrors()
            ->assertDispatched('matricula-registrada');

        $estudiante = Estudiante::query()->where('dni', '55667700')->firstOrFail();
        $matricula = $estudiante->matriculas()->firstOrFail();

        $this->assertDatabaseMissing('planes_pago', ['matricula_id' => $matricula->id]);
    }

    /**
     * Avanza el wizard hasta el paso 5 (Matrícula) para un estudiante mayor
     * de edad recién creado, listo para setear cicloId/gradoId.
     */
    private function wizardEnPasoDeMatricula(string $dni): Testable
    {
        return Volt::test('matricula.wizard')
            ->set('nombres', 'Luis')
            ->set('apellidos', 'Fernández Ruiz')
            ->set('dni', $dni)
            ->set('fechaNacimiento', now()->subYears(28)->format('Y-m-d'))
            ->call('avanzar')
            ->assertSet('paso', 3)
            ->set('dniEstudianteArchivo', UploadedFile::fake()->create('dni.pdf', 100, 'application/pdf'))
            ->call('avanzar')
            ->assertSet('paso', 4)
            ->call('avanzar')
            ->assertSet('paso', 5);
    }

    public function test_reintentar_confirmar_tras_error_de_matricula_no_duplica_al_estudiante(): void
    {
        Storage::fake('public');

        $usuario = User::factory()->create();
        $usuario->assignRole(RolEnum::COORDINADOR->value);

        // Ciclo sin periodo de matrícula abierto: matricular() falla.
        $ciclo = Ciclo::factory()->activo()->create();
        $grado = Grado::factory()->create();

        $this->actingAs($usuario);

        $component = $this->wizardEnPasoDeMatricula('55667802')
            ->set('cicloId', (string) $ciclo->id)
            ->set('gradoId', (string) $grado->id)
            ->call('confirmar')
            ->assertHasErrors();

        $this->assertDatabaseCount('estudiantes', 0);

        // El coordinador abre el periodo de matrícula y reintenta en el
        // mismo componente sin volver al paso 1: antes de la corrección de
        // este bug, esto duplicaba al estudiante (mismo DNI) y rompía con
        // un error sin manejar.
        $ciclo->periodosMatricula()->create([
            'fecha_inicio' => now()->subDays(10),
            'fecha_fin' => now()->addDays(10),
        ]);

        $component
            ->call('confirmar')
            ->assertHasNoErrors();

        $this->assertDatabaseCount('estudiantes', 1);
        $estudiante = Estudiante::query()->where('dni', '55667802')->firstOrFail();
        $this->assertDatabaseHas('matriculas', ['estudiante_id' => $estudiante->id, 'ciclo_id' => $ciclo->id]);
    }

    public function test_cancelar_el_wizard_dispara_el_evento_que_cierra_el_modal(): void
    {
        $usuario = User::factory()->create();
        $usuario->assignRole(RolEnum::COORDINADOR->value);

        $this->actingAs($usuario);

        Volt::test('matricula.wizard')
            ->call('cancelar')
            ->assertDispatched('wizard-cerrado');
    }

    public function test_el_listado_abre_y_cierra_el_modal_del_wizard(): void
    {
        $usuario = User::factory()->create();
        $usuario->assignRole(RolEnum::COORDINADOR->value);

        $this->actingAs($usuario);

        Volt::test('matricula.index')
            ->assertSet('mostrarWizard', false)
            ->set('mostrarWizard', true)
            ->assertSet('mostrarWizard', true)
            ->call('cerrarWizard')
            ->assertSet('mostrarWizard', false);
    }

    public function test_guardar_observaciones_desde_la_pagina_completa_de_la_ficha(): void
    {
        $usuario = User::factory()->create();
        $usuario->assignRole(RolEnum::COORDINADOR->value);
        $estudiante = Estudiante::factory()->create(['observaciones' => null]);

        $this->actingAs($usuario);

        Volt::test('matricula.show', ['estudiante' => $estudiante])
            ->set('observacionesTexto', 'Promete traer la partida de nacimiento la próxima semana.')
            ->call('guardarObservaciones')
            ->assertHasNoErrors();

        $this->assertSame(
            'Promete traer la partida de nacimiento la próxima semana.',
            $estudiante->fresh()->observaciones
        );
    }

    public function test_guardar_observaciones_desde_el_modal_de_ficha(): void
    {
        $usuario = User::factory()->create();
        $usuario->assignRole(RolEnum::COORDINADOR->value);
        $estudiante = Estudiante::factory()->create(['observaciones' => null]);

        $this->actingAs($usuario);

        Volt::test('matricula.ficha-modal')
            ->call('abrir', $estudiante->id)
            ->set('observacionesTexto', 'Rindió examen de ubicación, resultado pendiente.')
            ->call('guardarObservaciones')
            ->assertHasNoErrors();

        $this->assertSame(
            'Rindió examen de ubicación, resultado pendiente.',
            $estudiante->fresh()->observaciones
        );
    }

    public function test_editar_fecha_fin_estudio_desde_la_pagina_completa_de_la_ficha(): void
    {
        $usuario = User::factory()->create();
        $usuario->assignRole(RolEnum::COORDINADOR->value);

        $estudiante = Estudiante::factory()->create();
        $matricula = Matricula::factory()->create(['estudiante_id' => $estudiante->id]);
        $nuevaFecha = now()->addMonths(6)->format('Y-m-d');

        $this->actingAs($usuario);

        Volt::test('matricula.show', ['estudiante' => $estudiante])
            ->call('editarFechaFinEstudio', $matricula->id)
            ->set('fechaFinEstudioNueva', $nuevaFecha)
            ->call('guardarFechaFinEstudio')
            ->assertHasNoErrors();

        $this->assertSame($nuevaFecha, $matricula->fresh()->fecha_fin_estudio->format('Y-m-d'));
    }

    public function test_editar_fecha_fin_estudio_desde_el_modal_de_ficha(): void
    {
        $usuario = User::factory()->create();
        $usuario->assignRole(RolEnum::COORDINADOR->value);

        $estudiante = Estudiante::factory()->create();
        $matricula = Matricula::factory()->create(['estudiante_id' => $estudiante->id]);
        $nuevaFecha = now()->addMonths(6)->format('Y-m-d');

        $this->actingAs($usuario);

        Volt::test('matricula.ficha-modal')
            ->call('abrir', $estudiante->id)
            ->call('editarFechaFinEstudio', $matricula->id)
            ->set('fechaFinEstudioNueva', $nuevaFecha)
            ->call('guardarFechaFinEstudio')
            ->assertHasNoErrors();

        $this->assertSame($nuevaFecha, $matricula->fresh()->fecha_fin_estudio->format('Y-m-d'));
    }

    public function test_no_se_puede_editar_fecha_fin_estudio_sin_el_permiso_de_editar_matricula(): void
    {
        $usuario = User::factory()->create();
        $usuario->assignRole(RolEnum::ADMINISTRATIVO->value);

        $estudiante = Estudiante::factory()->create();
        $matricula = Matricula::factory()->create(['estudiante_id' => $estudiante->id]);

        $this->actingAs($usuario);

        Volt::test('matricula.show', ['estudiante' => $estudiante])
            ->call('editarFechaFinEstudio', $matricula->id)
            ->assertForbidden();
    }

    public function test_editar_horario_desde_la_ficha_asigna_el_horario_de_ese_curso(): void
    {
        $usuario = User::factory()->create();
        $usuario->assignRole(RolEnum::COORDINADOR->value);

        $ciclo = Ciclo::factory()->activo()->create();
        $grado = Grado::factory()->create();
        $curso = Curso::factory()->create(['grado_id' => $grado->id]);
        $seccionA = Horario::factory()->create(['curso_id' => $curso->id, 'grado_id' => $grado->id, 'ciclo_id' => $ciclo->id]);
        $seccionB = Horario::factory()->create(['curso_id' => $curso->id, 'grado_id' => $grado->id, 'ciclo_id' => $ciclo->id]);
        $estudiante = Estudiante::factory()->create();
        $matricula = Matricula::factory()->create([
            'estudiante_id' => $estudiante->id,
            'ciclo_id' => $ciclo->id,
            'grado_id' => $grado->id,
        ]);

        $this->actingAs($usuario);

        Volt::test('matricula.show', ['estudiante' => $estudiante])
            ->call('editarHorario', $matricula->id, $curso->id)
            ->set('horarioSeleccionado', (string) $seccionB->id)
            ->call('guardarHorario')
            ->assertHasNoErrors();

        $this->assertSame([$seccionB->id], $matricula->fresh()->horarios->pluck('id')->all());
        $this->assertFalse($matricula->fresh()->horarios->pluck('id')->contains($seccionA->id));
    }

    public function test_no_se_puede_editar_horario_sin_el_permiso_de_editar_matricula(): void
    {
        $usuario = User::factory()->create();
        $usuario->assignRole(RolEnum::ADMINISTRATIVO->value);

        $ciclo = Ciclo::factory()->activo()->create();
        $grado = Grado::factory()->create();
        $horario = Horario::factory()->create(['grado_id' => $grado->id, 'ciclo_id' => $ciclo->id]);
        $estudiante = Estudiante::factory()->create();
        $matricula = Matricula::factory()->create([
            'estudiante_id' => $estudiante->id,
            'ciclo_id' => $ciclo->id,
            'grado_id' => $grado->id,
        ]);

        $this->actingAs($usuario);

        Volt::test('matricula.show', ['estudiante' => $estudiante])
            ->call('editarHorario', $matricula->id, $horario->curso_id)
            ->assertForbidden();
    }

    public function test_editar_monto_del_plan_de_pago_desde_la_pagina_completa_de_la_ficha(): void
    {
        $usuario = User::factory()->create();
        $usuario->assignRole(RolEnum::COORDINADOR->value);

        $estudiante = Estudiante::factory()->create();
        $matricula = Matricula::factory()->create(['estudiante_id' => $estudiante->id]);
        $plan = PlanPago::factory()->create(['matricula_id' => $matricula->id, 'monto_total' => 600]);
        $plan->cuotas()->create(['numero' => 1, 'monto' => 300, 'fecha_vencimiento' => now()->addMonth(), 'estado' => 'pagado']);
        $plan->cuotas()->create(['numero' => 2, 'monto' => 300, 'fecha_vencimiento' => now()->addMonths(2), 'estado' => 'pendiente']);

        $this->actingAs($usuario);

        Volt::test('matricula.show', ['estudiante' => $estudiante])
            ->call('editarMontoPlan', $plan->id)
            ->set('montoTotalNuevo', '900')
            ->call('guardarMontoPlan')
            ->assertHasNoErrors();

        $plan->refresh();
        $this->assertSame('900.00', $plan->monto_total);
        $this->assertSame('300.00', $plan->cuotas()->where('numero', 1)->first()->monto);
        $this->assertSame('600.00', $plan->cuotas()->where('numero', 2)->first()->monto);
    }

    public function test_editar_monto_del_plan_de_pago_desde_el_modal_de_ficha(): void
    {
        $usuario = User::factory()->create();
        $usuario->assignRole(RolEnum::COORDINADOR->value);

        $estudiante = Estudiante::factory()->create();
        $matricula = Matricula::factory()->create(['estudiante_id' => $estudiante->id]);
        $plan = PlanPago::factory()->create(['matricula_id' => $matricula->id, 'monto_total' => 600]);
        $plan->cuotas()->create(['numero' => 1, 'monto' => 600, 'fecha_vencimiento' => now()->addMonth(), 'estado' => 'pendiente']);

        $this->actingAs($usuario);

        Volt::test('matricula.ficha-modal')
            ->call('abrir', $estudiante->id)
            ->call('editarMontoPlan', $plan->id)
            ->set('montoTotalNuevo', '450')
            ->call('guardarMontoPlan')
            ->assertHasNoErrors();

        $this->assertSame('450.00', $plan->fresh()->monto_total);
    }

    public function test_no_se_puede_editar_monto_del_plan_de_pago_sin_el_permiso_de_gestionar_pagos(): void
    {
        $usuario = User::factory()->create();
        $usuario->assignRole(RolEnum::ADMINISTRATIVO->value);

        $estudiante = Estudiante::factory()->create();
        $matricula = Matricula::factory()->create(['estudiante_id' => $estudiante->id]);
        $plan = PlanPago::factory()->create(['matricula_id' => $matricula->id]);

        $this->actingAs($usuario);

        Volt::test('matricula.show', ['estudiante' => $estudiante])
            ->call('editarMontoPlan', $plan->id)
            ->assertForbidden();
    }

    public function test_editar_monto_menor_a_lo_ya_pagado_muestra_error(): void
    {
        $usuario = User::factory()->create();
        $usuario->assignRole(RolEnum::COORDINADOR->value);

        $estudiante = Estudiante::factory()->create();
        $matricula = Matricula::factory()->create(['estudiante_id' => $estudiante->id]);
        $plan = PlanPago::factory()->create(['matricula_id' => $matricula->id, 'monto_total' => 600]);
        $plan->cuotas()->create(['numero' => 1, 'monto' => 300, 'fecha_vencimiento' => now()->addMonth(), 'estado' => 'pagado']);
        $plan->cuotas()->create(['numero' => 2, 'monto' => 300, 'fecha_vencimiento' => now()->addMonths(2), 'estado' => 'pendiente']);

        $this->actingAs($usuario);

        Volt::test('matricula.show', ['estudiante' => $estudiante])
            ->call('editarMontoPlan', $plan->id)
            ->set('montoTotalNuevo', '100')
            ->call('guardarMontoPlan')
            ->assertHasErrors(['montoTotal']);

        $this->assertSame('600.00', $plan->fresh()->monto_total);
    }
}
