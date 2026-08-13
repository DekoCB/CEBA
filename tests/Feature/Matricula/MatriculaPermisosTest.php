<?php

namespace Tests\Feature\Matricula;

use App\Models\User;
use App\Modules\Academico\Enums\TipoPublicoEnum;
use App\Modules\Academico\Models\Ciclo;
use App\Modules\Academico\Models\Grado;
use App\Modules\Academico\Models\Horario;
use App\Modules\Identidad\Database\Seeders\RolesAndPermissionsSeeder;
use App\Modules\Matricula\Models\Estudiante;
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
        $grado = Grado::factory()->create(['tipo_publico' => TipoPublicoEnum::MAYOR]);

        $this->actingAs($usuario);

        Volt::test('matricula.wizard')
            ->set('nombres', 'Luis')
            ->set('apellidos', 'Fernández Ruiz')
            ->set('dni', '55667788')
            ->set('fechaNacimiento', now()->subYears(28)->format('Y-m-d'))
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
    }

    /**
     * Avanza el wizard hasta el paso 5 (Matrícula) para un estudiante mayor
     * de edad recién creado, listo para setear cicloId/gradoId/horarioId.
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

    public function test_el_wizard_asigna_automaticamente_el_unico_horario_del_grado(): void
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
        $grado = Grado::factory()->create(['tipo_publico' => TipoPublicoEnum::MAYOR]);
        $horario = Horario::factory()->create(['grado_id' => $grado->id, 'ciclo_id' => $ciclo->id]);

        $this->actingAs($usuario);

        $this->wizardEnPasoDeMatricula('55667799')
            ->set('cicloId', (string) $ciclo->id)
            ->set('gradoId', (string) $grado->id)
            ->assertDontSee('Sección')
            ->call('confirmar')
            ->assertHasNoErrors();

        $estudiante = Estudiante::query()->where('dni', '55667799')->firstOrFail();
        $this->assertDatabaseHas('matriculas', ['estudiante_id' => $estudiante->id, 'horario_id' => $horario->id]);
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
        $grado = Grado::factory()->create(['tipo_publico' => TipoPublicoEnum::MAYOR]);
        Horario::factory()->create(['grado_id' => $grado->id, 'ciclo_id' => $ciclo->id, 'seccion' => 'A']);
        $horarioB = Horario::factory()->create(['grado_id' => $grado->id, 'ciclo_id' => $ciclo->id, 'seccion' => 'B']);

        $this->actingAs($usuario);

        $this->wizardEnPasoDeMatricula('55667800')
            ->set('cicloId', (string) $ciclo->id)
            ->set('gradoId', (string) $grado->id)
            ->assertSee('Sección')
            ->set('horarioId', (string) $horarioB->id)
            ->call('confirmar')
            ->assertHasNoErrors();

        $estudiante = Estudiante::query()->where('dni', '55667800')->firstOrFail();
        $this->assertDatabaseHas('matriculas', ['estudiante_id' => $estudiante->id, 'horario_id' => $horarioB->id]);
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
        $grado = Grado::factory()->create(['tipo_publico' => TipoPublicoEnum::MAYOR]);
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
        $grado = Grado::factory()->create(['tipo_publico' => TipoPublicoEnum::MAYOR]);
        Horario::factory()->create(['grado_id' => $grado->id, 'ciclo_id' => $ciclo->id, 'seccion' => 'A']);
        $horarioB = Horario::factory()->create(['grado_id' => $grado->id, 'ciclo_id' => $ciclo->id, 'seccion' => 'B']);

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
            ->set('horarioId', (string) $horarioB->id)
            ->call('confirmar')
            ->assertHasNoErrors();

        $this->assertDatabaseCount('estudiantes', 1);
        $estudiante = Estudiante::query()->where('dni', '55667802')->firstOrFail();
        $this->assertDatabaseHas('matriculas', ['estudiante_id' => $estudiante->id, 'horario_id' => $horarioB->id]);
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
}
