<?php

declare(strict_types=1);

namespace App\Modules\Reportes\Services;

use App\Models\User;
use App\Modules\Academico\Enums\FranjaHorarioEnum;
use App\Modules\Academico\Models\Horario;
use App\Modules\Asistencia\Models\Asistencia;
use App\Modules\Certificados\Models\Certificado;
use App\Modules\Evaluaciones\Models\Calificacion;
use App\Modules\Evaluaciones\Models\Evaluacion;
use App\Modules\Matricula\Models\Matricula;
use App\Modules\Pagos\Enums\EstadoCuotaEnum;
use App\Modules\Pagos\Models\Cuota;
use App\Modules\Pagos\Models\Pago;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Constructor de reportes tabulares exportables. Cada método devuelve
 * ['columnas' => list<string>, 'filas' => list<list<string|int|float>>]
 * en el mismo formato para alimentar tanto la vista previa en pantalla
 * como los exportadores de Excel/CSV/PDF sin transformación adicional.
 */
class ReporteService
{
    /**
     * @return array{columnas: list<string>, filas: list<array<int, string|int|float>>}
     */
    public function matricula(?string $desde, ?string $hasta, ?string $franja = null): array
    {
        $paresGradoCiclo = $this->paresGradoCicloDeFranja($franja);

        $matriculas = Matricula::query()
            ->with(['estudiante', 'grado', 'ciclo'])
            ->when($desde, fn ($query) => $query->whereDate('fecha_matricula', '>=', $desde))
            ->when($hasta, fn ($query) => $query->whereDate('fecha_matricula', '<=', $hasta))
            ->when($paresGradoCiclo !== null, fn ($query) => $this->filtrarPorGradoYCiclo($query, $paresGradoCiclo))
            ->latest('fecha_matricula')
            ->get();

        return [
            'columnas' => ['Estudiante', 'DNI', 'Grado', 'Ciclo', 'Estado', 'Fecha de matrícula'],
            'filas' => $matriculas->map(fn (Matricula $matricula) => [
                $matricula->estudiante?->nombreCompleto() ?? '—',
                $matricula->estudiante->dni ?? '—',
                $matricula->grado->nombre,
                $matricula->ciclo->nombre,
                $matricula->estado->label(),
                $matricula->fecha_matricula->format('d/m/Y'),
            ])->all(),
        ];
    }

    /**
     * @return array{columnas: list<string>, filas: list<array<int, string|int|float>>}
     */
    public function academico(?string $desde, ?string $hasta, ?string $franja = null): array
    {
        $horarioIds = $this->horarioIdsDeFranja($franja);

        $calificaciones = Calificacion::query()
            ->with(['estudiante', 'evaluacion.horario.grado', 'evaluacion.horario.curso'])
            ->whereHas('evaluacion', function ($query) use ($desde, $hasta, $horarioIds) {
                $query->when($desde, fn ($sub) => $sub->whereDate('fecha', '>=', $desde))
                    ->when($hasta, fn ($sub) => $sub->whereDate('fecha', '<=', $hasta))
                    ->when($horarioIds !== null, fn ($sub) => $sub->whereIn('horario_id', $horarioIds));
            })
            ->latest('id')
            ->get();

        return [
            'columnas' => ['Estudiante', 'Grado', 'Curso', 'Evaluación', 'Nota', 'Resultado'],
            'filas' => $calificaciones->map(fn (Calificacion $calificacion) => [
                $calificacion->estudiante?->nombreCompleto() ?? '—',
                $calificacion->evaluacion->horario->grado->nombre,
                $calificacion->evaluacion->horario->curso->nombre,
                $calificacion->evaluacion->nombre,
                number_format((float) $calificacion->nota_numerica, 2),
                (float) $calificacion->nota_numerica >= 11 ? 'Aprobado' : 'Desaprobado',
            ])->all(),
        ];
    }

    /**
     * @return array{columnas: list<string>, filas: list<array<int, string|int|float>>}
     */
    public function financiero(?string $desde, ?string $hasta, ?string $franja = null): array
    {
        $paresGradoCiclo = $this->paresGradoCicloDeFranja($franja);

        $pagos = Pago::query()
            ->with(['estudiante', 'concepto'])
            ->when($desde, fn ($query) => $query->whereDate('fecha_pago', '>=', $desde))
            ->when($hasta, fn ($query) => $query->whereDate('fecha_pago', '<=', $hasta))
            ->when($paresGradoCiclo !== null, fn ($query) => $query->whereHas(
                'estudiante.matriculas',
                fn ($sub) => $this->filtrarPorGradoYCiclo($sub, $paresGradoCiclo),
            ))
            ->latest('fecha_pago')
            ->get();

        return [
            'columnas' => ['Estudiante', 'Concepto', 'Monto', 'Método', 'Estado', 'Fecha de pago'],
            'filas' => $pagos->map(fn (Pago $pago) => [
                $pago->estudiante?->nombreCompleto() ?? '—',
                $pago->concepto->nombre,
                number_format((float) $pago->monto, 2),
                $pago->metodo->label(),
                $pago->estado->label(),
                $pago->fecha_pago->format('d/m/Y'),
            ])->all(),
        ];
    }

