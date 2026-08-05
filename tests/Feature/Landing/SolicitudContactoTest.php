<?php

namespace Tests\Feature\Landing;

use App\Modules\Landing\Models\SolicitudContacto;
use App\Modules\Landing\Services\SolicitudContactoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class SolicitudContactoTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_formulario_de_contacto_guarda_la_solicitud(): void
    {
        Volt::test('landing.index')
            ->set('nombre', 'Juan Pérez')
            ->set('email', 'juan.perez@example.com')
            ->set('telefono', '987654321')
            ->set('programaInteres', 'Secundaria EBA')
            ->set('mensaje', 'Quisiera información sobre horarios.')
            ->call('enviarMensaje')
            ->assertHasNoErrors()
            ->assertSet('enviado', true)
            ->assertSet('nombre', '');

        $this->assertDatabaseHas('solicitudes_contacto', [
            'nombre' => 'Juan Pérez',
            'email' => 'juan.perez@example.com',
            'telefono' => '987654321',
            'programa_interes' => 'Secundaria EBA',
            'mensaje' => 'Quisiera información sobre horarios.',
        ]);
    }

    public function test_el_formulario_exige_los_campos_obligatorios(): void
    {
        Volt::test('landing.index')
            ->set('nombre', '')
            ->set('email', 'correo-invalido')
            ->set('telefono', '')
            ->set('mensaje', '')
            ->call('enviarMensaje')
            ->assertHasErrors(['nombre', 'email', 'telefono', 'mensaje']);

        $this->assertDatabaseCount('solicitudes_contacto', 0);
    }

    public function test_el_filtro_de_noticias_reduce_el_listado_visible(): void
    {
        Volt::test('landing.index')
            ->assertSee('nuevo ciclo de secundaria')
            ->assertSee('Taller de orientación')
            ->set('filtroNoticias', 'taller')
            ->assertSee('Taller de orientación')
            ->assertDontSee('nuevo ciclo de secundaria');
    }

    public function test_registrar_crea_la_solicitud_en_base_de_datos(): void
    {
        $service = $this->app->make(SolicitudContactoService::class);

        $solicitud = $service->registrar('Ana Torres', 'ana@example.com', '999888777', null, 'Consulta general.');

        $this->assertInstanceOf(SolicitudContacto::class, $solicitud);
        $this->assertFalse($solicitud->refresh()->atendido);
        $this->assertDatabaseHas('solicitudes_contacto', ['nombre' => 'Ana Torres', 'atendido' => false]);
    }
}
