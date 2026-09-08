<?php

declare(strict_types=1);

namespace App\Modules\Matricula\DTOs;

use App\Modules\Matricula\Enums\PeriodoSiagieEnum;

final readonly class RegistrarMatriculaData
{
    public function __construct(
        public int $cicloId,
        public int $gradoId,
        public ?string $observaciones,
        public ?int $registradoPor,
        public ?PeriodoSiagieEnum $periodoSiagie = null,
    ) {}
}
