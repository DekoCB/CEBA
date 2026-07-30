<?php

declare(strict_types=1);

namespace App\Modules\Academico\Repositories\Eloquent;

use App\Modules\Academico\Enums\DiaSemanaEnum;
use App\Modules\Academico\Models\Horario;
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
        return Horario::query()->with(['curso', 'docente', 'aula', 'ciclo', 'grado']);
    }

    public function enAulaQueSolapan(int $aulaId, int $cicloId, DiaSemanaEnum $dia, string $horaInicio, string $horaFin, ?int $exceptoId = null): Collection
    {
        return $this->consultaSolapados($horaInicio, $horaFin, $exceptoId)
            ->where('aula_id', $aulaId)
            ->where('ciclo_id', $cicloId)
            ->where('dia_semana', $dia->value)
            ->get();
    }

    public function delDocenteQueSolapan(int $docenteId, int $cicloId, DiaSemanaEnum $dia, string $horaInicio, string $horaFin, ?int $exceptoId = null): Collection
    {
        return $this->consultaSolapados($horaInicio, $horaFin, $exceptoId)
            ->where('docente_id', $docenteId)
            ->where('ciclo_id', $cicloId)
            ->where('dia_semana', $dia->value)
            ->get();
    }

    public function delCiclo(int $cicloId): Collection
    {
        return $this->query()->where('ciclo_id', $cicloId)->get();
    }

    /**
     * @return Builder<Horario>
     */
    private function consultaSolapados(string $horaInicio, string $horaFin, ?int $exceptoId): Builder
    {
        return $this->query()
            ->where('hora_inicio', '<', $horaFin)
            ->where('hora_fin', '>', $horaInicio)
            ->when($exceptoId, fn ($query) => $query->whereKeyNot($exceptoId));
    }
}
