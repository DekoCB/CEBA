<?php

namespace Tests\Feature\Matricula;

use App\Models\User;
use App\Modules\Identidad\Database\Seeders\RolesAndPermissionsSeeder;
use App\Modules\Identidad\Models\AuditLog;
use App\Modules\Matricula\Models\Apoderado;
use App\Modules\Matricula\Models\Estudiante;
use App\Shared\Enums\RolEnum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

/**
 * Pedido del cliente: poder actualizar el celular del estudiante y del
 * apoderado, conservando el historial de los cambios. La captura del
 * historial ya la hacía gratis el trait Auditable (ver AuditService); lo
 * que faltaba era la edición en sí (no existía ningún camino para cambiar
 * el celular después de matricular) y mostrar el valor anterior/nuevo.
 */
class MatriculaCelularHistorialTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_editar_celular_del_estudiante_lo_actualiza_y_normaliza(): void
    {
        $usuario = User::factory()->create();
        $usuario->assignRole(RolEnum::COORDINADOR->value);

        $estudiante = Estudiante::factory()->create(['es_menor_edad' => false, 'celular' => '987654321']);

        $this->actingAs($usuario);

        Volt::test('matricula.show', ['estudiante' => $estudiante])
            ->call('editarCelular', 'estudiante')
            ->assertSet('celularNuevo', '987654321')
            ->set('celularNuevo', '+51 912 345 678')
            ->call('guardarCelular')
            ->assertHasNoErrors()
            ->assertSet('editandoCelular', null);

        $this->assertSame('912345678', $estudiante->fresh()->celular);
    }

    public function test_editar_celular_del_apoderado_lo_actualiza(): void
    {
        $usuario = User::factory()->create();
        $usuario->assignRole(RolEnum::COORDINADOR->value);

        $estudiante = Estudiante::factory()->menorDeEdad()->create();
        $apoderado = Apoderado::factory()->create(['estudiante_id' => $estudiante->id, 'celular' => '987654321']);

        $this->actingAs($usuario);

        Volt::test('matricula.show', ['estudiante' => $estudiante])
            ->call('editarCelular', 'apoderado')
            ->assertSet('celularNuevo', '987654321')
            ->set('celularNuevo', '912345678')
            ->call('guardarCelular')
            ->assertHasNoErrors();

        $this->assertSame('912345678', $apoderado->fresh()->celular);
    }

    public function test_editar_celular_mal_escrito_queda_como_error_de_formulario(): void
    {
        $usuario = User::factory()->create();
        $usuario->assignRole(RolEnum::COORDINADOR->value);

        $estudiante = Estudiante::factory()->create(['es_menor_edad' => false, 'celular' => '987654321']);

        $this->actingAs($usuario);

        Volt::test('matricula.show', ['estudiante' => $estudiante])
            ->call('editarCelular', 'estudiante')
            ->set('celularNuevo', 'Sólo al Papá escribirle de la pensión 966775990 / mamá 977 187 160')
            ->call('guardarCelular')
            ->assertHasErrors(['celularNuevo']);

        $this->assertSame('987654321', $estudiante->fresh()->celular);
    }

    public function test_editar_celular_desde_el_modal_de_ficha(): void
    {
        $usuario = User::factory()->create();
        $usuario->assignRole(RolEnum::COORDINADOR->value);

        $estudiante = Estudiante::factory()->create(['es_menor_edad' => false, 'celular' => '987654321']);

        $this->actingAs($usuario);

        Volt::test('matricula.ficha-modal')
            ->call('abrir', $estudiante->id)
            ->call('editarCelular', 'estudiante')
            ->set('celularNuevo', '912345678')
            ->call('guardarCelular')
            ->assertHasNoErrors();

        $this->assertSame('912345678', $estudiante->fresh()->celular);
    }

    public function test_el_historial_de_celular_muestra_el_valor_anterior_y_el_nuevo(): void
    {
        $usuario = User::factory()->create();
        $usuario->assignRole(RolEnum::COORDINADOR->value);

        $estudiante = Estudiante::factory()->create(['es_menor_edad' => false, 'celular' => '987654321']);

        $this->actingAs($usuario);

        Volt::test('matricula.show', ['estudiante' => $estudiante])
            ->call('editarCelular', 'estudiante')
            ->set('celularNuevo', '912345678')
            ->call('guardarCelular')
            ->assertHasNoErrors();

        $component = Volt::test('matricula.show', ['estudiante' => $estudiante->fresh()]);

        $component
            ->assertSee('987654321')
            ->assertSee('912345678');
    }

    public function test_actualizar_el_celular_de_un_estudiante_registra_auditoria(): void
    {
        $usuario = User::factory()->create();
        $usuario->assignRole(RolEnum::COORDINADOR->value);

        $estudiante = Estudiante::factory()->create(['es_menor_edad' => false, 'celular' => '987654321']);

        $this->actingAs($usuario);

        Volt::test('matricula.show', ['estudiante' => $estudiante])
            ->call('editarCelular', 'estudiante')
            ->set('celularNuevo', '912345678')
            ->call('guardarCelular');

        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => $estudiante->getMorphClass(),
            'auditable_id' => $estudiante->id,
            'event' => 'updated',
        ]);

        $registro = AuditLog::query()
            ->where('auditable_type', $estudiante->getMorphClass())
            ->where('auditable_id', $estudiante->id)
            ->where('event', 'updated')
            ->latest('id')
            ->first();

        $this->assertSame('912345678', $registro->new_values['celular']);
        $this->assertSame('987654321', $registro->old_values['celular']);
    }

    public function test_no_se_puede_editar_celular_sin_el_permiso_de_editar_matricula(): void
    {
        $usuario = User::factory()->create();
        $usuario->assignRole(RolEnum::ADMINISTRATIVO->value);

        $estudiante = Estudiante::factory()->create(['es_menor_edad' => false]);

        $this->actingAs($usuario);

        Volt::test('matricula.show', ['estudiante' => $estudiante])
            ->call('editarCelular', 'estudiante')
            ->assertForbidden();
    }
}
