<?php

declare(strict_types=1);

namespace App\Modules\Evaluaciones\Services;

use App\Modules\Academico\Models\Horario;
use App\Modules\Evaluaciones\Enums\EstadoEvaluacionEnum;
use App\Modules\Evaluaciones\Models\Calificacion;
use App\Modules\Evaluaciones\Models\Evaluacion;
use App\Modules\Matricula\Models\Estudiante;
use App\Modules\Matricula\Models\Matricula;
use Illuminate\Database\Eloquent\Collection;

class EvaluacionService
{
    /**
     * Estudiantes matriculados (aprobados) en el grado y ciclo de un horario.
     *
     * @return Collection<int, Estudiante>
     */
    public function estudiantesDelHorario(Horario $horario): Collection
    {
        return Estudiante::query()
            ->whereIn('id', Matricula::query()
                ->where('grado_id', $horario->grado_id)
                ->where('ciclo_id', $horario->ciclo_id)
                ->where('estado', 'aprobada')
                ->pluck('estudiante_id'))
            ->orderBy('apellidos')
            ->orderBy('nombres')
            ->get();
    }

    /**
     * @return Collection<int, Horario>
     */
    public function horariosDelDocente(int $docenteId): Collection
    {
        return Horario::query()
            ->where('docente_id', $docenteId)
            ->with(['curso', 'grado', 'ciclo'])
            ->get();
    }

    /**
     * @return Collection<int, Horario>
     */
    public function horariosDelEstudiante(Estudiante $estudiante): Collection
    {
        $matriculas = $estudiante->matriculas()
            ->where('estado', 'aprobada')
            ->get(['grado_id', 'ciclo_id']);

        if ($matriculas->isEmpty()) {
            return new Collection;
        }

        return Horario::query()
            ->where(function ($query) use ($matriculas) {
                foreach ($matriculas as $matricula) {
                    $query->orWhere(function ($query) use ($matricula) {
                        $query->where('grado_id', $matricula->grado_id)->where('ciclo_id', $matricula->ciclo_id);
                    });
                }
            })
            ->with(['curso', 'grado', 'ciclo', 'docente'])
            ->get();
    }

    /**
     * @return Collection<int, Horario>
     */
    public function todos(): Collection
    {
        return Horario::query()->with(['curso', 'grado', 'ciclo', 'docente'])->get();
    }

    public function crear(Horario $horario, string $nombre, string $fecha): Evaluacion
    {
        return Evaluacion::query()->create([
            'horario_id' => $horario->id,
            'nombre' => $nombre,
            'fecha' => $fecha,
            'estado' => EstadoEvaluacionEnum::BORRADOR,
        ]);
    }

    /**
     * @return Collection<int, Evaluacion>
     */
    public function evaluacionesDelHorario(Horario $horario): Collection
    {
        return Evaluacion::query()
            ->where('horario_id', $horario->id)
            ->orderByDesc('fecha')
            ->get();
    }

    /**
     * @return Collection<int, Evaluacion>
     */
    public function evaluacionesPublicadasDelHorario(Horario $horario): Collection
    {
        return Evaluacion::query()
            ->where('horario_id', $horario->id)
            ->where('estado', EstadoEvaluacionEnum::PUBLICADA)
            ->orderByDesc('fecha')
            ->get();
    }

    /**
     * @return Collection<int, Calificacion>
     */
    public function calificacionesDe(Evaluacion $evaluacion): Collection
    {
        return Calificacion::query()
            ->where('evaluacion_id', $evaluacion->id)
            ->with('estudiante')
            ->get()
            ->keyBy('estudiante_id');
    }

    public function calificar(Evaluacion $evaluacion, Estudiante $estudiante, float $nota, ?string $observaciones, ?int $registradoPor): Calificacion
    {
        return Calificacion::query()->updateOrCreate(
            ['evaluacion_id' => $evaluacion->id, 'estudiante_id' => $estudiante->id],
            ['nota_numerica' => $nota, 'observaciones' => $observaciones, 'registrado_por' => $registradoPor],
        );
    }

    public function publicar(Evaluacion $evaluacion): void
    {
        $evaluacion->update(['estado' => EstadoEvaluacionEnum::PUBLICADA]);
    }

    /**
     * Calificaciones del estudiante en evaluaciones ya publicadas de un horario.
     *
     * @return Collection<int, Calificacion>
     */
    public function misCalificaciones(Estudiante $estudiante, Horario $horario): Collection
    {
        return Calificacion::query()
            ->where('estudiante_id', $estudiante->id)
            ->whereHas('evaluacion', function ($query) use ($horario) {
                $query->where('horario_id', $horario->id)->where('estado', EstadoEvaluacionEnum::PUBLICADA);
            })
            ->with('evaluacion')
            ->get();
    }

    /**
     * Promedio simple de las calificaciones publicadas del estudiante en un
     * horario. La ponderación por tipo de evaluación queda fuera de
     * alcance: hoy solo existe un tipo de evaluación (mensual).
     */
    public function promedioDelEstudiante(Estudiante $estudiante, Horario $horario): ?float
    {
        $notas = $this->misCalificaciones($estudiante, $horario)->pluck('nota_numerica');

        if ($notas->isEmpty()) {
            return null;
        }

        return round((float) $notas->avg(), 2);
    }
}
