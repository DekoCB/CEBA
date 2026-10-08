<?php

declare(strict_types=1);

namespace App\Modules\AulaVirtual\Repositories\Contracts;

use App\Modules\AulaVirtual\Models\CursoVirtual;
use App\Modules\Matricula\Models\Estudiante;
use App\Shared\Repositories\RepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends RepositoryInterface<CursoVirtual>
 */
interface CursoVirtualRepositoryInterface extends RepositoryInterface
{
    /**
     * Cursos virtuales de los horarios que dicta este docente.
     *
     * @return Collection<int, CursoVirtual>
     */
    public function delDocente(int $docenteId): Collection;

    /**
     * Cursos virtuales visibles para un estudiante: los que corresponden al
     * grado y ciclo de alguna de sus matrículas aprobadas.
     *
     * @return Collection<int, CursoVirtual>
     */
    public function delEstudiante(Estudiante $estudiante): Collection;

    /**
     * El curso virtual ya activado para este curso+grado+ciclo+docente, si
     * existe -- es la clave de deduplicación: varios Horario que comparten
     * estos 4 datos (ej. distintas franjas del mismo curso) reutilizan el
     * mismo curso virtual en vez de crear uno nuevo cada uno.
     */
    public function paraGrupo(int $cursoId, int $gradoId, int $cicloId, int $docenteId): ?CursoVirtual;
}
