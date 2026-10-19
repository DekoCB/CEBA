<?php

namespace Tests\Feature\Academico;

use App\Models\User;
use App\Modules\Academico\Enums\TipoCicloEnum;
use App\Modules\Identidad\Database\Seeders\RolesAndPermissionsSeeder;
use App\Shared\Enums\RolEnum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

/**
 * Pedido del cliente: al crear un grupo con fechas que no cuadran con su
 * tipo (ej. Grupo 4 debe iniciar en noviembre), el formulario se quedaba
 * sin ningún aviso -- CicloService lanzaba el error con las claves
 * fecha_inicio/fecha_fin (snake_case, como las columnas de la BD), pero
 * el componente Livewire y su blade leen $errors->get('fechaInicio')/
 * ('fechaFin') (camelCase, como las propiedades del componente), así que
 * el mensaje nunca llegaba a mostrarse.
 */
class CicloCrearDesdeUiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function direccion(): User
    {
        $usuario = User::factory()->create();
        $usuario->assignRole(RolEnum::DIRECCION->value);

        return $usuario;
    }

    public function test_crear_un_grupo_con_mes_de_inicio_muy_alejado_muestra_el_error_en_el_formulario(): void
    {
        $this->actingAs($this->direccion());

        Volt::test('academico.ciclos.index')
            ->call('abrirModal')
            ->set('nombre', '2026-4')
            ->set('tipo', TipoCicloEnum::GRUPO_4->value)
            ->set('anio', '2026')
            ->set('fechaInicio', '2026-09-01')
            ->set('fechaFin', '2027-03-31')
            ->call('guardar')
            ->assertHasErrors('fechaInicio')
            ->assertSet('mostrarModal', true);

        $this->assertDatabaseMissing('ciclos', ['nombre' => '2026-4']);
    }

    public function test_crear_un_grupo_con_fechas_coherentes_lo_guarda_sin_errores(): void
    {
        $this->actingAs($this->direccion());

        Volt::test('academico.ciclos.index')
            ->call('abrirModal')
            ->set('nombre', '2026-4')
            ->set('tipo', TipoCicloEnum::GRUPO_4->value)
            ->set('anio', '2026')
            ->set('fechaInicio', '2026-11-01')
            ->set('fechaFin', '2027-04-30')
            ->call('guardar')
            ->assertHasNoErrors()
            ->assertSet('mostrarModal', false);

        $this->assertDatabaseHas('ciclos', ['nombre' => '2026-4']);
    }

    /**
     * Pedido del cliente: permitir hasta 1 mes de diferencia respecto al
     * mes fijo del tipo, en vez de exigirlo exacto.
     */
    public function test_crear_un_grupo_con_un_mes_de_diferencia_respecto_al_mes_fijo_se_permite(): void
    {
        $this->actingAs($this->direccion());

        Volt::test('academico.ciclos.index')
            ->call('abrirModal')
            ->set('nombre', '2026-4')
            ->set('tipo', TipoCicloEnum::GRUPO_4->value)
            ->set('anio', '2026')
            ->set('fechaInicio', '2026-10-01')
            ->set('fechaFin', '2027-04-01')
            ->call('guardar')
            ->assertHasNoErrors()
            ->assertSet('mostrarModal', false);

        $this->assertDatabaseHas('ciclos', ['nombre' => '2026-4']);
    }
}
