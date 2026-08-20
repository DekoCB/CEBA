<?php

namespace Tests\Feature\Matricula;

use App\Models\User;
use App\Modules\Academico\Models\Ciclo;
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
     * de edad recién creado, listo para setear cicloId/gradoId/seccionElegida.
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

    public function test_el_wizard_no_exige_elegir_seccion_para_un_grado_con_un_solo_horario(): void
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
        Horario::factory()->create(['grado_id' => $grado->id, 'ciclo_id' => $ciclo->id]);

        $this->actingAs($usuario);

        $this->wizardEnPasoDeMatricula('55667799')
            ->set('cicloId', (string) $ciclo->id)
            ->set('gradoId', (string) $grado->id)
            ->assertDontSee('Sección')
            ->call('confirmar')
            ->assertHasNoErrors();

        $estudiante = Estudiante::query()->where('dni', '55667799')->firstOrFail();
        $this->assertDatabaseHas('matriculas', ['estudiante_id' => $estudiante->id, 'seccion' => null]);
    }

    public function test_el_wizard_no_pide_seccion_para_un_grado_con_varios_cursos_sin_dividir(): void
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
        Horario::factory()->create(['grado_id' => $grado->id, 'ciclo_id' => $ciclo->id]);
        Horario::factory()->create(['grado_id' => $grado->id, 'ciclo_id' => $ciclo->id]);
        Horario::factory()->create(['grado_id' => $grado->id, 'ciclo_id' => $ciclo->id]);

        $this->actingAs($usuario);

        $this->wizardEnPasoDeMatricula('55667788')
            ->set('cicloId', (string) $ciclo->id)
            ->set('gradoId', (string) $grado->id)
            ->assertDontSee('Sección')
            ->call('confirmar')
            ->assertHasNoErrors();

        $estudiante = Estudiante::query()->where('dni', '55667788')->firstOrFail();
        $this->assertDatabaseHas('matriculas', ['estudiante_id' => $estudiante->id, 'seccion' => null]);
    }

    public function test_el_wizard_exige_elegir_seccion_cuando_el_grado_tiene_varias(): void
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
        Horario::factory()->create(['grado_id' => $grado->id, 'ciclo_id' => $ciclo->id, 'seccion' => 'A']);
        Horario::factory()->create(['grado_id' => $grado->id, 'ciclo_id' => $ciclo->id, 'seccion' => 'B']);

        $this->actingAs($usuario);

        $this->wizardEnPasoDeMatricula('55667800')
            ->set('cicloId', (string) $ciclo->id)
            ->set('gradoId', (string) $grado->id)
            ->assertSee('Sección')
            ->set('seccionElegida', 'B')
            ->call('confirmar')
            ->assertHasNoErrors();

        $estudiante = Estudiante::query()->where('dni', '55667800')->firstOrFail();
        $this->assertDatabaseHas('matriculas', ['estudiante_id' => $estudiante->id, 'seccion' => 'B']);
    }

    public function test_el_wizard_muestra_error_si_no_se_elige_seccion_habiendo_varias(): void
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
        Horario::factory()->create(['grado_id' => $grado->id, 'ciclo_id' => $ciclo->id, 'seccion' => 'A']);
        Horario::factory()->create(['grado_id' => $grado->id, 'ciclo_id' => $ciclo->id, 'seccion' => 'B']);

        $this->actingAs($usuario);

        $this->wizardEnPasoDeMatricula('55667801')
            ->set('cicloId', (string) $ciclo->id)
            ->set('gradoId', (string) $grado->id)
            ->call('confirmar')
            ->assertHasErrors();

        $this->assertDatabaseCount('matriculas', 0);

        // El estudiante tampoco debe quedar creado a medias: confirmar()
        // registra estudiante y matricula en una sola transacción, así que
        // si matricular() falla (aquí, por falta de sección), todo se
        // revierte -- incluido el estudiante recién creado.
        $this->assertDatabaseCount('estudiantes', 0);
    }

    public function test_reintentar_confirmar_tras_error_de_seccion_no_duplica_al_estudiante(): void
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
        Horario::factory()->create(['grado_id' => $grado->id, 'ciclo_id' => $ciclo->id, 'seccion' => 'A']);
        Horario::factory()->create(['grado_id' => $grado->id, 'ciclo_id' => $ciclo->id, 'seccion' => 'B']);

        $this->actingAs($usuario);

        $component = $this->wizardEnPasoDeMatricula('55667802')
            ->set('cicloId', (string) $ciclo->id)
            ->set('gradoId', (string) $grado->id)
            ->call('confirmar')
            ->assertHasErrors();

        $this->assertDatabaseCount('estudiantes', 0);

        // El coordinador corrige el dato sin volver al paso 1 y reintenta
        // en el mismo componente: antes de la corrección, esto duplicaba
        // al estudiante (mismo DNI) y rompía con un error sin manejar.
        $component
            ->set('seccionElegida', 'B')
            ->call('confirmar')
            ->assertHasNoErrors();

        $this->assertDatabaseCount('estudiantes', 1);
        $estudiante = Estudiante::query()->where('dni', '55667802')->firstOrFail();
        $this->assertDatabaseHas('matriculas', ['estudiante_id' => $estudiante->id, 'seccion' => 'B']);
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

    public function test_editar_seccion_desde_la_pagina_completa_de_la_ficha(): void
    {
        $usuario = User::factory()->create();
        $usuario->assignRole(RolEnum::COORDINADOR->value);

        $ciclo = Ciclo::factory()->activo()->create();
        $grado = Grado::factory()->create();
        Horario::factory()->create(['grado_id' => $grado->id, 'ciclo_id' => $ciclo->id, 'seccion' => 'A']);
        Horario::factory()->create(['grado_id' => $grado->id, 'ciclo_id' => $ciclo->id, 'seccion' => 'B']);
        $estudiante = Estudiante::factory()->create();
        $matricula = Matricula::factory()->create([
            'estudiante_id' => $estudiante->id,
            'ciclo_id' => $ciclo->id,
            'grado_id' => $grado->id,
            'seccion' => 'A',
        ]);

        $this->actingAs($usuario);

        Volt::test('matricula.show', ['estudiante' => $estudiante])
            ->call('editarSeccion', $matricula->id)
            ->set('seccionSeleccionada', 'B')
            ->call('guardarSeccion')
            ->assertHasNoErrors();

        $this->assertSame('B', $matricula->fresh()->seccion);
    }

    public function test_editar_seccion_desde_el_modal_de_ficha(): void
    {
        $usuario = User::factory()->create();
        $usuario->assignRole(RolEnum::COORDINADOR->value);

        $ciclo = Ciclo::factory()->activo()->create();
        $grado = Grado::factory()->create();
        Horario::factory()->create(['grado_id' => $grado->id, 'ciclo_id' => $ciclo->id, 'seccion' => 'A']);
        Horario::factory()->create(['grado_id' => $grado->id, 'ciclo_id' => $ciclo->id, 'seccion' => 'B']);
        $estudiante = Estudiante::factory()->create();
        $matricula = Matricula::factory()->create([
            'estudiante_id' => $estudiante->id,
            'ciclo_id' => $ciclo->id,
            'grado_id' => $grado->id,
            'seccion' => 'A',
        ]);

        $this->actingAs($usuario);

        Volt::test('matricula.ficha-modal')
            ->call('abrir', $estudiante->id)
            ->call('editarSeccion', $matricula->id)
            ->set('seccionSeleccionada', 'B')
            ->call('guardarSeccion')
            ->assertHasNoErrors();

        $this->assertSame('B', $matricula->fresh()->seccion);
    }

    public function test_no_se_puede_editar_seccion_sin_el_permiso_de_editar_matricula(): void
    {
        $usuario = User::factory()->create();
        $usuario->assignRole(RolEnum::ADMINISTRATIVO->value);

        $ciclo = Ciclo::factory()->activo()->create();
        $grado = Grado::factory()->create();
        Horario::factory()->create(['grado_id' => $grado->id, 'ciclo_id' => $ciclo->id, 'seccion' => 'A']);
        Horario::factory()->create(['grado_id' => $grado->id, 'ciclo_id' => $ciclo->id, 'seccion' => 'B']);
        $estudiante = Estudiante::factory()->create();
        $matricula = Matricula::factory()->create([
            'estudiante_id' => $estudiante->id,
            'ciclo_id' => $ciclo->id,
            'grado_id' => $grado->id,
            'seccion' => 'A',
        ]);

        $this->actingAs($usuario);

        Volt::test('matricula.show', ['estudiante' => $estudiante])
            ->call('editarSeccion', $matricula->id)
            ->assertForbidden();
    }

    /**
     * "Editar horario" en la ficha es independiente de la sección: permite
     * apuntar la matrícula a cualquier horario del grado/ciclo, aunque su
     * sección sea distinta a la del estudiante, sin tocar matricula.seccion.
     */
    public function test_editar_horario_desde_la_ficha_no_exige_que_coincida_con_la_seccion(): void
    {
        $usuario = User::factory()->create();
        $usuario->assignRole(RolEnum::COORDINADOR->value);

        $ciclo = Ciclo::factory()->activo()->create();
        $grado = Grado::factory()->create();
        Horario::factory()->create(['grado_id' => $grado->id, 'ciclo_id' => $ciclo->id, 'seccion' => 'A']);
        $horarioB = Horario::factory()->create(['grado_id' => $grado->id, 'ciclo_id' => $ciclo->id, 'seccion' => 'B']);
        $estudiante = Estudiante::factory()->create();
        $matricula = Matricula::factory()->create([
            'estudiante_id' => $estudiante->id,
            'ciclo_id' => $ciclo->id,
            'grado_id' => $grado->id,
            'seccion' => 'A',
        ]);

        $this->actingAs($usuario);

        Volt::test('matricula.show', ['estudiante' => $estudiante])
            ->call('editarHorario', $matricula->id)
            ->set('horarioSeleccionado', (string) $horarioB->id)
            ->call('guardarHorario')
            ->assertHasNoErrors();

        $this->assertSame($horarioB->id, $matricula->fresh()->horario_id);
        $this->assertSame('A', $matricula->fresh()->seccion);
    }

    public function test_no_se_puede_editar_horario_sin_el_permiso_de_editar_matricula(): void
    {
        $usuario = User::factory()->create();
        $usuario->assignRole(RolEnum::ADMINISTRATIVO->value);

        $ciclo = Ciclo::factory()->activo()->create();
        $grado = Grado::factory()->create();
        Horario::factory()->create(['grado_id' => $grado->id, 'ciclo_id' => $ciclo->id, 'seccion' => 'A']);
        Horario::factory()->create(['grado_id' => $grado->id, 'ciclo_id' => $ciclo->id, 'seccion' => 'B']);
        $estudiante = Estudiante::factory()->create();
        $matricula = Matricula::factory()->create([
            'estudiante_id' => $estudiante->id,
            'ciclo_id' => $ciclo->id,
            'grado_id' => $grado->id,
            'seccion' => 'A',
        ]);

        $this->actingAs($usuario);

        Volt::test('matricula.show', ['estudiante' => $estudiante])
            ->call('editarHorario', $matricula->id)
            ->assertForbidden();
    }
}
