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
 *
 * El filtro común a todos es Grupo (ciclo) → Grado → Curso, en cascada,
 * más franja institucional -- ya no hay filtro por rango de fechas: el
 * ciclo (que ya tiene su propio periodo) alcanza para acotar el reporte
 * a un periodo lectivo concreto.
 */
class ReporteService
{
    /**
     * @return array{columnas: list<string>, filas: list<array<int, string|int|float>>}
     */
    public function matricula(?int $cicloId, ?int $gradoId, ?int $cursoId, ?string $franja = null): array
    {
        $matriculas = $this->filtrarMatriculasPorFiltros(
            Matricula::query()->with(['estudiante', 'grado', 'ciclo']),
            $cicloId,
            $gradoId,
            $cursoId,
            $franja,
        )->latest('fecha_matricula')->get();

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
    public function academico(?int $cicloId, ?int $gradoId, ?int $cursoId, ?string $franja = null): array
    {
        $horarioIds = $this->horarioIdsFiltrados($cicloId, $gradoId, $cursoId, $franja);

        $calificaciones = Calificacion::query()
            ->with(['estudiante', 'evaluacion.horario.grado', 'evaluacion.horario.curso'])
            ->when($horarioIds !== null, fn ($query) => $query->whereHas(
                'evaluacion',
                fn ($sub) => $sub->whereIn('horario_id', $horarioIds),
            ))
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
    public function financiero(?int $cicloId, ?int $gradoId, ?int $cursoId, ?string $franja = null): array
    {
        $sinFiltros = $this->sinFiltros($cicloId, $gradoId, $cursoId, $franja);

        $pagos = Pago::query()
            ->with(['estudiante', 'concepto'])
            ->when(! $sinFiltros, fn ($query) => $query->whereHas(
                'estudiante.matriculas',
                fn ($sub) => $this->filtrarMatriculasPorFiltros($sub, $cicloId, $gradoId, $cursoId, $franja),
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
    public function certificados(?int $cicloId, ?int $gradoId, ?int $cursoId, ?string $franja = null): array
    {
        $sinFiltros = $this->sinFiltros($cicloId, $gradoId, $cursoId, $franja);

        $certificados = Certificado::query()
            ->with(['estudiante', 'matricula.grado'])
            ->when(! $sinFiltros, fn ($query) => $query->whereHas(
                'matricula',
                fn ($sub) => $this->filtrarMatriculasPorFiltros($sub, $cicloId, $gradoId, $cursoId, $franja),
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
    public function operativo(?int $cicloId, ?int $gradoId, ?int $cursoId, ?string $franja = null): array
    {
        $horarioIds = $this->horarioIdsFiltrados($cicloId, $gradoId, $cursoId, $franja);

        $asistencias = Asistencia::query()
            ->with(['estudiante', 'horario.grado'])
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
     * Solo cuenta cuotas ya vencidas a hoy, sin importar el filtro elegido
     * -- eso es lo que define a un "deudor", no una fecha de corte manual.
     *
     * @return array{columnas: list<string>, filas: list<array<int, string|int|float>>}
     */
    public function morosos(?int $cicloId, ?int $gradoId, ?int $cursoId, ?string $franja = null): array
    {
        $sinFiltros = $this->sinFiltros($cicloId, $gradoId, $cursoId, $franja);

        $cuotasVencidas = Cuota::query()
            ->where('estado', EstadoCuotaEnum::PENDIENTE)
            ->where('fecha_vencimiento', '<', now()->toDateString())
            ->when(! $sinFiltros, fn ($query) => $query->whereHas(
                'planPago.matricula',
                fn ($sub) => $this->filtrarMatriculasPorFiltros($sub, $cicloId, $gradoId, $cursoId, $franja),
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
    public function propio(User $docente, ?int $cicloId, ?int $gradoId, ?int $cursoId, ?string $franja = null): array
    {
        $horarioIds = $this->horarioIdsFiltrados($cicloId, $gradoId, $cursoId, $franja);

        $evaluaciones = Evaluacion::query()
            ->with(['horario.grado', 'horario.curso'])
            ->whereHas('horario', fn ($query) => $query->where('docente_id', $docente->id))
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

    private function sinFiltros(?int $cicloId, ?int $gradoId, ?int $cursoId, ?string $franja): bool
    {
        return $cicloId === null && $gradoId === null && $cursoId === null && $franja === null;
    }

    /**
     * Horarios que coinciden con el Grupo (ciclo), Grado y Curso elegidos
     * en el selector en cascada, más la franja institucional si se usa.
     * null en cualquiera de los filtros significa "no restringir por ese
     * campo". Devuelve null si no se pidió ningún filtro (para que el
     * caller no aplique ninguna restricción en absoluto).
     *
     * @return ?Collection<int, Horario>
     */
    private function horariosFiltrados(?int $cicloId, ?int $gradoId, ?int $cursoId, ?string $franja): ?Collection
    {
        if ($this->sinFiltros($cicloId, $gradoId, $cursoId, $franja)) {
            return null;
        }

        $franjaEnum = $franja !== null ? FranjaHorarioEnum::tryFrom($franja) : null;

        return Horario::query()
            ->with('dias')
            ->when($cicloId !== null, fn ($query) => $query->where('ciclo_id', $cicloId))
            ->when($gradoId !== null, fn ($query) => $query->where('grado_id', $gradoId))
            ->when($cursoId !== null, fn ($query) => $query->where('curso_id', $cursoId))
            ->get()
            ->filter(fn (Horario $horario) => $franjaEnum === null || $horario->franja() === $franjaEnum)
            ->values();
    }

    /**
     * @return ?list<int>
     */
    private function horarioIdsFiltrados(?int $cicloId, ?int $gradoId, ?int $cursoId, ?string $franja): ?array
    {
        return $this->horariosFiltrados($cicloId, $gradoId, $cursoId, $franja)?->pluck('id')->all();
    }

    /**
     * Aplica el filtro de Grupo/Grado/Curso/franja a una consulta de
     * Matricula. Ciclo y grado se filtran directo por columna (Matricula
     * ya las tiene). Curso y franja no existen ahí -- se resuelven vía
     * Horario: un estudiante matriculado en ese grado+ciclo lleva
     * automáticamente cualquier curso de su grado, salvo que ese curso
     * tenga secciones paralelas, donde se exige la asignación explícita en
     * el pivote matricula_horario a uno de los horarios filtrados (mismo
     * criterio que Matricula::scopeDelHorario()).
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    private function filtrarMatriculasPorFiltros(Builder $query, ?int $cicloId, ?int $gradoId, ?int $cursoId, ?string $franja): Builder
    {
        $query = $query
            ->when($cicloId !== null, fn ($q) => $q->where('ciclo_id', $cicloId))
            ->when($gradoId !== null, fn ($q) => $q->where('grado_id', $gradoId));

        if ($cursoId === null && $franja === null) {
            return $query;
        }

        $horarios = $this->horariosFiltrados($cicloId, $gradoId, $cursoId, $franja);

        if ($horarios === null || $horarios->isEmpty()) {
            return $query->whereIn('id', []);
        }

        // Si ciclo o grado no venían fijos arriba, hace falta acotar la
        // matrícula a los pares grado+ciclo donde sí cae la franja/curso.
        if ($cicloId === null || $gradoId === null) {
            $pares = $horarios
                ->map(fn (Horario $horario) => ['grado_id' => $horario->grado_id, 'ciclo_id' => $horario->ciclo_id])
                ->unique(fn (array $par) => $par['grado_id'].'-'.$par['ciclo_id'])
                ->values();

            $query = $this->filtrarPorGradoYCiclo($query, $pares);
        }

        if ($cursoId === null) {
            return $query;
        }

        $tieneParalelos = Horario::query()->where('curso_id', $cursoId)->count() > 1;

        if (! $tieneParalelos) {
            return $query;
        }

        return $query->whereHas('horarios', fn ($sub) => $sub->whereIn('horarios.id', $horarios->pluck('id')));
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
