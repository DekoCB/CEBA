<?php

namespace Tests\Feature\Evaluaciones;

use App\Models\User;
use App\Modules\Academico\Models\Horario;
use App\Modules\Identidad\Database\Seeders\RolesAndPermissionsSeeder;
use App\Modules\Matricula\Models\Estudiante;
use App\Modules\Matricula\Models\Matricula;
use App\Shared\Enums\RolEnum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class EvaluacionesFlujoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_el_docente_crea_una_evaluacion_y_registra_notas(): void
    {
        $docente = User::factory()->create();
        $docente->assignRole(RolEnum::DOCENTE->value);
        $horario = Horario::factory()->create(['docente_id' => $docente->id]);

        $estudiante = Estudiante::factory()->create();
        Matricula::factory()->create([
            'estudiante_id' => $estudiante->id,
            'grado_id' => $horario->grado_id,
            'ciclo_id' => $horario->ciclo_id,
        ]);

        $this->actingAs($docente);

        $component = Volt::test('evaluaciones.show', ['horario' => $horario])
            ->set('nuevoNombre', 'Evaluación mensual — julio')
            ->set('nuevaFecha', '2026-07-15')
            ->call('crear')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('evaluaciones', ['nombre' => 'Evaluación mensual — julio']);

        $evaluacionId = $component->get('evaluacionId');

        $component
            ->set("notas.{$estudiante->id}", '17.5')
            ->set("observaciones.{$estudiante->id}", 'Buen desempeño')
            ->call('guardarNotas')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('calificaciones', [
            'evaluacion_id' => $evaluacionId,
            'estudiante_id' => $estudiante->id,
            'nota_numerica' => 17.5,
            'observaciones' => 'Buen desempeño',
        ]);
    }

    public function test_no_permite_registrar_una_nota_fuera_del_rango_0_20(): void
    {
        $docente = User::factory()->create();
        $docente->assignRole(RolEnum::DOCENTE->value);
        $horario = Horario::factory()->create(['docente_id' => $docente->id]);

        $estudiante = Estudiante::factory()->create();
        Matricula::factory()->create([
            'estudiante_id' => $estudiante->id,
            'grado_id' => $horario->grado_id,
            'ciclo_id' => $horario->ciclo_id,
        ]);

        $this->actingAs($docente);

        $component = Volt::test('evaluaciones.show', ['horario' => $horario])
            ->set('nuevoNombre', 'Evaluación')
            ->set('nuevaFecha', '2026-07-15')
            ->call('crear');

        $component
            ->set("notas.{$estudiante->id}", '25')
            ->call('guardarNotas')
            ->assertHasErrors(["notas.{$estudiante->id}"]);
    }
}