    /**
     * @return array{columnas: list<string>, filas: list<array<int, string|int|float>>}
     */
    public function certificados(?string $desde, ?string $hasta, ?string $franja = null): array
    {
        $paresGradoCiclo = $this->paresGradoCicloDeFranja($franja);

        $certificados = Certificado::query()
            ->with(['estudiante', 'matricula.grado'])
            ->when($desde, fn ($query) => $query->whereDate('fecha_emision', '>=', $desde))
            ->when($hasta, fn ($query) => $query->whereDate('fecha_emision', '<=', $hasta))
            ->when($paresGradoCiclo !== null, fn ($query) => $query->whereHas(
                'matricula',
                fn ($sub) => $this->filtrarPorGradoYCiclo($sub, $paresGradoCiclo),
            ))
            ->latest('fecha_emision')
            ->get();

        return [
            'columnas' => ['N.° certificado', 'Estudiante', 'Grado', 'Duplicado', 'Fecha de emisión'],
            'filas' => $certificados->map(fn (Certificado $certificado) => [
                $certificado->numero,
                $certificado->estudiante?->nombreCompleto() ?? '—',
                $certificado->matricula !== null ? $certificado->matricula->grado->nombre : '—',
                $certificado->es_duplicado ? 'Sí' : 'No',
                $certificado->fecha_emision->format('d/m/Y'),
            ])->all(),
        ];
    }

    /**
     * @return array{columnas: list<string>, filas: list<array<int, string|int|float>>}
     */
    public function operativo(?string $desde, ?string $hasta, ?string $franja = null): array
    {
        $horarioIds = $this->horarioIdsDeFranja($franja);

        $asistencias = Asistencia::query()
            ->with(['estudiante', 'horario.grado'])
            ->when($desde, fn ($query) => $query->whereDate('fecha', '>=', $desde))
            ->when($hasta, fn ($query) => $query->whereDate('fecha', '<=', $hasta))
            ->when($horarioIds !== null, fn ($query) => $query->whereIn('horario_id', $horarioIds))
            ->get()
            ->groupBy(fn (Asistencia $asistencia) => $asistencia->estudiante_id);

        $filas = $asistencias
            ->filter(fn ($registros) => $registros->first()->estudiante !== null)
            ->map(function ($registros) {
                $estudiante = $registros->first()->estudiante;
                $total = $registros->count();
                $presentes = $registros->whereIn('estado', ['presente', 'justificado'])->count();

                return [
                    $estudiante->nombreCompleto(),
                    $registros->first()->horario->grado->nombre,
                    $total,
                    $presentes,
                    $total > 0 ? number_format($presentes / $total * 100, 1).'%' : '—',
                ];
            })->values()->all();

        return [
            'columnas' => ['Estudiante', 'Grado', 'Sesiones registradas', 'Asistencias', '% Asistencia'],
            'filas' => $filas,
        ];
    }

    /**
     * Un estudiante por fila, con sus cuotas vencidas sin pagar agrupadas:
     * cuántas, cuánto suman y desde cuándo la más antigua está vencida.
     * $desde/$hasta filtran por fecha de vencimiento de la cuota, no por
     * cuándo se generó el reporte.
     *
     * @return array{columnas: list<string>, filas: list<array<int, string|int|float>>}
     */
    public function morosos(?string $desde, ?string $hasta, ?string $franja = null): array
    {
        $paresGradoCiclo = $this->paresGradoCicloDeFranja($franja);

        $cuotasVencidas = Cuota::query()
            ->where('estado', EstadoCuotaEnum::PENDIENTE)
            ->where('fecha_vencimiento', '<', now()->toDateString())
            ->when($desde, fn ($query) => $query->whereDate('fecha_vencimiento', '>=', $desde))
            ->when($hasta, fn ($query) => $query->whereDate('fecha_vencimiento', '<=', $hasta))
            ->when($paresGradoCiclo !== null, fn ($query) => $query->whereHas(
                'planPago.matricula',
                fn ($sub) => $this->filtrarPorGradoYCiclo($sub, $paresGradoCiclo),
            ))
            ->with('planPago.matricula.estudiante', 'planPago.matricula.grado')
            ->get()
            ->filter(fn (Cuota $cuota) => $cuota->planPago->matricula?->estudiante !== null)
            ->groupBy(fn (Cuota $cuota) => $cuota->planPago->matricula->estudiante_id);

        $filas = $cuotasVencidas
            ->map(function ($cuotas) {
                $matricula = $cuotas->first()->planPago->matricula;
                $estudiante = $matricula->estudiante;

                return [
                    $estudiante->nombreCompleto(),
                    $estudiante->dni,
                    $matricula->grado->nombre,
                    $cuotas->count(),
                    number_format((float) $cuotas->sum('monto'), 2),
                    $cuotas->min('fecha_vencimiento')->format('d/m/Y'),
                ];
            })
            ->sortByDesc(fn (array $fila) => $fila[3])
            ->values()
            ->all();

        return [
            'columnas' => ['Estudiante', 'DNI', 'Grado', 'Cuotas vencidas', 'Monto adeudado', 'Vencida desde'],
            'filas' => $filas,
        ];
    }

