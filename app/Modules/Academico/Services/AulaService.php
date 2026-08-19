<?php

declare(strict_types=1);

namespace App\Modules\Academico\Services;

use App\Modules\Academico\Enums\FranjaHorarioEnum;
use App\Modules\Academico\Models\Aula;
use App\Modules\Academico\Models\Horario;
use App\Modules\Matricula\Models\Matricula;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;

class AulaService
{
    /**
     * @return Collection<int, Aula>
     */
    public function todas(): Collection
    {
        return Aula::query()->orderBy('nombre')->get();
    }

    /**
     * @param  array{nombre: string, capacidad: int, ubicacion: ?string}  $datos
     */
    public function crear(array $datos): Aula
    {
        return Aula::query()->create($datos);
    }

    /**
     * @param  array{nombre: string, capacidad: int, ubicacion: ?string, activa: bool}  $datos
     */
    public function actualizar(Aula $aula, array $datos): Aula
    {
        $aula->update($datos);

        return $aula;
    }

    /**
     * Ocupación de cada aula activa en un ciclo, agrupada por turno
     * (franja institucional): cuántos estudiantes le corresponden a cada
     * horario que usa esa aula, frente a su capacidad máxima. Un aula sin
     * horarios en el ciclo aparece igual, con "porFranja" vacío, para que
     * el personal también vea qué aulas están libres.
     *
     * @return SupportCollection<int, array{aula: Aula, porFranja: SupportCollection<string, array{label: string, horarios: SupportCollection<int, array{horario: Horario, estudiantes: int}>, totalEstudiantes: int}>}>
     */
    public function ocupacion(int $cicloId): SupportCollection
    {
        $aulas = Aula::query()->where('activa', true)->orderBy('nombre')->get();

        $horarios = Horario::query()
            ->where('ciclo_id', $cicloId)
            ->with(['curso', 'grado', 'dias'])
            ->get();

        return $aulas->map(function (Aula $aula) use ($horarios) {
            $deEstaAula = $horarios->where('aula_id', $aula->id);

            $porFranja = collect(FranjaHorarioEnum::cases())
                ->mapWithKeys(function (FranjaHorarioEnum $franja) use ($deEstaAula) {
                    $enFranja = $deEstaAula->filter(fn (Horario $horario) => $horario->franja() === $franja)
                        ->map(fn (Horario $horario) => [
                            'horario' => $horario,
                            'estudiantes' => $this->contarEstudiantesDelHorario($horario),
                        ])
                        ->values();

                    return [$franja->value => [
                        'label' => $franja->label(),
                        'horarios' => $enFranja,
                        'totalEstudiantes' => (int) $enFranja->sum('estudiantes'),
                    ]];
                })
                ->filter(fn (array $grupo) => $grupo['horarios']->isNotEmpty());

            return ['aula' => $aula, 'porFranja' => $porFranja];
        });
    }

    private function contarEstudiantesDelHorario(Horario $horario): int
    {
        return Matricula::query()->delHorario($horario)->count();
    }
}
