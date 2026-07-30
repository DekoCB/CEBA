<?php

declare(strict_types=1);

namespace App\Modules\Academico\Enums;

/**
 * Los horarios de CEBA se configuran en tres franjas fijas, nunca por
 * día suelto: domingos, lunes-y-miércoles, o martes-y-jueves.
 */
enum DiaSemanaEnum: string
{
    case DOMINGO = 'domingo';
    case LUN_MIE = 'lun_mie';
    case MAR_JUE = 'mar_jue';

    public function label(): string
    {
        return match ($this) {
            self::DOMINGO => 'Domingos',
            self::LUN_MIE => 'Lunes y Miércoles',
            self::MAR_JUE => 'Martes y Jueves',
        };
    }
}
