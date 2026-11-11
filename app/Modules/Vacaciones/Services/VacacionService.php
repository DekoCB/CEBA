<?php

declare(strict_types=1);

namespace App\Modules\Vacaciones\Services;

use App\Modules\Academico\Enums\TipoSiagieEnum;
use App\Modules\Matricula\Enums\EstadoMatriculaEnum;
use App\Modules\Matricula\Models\Estudiante;
use App\Modules\Matricula\Models\Matricula;
use App\Modules\Vacaciones\Models\Vacacion;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class VacacionService
{
    /**
     * La duración de vacaciones de SIAGIE anual es fija: 2 meses desde la
     * fecha que el coordinador elija como inicio.
     */
    private const MESES_DE_VACACIONES = 2;

    /**
     * $fechaFin es opcional: si no se indica, se calcula como siempre
     * (fecha de inicio + 2 meses). Si se indica (el coordinador la
     * personaliza desde la UI), se usa tal cual -- debe ser posterior a la
     * fecha de inicio.
     */
    public function activar(Estudiante $estudiante, string $fechaInicio, ?int $registradoPor, ?string $fechaFin = null): Vacacion
    {
        $matricula = $this->matriculaVigente($estudiante);

        if ($matricula === null || $matricula->ciclo->siagie?->tipo !== TipoSiagieEnum::ANUAL) {
            throw ValidationException::withMessages([
                'estudiante' => 'Las vacaciones solo aplican a estudiantes matriculados en SIAGIE anual.',
            ]);
        }

        $fechaFinResuelta = $fechaFin !== null ? Carbon::parse($fechaFin) : Carbon::parse($fechaInicio)->addMonths(self::MESES_DE_VACACIONES);

        if ($fechaFinResuelta->lessThanOrEqualTo(Carbon::parse($fechaInicio))) {
            throw ValidationException::withMessages([
                'fechaFin' => 'La fecha fin debe ser posterior a la fecha de inicio.',
            ]);
        }

        return Vacacion::query()->create([
            'estudiante_id' => $estudiante->id,
            'matricula_id' => $matricula->id,
            'fecha_inicio' => $fechaInicio,
            'fecha_fin' => $fechaFinResuelta,
            'registrado_por' => $registradoPor,
        ]);
    }

    /**
     * @return Collection<int, Vacacion>
     */
    public function vigentes(): Collection
    {
        $hoy = Carbon::today();

        return Vacacion::query()
            ->where('fecha_inicio', '<=', $hoy)
            ->where('fecha_fin', '>=', $hoy)
            ->with(['estudiante', 'matricula.grado'])
            ->orderBy('fecha_fin')
            ->get();
    }

    /**
     * @return Collection<int, Vacacion>
     */
    public function historial(): Collection
    {
        return Vacacion::query()
            ->where('fecha_fin', '<', Carbon::today())
            ->with(['estudiante', 'matricula.grado'])
            ->latest('fecha_fin')
            ->get();
    }

    private function matriculaVigente(Estudiante $estudiante): ?Matricula
    {
        return Matricula::query()
            ->where('estudiante_id', $estudiante->id)
            ->where('estado', EstadoMatriculaEnum::APROBADA)
            ->latest('fecha_matricula')
            ->with('ciclo.siagie')
            ->first();
    }
}
