<?php

namespace Tests\Feature\Pagos;

use App\Models\User;
use App\Modules\Identidad\Database\Seeders\RolesAndPermissionsSeeder;
use App\Modules\Matricula\Models\Estudiante;
use App\Modules\Matricula\Models\Matricula;
use App\Modules\Pagos\Models\ConceptoPago;
use App\Modules\Pagos\Services\SolicitudCambioMontoService;
use App\Shared\Enums\RolEnum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class PagosPermisosTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_tesoreria_puede_ver_la_cola_de_pagos(): void
    {
        $tesoreria = User::factory()->create();
        $tesoreria->assignRole(RolEnum::TESORERIA->value);

        $this->actingAs($tesoreria)
            ->get(route('pagos.index'))
            ->assertOk();
    }

    public function test_un_docente_no_puede_ver_la_gestion_de_pagos(): void
    {
        $docente = User::factory()->create();
        $docente->assignRole(RolEnum::DOCENTE->value);

        $this->actingAs($docente)
            ->get(route('pagos.index'))
            ->assertForbidden();
    }

    public function test_administrativo_puede_ver_la_gestion_de_pagos(): void
    {
        $administrativo = User::factory()->create();
        $administrativo->assignRole(RolEnum::ADMINISTRATIVO->value);

        $this->actingAs($administrativo)
            ->get(route('pagos.index'))
            ->assertOk();
    }

    public function test_la_pestana_planes_de_pago_no_falla_si_una_matricula_quedo_sin_estudiante(): void
    {
        $administrativo = User::factory()->create();
        $administrativo->assignRole(RolEnum::ADMINISTRATIVO->value);

        $estudiante = Estudiante::factory()->create();
        Matricula::factory()->create(['estudiante_id' => $estudiante->id, 'estado' => 'aprobada']);
        $estudiante->delete();

        $this->actingAs($administrativo);

        Volt::test('pagos.index')
            ->set('tab', 'planes')
            ->assertOk()
            ->assertSee('—');
    }

    public function test_un_estudiante_puede_ver_su_estado_de_cuenta(): void
    {
        $usuario = User::factory()->create();
        $usuario->assignRole(RolEnum::ESTUDIANTE->value);
        Estudiante::factory()->create(['user_id' => $usuario->id]);

        $this->actingAs($usuario)
            ->get(route('pagos.mi-cuenta'))
            ->assertOk();
    }

    public function test_un_estudiante_no_puede_ver_la_gestion_de_pagos_del_staff(): void
    {
        $usuario = User::factory()->create();
        $usuario->assignRole(RolEnum::ESTUDIANTE->value);
        Estudiante::factory()->create(['user_id' => $usuario->id]);

        $this->actingAs($usuario)
            ->get(route('pagos.index'))
            ->assertForbidden();
    }

    public function test_coordinador_puede_gestionar_conceptos_de_pago(): void
    {
        $coordinador = User::factory()->create();
        $coordinador->assignRole(RolEnum::COORDINADOR->value);

        $this->actingAs($coordinador)
            ->get(route('pagos.conceptos'))
            ->assertOk();
    }

    public function test_un_docente_no_puede_gestionar_conceptos_de_pago(): void
    {
        $docente = User::factory()->create();
        $docente->assignRole(RolEnum::DOCENTE->value);

        $this->actingAs($docente)
            ->get(route('pagos.conceptos'))
            ->assertForbidden();
    }

    public function test_un_coordinador_no_puede_aprobar_cambios_de_monto(): void
    {
        $coordinador = User::factory()->create();
        $coordinador->assignRole(RolEnum::COORDINADOR->value);
        $concepto = ConceptoPago::factory()->create(['monto_base' => 100]);
        $solicitud = $this->app->make(SolicitudCambioMontoService::class)
            ->solicitar($concepto, 150.0, $coordinador->id);

        $this->actingAs($coordinador);

        rescue(fn () => Volt::test('pagos.conceptos')->call('aprobarCambioMonto', $solicitud->id), report: false);

        $this->assertSame('100.00', $concepto->fresh()->monto_base);
    }

    public function test_solo_tesoreria_puede_gestionar_cuentas_bancarias(): void
    {
        $tesoreria = User::factory()->create();
        $tesoreria->assignRole(RolEnum::TESORERIA->value);

        $this->actingAs($tesoreria)
            ->get(route('pagos.cuentas-bancarias'))
            ->assertOk();

        $coordinador = User::factory()->create();
        $coordinador->assignRole(RolEnum::COORDINADOR->value);

        $this->actingAs($coordinador)
            ->get(route('pagos.cuentas-bancarias'))
            ->assertForbidden();
    }
}
