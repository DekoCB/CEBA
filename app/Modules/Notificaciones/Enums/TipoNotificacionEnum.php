<?php

declare(strict_types=1);

namespace App\Modules\Notificaciones\Enums;

enum TipoNotificacionEnum: string
{
    case TAREA_CALIFICADA = 'tarea_calificada';
    case EVALUACION_PUBLICADA = 'evaluacion_publicada';

    public function label(): string
    {
        return match ($this) {
            self::TAREA_CALIFICADA => 'Tarea calificada',
            self::EVALUACION_PUBLICADA => 'Evaluación publicada',
        };
    }

    public function icono(): string
    {
        return match ($this) {
            self::TAREA_CALIFICADA => 'clipboard-document-check',
            self::EVALUACION_PUBLICADA => 'pencil-square',
        };
    }
}
