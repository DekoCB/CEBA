<?php

declare(strict_types=1);

namespace App\Modules\Pagos\Enums;

/**
 * CEBA solo ofrece planes de pago en 1, 6 u 8 cuotas.
 */
enum NumeroCuotasEnum: int
{
    case UNA = 1;
    case SEIS = 6;
    case OCHO = 8;

    public function label(): string
    {
        return match ($this) {
            self::UNA => 'Pago único',
            self::SEIS => '6 cuotas',
            self::OCHO => '8 cuotas',
        };
    }
}
