<?php

declare(strict_types=1);

namespace App\Modules\Evaluaciones\Services;

use App\Modules\Academico\Models\Ciclo;
use App\Modules\Academico\Models\Horario;
use App\Modules\Evaluaciones\Enums\NotaLetraEnum;
use App\Modules\Evaluaciones\Models\Libreta;
use App\Modules\Matricula\Models\Estudiante;
use App\Modules\Matricula\Models\Matricula;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Validation\ValidationException;

class LibretaService
{
    public function __construct(
        private readonly EvaluacionService $evaluaciones,
    ) {}

    public function generar(Estudiante $estudiante, Ciclo $ciclo): Libreta
    {
        $matricula = Matricula::query()
            ->where('estudiante_id', $estudiante->id)
            ->where('ciclo_id', $ciclo->id)
            ->where('estado', 'aprobada')
            ->first();

        if (! $matricula) {
            throw ValidationException::withMessages([
                'ciclo' => 'El estudiante no tiene una matrícula aprobada en ese ciclo.',
            ]);
        }

        $horarios = Horario::query()
            ->where('ciclo_id', $ciclo->id)
            ->where('grado_id', $matricula->grado_id)
            ->with('curso')
            ->get();

        $cursos = $horarios->map(function (Horario $horario) use ($estudiante) {
            $promedio = $this->evaluaciones->promedioDelEstudiante($estudiante, $horario);

            return [
                'nombre' => $horario->curso->nombre,
                'promedio' => $promedio,
                'letra' => $promedio !== null ? NotaLetraEnum::desde($promedio)->value : null,
            ];
        });

        $pdf = Pdf::loadView('pdf.libreta', [
            'estudiante' => $estudiante,
            'ciclo' => $ciclo,
            'cursos' => $cursos,
        ]);

        /** @var Libreta $libreta */
        $libreta = Libreta::query()->updateOrCreate(
            ['estudiante_id' => $estudiante->id, 'ciclo_id' => $ciclo->id],
            ['generado_en' => now()],
        );

        $libreta->addMediaFromString($pdf->output())
            ->usingFileName("libreta-{$estudiante->id}-{$ciclo->id}.pdf")
            ->toMediaCollection('pdf');

        return $libreta;
    }
}
