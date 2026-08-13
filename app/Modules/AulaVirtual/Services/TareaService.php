<?php

declare(strict_types=1);

namespace App\Modules\AulaVirtual\Services;

use App\Modules\AulaVirtual\Enums\EstadoEntregaEnum;
use App\Modules\AulaVirtual\Models\CursoVirtual;
use App\Modules\AulaVirtual\Models\EntregaTarea;
use App\Modules\AulaVirtual\Models\Tarea;
use App\Modules\Matricula\Models\Estudiante;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class TareaService
{
    /**
     * @param  array{titulo: string, descripcion: ?string, fecha_limite: string, puntaje_max: int, semana?: ?int}  $datos
     */
    public function crear(CursoVirtual $curso, array $datos): Tarea
    {
        return $curso->tareas()->create($datos);
    }

    public function entregaDe(Tarea $tarea, Estudiante $estudiante): ?EntregaTarea
    {
        return EntregaTarea::query()
            ->where('tarea_id', $tarea->id)
            ->where('estudiante_id', $estudiante->id)
            ->first();
    }

    public function entregar(Tarea $tarea, Estudiante $estudiante, ?string $comentario, ?UploadedFile $archivo): EntregaTarea
    {
        return DB::transaction(function () use ($tarea, $estudiante, $comentario, $archivo) {
            $ahora = now();
            $estado = $ahora->greaterThan($tarea->fecha_limite) ? EstadoEntregaEnum::TARDE : EstadoEntregaEnum::ENTREGADO;

            /** @var EntregaTarea $entrega */
            $entrega = EntregaTarea::query()->updateOrCreate(
                ['tarea_id' => $tarea->id, 'estudiante_id' => $estudiante->id],
                ['comentario' => $comentario, 'fecha_entrega' => $ahora, 'estado' => $estado],
            );

            if ($archivo) {
                $entrega->addMedia($archivo)->toMediaCollection('archivo');
            }

            return $entrega;
        });
    }

    public function calificar(EntregaTarea $entrega, float $nota): EntregaTarea
    {
        $entrega->update([
            'nota' => $nota,
            'estado' => EstadoEntregaEnum::CALIFICADO,
        ]);

        return $entrega;
    }
}
