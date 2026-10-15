<?php

namespace Tests\Feature\Pagos;

use App\Models\User;
use App\Modules\Identidad\Database\Seeders\RolesAndPermissionsSeeder;
use App\Modules\Matricula\Models\Estudiante;
use App\Modules\Pagos\Enums\SerieReciboEnum;
use App\Modules\Pagos\Models\ConceptoPago;
use App\Modules\Pagos\Models\Pago;
use App\Modules\Pagos\Services\PagoService;
use App\Shared\Enums\RolEnum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pedido del cliente: poder ver una vista previa del recibo en la Cola de
 * aprobación, antes de aprobar -- reutiliza la misma plantilla que
 * ReciboService::emitir(), pero sin crear ni guardar ningún Recibo real.
 */
class PagoVistaPreviaReciboTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function pagoPendiente(): Pago
    {
        $estudiante = Estudiante::factory()->create();
        $concepto = ConceptoPago::factory()->create();

        return $this->app->make(PagoService::class)->registrar(
            $estudiante,
            [['concepto_id' => $concepto->id, 'cuota_id' => null, 'cargo_adicional_id' => null, 'monto' => 100.0]],
            [['monto' => 100.0, 'metodo' => 'yape']],
            null,
            null,
        );
    }

    public function test_tesoreria_puede_ver_la_vista_previa_de_un_pago_pendiente(): void
    {
        $tesoreria = User::factory()->create();
        $tesoreria->assignRole(RolEnum::TESORERIA->value);
        $pago = $this->pagoPendiente();

        $respuesta = $this->actingAs($tesoreria)->get(route('pagos.vista-previa', $pago));

        $respuesta->assertOk();
        $respuesta->assertHeader('content-type', 'application/pdf');
    }

    public function test_la_vista_previa_no_crea_ningun_recibo_real(): void
    {
        $tesoreria = User::factory()->create();
        $tesoreria->assignRole(RolEnum::TESORERIA->value);
        $pago = $this->pagoPendiente();

        $this->actingAs($tesoreria)->get(route('pagos.vista-previa', $pago));

        $this->assertDatabaseMissing('recibos', ['pago_id' => $pago->id]);
    }

    public function test_la_vista_previa_de_un_pago_ya_aprobado_devuelve_404(): void
    {
        $tesoreria = User::factory()->create();
        $tesoreria->assignRole(RolEnum::TESORERIA->value);
        $pago = $this->pagoPendiente();
        $this->app->make(PagoService::class)->aprobar($pago, $tesoreria->id, SerieReciboEnum::ORIGINAL);

        $this->actingAs($tesoreria)
            ->get(route('pagos.vista-previa', $pago))
            ->assertNotFound();
    }

    public function test_un_administrativo_sin_el_permiso_de_aprobar_no_puede_ver_la_vista_previa(): void
    {
        $administrativo = User::factory()->create();
        $administrativo->assignRole(RolEnum::ADMINISTRATIVO->value);
        $pago = $this->pagoPendiente();

        $this->actingAs($administrativo)
            ->get(route('pagos.vista-previa', $pago))
            ->assertForbidden();
    }
}