    /**
     * @return array{columnas: list<string>, filas: list<array<int, string|int|float>>}
     */
    public function propio(User $docente, ?string $desde, ?string $hasta, ?string $franja = null): array
    {
        $horarioIds = $this->horarioIdsDeFranja($franja);

        $evaluaciones = Evaluacion::query()
            ->with(['horario.grado', 'horario.curso'])
            ->whereHas('horario', fn ($query) => $query->where('docente_id', $docente->id))
            ->when($desde, fn ($query) => $query->whereDate('fecha', '>=', $desde))
            ->when($hasta, fn ($query) => $query->whereDate('fecha', '<=', $hasta))
            ->when($horarioIds !== null, fn ($query) => $query->whereIn('horario_id', $horarioIds))
            ->latest('fecha')
            ->get();

        return [
            'columnas' => ['Evaluación', 'Grado', 'Curso', 'Fecha', 'Estado'],
            'filas' => $evaluaciones->map(fn (Evaluacion $evaluacion) => [
                $evaluacion->nombre,
                $evaluacion->horario->grado->nombre,
                $evaluacion->horario->curso->nombre,
                $evaluacion->fecha->format('d/m/Y'),
                $evaluacion->estado->label(),
            ])->all(),
        ];
    }

    /**
     * IDs de todos los horarios que caen en una franja institucional (ver
     * FranjaHorarioEnum), o null si no se pidió filtrar por franja -- los
     * reportes usan esto en vez de un horario_id puntual, ya que ahora se
     * filtra por franja (Lunes-Miércoles / Martes-Jueves / Domingo) en vez
     * de por horario individual (ver Fase de reportes por franja).
     *
     * @return ?list<int>
     */
    private function horarioIdsDeFranja(?string $franja): ?array
    {
        $franjaEnum = $franja !== null ? FranjaHorarioEnum::tryFrom($franja) : null;

        if ($franjaEnum === null) {
            return null;
        }

        return Horario::query()
            ->with('dias')
            ->get()
            ->filter(fn (Horario $horario) => $horario->franja() === $franjaEnum)
            ->pluck('id')
            ->all();
    }

    /**
     * Pares únicos de grado_id+ciclo_id de los horarios que caen en una
     * franja institucional, o null si no se pidió filtrar por franja. Los
     * reportes que dependen de `matriculas` (donde ya no existe un
     * horario_id puntual: la asignación real vive en el pivote
     * matricula_horario, y solo se usa cuando un curso tiene secciones
     * paralelas) filtran por estos pares para replicar la misma semántica
     * de pertenencia automática que usa Matricula::scopeDelHorario().
     *
     * @return ?Collection<int, array{grado_id: int, ciclo_id: int}>
     */
    private function paresGradoCicloDeFranja(?string $franja): ?Collection
    {
        $franjaEnum = $franja !== null ? FranjaHorarioEnum::tryFrom($franja) : null;

        if ($franjaEnum === null) {
            return null;
        }

        return Horario::query()
            ->with('dias')
            ->get()
            ->filter(fn (Horario $horario) => $horario->franja() === $franjaEnum)
            ->map(fn (Horario $horario) => ['grado_id' => $horario->grado_id, 'ciclo_id' => $horario->ciclo_id])
            ->unique(fn (array $par) => $par['grado_id'].'-'.$par['ciclo_id'])
            ->values();
    }

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @param  Collection<int, array{grado_id: int, ciclo_id: int}>  $pares
     * @return Builder<TModel>
     */
    private function filtrarPorGradoYCiclo(Builder $query, Collection $pares): Builder
    {
        if ($pares->isEmpty()) {
            return $query->whereIn('id', []);
        }

        return $query->where(function (Builder $q) use ($pares) {
            foreach ($pares as $par) {
                $q->orWhere(fn (Builder $qq) => $qq->where('grado_id', $par['grado_id'])->where('ciclo_id', $par['ciclo_id']));
            }
        });
    }
}
