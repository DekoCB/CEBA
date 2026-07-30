<?php

namespace Tests\Feature\Asistencia;

use App\Models\User;
use App\Modules\Academico\Models\Horario;
use App\Modules\Identidad\Database\Seeders\RolesAndPermissionsSeeder;
use App\Modules\Matricula\Models\Estudiante;
use App\Modules\Matricula\Models\Matricula;
use App\Shared\Enums\RolEnum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AsistenciaPermisosTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_el_docente_dueno_del_horario_puede_ver_y_registrar_asistencia(): void
    {
        $docente = User::factory()->create();
        $docente->assignRole(RolEnum::DOCENTE->value);
        $horario = Horario::factory()->create(['docente_id' => $docente->id]);

        $this->actingAs($docente)
            ->get(route('asistencia.show', $horario))
            ->assertOk();
    }

    public function test_un_docente_no_puede_ver_la_asistencia_de_otro_docente(): void
    {
        $docente = User::factory()->create();
        $docente->assignRole(RolEnum::DOCENTE->value);

        $otroDocente = User::factory()->create();
        $otroDocente->assignRole(RolEnum::DOCENTE->value);
        $horario = Horario::factory()->create(['docente_id' => $otroDocente->id]);

        $this->actingAs($docente)
            ->get(route('asistencia.show', $horario))
            ->assertForbidden();
    }

    public function test_un_estudiante_matriculado_en_el_grado_y_ciclo_del_horario_puede_ver_su_resumen(): void
    {
        $usuario = User::factory()->create();
        $usuario->assignRole(RolEnum::ESTUDIANTE->value);
        $estudiante = Estudiante::factory()->create(['user_id' => $usuario->id]);

        $horario = Horario::factory()->create();
        Matricula::factory()->create([
            'estudiante_id' => $estudiante->id,
            'grado_id' => $horario->grado_id,
            'ciclo_id' => $horario->ciclo_id,
        ]);

        $this->actingAs($usuario)
            ->get(route('asistencia.show', $horario))
            ->assertOk();
    }

    public function test_un_estudiante_no_matriculado_en_ese_grado_y_ciclo_no_puede_ver_la_asistencia(): void
    {
        $usuario = User::factory()->create();
        $usuario->assignRole(RolEnum::ESTUDIANTE->value);
        Estudiante::factory()->create(['user_id' => $usuario->id]);

        $horario = Horario::factory()->create();

        $this->actingAs($usuario)
            ->get(route('asistencia.show', $horario))
            ->assertForbidden();
    }

    public function test_coordinador_puede_supervisar_cualquier_horario(): void
    {
        $coordinador = User::factory()->create();
        $coordinador->assignRole(RolEnum::COORDINADOR->value);

        $docente = User::factory()->create();
        $docente->assignRole(RolEnum::DOCENTE->value);
        $horario = Horario::factory()->create(['docente_id' => $docente->id]);

        $this->actingAs($coordinador)
            ->get(route('asistencia.show', $horario))
            ->assertOk();
    }

    public function test_coordinador_puede_ver_el_listado(): void
    {
        $coordinador = User::factory()->create();
        $coordinador->assignRole(RolEnum::COORDINADOR->value);

        $this->actingAs($coordinador)
            ->get(route('asistencia.index'))
            ->assertOk();
    }

    public function test_un_usuario_sin_permisos_de_asistencia_no_puede_ver_el_listado(): void
    {
        $usuario = User::factory()->create();
        $usuario->assignRole(RolEnum::TESORERIA->value);

        $this->actingAs($usuario)
            ->get(route('asistencia.index'))
            ->assertForbidden();
    }
}
