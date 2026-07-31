<?php

namespace Tests\Feature\Pagos;

use App\Models\User;
use App\Modules\Identidad\Database\Seeders\RolesAndPermissionsSeeder;
use App\Modules\Matricula\Models\Estudiante;
use App\Shared\Enums\RolEnum;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
