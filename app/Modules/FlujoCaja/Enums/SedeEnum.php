<?php

declare(strict_types=1);

namespace App\Modules\FlujoCaja\Enums;

/**
 * Las dos sedes del colegio, cada una con su propio flujo de caja -- mismo
 * vocabulario que ya usa SerieReciboEnum::centroCostoIniciales() para los
 * recibos (001/PB, 002/CA), pero independiente de esa numeración: un
 * Egreso no tiene recibo.
 */
enum SedeEnum: string
{
    case PB = 'PB';
    case CA = 'CA';

    public function label(): string
    {
        return match ($this) {
            self::PB => 'PB (Peruano Británico)',
            self::CA => 'CA (Ciro Alegría)',
        };
    }
}
