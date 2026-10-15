<?php

namespace Tests\Feature\Pagos;

use App\Models\User;
use App\Modules\Identidad\Database\Seeders\RolesAndPermissionsSeeder;
use App\Modules\Matricula\Models\Estudiante;
use App\Modules\Matricula\Models\Matricula;
use App\Modules\Pagos\Enums\SerieReciboEnum;
use App\Modules\Pagos\Models\ConceptoPago;
use App\Modules\Pagos\Models\Cuota;
use App\Modules\Pagos\Models\Pago;
use App\Modules\Pagos\Models\PlanPago;
use App\Shared\Enums\RolEnum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class PagosSerieObservacionCuotaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function administrativo(): User
    {
        $usuario = User::factory()->create();
        $usuario->assignRole(RolEnum::ADMINISTRATIVO->value);

        return $usuario;
    }

    private function tesoreria(): User
    {
        $usuario = User::factory()->create();
        $usuario->assignRole(RolEnum::TESORERIA->value);

        return $usuario;
    }

    public function test_registrar_pago_de_mensualidad_marcando_la_cuota_pendiente_la_vincula(): void
    {
        $matricula = Matricula::factory()->create();
        $planPago = PlanPago::factory()->create(['matricula_id' => $matricula->id]);
        $cuota = Cuota::factory()->create(['plan_pago_id' => $planPago->id, 'monto' => 80]);
        ConceptoPago::factory()->create(['tipo' => 'mensualidad']);

        $this->actingAs($this->administrativo());

        Volt::test('pagos.index')
            ->call('seleccionarEstudiante', $matricula->estudiante_id, $matricula->estudiante->nombreCompleto())
            ->set('cuotasSeleccionadas', [(string) $cuota->id])
            ->set('partes.0.monto', '80')
            ->set('partes.0.metodo', 'yape')
            ->call('registrarPago')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('pagos', [
            'estudiante_id' => $matricula->estudiante_id,
            'cuota_id' => $cuota->id,
        ]);
    }

    public function test_registrar_pago_de_otro_concepto_no_vincula_ninguna_cuota(): void
    {
        $matricula = Matricula::factory()->create();
        PlanPago::factory()->create(['matricula_id' => $matricula->id])
            ->cuotas()->save(Cuota::factory()->make());
        $concepto = ConceptoPago::factory()->create(['tipo' => 'certificado']);

        $this->actingAs($this->administrativo());

        Volt::test('pagos.index')
            ->call('seleccionarEstudiante', $matricula->estudiante_id, $matricula->estudiante->nombreCompleto())
            ->set('conceptoSueltoId', (string) $concepto->id)
            ->set('montoConceptoSuelto', '30')
            ->set('partes.0.monto', '30')
            ->set('partes.0.metodo', 'efectivo')
            ->call('registrarPago')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('pagos', [
            'estudiante_id' => $matricula->estudiante_id,
            'cuota_id' => null,
        ]);
    }

    /**
     * Pedido del cliente: una mensualidad elegida en "Otro concepto" quedaba
     * sin vincular a ninguna cuota (no descontaba ningún saldo) -- esa
     * sección es solo para conceptos sueltos, las mensualidades deben
     * marcarse en el checklist de cuotas.
     */
    public function test_elegir_una_mensualidad_en_otro_concepto_rechaza_el_registro(): void
    {
        $matricula = Matricula::factory()->create();
        PlanPago::factory()->create(['matricula_id' => $matricula->id])
            ->cuotas()->save(Cuota::factory()->make());
        $concepto = ConceptoPago::factory()->create(['tipo' => 'mensualidad']);

        $this->actingAs($this->administrativo());

        Volt::test('pagos.index')
            ->call('seleccionarEstudiante', $matricula->estudiante_id, $matricula->estudiante->nombreCompleto())
            ->set('conceptoSueltoId', (string) $concepto->id)
            ->set('montoConceptoSuelto', '30')
            ->set('partes.0.monto', '30')
            ->set('partes.0.metodo', 'efectivo')
            ->call('registrarPago')
            ->assertHasErrors('conceptoSueltoId');

        $this->assertDatabaseMissing('pagos', ['estudiante_id' => $matricula->estudiante_id]);
    }

    public function test_el_recibo_muestra_grupo_y_cuota_completa_cuando_el_monto_alcanza(): void
    {
        $matricula = Matricula::factory()->create();
        $planPago = PlanPago::factory()->create(['matricula_id' => $matricula->id]);
        $cuota = Cuota::factory()->create(['plan_pago_id' => $planPago->id, 'monto' => 80]);
        ConceptoPago::factory()->create(['tipo' => 'mensualidad']);

        $this->actingAs($this->administrativo());

        Volt::test('pagos.index')
            ->call('seleccionarEstudiante', $matricula->estudiante_id, $matricula->estudiante->nombreCompleto())
            ->set('cuotasSeleccionadas', [(string) $cuota->id])
            ->set('partes.0.monto', '80')
            ->set('partes.0.metodo', 'yape')
            ->call('registrarPago');

        $pago = Pago::query()->where('estudiante_id', $matricula->estudiante_id)->firstOrFail();

        $this->actingAs($this->tesoreria());
        Volt::test('pagos.index')->call('aprobar', $pago->id);

        $html = view('pdf.recibo', [
            'pago' => $pago->fresh(['estudiante.matriculas.ciclo', 'destinos.concepto', 'destinos.cuota.planPago']),
            'recibo' => $pago->recibo,
            'serie' => $pago->recibo->serie,
        ])->render();

        $this->assertStringContainsString($matricula->ciclo->nombre, $html);
        $this->assertStringContainsString('Completo', $html);
        $this->assertStringNotContainsString('Parcial', $html);
    }

    public function test_el_recibo_muestra_cuota_parcial_cuando_el_monto_no_alcanza(): void
    {
        $matricula = Matricula::factory()->create();
        $planPago = PlanPago::factory()->create(['matricula_id' => $matricula->id]);
        $cuota = Cuota::factory()->create(['plan_pago_id' => $planPago->id, 'monto' => 80]);
        ConceptoPago::factory()->create(['tipo' => 'mensualidad']);

        $this->actingAs($this->administrativo());

        Volt::test('pagos.index')
            ->call('seleccionarEstudiante', $matricula->estudiante_id, $matricula->estudiante->nombreCompleto())
            ->set('cuotasSeleccionadas', [(string) $cuota->id])
            ->set("montoPorCuotaSeleccionada.{$cuota->id}", '30')
            ->set('partes.0.monto', '30')
            ->set('partes.0.metodo', 'yape')
            ->call('registrarPago');

        $pago = Pago::query()->where('estudiante_id', $matricula->estudiante_id)->firstOrFail();

        $this->actingAs($this->tesoreria());
        Volt::test('pagos.index')->call('aprobar', $pago->id);

        $html = view('pdf.recibo', [
            'pago' => $pago->fresh(['estudiante.matriculas.ciclo', 'destinos.concepto', 'destinos.cuota.planPago']),
            'recibo' => $pago->recibo,
            'serie' => $pago->recibo->serie,
        ])->render();

        $this->assertStringContainsString('Parcial', $html);
    }

    public function test_aprobar_desde_la_cola_usa_la_serie_elegida_en_el_selector(): void
    {
        $estudiante = Estudiante::factory()->create();
        $concepto = ConceptoPago::factory()->create(['tipo' => 'certificado']);

        $this->actingAs($this->administrativo());
        Volt::test('pagos.index')
            ->call('seleccionarEstudiante', $estudiante->id, $estudiante->nombreCompleto())
            ->set('conceptoSueltoId', (string) $concepto->id)
            ->set('montoConceptoSuelto', '50')
            ->set('partes.0.monto', '50')
            ->set('partes.0.metodo', 'efectivo')
            ->call('registrarPago');

        $pago = Pago::query()->where('estudiante_id', $estudiante->id)->firstOrFail();

        $this->actingAs($this->tesoreria());
        Volt::test('pagos.index')
            ->set('serieElegida.'.$pago->id, SerieReciboEnum::COPIA->value)
            ->call('aprobar', $pago->id)
            ->assertHasNoErrors();

        $this->assertDatabaseHas('recibos', ['pago_id' => $pago->id, 'serie' => SerieReciboEnum::COPIA->value]);
    }

    public function test_la_observacion_escrita_al_registrar_queda_en_el_recibo(): void
    {
        $estudiante = Estudiante::factory()->create();
        $concepto = ConceptoPago::factory()->create(['tipo' => 'certificado']);

        $this->actingAs($this->administrativo());
        Volt::test('pagos.index')
            ->call('seleccionarEstudiante', $estudiante->id, $estudiante->nombreCompleto())
            ->set('conceptoSueltoId', (string) $concepto->id)
            ->set('montoConceptoSuelto', '50')
            ->set('observacion', 'Pago adelantado de dos meses')
            ->set('partes.0.monto', '50')
            ->set('partes.0.metodo', 'efectivo')
            ->call('registrarPago');

        $this->assertDatabaseHas('pagos', [
            'estudiante_id' => $estudiante->id,
            'observacion' => 'Pago adelantado de dos meses',
        ]);
    }
}
