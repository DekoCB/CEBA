<?php

declare(strict_types=1);

namespace App\Modules\Academico\Enums;

enum TipoPublicoEnum: string
{
    case MENOR = 'menor';
    case MAYOR = 'mayor';

    public function label(): string
    {
        return match ($this) {
            self::MENOR => 'Menor de edad',
            self::MAYOR => 'Mayor de edad',
        };
    }
}
