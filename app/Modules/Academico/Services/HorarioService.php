<?php

declare(strict_types=1);

namespace App\Modules\Academico\Services;

use App\Modules\Academico\Enums\DiaSemanaEnum;
use App\Modules\Academico\Models\Horario;
use App\Modules\Academico\Repositories\Contracts\HorarioRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

class HorarioService
{
    public function __construct(
        private readonly HorarioRepositoryInterface $horarios,
    ) {}

    /**
     * @return Collection<int, Horario>
     */
    public function delCiclo(int $cicloId): Collection
    {
        return $this->horarios->delCiclo($cicloId);
    }

    /**
     * @param  array{curso_id: int, docente_id: int, aula_id: int, ciclo_id: int, grado_id: int, dia_semana: DiaSemanaEnum, hora_inicio: string, hora_fin: string}  $datos
     */
    public function crear(array $datos): Horario
    {
        $this->validarSinTraslape($datos);

        return $this->horarios->create($datos);
    }

    /**
     * @param  array{curso_id: int, docente_id: int, aula_id: int, ciclo_id: int, grado_id: int, dia_semana: DiaSemanaEnum, hora_inicio: string, hora_fin: string}  $datos
     */
    public function actualizar(Horario $horario, array $datos): Horario
    {
        $this->validarSinTraslape($datos, $horario->id);

        return $this->horarios->update($horario, $datos);
    }

    /**
     * @param  array{aula_id: int, docente_id: int, ciclo_id: int, dia_semana: DiaSemanaEnum, hora_inicio: string, hora_fin: string}  $datos
     */
    private function validarSinTraslape(array $datos, ?int $exceptoId = null): void
    {
        if ($datos['hora_fin'] <= $datos['hora_inicio']) {
            throw ValidationException::withMessages([
                'hora_fin' => 'La hora de fin debe ser posterior a la hora de inicio.',
            ]);
        }

        $enAula = $this->horarios->enAulaQueSolapan(
            $datos['aula_id'],
            $datos['ciclo_id'],
            $datos['dia_semana'],
            $datos['hora_inicio'],
            $datos['hora_fin'],
            $exceptoId,
        );

        if ($enAula->isNotEmpty()) {
            $choque = $enAula->first();
            throw ValidationException::withMessages([
                'aula_id' => "El aula ya está ocupada ese horario por el curso «{$choque->curso->nombre}» ({$choque->hora_inicio}–{$choque->hora_fin}).",
            ]);
        }

        $delDocente = $this->horarios->delDocenteQueSolapan(
            $datos['docente_id'],
            $datos['ciclo_id'],
            $datos['dia_semana'],
            $datos['hora_inicio'],
            $datos['hora_fin'],
            $exceptoId,
        );

        if ($delDocente->isNotEmpty()) {
            $choque = $delDocente->first();
            throw ValidationException::withMessages([
                'docente_id' => "El docente ya tiene una clase asignada ese horario: «{$choque->curso->nombre}» ({$choque->hora_inicio}–{$choque->hora_fin}).",
            ]);
        }
    }
}
