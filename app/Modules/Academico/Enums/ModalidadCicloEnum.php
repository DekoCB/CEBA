<?php

declare(strict_types=1);

namespace App\Modules\Academico\Enums;

/**
 * La vía de estudio de un Ciclo: "seis_meses" es el esquema rotativo ya
 * existente (Grupo 1 a 4, ver TipoCicloEnum), y "anual" es SIAGE anual --
 * un ciclo independiente que no rota entre Grupos, corre el año escolar
 * completo (8 meses de clases + 2 de vacaciones) y no tiene TipoCicloEnum
 * asociado (Ciclo::tipo queda null para estos).
 */
enum ModalidadCicloEnum: string
{
    case SEIS_MESES = 'seis_meses';
    case ANUAL = 'anual';

    public function label(): string
    {
        return match ($this) {
            self::SEIS_MESES => 'SIAGE 6 meses (Grupo rotativo)',
            self::ANUAL => 'SIAGE anual',
        };
    }
}
