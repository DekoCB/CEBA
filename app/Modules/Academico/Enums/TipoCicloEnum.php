<?php

declare(strict_types=1);

namespace App\Modules\Academico\Enums;

/**
 * Mayores de edad cursan 2 ciclos por año (enero-junio, julio-diciembre).
 * Menores cursan ciclos de 6, 8 o 12 meses, con fecha de inicio flexible.
 */
enum TipoCicloEnum: string
{
    case ENE_JUN = 'ene_jun';
    case JUL_DIC = 'jul_dic';
    case SEIS_MESES = 'seis_meses';
    case OCHO_MESES = 'ocho_meses';
    case ANUAL = 'anual';

    public function label(): string
    {
        return match ($this) {
            self::ENE_JUN => 'Enero - Junio',
            self::JUL_DIC => 'Julio - Diciembre',
            self::SEIS_MESES => '6 meses',
            self::OCHO_MESES => '8 meses',
            self::ANUAL => 'Anual (12 meses)',
        };
    }

    public function publico(): TipoPublicoEnum
    {
        return match ($this) {
            self::ENE_JUN, self::JUL_DIC => TipoPublicoEnum::MAYOR,
            self::SEIS_MESES, self::OCHO_MESES, self::ANUAL => TipoPublicoEnum::MENOR,
        };
    }

    public function duracionEnMeses(): int
    {
        return match ($this) {
            self::ENE_JUN, self::JUL_DIC => 6,
            self::SEIS_MESES => 6,
            self::OCHO_MESES => 8,
            self::ANUAL => 12,
        };
    }

    /**
     * Para los ciclos de mayores, el mes calendario en que debe iniciar
     * (enero o julio). Los ciclos de menores no tienen mes fijo.
     */
    public function mesInicioFijo(): ?int
    {
        return match ($this) {
            self::ENE_JUN => 1,
            self::JUL_DIC => 7,
            default => null,
        };
    }
}
