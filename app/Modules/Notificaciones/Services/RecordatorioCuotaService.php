<?php

declare(strict_types=1);

namespace App\Modules\Notificaciones\Services;

use App\Modules\Notificaciones\Enums\TipoMensajeWhatsappEnum;
use App\Modules\Notificaciones\Jobs\EnviarMensajeWhatsappJob;
use App\Modules\Notificaciones\Models\MensajeWhatsapp;
use App\Modules\Pagos\Models\Cuota;

/**
 * Recordatorios automáticos de cuotas por vencer, pensado para correr a
 * diario desde el Scheduler (ver routes/console.php).
 */
class RecordatorioCuotaService
{
    public function enviarRecordatorios(int $diasAntes = 3): int
    {
        // Cuota (módulo Pagos) no conoce a Notificaciones, así que el filtro
        // "ya se le recordó" se resuelve desde este lado con whereNotIn, en
        // vez de una relación whereDoesntHave en el modelo Cuota.
        $cuotasYaRecordadas = MensajeWhatsapp::query()
            ->where('tipo', TipoMensajeWhatsappEnum::RECORDATORIO)
            ->whereNotNull('cuota_id')
            ->pluck('cuota_id');

        $cuotas = Cuota::query()
            ->where('estado', 'pendiente')
            ->whereBetween('fecha_vencimiento', [now()->startOfDay(), now()->addDays($diasAntes)->endOfDay()])
            ->whereNotIn('id', $cuotasYaRecordadas)
            ->with('planPago.matricula.estudiante')
            ->get();

        $enviados = 0;

        foreach ($cuotas as $cuota) {
            $estudiante = $cuota->planPago->matricula->estudiante;
            $telefono = $estudiante->telefonoDeContacto();

            if (! $telefono) {
                continue;
            }

            $contenido = sprintf(
                'Hola, recordamos que la cuota %d de %s vence el %s por S/ %s. Evita el bloqueo de la libreta de notas regularizando a tiempo.',
                $cuota->numero,
                $estudiante->nombreCompleto(),
                $cuota->fecha_vencimiento->format('d/m/Y'),
                number_format((float) $cuota->monto, 2),
            );

            /** @var MensajeWhatsapp $mensaje */
            $mensaje = MensajeWhatsapp::query()->create([
                'estudiante_id' => $estudiante->id,
                'cuota_id' => $cuota->id,
                'telefono' => $telefono,
                'contenido' => $contenido,
                'tipo' => TipoMensajeWhatsappEnum::RECORDATORIO,
            ]);

            EnviarMensajeWhatsappJob::dispatch($mensaje);
            $enviados++;
        }

        return $enviados;
    }
}
