<?php

declare(strict_types=1);

namespace App\Modules\Pagos\Enums;

enum MetodoPagoEnum: string
{
    case EFECTIVO = 'efectivo';
    case TRANSFERENCIA = 'transferencia';
    case YAPE = 'yape';
    case PLIN = 'plin';
    case OTRO = 'otro';

    public function label(): string
    {
        return match ($this) {
            self::EFECTIVO => 'Efectivo',
            self::TRANSFERENCIA => 'Transferencia',
            self::YAPE => 'Yape',
            self::PLIN => 'Plin',
            self::OTRO => 'Otro',
        };
    }
}
