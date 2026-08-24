<?php

namespace Tests\Feature\Reportes;

use App\Models\User;
use App\Modules\Identidad\Database\Seeders\RolesAndPermissionsSeeder;
use App\Modules\Matricula\Models\Estudiante;
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
            ->get(route('reportes.historial'))
            ->assertOk();
    }

    public function test_direccion_puede_ver_el_historial_de_estudiante(): void
    {
        $direccion = User::factory()->create();
        $direccion->assignRole(RolEnum::DIRECCION->value);

        $this->actingAs($direccion)
            ->get(route('reportes.historial'))
            ->assertOk();
    }

    public function test_docente_no_puede_ver_el_historial_de_estudiante(): void
    {
        $docente = User::factory()->create();
        $docente->assignRole(RolEnum::DOCENTE->value);

        $this->actingAs($docente)
            ->get(route('reportes.historial'))
            ->assertForbidden();
    }

    public function test_tesoreria_no_puede_ver_el_historial_de_estudiante(): void
    {
        $tesoreria = User::factory()->create();
        $tesoreria->assignRole(RolEnum::TESORERIA->value);

        $this->actingAs($tesoreria)
            ->get(route('reportes.historial'))
            ->assertForbidden();
    }

    public function test_administrativo_no_puede_ver_el_historial_de_estudiante(): void
    {
        $administrativo = User::factory()->create();
        $administrativo->assignRole(RolEnum::ADMINISTRATIVO->value);

        $this->actingAs($administrativo)
            ->get(route('reportes.historial'))
            ->assertForbidden();
    }

    public function test_estudiante_no_puede_ver_el_historial_de_estudiante(): void
    {
        $usuario = User::factory()->create();
        $usuario->assignRole(RolEnum::ESTUDIANTE->value);

        $this->actingAs($usuario)
            ->get(route('reportes.historial'))
            ->assertForbidden();
    }

    public function test_buscar_con_un_dni_inexistente_muestra_un_mensaje_claro(): void
    {
        $coordinador = User::factory()->create();
        $coordinador->assignRole(RolEnum::COORDINADOR->value);

        $this->actingAs($coordinador);

        Volt::test('reportes.historial-estudiante')
            ->set('dni', '99999999')
            ->call('buscar')
            ->assertHasNoErrors()
            ->assertSee('No se encontró ningún estudiante');
    }

    public function test_buscar_con_un_dni_existente_muestra_su_nombre(): void
    {
        $coordinador = User::factory()->create();
        $coordinador->assignRole(RolEnum::COORDINADOR->value);
        Estudiante::factory()->create(['dni' => '55667788', 'nombres' => 'Carla', 'apellidos' => 'Robles Díaz']);

        $this->actingAs($coordinador);

        Volt::test('reportes.historial-estudiante')
            ->set('dni', '55667788')
            ->call('buscar')
            ->assertHasNoErrors()
            ->assertSee('Carla Robles Díaz');
    }

    public function test_el_enlace_de_historial_aparece_en_reportes_para_coordinador(): void
    {
        $coordinador = User::factory()->create();
        $coordinador->assignRole(RolEnum::COORDINADOR->value);

        $this->actingAs($coordinador)
            ->get(route('reportes.index'))
            ->assertOk()
            ->assertSee('Historial de estudiante');
    }

    public function test_el_enlace_de_historial_no_aparece_en_reportes_para_docente(): void
    {
        $docente = User::factory()->create();
        $docente->assignRole(RolEnum::DOCENTE->value);

        $this->actingAs($docente)
            ->get(route('reportes.index'))
            ->assertOk()
            ->assertDontSee('Historial de estudiante');
    }
}
