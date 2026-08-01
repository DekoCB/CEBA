<?php

namespace Tests\Feature\AulaVirtual;

use App\Models\User;
use App\Modules\Academico\Models\Horario;
use App\Modules\AulaVirtual\Models\CursoVirtual;
use App\Modules\AulaVirtual\Models\Foro;
use App\Modules\Identidad\Database\Seeders\RolesAndPermissionsSeeder;
use App\Modules\Matricula\Models\Estudiante;
use App\Modules\Matricula\Models\Matricula;
use App\Shared\Enums\RolEnum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class AulaVirtualPermisosTest extends TestCase
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

    public function test_el_docente_dueno_del_curso_puede_verlo(): void
    {
        $docente = User::factory()->create();
        $docente->assignRole(RolEnum::DOCENTE->value);
        $curso = $this->cursoDelDocente($docente);

        $this->actingAs($docente)
            ->get(route('aula-virtual.show', $curso))
            ->assertOk();
    }

    public function test_un_docente_no_puede_ver_el_curso_de_otro_docente(): void
    {
        $docente = User::factory()->create();
        $docente->assignRole(RolEnum::DOCENTE->value);

        $otroDocente = User::factory()->create();
        $otroDocente->assignRole(RolEnum::DOCENTE->value);
        $curso = $this->cursoDelDocente($otroDocente);

        $this->actingAs($docente)
            ->get(route('aula-virtual.show', $curso))
            ->assertForbidden();
    }

    public function test_un_estudiante_matriculado_en_el_grado_y_ciclo_del_curso_puede_verlo(): void
    {
        $usuario = User::factory()->create();
        $usuario->assignRole(RolEnum::ESTUDIANTE->value);
        $estudiante = Estudiante::factory()->create(['user_id' => $usuario->id]);

        $docente = User::factory()->create();
        $docente->assignRole(RolEnum::DOCENTE->value);
        $horario = Horario::factory()->create(['docente_id' => $docente->id]);
        $curso = CursoVirtual::factory()->create(['horario_id' => $horario->id]);

        Matricula::factory()->create([
            'estudiante_id' => $estudiante->id,
            'ciclo_id' => $horario->ciclo_id,
            'grado_id' => $horario->grado_id,
        ]);

        $this->actingAs($usuario)
            ->get(route('aula-virtual.show', $curso))
            ->assertOk();
    }

    public function test_un_estudiante_matriculado_puede_responder_en_un_foro(): void
    {
        $usuario = User::factory()->create();
        $usuario->assignRole(RolEnum::ESTUDIANTE->value);
        $estudiante = Estudiante::factory()->create(['user_id' => $usuario->id]);

        $docente = User::factory()->create();
        $docente->assignRole(RolEnum::DOCENTE->value);
        $horario = Horario::factory()->create(['docente_id' => $docente->id]);
        $curso = CursoVirtual::factory()->create(['horario_id' => $horario->id]);

        Matricula::factory()->create([
            'estudiante_id' => $estudiante->id,
            'ciclo_id' => $horario->ciclo_id,
            'grado_id' => $horario->grado_id,
        ]);

        $foro = Foro::factory()->create(['curso_virtual_id' => $curso->id, 'autor_id' => $docente->id]);

        $this->actingAs($usuario);

        Volt::test('aula-virtual.show', ['curso' => $curso])
            ->set("nuevaRespuestaForo.{$foro->id}", 'Mi respuesta')
            ->call('responderForo', $foro->id)
            ->assertHasNoErrors();

        $this->assertDatabaseHas('foro_respuestas', [
            'foro_id' => $foro->id,
            'autor_id' => $usuario->id,
            'contenido' => 'Mi respuesta',
        ]);
    }

    public function test_un_estudiante_no_matriculado_en_ese_grado_y_ciclo_no_puede_ver_el_curso(): void
    {
        $usuario = User::factory()->create();
        $usuario->assignRole(RolEnum::ESTUDIANTE->value);
        Estudiante::factory()->create(['user_id' => $usuario->id]);

        $docente = User::factory()->create();
        $docente->assignRole(RolEnum::DOCENTE->value);
        $curso = $this->cursoDelDocente($docente);

        $this->actingAs($usuario)
            ->get(route('aula-virtual.show', $curso))
            ->assertForbidden();
    }

    public function test_solo_el_docente_dueno_puede_crear_materiales(): void
    {
        $docente = User::factory()->create();
        $docente->assignRole(RolEnum::DOCENTE->value);
        $curso = $this->cursoDelDocente($docente);

        $otroDocente = User::factory()->create();
        $otroDocente->assignRole(RolEnum::DOCENTE->value);

        $this->assertTrue($otroDocente->can('manage', $curso) === false);
        $this->assertTrue($docente->can('manage', $curso));
    }

    public function test_direccion_puede_gestionar_cualquier_curso(): void
    {
        $direccion = User::factory()->create();
        $direccion->assignRole(RolEnum::DIRECCION->value);

        $docente = User::factory()->create();
        $docente->assignRole(RolEnum::DOCENTE->value);
        $curso = $this->cursoDelDocente($docente);

        $this->assertTrue($direccion->can('manage', $curso));
    }

    public function test_coordinador_puede_ver_el_listado_de_cursos(): void
    {
        $coordinador = User::factory()->create();
        $coordinador->assignRole(RolEnum::COORDINADOR->value);

        $this->actingAs($coordinador)
            ->get(route('aula-virtual.index'))
            ->assertOk();
    }

    public function test_un_usuario_sin_permisos_de_aula_virtual_no_puede_ver_el_listado(): void
    {
        $usuario = User::factory()->create();
        $usuario->assignRole(RolEnum::TESORERIA->value);

        $this->actingAs($usuario)
            ->get(route('aula-virtual.index'))
            ->assertForbidden();
    }

    /**
     * Dirección tiene aula_virtual.gestionar_propio vía '*' sin ser docente:
     * debía mostrar la vista de supervisión, no la de "tus cursos" (vacía).
     */
    public function test_direccion_ve_la_supervision_general_no_la_vista_de_docente(): void
    {
        $direccion = User::factory()->create();
        $direccion->assignRole(RolEnum::DIRECCION->value);
        $docente = User::factory()->create();
        $docente->assignRole(RolEnum::DOCENTE->value);
        $this->cursoDelDocente($docente);

        $this->actingAs($direccion)
            ->get(route('aula-virtual.index'))
            ->assertOk()
            ->assertSee('Todos los cursos virtuales activos.')
            ->assertDontSee('Todavía no tienes cursos con aula virtual activada.');
    }
}
