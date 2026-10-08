<?php

declare(strict_types=1);

namespace App\Modules\Academico\Repositories\Eloquent;

use App\Modules\Academico\Enums\DiaSemanaEnum;
use App\Modules\Academico\Models\Horario;
use App\Modules\Academico\Models\HorarioDia;
use App\Modules\Academico\Repositories\Contracts\HorarioRepositoryInterface;
use App\Shared\Repositories\BaseRepository;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends BaseRepository<Horario>
 */
class EloquentHorarioRepository extends BaseRepository implements HorarioRepositoryInterface
{
    /**
     * @return Builder<Horario>
     */
    protected function query(): Builder
    {
        return Horario::query()->with(['curso', 'docente', 'aula', 'ciclo', 'grado', 'dias']);
    }

    public function enAulaQueSolapan(int $aulaId, int $cicloId, int $gradoId, DiaSemanaEnum $dia, string $horaInicio, string $horaFin, array $exceptoHorarioIds = []): Collection
    {
        return $this->consultaDiasSolapados($dia, $horaInicio, $horaFin, $exceptoHorarioIds)
            ->whereHas('horario', fn ($query) => $query->where('aula_id', $aulaId)->where('ciclo_id', $cicloId)->where('grado_id', $gradoId))
            ->get();
    }

    public function delDocenteQueSolapan(int $docenteId, int $cicloId, int $gradoId, DiaSemanaEnum $dia, string $horaInicio, string $horaFin, array $exceptoHorarioIds = []): Collection
    {
        return $this->consultaDiasSolapados($dia, $horaInicio, $horaFin, $exceptoHorarioIds)
            ->whereHas('horario', fn ($query) => $query->where('docente_id', $docenteId)->where('ciclo_id', $cicloId)->where('grado_id', $gradoId))
            ->get();
    }

    public function delCiclo(int $cicloId): Collection
    {
        return $this->query()->where('ciclo_id', $cicloId)->get();
    }

    /**
     * @param  list<int>  $exceptoHorarioIds
     * @return Builder<HorarioDia>
     */
    private function consultaDiasSolapados(DiaSemanaEnum $dia, string $horaInicio, string $horaFin, array $exceptoHorarioIds): Builder
    {
        return HorarioDia::query()
            ->with('horario.curso')
            ->where('dia_semana', $dia->value)
            ->where('hora_inicio', '<', $horaFin)
            ->where('hora_fin', '>', $horaInicio)
            ->when($exceptoHorarioIds !== [], fn ($query) => $query->whereNotIn('horario_id', $exceptoHorarioIds));
    }
}
