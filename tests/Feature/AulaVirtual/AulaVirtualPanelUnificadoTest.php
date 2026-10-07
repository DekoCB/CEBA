<?php

namespace Tests\Feature\AulaVirtual;

use App\Models\User;
use App\Modules\Academico\Models\Horario;
use App\Modules\AulaVirtual\Enums\TipoClaseGrabadaEnum;
use App\Modules\AulaVirtual\Enums\TipoMaterialEnum;
use App\Modules\AulaVirtual\Models\CursoVirtual;
use App\Modules\AulaVirtual\Services\ClaseGrabadaService;
use App\Modules\AulaVirtual\Services\MaterialService;
use App\Modules\AulaVirtual\Services\TareaService;
use App\Modules\Identidad\Database\Seeders\RolesAndPermissionsSeeder;
use App\Modules\Matricula\Models\Estudiante;
use App\Modules\Matricula\Models\Matricula;
use App\Shared\Enums\RolEnum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

/**
 * Pedido del cliente: "Los (Materiales, clases grabadas y tareas), se
 * podría incluir en un solo panel al igual que el classroom." -- las 3
 * pestañas se fusionan en una sola ("Contenido"), agrupada por semana,
 * tanto para el staff que gestiona como para el estudiante que solo mira.
 */
class AulaVirtualPanelUnificadoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function cursoDelDocente(User $docente): CursoVirtual
    {
        $horario = Horario::factory()->create(['docente_id' => $docente->id]);

        return CursoVirtual::factory()->create(['horario_id' => $horario->id]);
    }

    public function test_el_tab_contenido_muestra_materiales_clases_y_tareas_juntos_por_semana(): void
    {
        $docente = User::factory()->create();
        $docente->assignRole(RolEnum::DOCENTE->value);
        $curso = $this->cursoDelDocente($docente);

        $this->app->make(MaterialService::class)
            ->crear($curso, TipoMaterialEnum::ENLACE, 'Guía de lectura', 'https://ejemplo.test/guia', null, '1');
        $this->app->make(ClaseGrabadaService::class)
            ->crear($curso, TipoClaseGrabadaEnum::ENLACE, 'Clase del lunes', 'https://youtube.test/clase', null, '1');
        $this->app->make(TareaService::class)->crear($curso, [
            'titulo' => 'Ensayo semana 1',
            'descripcion' => null,
            'fecha_limite' => now()->addDay(),
            'puntaje_max' => 20,
            'semana' => '1',
        ]);

        $this->actingAs($docente);

        Volt::test('aula-virtual.show', ['curso' => $curso])
            ->assertSet('tab', 'contenido')
            ->assertSee('Guía de lectura')
            ->assertSee('Clase del lunes')
            ->assertSee('Ensayo semana 1');
    }

    public function test_estudiante_ve_el_mismo_panel_unificado_sin_controles_de_gestion(): void
    {
        $usuario = User::factory()->create();
        $usuario->assignRole(RolEnum::ESTUDIANTE->value);
        $estudiante = Estudiante::factory()->create(['user_id' => $usuario->id]);

        $docente = User::factory()->create();
        $docente->assignRole(RolEnum::DOCENTE->value);
        $curso = $this->cursoDelDocente($docente);

        Matricula::factory()->create([
            'estudiante_id' => $estudiante->id,
            'ciclo_id' => $curso->horario->ciclo_id,
            'grado_id' => $curso->horario->grado_id,
        ]);

        $this->app->make(MaterialService::class)
            ->crear($curso, TipoMaterialEnum::ENLACE, 'Guía de lectura', 'https://ejemplo.test/guia', null, null);

        $this->actingAs($usuario);

        Volt::test('aula-virtual.show', ['curso' => $curso])
            ->assertSee('Guía de lectura')
            ->assertDontSee('+ Nuevo material')
            ->assertDontSee('+ Nueva clase grabada')
            ->assertDontSee('+ Nueva tarea')
            ->assertDontSee('Eliminar');
    }

    public function test_eliminar_material_desde_el_panel_unificado_sigue_funcionando(): void
    {
        $docente = User::factory()->create();
        $docente->assignRole(RolEnum::DOCENTE->value);
        $curso = $this->cursoDelDocente($docente);

        $material = $this->app->make(MaterialService::class)
            ->crear($curso, TipoMaterialEnum::ENLACE, 'Guía de lectura', 'https://ejemplo.test/guia', null, null);

        $this->actingAs($docente);

        Volt::test('aula-virtual.show', ['curso' => $curso])
            ->call('eliminarMaterial', $material->id)
            ->assertHasNoErrors();

        $this->assertDatabaseMissing('materiales', ['id' => $material->id]);
    }

    public function test_publicaciones_y_foros_siguen_en_pestanas_separadas(): void
    {
        $docente = User::factory()->create();
        $docente->assignRole(RolEnum::DOCENTE->value);
        $curso = $this->cursoDelDocente($docente);

        $this->actingAs($docente);

        $html = Volt::test('aula-virtual.show', ['curso' => $curso])->html();

        $this->assertStringContainsString('Publicaciones', $html);
        $this->assertStringContainsString('Foros', $html);
        $this->assertStringNotContainsString('>Materiales<', $html);
        $this->assertStringNotContainsString('>Clases grabadas<', $html);
        $this->assertStringNotContainsString('>Tareas<', $html);
    }
}
