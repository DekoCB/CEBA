<?php

declare(strict_types=1);

namespace App\Modules\Academico\Repositories\Contracts;

use App\Modules\Academico\Enums\DiaSemanaEnum;
use App\Modules\Academico\Models\Horario;
use App\Modules\Academico\Models\HorarioDia;
use App\Shared\Repositories\RepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

/**
 * @extends RepositoryInterface<Horario>
 */
interface HorarioRepositoryInterface extends RepositoryInterface
{
    /**
     * Días (de otros horarios) que ya ocupan la misma aula, mismo día,
     * mismo ciclo y mismo grado, con un rango horario que se cruza con
     * [$horaInicio, $horaFin). El choque solo cuenta dentro del mismo
     * grado -- un docente puede combinar varios grados en la misma
     * aula/hora a propósito (pedido del cliente: "no debe restringirse de
     * grado"). $exceptoHorarioIds excluye esos horarios de la búsqueda --
     * se usa al editar, para no chocar contra una misma versión anterior
     * de sí mismo.
     *
     * @param  list<int>  $exceptoHorarioIds
     * @return Collection<int, HorarioDia>
     */
    public function enAulaQueSolapan(int $aulaId, int $cicloId, int $gradoId, DiaSemanaEnum $dia, string $horaInicio, string $horaFin, array $exceptoHorarioIds = []): Collection;

    /**
     * Igual que {@see enAulaQueSolapan} pero para el mismo docente: dentro
     * de un mismo grado, un profesor no puede dictar dos cursos a la misma
     * hora aunque sea en aulas distintas. Entre grados distintos no
     * choca -- es el mismo docente combinando grados a propósito.
     *
     * @param  list<int>  $exceptoHorarioIds
     * @return Collection<int, HorarioDia>
     */
    public function delDocenteQueSolapan(int $docenteId, int $cicloId, int $gradoId, DiaSemanaEnum $dia, string $horaInicio, string $horaFin, array $exceptoHorarioIds = []): Collection;

    /**
     * @return Collection<int, Horario>
     */
    public function delCiclo(int $cicloId): Collection;
}
