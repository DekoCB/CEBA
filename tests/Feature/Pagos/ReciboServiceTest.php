<?php

namespace Tests\Feature\Pagos;

use App\Modules\Pagos\Models\Pago;
use App\Modules\Pagos\Models\Recibo;
use App\Modules\Pagos\Services\ReciboService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReciboServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): ReciboService
    {
        return $this->app->make(ReciboService::class);
    }

    public function test_emitir_asigna_un_correlativo_simple_sin_prefijo_de_ano(): void
    {
        $pago = Pago::factory()->create();

        $recibo = $this->service()->emitir($pago);

        $this->assertSame('000001', $recibo->numero_recibo);
    }

    public function test_el_correlativo_no_se_reinicia_entre_recibos(): void
    {
        $primero = $this->service()->emitir(Pago::factory()->create());
        $segundo = $this->service()->emitir(Pago::factory()->create());

        $this->assertSame('000001', $primero->numero_recibo);
        $this->assertSame('000002', $segundo->numero_recibo);
    }

    public function test_emitir_genera_un_pdf_con_las_dos_series(): void
    {
        $pago = Pago::factory()->create();

        $recibo = $this->service()->emitir($pago);

        $this->assertNotNull($recibo->getFirstMedia('pdf'));
    }

    public function test_el_html_del_recibo_incluye_ambas_series(): void
    {
        $pago = Pago::factory()->create();
        $recibo = $this->service()->emitir($pago);

        $html = view('pdf.recibo', ['pago' => $pago, 'recibo' => $recibo])->render();

        $this->assertStringContainsString('Recibo de pago', $html);
        $this->assertStringContainsString('001-000001', $html);
        $this->assertStringContainsString('002-000001', $html);
    }

    public function test_regenerar_reasigna_correlativos_en_orden_y_no_choca_con_el_unique(): void
    {
        $recibos = Recibo::factory()->count(3)->sequence(
            ['numero_recibo' => 'R-2025-000003'],
            ['numero_recibo' => 'R-2025-000001'],
            ['numero_recibo' => 'R-2025-000002'],
        )->create();

        $this->artisan('recibos:regenerar')->assertSuccessful();

        $ordenados = $recibos->fresh();

        $this->assertSame('000001', $ordenados[0]->numero_recibo);
        $this->assertSame('000002', $ordenados[1]->numero_recibo);
        $this->assertSame('000003', $ordenados[2]->numero_recibo);

        foreach ($ordenados as $recibo) {
            $this->assertNotNull($recibo->getFirstMedia('pdf'));
        }
    }
}
