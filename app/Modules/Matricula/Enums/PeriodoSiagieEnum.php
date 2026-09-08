<?php

declare(strict_types=1);

namespace App\Modules\Matricula\Enums;

/**
 * El periodo SIAGIE de una matrícula: independiente del Grupo (Ciclo) al
 * que pertenece el estudiante en el sistema propio de CEBA -- SIAGIE es
 * el sistema del MINEDU y clasifica a cada alumno por su cuenta, no por
 * el Grupo rotativo (ver ModalidadCicloEnum, que es un eje totalmente
 * aparte). Se guarda por matrícula (Matricula::$periodo_siagie) porque
 * puede cambiar de una matrícula a la siguiente del mismo estudiante.
 */
enum PeriodoSiagieEnum: string
{
    case PRIMERO = '1';
    case SEGUNDO = '2';
    case ANUAL = 'anual';

    public function label(): string
    {
        return match ($this) {
            self::PRIMERO => '1.° periodo',
            self::SEGUNDO => '2.° periodo',
            self::ANUAL => 'Anual',
        };
    }
}
