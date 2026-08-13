<?php

namespace Tests\Feature\Evaluaciones;

use App\Models\User;
use App\Modules\Academico\Models\Horario;
use App\Modules\Evaluaciones\Services\EvaluacionService;
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

    public function test_el_docente_crea_una_evaluacion_con_enlace_externo(): void
    {
        $docente = User::factory()->create();
        $docente->assignRole(RolEnum::DOCENTE->value);
        $horario = Horario::factory()->create(['docente_id' => $docente->id]);

        $this->actingAs($docente);

        Volt::test('evaluaciones.show', ['horario' => $horario])
            ->set('nuevoNombre', 'Evaluación mensual')
            ->set('nuevaFecha', '2026-07-15')
            ->set('nuevoEnlace', 'https://forms.test/examen')
            ->call('crear')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('evaluaciones', [
            'nombre' => 'Evaluación mensual',
            'enlace_externo' => 'https://forms.test/examen',
        ]);
    }

    public function test_no_permite_un_enlace_externo_invalido(): void
    {
        $docente = User::factory()->create();
        $docente->assignRole(RolEnum::DOCENTE->value);
        $horario = Horario::factory()->create(['docente_id' => $docente->id]);

        $this->actingAs($docente);

        Volt::test('evaluaciones.show', ['horario' => $horario])
            ->set('nuevoNombre', 'Evaluación mensual')
            ->set('nuevaFecha', '2026-07-15')
            ->set('nuevoEnlace', 'no-es-una-url')
            ->call('crear')
            ->assertHasErrors(['nuevoEnlace']);
    }

    public function test_el_docente_puede_editar_el_enlace_de_una_evaluacion_existente(): void
    {
        $docente = User::factory()->create();
        $docente->assignRole(RolEnum::DOCENTE->value);
        $horario = Horario::factory()->create(['docente_id' => $docente->id]);

        $this->actingAs($docente);

        $component = Volt::test('evaluaciones.show', ['horario' => $horario])
            ->set('nuevoNombre', 'Evaluación mensual')
            ->set('nuevaFecha', '2026-07-15')
            ->call('crear');

        $evaluacionId = $component->get('evaluacionId');

        $component
            ->set('enlaceEditar', 'https://forms.test/actualizado')
            ->call('actualizarEnlace')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('evaluaciones', [
            'id' => $evaluacionId,
            'enlace_externo' => 'https://forms.test/actualizado',
        ]);
    }

    public function test_un_estudiante_matriculado_no_ve_el_enlace_de_una_evaluacion_en_borrador(): void
    {
        $docente = User::factory()->create();
        $docente->assignRole(RolEnum::DOCENTE->value);
        $horario = Horario::factory()->create(['docente_id' => $docente->id]);

        $usuario = User::factory()->create();
        $usuario->assignRole(RolEnum::ESTUDIANTE->value);
        $estudiante = Estudiante::factory()->create(['user_id' => $usuario->id]);
        Matricula::factory()->create([
            'estudiante_id' => $estudiante->id,
            'grado_id' => $horario->grado_id,
            'ciclo_id' => $horario->ciclo_id,
        ]);

        $this->actingAs($docente);
        Volt::test('evaluaciones.show', ['horario' => $horario])
            ->set('nuevoNombre', 'Evaluación mensual')
            ->set('nuevaFecha', '2026-07-15')
            ->set('nuevoEnlace', 'https://forms.test/examen')
            ->call('crear');

        $this->actingAs($usuario);

        Volt::test('evaluaciones.show', ['horario' => $horario])
            ->assertDontSee('Evaluaciones para rendir')
            ->assertDontSee('https://forms.test/examen');
    }

    public function test_un_estudiante_matriculado_ve_el_enlace_recien_cuando_se_publica(): void
    {
        $docente = User::factory()->create();
        $docente->assignRole(RolEnum::DOCENTE->value);
        $horario = Horario::factory()->create(['docente_id' => $docente->id]);

        $usuario = User::factory()->create();
        $usuario->assignRole(RolEnum::ESTUDIANTE->value);
        $estudiante = Estudiante::factory()->create(['user_id' => $usuario->id]);
        Matricula::factory()->create([
            'estudiante_id' => $estudiante->id,
            'grado_id' => $horario->grado_id,
            'ciclo_id' => $horario->ciclo_id,
        ]);

        $evaluacion = $this->app->make(EvaluacionService::class)->crear($horario, 'Evaluación mensual', now()->format('Y-m-d'), 'https://forms.test/examen');
        $this->app->make(EvaluacionService::class)->publicar($evaluacion);

        $this->actingAs($usuario);

        Volt::test('evaluaciones.show', ['horario' => $horario])
            ->assertSee('Evaluaciones para rendir')
            ->assertSee('https://forms.test/examen');
    }
}
