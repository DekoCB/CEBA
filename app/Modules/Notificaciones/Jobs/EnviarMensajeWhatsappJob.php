<?php

declare(strict_types=1);

namespace App\Modules\Notificaciones\Jobs;

use App\Modules\Notificaciones\Contracts\WhatsAppGateway;
use App\Modules\Notificaciones\Enums\EstadoCampaniaEnum;
use App\Modules\Notificaciones\Enums\EstadoMensajeWhatsappEnum;
use App\Modules\Notificaciones\Models\MensajeWhatsapp;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class EnviarMensajeWhatsappJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private readonly MensajeWhatsapp $mensaje,
    ) {}

    public function handle(WhatsAppGateway $gateway): void
    {
        $resultado = $gateway->enviar($this->mensaje->telefono, $this->mensaje->contenido);

        $this->mensaje->update([
            'estado' => $resultado['exitoso'] ? EstadoMensajeWhatsappEnum::ENVIADO : EstadoMensajeWhatsappEnum::FALLIDO,
            'external_id' => $resultado['external_id'],
            'error' => $resultado['error'],
            'enviado_en' => $resultado['exitoso'] ? now() : null,
        ]);

        $campania = $this->mensaje->campania;

        if (! $campania) {
            return;
        }

        $campania->increment($resultado['exitoso'] ? 'enviados' : 'fallidos');
        $campania->refresh();

        if ($campania->enviados + $campania->fallidos >= $campania->total_destinatarios) {
            $campania->update(['estado' => EstadoCampaniaEnum::COMPLETADA]);
        }
    }
}
