<?php

namespace Tests\Feature\Identidad;

use App\Models\User;
use App\Modules\Identidad\Database\Seeders\RolesAndPermissionsSeeder;
use App\Shared\Enums\RolEnum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class UsuariosTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_direccion_puede_ver_el_listado_de_usuarios(): void
    {
        $direccion = User::factory()->create();
        $direccion->assignRole(RolEnum::DIRECCION->value);

        $this->actingAs($direccion)
            ->get('/usuarios')
            ->assertOk()
            ->assertSeeVolt('usuarios.index');
    }

    public function test_un_docente_no_puede_ver_el_listado_de_usuarios(): void
    {
        $docente = User::factory()->create();
        $docente->assignRole(RolEnum::DOCENTE->value);

        $this->actingAs($docente)
            ->get('/usuarios')
            ->assertForbidden();
    }

    public function test_direccion_puede_crear_un_usuario_con_rol(): void
    {
        $direccion = User::factory()->create();
        $direccion->assignRole(RolEnum::DIRECCION->value);

        $this->actingAs($direccion);

        Volt::test('usuarios.index')
            ->set('name', 'Nueva Docente')
            ->set('email', 'nueva.docente@ceba.test')
            ->set('dni', '87654321')
            ->set('phone', '987654321')
            ->set('password', 'password123')
            ->set('rol', RolEnum::DOCENTE->value)
            ->call('crear')
            ->assertHasNoErrors();

        $creado = User::query()->where('email', 'nueva.docente@ceba.test')->first();

        $this->assertNotNull($creado);
        $this->assertTrue($creado->hasRole(RolEnum::DOCENTE->value));
    }

    public function test_no_permite_crear_usuario_con_email_duplicado(): void
    {
        $direccion = User::factory()->create();
        $direccion->assignRole(RolEnum::DIRECCION->value);

        User::factory()->create(['email' => 'existente@ceba.test']);

        $this->actingAs($direccion);

        Volt::test('usuarios.index')
            ->set('name', 'Otro Usuario')
            ->set('email', 'existente@ceba.test')
            ->set('dni', '11223344')
            ->set('password', 'password123')
            ->set('rol', RolEnum::DOCENTE->value)
            ->call('crear')
            ->assertHasErrors('email');
    }
}
