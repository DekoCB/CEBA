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

    public function test_la_seccion_de_noticias_muestra_todas_las_categorias_para_filtrar_en_el_cliente(): void
    {
        // El filtro de noticias es client-side (Alpine): el servidor siempre
        // renderiza todas las tarjetas y el filtrado ocurre en el navegador,
        // así que el test solo verifica que el contenido y los botones de
        // categoría estén presentes en el HTML.
        Volt::test('landing.index')
            ->assertSee('nuevo ciclo de secundaria')
            ->assertSee('Taller de orientación')
            ->assertSee('Todos')
            ->assertSee('Admisión')
            ->assertSee('Taller');
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
