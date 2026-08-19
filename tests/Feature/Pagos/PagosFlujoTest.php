<?php

namespace Tests\Feature\Pagos;

use App\Models\User;
use App\Modules\Identidad\Database\Seeders\RolesAndPermissionsSeeder;
use App\Modules\Matricula\Models\Estudiante;
use App\Modules\Matricula\Models\Matricula;
use App\Modules\Pagos\Enums\NumeroCuotasEnum;
use App\Modules\Pagos\Models\ConceptoPago;
use App\Modules\Pagos\Models\Pago;
use App\Modules\Pagos\Services\PagoService;
use App\Modules\Pagos\Services\PlanPagoService;
use App\Shared\Enums\RolEnum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Volt;
use Tests\TestCase;

class PagosFlujoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_administrativo_registra_un_pago_y_tesoreria_lo_aprueba(): void
    {
        $administrativo = User::factory()->create();
        $administrativo->assignRole(RolEnum::ADMINISTRATIVO->value);

        $estudiante = Estudiante::factory()->create();
        $concepto = ConceptoPago::factory()->create();

        $this->actingAs($administrativo);

        Volt::test('pagos.index')
            ->call('seleccionarEstudiante', $estudiante->id, $estudiante->nombreCompleto())
            ->set('conceptoId', (string) $concepto->id)
            ->set('monto', '150')
            ->set('metodo', 'yape')
            ->call('registrarPago')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('pagos', [
            'estudiante_id' => $estudiante->id,
            'concepto_id' => $concepto->id,
            'estado' => 'pendiente',
        ]);

        $pago = Pago::query()->where('estudiante_id', $estudiante->id)->firstOrFail();

        $tesoreria = User::factory()->create();
        $tesoreria->assignRole(RolEnum::TESORERIA->value);
        $this->actingAs($tesoreria);

        Volt::test('pagos.index')
            ->call('aprobar', $pago->id)
            ->assertHasNoErrors();

        $this->assertDatabaseHas('pagos', ['id' => $pago->id, 'estado' => 'aprobado']);
        $this->assertDatabaseHas('recibos', ['pago_id' => $pago->id]);
    }

    public function test_registrar_un_pago_con_concepto_otro_exige_y_guarda_el_detalle_libre(): void
    {
        $administrativo = User::factory()->create();
        $administrativo->assignRole(RolEnum::ADMINISTRATIVO->value);

        $estudiante = Estudiante::factory()->create();
        $concepto = ConceptoPago::factory()->create(['tipo' => 'otro']);

        $this->actingAs($administrativo);

        Volt::test('pagos.index')
            ->call('seleccionarEstudiante', $estudiante->id, $estudiante->nombreCompleto())
            ->set('conceptoId', (string) $concepto->id)
            ->set('monto', '25')
            ->set('metodo', 'efectivo')
            ->call('registrarPago')
            ->assertHasErrors('detalle');

        $this->assertDatabaseMissing('pagos', ['estudiante_id' => $estudiante->id]);

        Volt::test('pagos.index')
            ->call('seleccionarEstudiante', $estudiante->id, $estudiante->nombreCompleto())
            ->set('conceptoId', (string) $concepto->id)
            ->set('detalle', 'Duplicado de constancia de matrícula')
            ->set('monto', '25')
            ->set('metodo', 'efectivo')
            ->call('registrarPago')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('pagos', [
            'estudiante_id' => $estudiante->id,
            'detalle' => 'Duplicado de constancia de matrícula',
        ]);
    }

    public function test_tesoreria_rechaza_un_pago_con_motivo(): void
    {
        $tesoreria = User::factory()->create();
        $tesoreria->assignRole(RolEnum::TESORERIA->value);

        $estudiante = Estudiante::factory()->create();
        $concepto = ConceptoPago::factory()->create();
        $pago = $this->app->make(PagoService::class)
            ->registrar($estudiante, $concepto, 100.0, 'efectivo', null, null, null);

        $this->actingAs($tesoreria);

        Volt::test('pagos.index')
            ->set("motivoRechazo.{$pago->id}", 'Comprobante no corresponde')
            ->call('rechazar', $pago->id)
            ->assertHasNoErrors();

        $this->assertDatabaseHas('pagos', [
            'id' => $pago->id,
            'estado' => 'rechazado',
            'motivo_rechazo' => 'Comprobante no corresponde',
        ]);
    }

    public function test_un_administrativo_no_puede_aprobar_pagos(): void
    {
        $administrativo = User::factory()->create();
        $administrativo->assignRole(RolEnum::ADMINISTRATIVO->value);

        $estudiante = Estudiante::factory()->create();
        $concepto = ConceptoPago::factory()->create();
        $pago = $this->app->make(PagoService::class)
            ->registrar($estudiante, $concepto, 100.0, 'efectivo', null, null, null);

        $this->actingAs($administrativo);

        rescue(fn () => Volt::test('pagos.index')->call('aprobar', $pago->id), report: false);

        $this->assertDatabaseHas('pagos', ['id' => $pago->id, 'estado' => 'pendiente']);
    }

    public function test_coordinador_crea_un_plan_de_pago_desde_el_listado(): void
    {
        $coordinador = User::factory()->create();
        $coordinador->assignRole(RolEnum::COORDINADOR->value);

        $matricula = Matricula::factory()->create(['fecha_matricula' => now()]);

        $this->actingAs($coordinador);

        Volt::test('pagos.index')
            ->set("numeroCuotasPorMatricula.{$matricula->id}", (string) NumeroCuotasEnum::SEIS->value)
            ->set("montoTotalPorMatricula.{$matricula->id}", '600')
            ->call('crearPlan', $matricula->id)
            ->assertHasNoErrors();

        $this->assertDatabaseHas('planes_pago', ['matricula_id' => $matricula->id, 'numero_cuotas' => 6]);
        $this->assertDatabaseCount('cuotas', 6);
    }

    public function test_el_estudiante_sube_un_comprobante_para_su_cuota(): void
    {
        $usuario = User::factory()->create();
        $usuario->assignRole(RolEnum::ESTUDIANTE->value);
        $estudiante = Estudiante::factory()->create(['user_id' => $usuario->id]);
        $matricula = Matricula::factory()->create(['estudiante_id' => $estudiante->id, 'fecha_matricula' => now()]);
        ConceptoPago::factory()->create(['tipo' => 'mensualidad']);

        $plan = $this->app->make(PlanPagoService::class)->crear($matricula, NumeroCuotasEnum::UNA, 100.0);
        $cuota = $plan->cuotas()->firstOrFail();

        Storage::fake('public');

        $this->actingAs($usuario);

        Volt::test('pagos.mi-cuenta')
            ->set("metodoPorCuota.{$cuota->id}", 'yape')
            ->set("comprobantePorCuota.{$cuota->id}", UploadedFile::fake()->create('comprobante.pdf', 100, 'application/pdf'))
            ->call('subirComprobante', $cuota->id)
            ->assertHasNoErrors();

        $this->assertDatabaseHas('pagos', [
            'estudiante_id' => $estudiante->id,
            'cuota_id' => $cuota->id,
            'estado' => 'pendiente',
        ]);
    }
}
