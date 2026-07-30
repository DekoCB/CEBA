<?php

declare(strict_types=1);

namespace App\Modules\AulaVirtual\Repositories\Eloquent;

use App\Modules\AulaVirtual\Models\CursoVirtual;
use App\Modules\AulaVirtual\Repositories\Contracts\CursoVirtualRepositoryInterface;
use App\Modules\Matricula\Models\Estudiante;
use App\Shared\Repositories\BaseRepository;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends BaseRepository<CursoVirtual>
 */
class EloquentCursoVirtualRepository extends BaseRepository implements CursoVirtualRepositoryInterface
{
    /**
     * @return Builder<CursoVirtual>
     */
    protected function query(): Builder
    {
        return CursoVirtual::query()->with(['horario.curso', 'horario.grado', 'horario.ciclo', 'horario.docente']);
    }

    public function delDocente(int $docenteId): Collection
    {
        return $this->query()
            ->whereHas('horario', fn ($query) => $query->where('docente_id', $docenteId))
            ->get();
    }

    public function delEstudiante(Estudiante $estudiante): Collection
    {
        $gradosCiclos = $estudiante->matriculas()
            ->where('estado', 'aprobada')
            ->get(['grado_id', 'ciclo_id']);

        if ($gradosCiclos->isEmpty()) {
            return new Collection;
        }

        return $this->query()
            ->where('activo', true)
            ->whereHas('horario', function ($query) use ($gradosCiclos) {
                $query->where(function ($query) use ($gradosCiclos) {
                    foreach ($gradosCiclos as $matricula) {
                        $query->orWhere(function ($query) use ($matricula) {
                            $query->where('grado_id', $matricula->grado_id)
                                ->where('ciclo_id', $matricula->ciclo_id);
                        });
                    }
                });
            })
            ->get();
    }

    public function paraHorario(int $horarioId): ?CursoVirtual
    {
        return $this->query()->where('horario_id', $horarioId)->first();
    }
}
