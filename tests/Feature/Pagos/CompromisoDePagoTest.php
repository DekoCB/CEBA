<?php

namespace Tests\Feature\Pagos;

use App\Models\User;
use App\Modules\Identidad\Database\Seeders\RolesAndPermissionsSeeder;
use App\Modules\Matricula\Models\Estudiante;
use App\Modules\Matricula\Models\Matricula;
use App\Modules\Pagos\Models\PlanPago;
use App\Modules\Pagos\Services\PlanPagoService;
use App\Shared\Enums\RolEnum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Volt\Volt;
use Tests\TestCase;

/**
 * Pedido del cliente: en Cobranzas, poder registrar la fecha de compromiso
 * de pago de un deudor -- una por cuota, ya que es ahí donde hoy se listan
 * individualmente los pendientes/vencidos (Cobros individual, Historial del
 * estudiante).
 */
class CompromisoDePagoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function service(): PlanPagoService
    {
        return $this->app->make(PlanPagoService::class);
    }

    public function test_registrar_un_compromiso_de_pago_en_una_cuota_pendiente(): void
    {
        $matricula = Matricula::factory()->create();
        $plan = PlanPago::factory()->create(['matricula_id' => $matricula->id]);
        $cuota = $plan->cuotas()->create(['numero' => 1, 'monto' => 300, 'fecha_vencimiento' => now()->subDays(5), 'estado' => 'pendiente']);

        $fecha = now()->addDays(3)->format('Y-m-d');

        $this->service()->registrarCompromiso($cuota, $fecha);

        $this->assertSame($fecha, $cuota->fresh()->fecha_compromiso->format('Y-m-d'));
    }

    public function test_no_se_puede_registrar_compromiso_en_una_cuota_que_ya_no_esta_pendiente(): void
    {
        $matricula = Matricula::factory()->create();
        $plan = PlanPago::factory()->create(['matricula_id' => $matricula->id]);
        $cuota = $plan->cuotas()->create(['numero' => 1, 'monto' => 300, 'fecha_vencimiento' => now()->subDays(5), 'estado' => 'pagado']);

        $this->expectException(ValidationException::class);

        $this->service()->registrarCompromiso($cuota, now()->addDays(3)->format('Y-m-d'));
    }

    public function test_registrar_compromiso_desde_cobros_individual_en_la_ui(): void
    {
        $usuario = User::factory()->create();
        $usuario->assignRole(RolEnum::COORDINADOR->value);

        $estudiante = Estudiante::factory()->create();
        $matricula = Matricula::factory()->create(['estudiante_id' => $estudiante->id]);
        $plan = PlanPago::factory()->create(['matricula_id' => $matricula->id]);
        $cuota = $plan->cuotas()->create(['numero' => 1, 'monto' => 300, 'fecha_vencimiento' => now()->subDays(5), 'estado' => 'pendiente']);

        $this->actingAs($usuario);

        $fecha = now()->addDays(3)->format('Y-m-d');

        Volt::test('pagos.index')
            ->call('cobrosSeleccionarEstudiante', $estudiante->id, $estudiante->nombreCompleto())
            ->set("fechaCompromisoPorCuota.{$cuota->id}", $fecha)
            ->call('registrarCompromiso', $cuota->id)
            ->assertHasNoErrors();

        $this->assertSame($fecha, $cuota->fresh()->fecha_compromiso->format('Y-m-d'));
    }

    public function test_el_historial_del_estudiante_muestra_la_fecha_de_compromiso_si_esta_registrada(): void
    {
        $usuario = User::factory()->create();
        $usuario->assignRole(RolEnum::COORDINADOR->value);

        $estudiante = Estudiante::factory()->create();
        $matricula = Matricula::factory()->create(['estudiante_id' => $estudiante->id]);
        $plan = PlanPago::factory()->create(['matricula_id' => $matricula->id]);
        $plan->cuotas()->create([
            'numero' => 1,
            'monto' => 300,
            'fecha_vencimiento' => now()->subDays(5),
            'fecha_compromiso' => now()->addDays(2)->format('Y-m-d'),
            'estado' => 'pendiente',
        ]);

        $this->actingAs($usuario);

        Volt::test('historial-estudiante.index')
            ->call('seleccionarEstudiante', $estudiante->id, $estudiante->nombreCompleto())
            ->assertSee('Compromiso de pago');
    }

    public function test_no_se_puede_registrar_compromiso_sin_el_permiso_de_gestionar_pagos(): void
    {
        $usuario = User::factory()->create();
        $usuario->assignRole(RolEnum::ADMINISTRATIVO->value);

        $estudiante = Estudiante::factory()->create();
        $matricula = Matricula::factory()->create(['estudiante_id' => $estudiante->id]);
        $plan = PlanPago::factory()->create(['matricula_id' => $matricula->id]);
        $cuota = $plan->cuotas()->create(['numero' => 1, 'monto' => 300, 'fecha_vencimiento' => now()->subDays(5), 'estado' => 'pendiente']);

        $this->actingAs($usuario);

        Volt::test('pagos.index')
            ->call('registrarCompromiso', $cuota->id)
            ->assertForbidden();
    }
}
