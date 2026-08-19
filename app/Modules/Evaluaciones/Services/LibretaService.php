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
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Validation\ValidationException;

class LibretaService
{
    public function __construct(
        private readonly EvaluacionService $evaluaciones,
    ) {}

    /**
     * El promedio por curso del estudiante en el ciclo, para mostrar en la
     * página de la libreta (y para armar el PDF). Devuelve una colección
     * vacía si no tiene una matrícula aprobada en ese ciclo, en vez de
     * lanzar una excepción: a diferencia de generar(), esto solo se usa
     * para mostrar contenido, no para disparar una acción.
     *
     * Cada elemento es un array con las claves "nombre" (string), "promedio"
     * (?float), "letra" (?string) y "porMes" (el desglose mes a mes, ver
     * EvaluacionService::promedioMensualDelEstudiante()). Sin generics en el
     * tipo de retorno: el TValue de Collection no es covariante (ver
     * https://phpstan.org/blog/whats-up-with-template-covariant), así que
     * ninguna anotación de forma de array sobrevive a un ->map().
     */
    public function resumenPorCursos(Estudiante $estudiante, Ciclo $ciclo): SupportCollection
    {
        $matricula = Matricula::query()
            ->where('estudiante_id', $estudiante->id)
            ->where('ciclo_id', $ciclo->id)
            ->where('estado', 'aprobada')
            ->first();

        if (! $matricula) {
            return collect();
        }

        $horarios = Horario::query()
            ->where('ciclo_id', $ciclo->id)
            ->where('grado_id', $matricula->grado_id)
            ->with('curso')
            ->get();

        return $horarios->map(function (Horario $horario) use ($estudiante) {
            $promedio = $this->evaluaciones->promedioDelEstudiante($estudiante, $horario);

            return [
                'nombre' => $horario->curso->nombre,
                'promedio' => $promedio,
                'letra' => $promedio !== null ? NotaLetraEnum::desde($promedio)->value : null,
                'porMes' => $this->evaluaciones->promedioMensualDelEstudiante($estudiante, $horario),
            ];
        });
    }

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

        $cursos = $this->resumenPorCursos($estudiante, $ciclo);

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
