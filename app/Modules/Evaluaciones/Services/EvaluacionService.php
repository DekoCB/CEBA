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
     * Si el grado tiene varias secciones (Grupo A/B) y la matrícula ya tiene
     * un horario_id asignado, solo cuentan los de esa sección específica;
     * las matrículas sin horario_id (registradas antes de que existiera
     * este campo, o de un grado con una sola sección) siguen contando en
     * cualquier horario de su grado+ciclo, como antes de este cambio.
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
                ->where(function ($query) use ($horario) {
                    $query->where('horario_id', $horario->id)->orWhereNull('horario_id');
                })
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
            ->get(['grado_id', 'ciclo_id', 'horario_id']);

        if ($matriculas->isEmpty()) {
            return new Collection;
        }

        return Horario::query()
            ->where(function ($query) use ($matriculas) {
                foreach ($matriculas as $matricula) {
                    $query->orWhere(function ($query) use ($matricula) {
                        $query->where('grado_id', $matricula->grado_id)->where('ciclo_id', $matricula->ciclo_id);

                        // Si la matrícula ya fija una sección específica, solo
                        // ese horario cuenta; si no (grado de una sola sección
                        // o matrícula anterior a este campo), se mantiene el
                        // comportamiento previo de traer todos los del grado.
                        if ($matricula->horario_id !== null) {
                            $query->where('id', $matricula->horario_id);
                        }
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

    public function crear(Horario $horario, string $nombre, string $fecha, ?string $enlaceExterno = null, ?string $disponibleHasta = null): Evaluacion
    {
        return Evaluacion::query()->create([
            'horario_id' => $horario->id,
            'nombre' => $nombre,
            'fecha' => $fecha,
            'enlace_externo' => $enlaceExterno,
            'disponible_hasta' => $disponibleHasta,
            'estado' => EstadoEvaluacionEnum::BORRADOR,
        ]);
    }

    public function actualizarEnlace(Evaluacion $evaluacion, ?string $enlaceExterno, ?string $disponibleHasta = null): Evaluacion
    {
        $evaluacion->update([
            'enlace_externo' => $enlaceExterno,
            'disponible_hasta' => $disponibleHasta,
        ]);

        return $evaluacion;
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
     * Evaluaciones de un horario con un enlace externo que el estudiante ya
     * puede resolver: deben estar publicadas y, si el docente puso una
     * fecha límite, todavía no haber pasado (ver Evaluacion::enlaceDisponible()).
     *
     * @return Collection<int, Evaluacion>
     */
    public function evaluacionesConEnlaceDelHorario(Horario $horario): Collection
    {
        return Evaluacion::query()
            ->where('horario_id', $horario->id)
            ->where('estado', EstadoEvaluacionEnum::PUBLICADA)
            ->whereNotNull('enlace_externo')
            ->where(function ($query) {
                $query->whereNull('disponible_hasta')->orWhere('disponible_hasta', '>=', now());
            })
            ->orderByDesc('fecha')
            ->get();
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
