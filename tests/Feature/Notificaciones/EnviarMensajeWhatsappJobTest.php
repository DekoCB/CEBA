<?php

namespace Tests\Feature\Notificaciones;

use App\Modules\Notificaciones\Contracts\WhatsAppGateway;
use App\Modules\Notificaciones\Enums\EstadoCampaniaEnum;
use App\Modules\Notificaciones\Enums\EstadoMensajeWhatsappEnum;
use App\Modules\Notificaciones\Jobs\EnviarMensajeWhatsappJob;
use App\Modules\Notificaciones\Models\CampaniaWhatsapp;
use App\Modules\Notificaciones\Models\MensajeWhatsapp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EnviarMensajeWhatsappJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_job_marca_el_mensaje_como_enviado_con_el_driver_null(): void
    {
        $mensaje = MensajeWhatsapp::factory()->create();

        (new EnviarMensajeWhatsappJob($mensaje))->handle(app(WhatsAppGateway::class));

        $mensaje->refresh();
        $this->assertSame(EstadoMensajeWhatsappEnum::ENVIADO, $mensaje->estado);
        $this->assertNotNull($mensaje->external_id);
        $this->assertNotNull($mensaje->enviado_en);
    }

    public function test_el_job_actualiza_los_contadores_de_la_campania_y_la_completa(): void
    {
        $campania = CampaniaWhatsapp::factory()->create([
            'total_destinatarios' => 1,
            'estado' => EstadoCampaniaEnum::ENVIANDO,
        ]);
        $mensaje = MensajeWhatsapp::factory()->create(['campania_id' => $campania->id]);

        (new EnviarMensajeWhatsappJob($mensaje))->handle(app(WhatsAppGateway::class));

        $campania->refresh();
        $this->assertSame(1, $campania->enviados);
        $this->assertSame(EstadoCampaniaEnum::COMPLETADA, $campania->estado);
    }
}
