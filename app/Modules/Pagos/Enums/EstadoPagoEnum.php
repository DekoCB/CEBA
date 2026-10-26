<?php

declare(strict_types=1);

namespace App\Modules\Pagos\Enums;

enum EstadoPagoEnum: string
{
    case PENDIENTE = 'pendiente';
    case APROBADO = 'aprobado';
    case RECHAZADO = 'rechazado';
    case ANULADO = 'anulado';

    public function label(): string
    {
        return match ($this) {
            self::PENDIENTE => 'Pendiente',
            self::APROBADO => 'Aprobado',
            self::RECHAZADO => 'Rechazado',
            self::ANULADO => 'Anulado',
        };
    }
}
