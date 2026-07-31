<?php

namespace Tests\Feature\Certificados;

use App\Models\User;
use App\Modules\Certificados\Services\CertificadoService;
use App\Modules\Identidad\Database\Seeders\RolesAndPermissionsSeeder;
use App\Modules\Matricula\Models\Estudiante;
use App\Shared\Enums\RolEnum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class CertificadosPermisosTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_coordinador_puede_ver_la_gestion_de_certificados(): void
    {
        $coordinador = User::factory()->create();
        $coordinador->assignRole(RolEnum::COORDINADOR->value);

        $this->actingAs($coordinador)
            ->get(route('certificados.index'))
            ->assertOk();
    }

    public function test_un_docente_no_puede_ver_la_gestion_de_certificados(): void
    {
        $docente = User::factory()->create();
        $docente->assignRole(RolEnum::DOCENTE->value);

        $this->actingAs($docente)
            ->get(route('certificados.index'))
            ->assertForbidden();
    }

    public function test_un_estudiante_puede_ver_y_solicitar_sus_certificados(): void
    {
        $usuario = User::factory()->create();
        $usuario->assignRole(RolEnum::ESTUDIANTE->value);
        Estudiante::factory()->create(['user_id' => $usuario->id]);

        $this->actingAs($usuario)
            ->get(route('certificados.mis-certificados'))
            ->assertOk();
    }

    public function test_un_estudiante_no_puede_ver_la_gestion_de_certificados_del_staff(): void
    {
        $usuario = User::factory()->create();
        $usuario->assignRole(RolEnum::ESTUDIANTE->value);
        Estudiante::factory()->create(['user_id' => $usuario->id]);

        $this->actingAs($usuario)
            ->get(route('certificados.index'))
            ->assertForbidden();
    }

    public function test_la_verificacion_de_certificados_es_publica(): void
    {
        $estudiante = Estudiante::factory()->create();
        $emisor = User::factory()->create();
        $certificado = app(CertificadoService::class)->emitir($estudiante, null, null, null, $emisor);

        $this->get(route('certificados.verificar'))
            ->assertOk();

        Volt::test('certificados.verificar')
            ->set('codigo', $certificado->codigo_verificacion)
            ->call('verificar')
            ->assertSee($estudiante->nombreCompleto());
    }
}
