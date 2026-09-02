<?php

namespace Tests\Feature\Reportes;

use App\Models\User;
use App\Modules\Academico\Models\Ciclo;
use App\Modules\Identidad\Database\Seeders\RolesAndPermissionsSeeder;
use App\Modules\Matricula\Models\Estudiante;
use App\Modules\Matricula\Models\Matricula;
use App\Shared\Enums\RolEnum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class HistorialEstudiantePermisosTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_coordinador_puede_ver_el_historial_de_estudiante(): void
    {
        $coordinador = User::factory()->create();
        $coordinador->assignRole(RolEnum::COORDINADOR->value);

        $this->actingAs($coordinador)
            ->get(route('historial-estudiante.index'))
            ->assertOk();
    }

    public function test_direccion_puede_ver_el_historial_de_estudiante(): void
    {
        $direccion = User::factory()->create();
        $direccion->assignRole(RolEnum::DIRECCION->value);

        $this->actingAs($direccion)
            ->get(route('historial-estudiante.index'))
            ->assertOk();
    }

    public function test_docente_no_puede_ver_el_historial_de_estudiante(): void
    {
        $docente = User::factory()->create();
        $docente->assignRole(RolEnum::DOCENTE->value);

        $this->actingAs($docente)
            ->get(route('historial-estudiante.index'))
            ->assertForbidden();
    }

    public function test_tesoreria_no_puede_ver_el_historial_de_estudiante(): void
    {
        $tesoreria = User::factory()->create();
        $tesoreria->assignRole(RolEnum::TESORERIA->value);

        $this->actingAs($tesoreria)
            ->get(route('historial-estudiante.index'))
            ->assertForbidden();
    }

    public function test_administrativo_no_puede_ver_el_historial_de_estudiante(): void
    {
        $administrativo = User::factory()->create();
        $administrativo->assignRole(RolEnum::ADMINISTRATIVO->value);

        $this->actingAs($administrativo)
            ->get(route('historial-estudiante.index'))
            ->assertForbidden();
    }

    public function test_estudiante_no_puede_ver_el_historial_de_estudiante(): void
    {
        $usuario = User::factory()->create();
        $usuario->assignRole(RolEnum::ESTUDIANTE->value);

        $this->actingAs($usuario)
            ->get(route('historial-estudiante.index'))
            ->assertForbidden();
    }

    public function test_buscar_por_dni_muestra_al_estudiante_en_la_lista_de_resultados(): void
    {
        $coordinador = User::factory()->create();
        $coordinador->assignRole(RolEnum::COORDINADOR->value);
        Estudiante::factory()->create(['dni' => '55667788', 'nombres' => 'Carla', 'apellidos' => 'Robles Díaz']);

        $this->actingAs($coordinador);

        Volt::test('historial-estudiante.index')
            ->set('terminoBusqueda', '55667788')
            ->assertSee('Carla Robles Díaz');
    }

    public function test_buscar_por_nombre_muestra_al_estudiante_en_la_lista_de_resultados(): void
    {
        $coordinador = User::factory()->create();
        $coordinador->assignRole(RolEnum::COORDINADOR->value);
        Estudiante::factory()->create(['dni' => '55667788', 'nombres' => 'Carla', 'apellidos' => 'Robles Díaz']);

        $this->actingAs($coordinador);

        Volt::test('historial-estudiante.index')
            ->set('terminoBusqueda', 'Robles')
            ->assertSee('Carla Robles Díaz')
            ->assertSee('55667788');
    }

    public function test_buscar_un_termino_sin_coincidencias_muestra_un_mensaje_claro(): void
    {
        $coordinador = User::factory()->create();
        $coordinador->assignRole(RolEnum::COORDINADOR->value);

        $this->actingAs($coordinador);

        Volt::test('historial-estudiante.index')
            ->set('terminoBusqueda', 'Nadie Existe')
            ->assertSee('No se encontraron estudiantes');
    }

    public function test_elegir_un_resultado_muestra_el_historial_del_estudiante(): void
    {
        $coordinador = User::factory()->create();
        $coordinador->assignRole(RolEnum::COORDINADOR->value);
        $estudiante = Estudiante::factory()->create(['dni' => '55667788', 'nombres' => 'Carla', 'apellidos' => 'Robles Díaz']);

        $this->actingAs($coordinador);

        Volt::test('historial-estudiante.index')
            ->set('terminoBusqueda', 'Robles')
            ->call('seleccionarEstudiante', $estudiante->id, $estudiante->nombreCompleto())
            ->assertHasNoErrors()
            ->assertSee('DNI 55667788');
    }

    public function test_el_historial_muestra_la_modalidad_siage_de_cada_matricula(): void
    {
        $coordinador = User::factory()->create();
        $coordinador->assignRole(RolEnum::COORDINADOR->value);
        $estudiante = Estudiante::factory()->create(['dni' => '55667799', 'nombres' => 'Marco', 'apellidos' => 'Villar Soto']);
        $cicloAnual = Ciclo::factory()->anual()->create();
        Matricula::factory()->create(['estudiante_id' => $estudiante->id, 'ciclo_id' => $cicloAnual->id]);

        $this->actingAs($coordinador);

        Volt::test('historial-estudiante.index')
            ->set('terminoBusqueda', 'Villar Soto')
            ->call('seleccionarEstudiante', $estudiante->id, $estudiante->nombreCompleto())
            ->assertHasNoErrors()
            ->assertSee('SIAGE anual');
    }

    public function test_el_enlace_de_historial_aparece_en_el_menu_para_coordinador(): void
    {
        $coordinador = User::factory()->create();
        $coordinador->assignRole(RolEnum::COORDINADOR->value);

        $this->actingAs($coordinador)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Historial de estudiante');
    }

    public function test_el_enlace_de_historial_no_aparece_en_el_menu_para_docente(): void
    {
        $docente = User::factory()->create();
        $docente->assignRole(RolEnum::DOCENTE->value);

        $this->actingAs($docente)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Historial de estudiante');
    }
}
