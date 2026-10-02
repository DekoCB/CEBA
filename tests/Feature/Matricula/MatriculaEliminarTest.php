<?php

namespace Tests\Feature\Matricula;

use App\Models\User;
use App\Modules\Identidad\Database\Seeders\RolesAndPermissionsSeeder;
use App\Modules\Matricula\Models\Estudiante;
use App\Modules\Matricula\Models\Matricula;
use App\Modules\Pagos\Enums\EstadoPagoEnum;
use App\Modules\Pagos\Models\Pago;
use App\Modules\Pagos\Models\PlanPago;
use App\Shared\Enums\RolEnum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

/**
 * Pedido del cliente: poder eliminar una matrícula registrada por error
 * (grado equivocado, duplicada...) mientras todavía no tenga ningún pago o
 * recibo registrado -- el chequeo mira solo ESA matrícula específica, no
 * todo el historial del estudiante.
 */
class MatriculaEliminarTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_elimina_una_matricula_sin_plan_de_pago(): void
    {
        $usuario = User::factory()->create();
        $usuario->assignRole(RolEnum::COORDINADOR->value);

        $estudiante = Estudiante::factory()->create();
        $matricula = Matricula::factory()->create(['estudiante_id' => $estudiante->id]);

        $this->actingAs($usuario);

        Volt::test('matricula.show', ['estudiante' => $estudiante])
            ->call('eliminarMatricula', $matricula->id)
            ->assertHasNoErrors();

        $this->assertSoftDeleted('matriculas', ['id' => $matricula->id]);
    }

    public function test_elimina_una_matricula_con_plan_de_pago_pero_sin_pagos_y_borra_el_plan_y_sus_cuotas(): void
    {
        $usuario = User::factory()->create();
        $usuario->assignRole(RolEnum::COORDINADOR->value);

        $estudiante = Estudiante::factory()->create();
        $matricula = Matricula::factory()->create(['estudiante_id' => $estudiante->id]);
        $plan = PlanPago::factory()->create(['matricula_id' => $matricula->id, 'monto_total' => 600]);
        $cuota = $plan->cuotas()->create(['numero' => 1, 'monto' => 600, 'fecha_vencimiento' => now()->addMonth(), 'estado' => 'pendiente']);

        $this->actingAs($usuario);

        Volt::test('matricula.show', ['estudiante' => $estudiante])
            ->call('eliminarMatricula', $matricula->id)
            ->assertHasNoErrors();

        $this->assertSoftDeleted('matriculas', ['id' => $matricula->id]);
        $this->assertDatabaseMissing('planes_pago', ['id' => $plan->id]);
        $this->assertDatabaseMissing('cuotas', ['id' => $cuota->id]);
    }

    public function test_no_permite_eliminar_una_matricula_con_un_pago_pendiente(): void
    {
        $usuario = User::factory()->create();
        $usuario->assignRole(RolEnum::COORDINADOR->value);

        $estudiante = Estudiante::factory()->create();
        $matricula = Matricula::factory()->create(['estudiante_id' => $estudiante->id]);
        $plan = PlanPago::factory()->create(['matricula_id' => $matricula->id, 'monto_total' => 300]);
        $cuota = $plan->cuotas()->create(['numero' => 1, 'monto' => 300, 'fecha_vencimiento' => now()->addMonth(), 'estado' => 'pendiente']);
        Pago::factory()->create(['estudiante_id' => $estudiante->id, 'cuota_id' => $cuota->id, 'estado' => EstadoPagoEnum::PENDIENTE]);

        $this->actingAs($usuario);

        Volt::test('matricula.show', ['estudiante' => $estudiante])
            ->call('eliminarMatricula', $matricula->id);

        $this->assertDatabaseHas('matriculas', ['id' => $matricula->id, 'deleted_at' => null]);
    }

    public function test_no_permite_eliminar_una_matricula_con_un_pago_rechazado(): void
    {
        $usuario = User::factory()->create();
        $usuario->assignRole(RolEnum::COORDINADOR->value);

        $estudiante = Estudiante::factory()->create();
        $matricula = Matricula::factory()->create(['estudiante_id' => $estudiante->id]);
        $plan = PlanPago::factory()->create(['matricula_id' => $matricula->id, 'monto_total' => 300]);
        $cuota = $plan->cuotas()->create(['numero' => 1, 'monto' => 300, 'fecha_vencimiento' => now()->addMonth(), 'estado' => 'pendiente']);
        Pago::factory()->create(['estudiante_id' => $estudiante->id, 'cuota_id' => $cuota->id, 'estado' => EstadoPagoEnum::RECHAZADO]);

        $this->actingAs($usuario);

        Volt::test('matricula.show', ['estudiante' => $estudiante])
            ->call('eliminarMatricula', $matricula->id);

        $this->assertDatabaseHas('matriculas', ['id' => $matricula->id, 'deleted_at' => null]);
    }

    public function test_el_coordinador_puede_eliminar_una_matricula_sin_pagos_desde_el_modal_de_ficha(): void
    {
        $usuario = User::factory()->create();
        $usuario->assignRole(RolEnum::COORDINADOR->value);

        $estudiante = Estudiante::factory()->create();
        $matricula = Matricula::factory()->create(['estudiante_id' => $estudiante->id]);

        $this->actingAs($usuario);

        Volt::test('matricula.ficha-modal')
            ->call('abrir', $estudiante->id)
            ->call('eliminarMatricula', $matricula->id);

        $this->assertSoftDeleted('matriculas', ['id' => $matricula->id]);
    }

    public function test_eliminar_una_matricula_con_pagos_desde_la_ui_muestra_un_error_claro(): void
    {
        $usuario = User::factory()->create();
        $usuario->assignRole(RolEnum::COORDINADOR->value);

        $estudiante = Estudiante::factory()->create();
        $matricula = Matricula::factory()->create(['estudiante_id' => $estudiante->id]);
        $plan = PlanPago::factory()->create(['matricula_id' => $matricula->id, 'monto_total' => 300]);
        $cuota = $plan->cuotas()->create(['numero' => 1, 'monto' => 300, 'fecha_vencimiento' => now()->addMonth(), 'estado' => 'pendiente']);
        Pago::factory()->aprobado()->create(['estudiante_id' => $estudiante->id, 'cuota_id' => $cuota->id, 'monto' => 300]);

        $this->actingAs($usuario);

        Volt::test('matricula.show', ['estudiante' => $estudiante])
            ->call('eliminarMatricula', $matricula->id)
            ->assertSee('ya tiene pagos registrados');

        $this->assertDatabaseHas('matriculas', ['id' => $matricula->id, 'deleted_at' => null]);
    }

    public function test_no_se_puede_eliminar_matricula_sin_el_permiso(): void
    {
        $usuario = User::factory()->create();
        $usuario->assignRole(RolEnum::ADMINISTRATIVO->value);

        $estudiante = Estudiante::factory()->create();
        $matricula = Matricula::factory()->create(['estudiante_id' => $estudiante->id]);

        $this->actingAs($usuario);

        Volt::test('matricula.show', ['estudiante' => $estudiante])
            ->call('eliminarMatricula', $matricula->id)
            ->assertForbidden();
    }

    public function test_el_boton_de_eliminar_no_aparece_si_la_matricula_tiene_pagos(): void
    {
        $usuario = User::factory()->create();
        $usuario->assignRole(RolEnum::COORDINADOR->value);

        $estudiante = Estudiante::factory()->create();
        $matricula = Matricula::factory()->create(['estudiante_id' => $estudiante->id]);
        $plan = PlanPago::factory()->create(['matricula_id' => $matricula->id, 'monto_total' => 300]);
        $cuota = $plan->cuotas()->create(['numero' => 1, 'monto' => 300, 'fecha_vencimiento' => now()->addMonth(), 'estado' => 'pendiente']);
        Pago::factory()->aprobado()->create(['estudiante_id' => $estudiante->id, 'cuota_id' => $cuota->id, 'monto' => 300]);

        $this->actingAs($usuario);

        Volt::test('matricula.show', ['estudiante' => $estudiante])
            ->assertSee('No se puede eliminar (tiene pagos registrados)')
            ->assertDontSee('Eliminar matrícula');
    }
}
