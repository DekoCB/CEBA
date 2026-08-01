<?php

declare(strict_types=1);

namespace App\Modules\Asistencia\Services;

use App\Modules\Academico\Models\Horario;
use App\Modules\Asistencia\Enums\EstadoAsistenciaEnum;
use App\Modules\Asistencia\Models\Asistencia;
use App\Modules\Matricula\Models\Estudiante;
use App\Modules\Matricula\Models\Matricula;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\DB;

class AsistenciaService
{
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
     * @return SupportCollection<int, string>
     */
    public function fechasRegistradas(Horario $horario): SupportCollection
    {
        return Asistencia::query()
            ->where('horario_id', $horario->id)
            ->distinct()
            ->orderByDesc('fecha')
            ->pluck('fecha');
    }

    /**
     * @return Collection<int, Asistencia>
     */
    public function deSesion(Horario $horario, string $fecha): Collection
    {
        return Asistencia::query()
            ->where('horario_id', $horario->id)
            ->where('fecha', $fecha)
            ->with('estudiante')
            ->get()
            ->keyBy('estudiante_id');
    }

    /**
     * @param  array<int, string>  $registros  estudiante_id => EstadoAsistenciaEnum::value
     * @param  array<int, string|null>  $observaciones  estudiante_id => motivo de la justificación
     * @param  array<int, UploadedFile|null>  $justificantes  estudiante_id => documento de sustento
     */
    public function registrar(Horario $horario, string $fecha, array $registros, array $observaciones = [], array $justificantes = []): void
    {
        DB::transaction(function () use ($horario, $fecha, $registros, $observaciones, $justificantes) {
            foreach ($registros as $estudianteId => $estado) {
                $asistencia = Asistencia::query()->updateOrCreate(
                    ['horario_id' => $horario->id, 'estudiante_id' => $estudianteId, 'fecha' => $fecha],
                    ['estado' => $estado, 'observacion' => $observaciones[$estudianteId] ?? null],
                );

                if (! empty($justificantes[$estudianteId])) {
                    $asistencia->addMedia($justificantes[$estudianteId]->getRealPath())
                        ->usingFileName($justificantes[$estudianteId]->getClientOriginalName())
                        ->toMediaCollection('justificante');
                }
            }
        });
    }

    /**
     * @return array{total: int, asistio: int, porcentaje: float, por_estado: array<string, int>, registros: Collection<int, Asistencia>}
     */
    public function resumenEstudiante(Estudiante $estudiante, Horario $horario): array
    {
        $registros = Asistencia::query()
            ->where('horario_id', $horario->id)
            ->where('estudiante_id', $estudiante->id)
            ->with('solicitudJustificacion')
            ->orderByDesc('fecha')
            ->get();

        $total = $registros->count();
        $asistio = $registros->filter(fn (Asistencia $asistencia) => $asistencia->estado->cuentaComoAsistio())->count();

        $porEstado = [];
        foreach (EstadoAsistenciaEnum::cases() as $estado) {
            $porEstado[$estado->value] = $registros->where('estado', $estado)->count();
        }

        return [
            'total' => $total,
            'asistio' => $asistio,
            'porcentaje' => $total > 0 ? round(($asistio / $total) * 100, 1) : 0.0,
            'por_estado' => $porEstado,
            'registros' => $registros,
        ];
    }
}
