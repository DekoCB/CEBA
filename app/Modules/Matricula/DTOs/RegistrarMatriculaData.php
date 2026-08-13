<?php

declare(strict_types=1);

namespace App\Modules\Matricula\DTOs;

final readonly class RegistrarMatriculaData
{
    public function __construct(
        public int $cicloId,
        public int $gradoId,
        public ?int $horarioId,
        public ?string $observaciones,
        public ?int $registradoPor,
    ) {}
}
