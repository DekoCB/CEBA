<?php

declare(strict_types=1);

namespace App\Modules\Academico\Services;

use App\Modules\Academico\Models\Curso;
use App\Modules\Academico\Repositories\Contracts\CursoRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class CursoService
{
    public function __construct(
        private readonly CursoRepositoryInterface $cursos,
    ) {}

    public function listar(int $perPage = 15): LengthAwarePaginator
    {
        return $this->cursos->paginate($perPage);
    }

    /**
     * @param  array{nombre: string, codigo: string, grado_id: int, horas: int}  $datos
     */
    public function crear(array $datos): Curso
    {
        return $this->cursos->create($datos);
    }

    /**
     * @param  array{nombre: string, codigo: string, grado_id: int, horas: int, activo: bool}  $datos
     */
    public function actualizar(Curso $curso, array $datos): Curso
    {
        return $this->cursos->update($curso, $datos);
    }

    public function codigoDisponible(string $codigo, ?int $exceptoId = null): bool
    {
        return ! $this->cursos->existeCodigo($codigo, $exceptoId);
    }
}
