<?php

namespace Tests\Feature\Matricula;

use App\Models\User;
use App\Modules\Academico\Enums\TipoPublicoEnum;
use App\Modules\Academico\Models\Ciclo;
use App\Modules\Academico\Models\Grado;
use App\Modules\Identidad\Database\Seeders\RolesAndPermissionsSeeder;
use App\Modules\Matricula\Models\Estudiante;
use App\Shared\Enums\RolEnum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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

    public function test_rol_matricula_puede_ver_el_listado(): void
    {
        $usuario = User::factory()->create();
        $usuario->assignRole(RolEnum::MATRICULA->value);

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
        $usuario->assignRole(RolEnum::MATRICULA->value);

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
            ->assertHasNoErrors();

        $this->assertDatabaseHas('estudiantes', ['dni' => '55667788']);
        $estudiante = Estudiante::query()->where('dni', '55667788')->firstOrFail();
        $this->assertDatabaseHas('matriculas', ['estudiante_id' => $estudiante->id, 'ciclo_id' => $ciclo->id]);
    }
}
