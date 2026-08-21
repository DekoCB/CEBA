<?php

declare(strict_types=1);

namespace App\Modules\Academico\Services;

use App\Modules\Academico\Enums\TipoCicloEnum;
use App\Modules\Academico\Models\Ciclo;
use App\Modules\Academico\Models\PeriodoMatricula;
use App\Modules\Academico\Repositories\Contracts\CicloRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Las 4 ventanas de admisión rotativas del año (Grupo 1 a 4). Aplica la
 * "doble validación" del roadmap: las fechas del propio ciclo deben ser
 * coherentes con su tipo, y las fechas de matrícula deben ser coherentes
 * con las del ciclo.
 */
class CicloService
{
    /**
     * Cuántos días antes del inicio del ciclo se permite abrir matrícula.
     */
    private const DIAS_MATRICULA_ANTICIPADA = 30;

    public function __construct(
        private readonly CicloRepositoryInterface $ciclos,
    ) {}

    public function listar(int $perPage = 15): LengthAwarePaginator
    {
        return $this->ciclos->paginate($perPage);
    }

    /**
     * @param  array{nombre: string, tipo: TipoCicloEnum, anio: int, fecha_inicio: string, fecha_fin: string}  $datos
     */
    public function crear(array $datos): Ciclo
    {
        $this->validarFechasDelCiclo($datos['tipo'], $datos['fecha_inicio'], $datos['fecha_fin']);
        $this->validarSinSolapeDeMismoTipo($datos['tipo'], $datos['fecha_inicio'], $datos['fecha_fin']);

        return $this->ciclos->create($datos);
    }

    /**
     * @param  array{nombre: string, tipo: TipoCicloEnum, anio: int, fecha_inicio: string, fecha_fin: string, estado: string}  $datos
     */
    public function actualizar(Ciclo $ciclo, array $datos): Ciclo
    {
        $this->validarFechasDelCiclo($datos['tipo'], $datos['fecha_inicio'], $datos['fecha_fin']);
        $this->validarSinSolapeDeMismoTipo($datos['tipo'], $datos['fecha_inicio'], $datos['fecha_fin'], $ciclo->id);

        return $this->ciclos->update($ciclo, $datos);
    }

    /**
     * Primera validación: las fechas del ciclo deben cuadrar con su tipo.
     * Los 4 grupos tienen mes de inicio fijo, y una duración objetivo (6
     * meses) con un margen de una semana para acomodar feriados/ajustes
     * administrativos.
     */
    public function validarFechasDelCiclo(TipoCicloEnum $tipo, string $fechaInicio, string $fechaFin): void
    {
        $inicio = Carbon::parse($fechaInicio);
        $fin = Carbon::parse($fechaFin);

        if ($fin->lessThanOrEqualTo($inicio)) {
            throw ValidationException::withMessages([
                'fecha_fin' => 'La fecha de fin debe ser posterior a la fecha de inicio.',
            ]);
        }

        if ($inicio->month !== $tipo->mesInicioFijo()) {
            throw ValidationException::withMessages([
                'fecha_inicio' => "Un ciclo {$tipo->label()} debe iniciar en el mes ".Carbon::create(month: $tipo->mesInicioFijo())->translatedFormat('F').'.',
            ]);
        }

        $mesesEsperados = $tipo->duracionEnMeses();
        $finEsperado = $inicio->copy()->addMonths($mesesEsperados);
        $diferenciaEnDias = abs($fin->diffInDays($finEsperado));

        if ($diferenciaEnDias > 7) {
            throw ValidationException::withMessages([
                'fecha_fin' => "Un ciclo {$tipo->label()} dura {$mesesEsperados} meses; la fecha de fin no cuadra con la de inicio (margen de una semana).",
            ]);
        }
    }

    /**
     * Evita planificar dos ciclos del mismo tipo con fechas que se cruzan
     * (p. ej. dos "Enero-Junio" solapados sería un error de carga).
     */
    private function validarSinSolapeDeMismoTipo(TipoCicloEnum $tipo, string $fechaInicio, string $fechaFin, ?int $exceptoId = null): void
    {
        $solapados = $this->ciclos->solapadosCon($fechaInicio, $fechaFin, $exceptoId)
            ->where('tipo', $tipo);

        if ($solapados->isNotEmpty()) {
            throw ValidationException::withMessages([
                'fecha_inicio' => 'Ya existe un ciclo del mismo tipo con fechas que se cruzan: '.$solapados->first()->nombre,
            ]);
        }
    }

    /**
     * Segunda validación (la "doble validación" del roadmap): el periodo de
     * matrícula debe caer dentro de una ventana razonable respecto al
     * ciclo — puede abrir hasta 30 días antes de que el ciclo inicie, pero
     * debe cerrar a más tardar cuando el ciclo termina.
     */
    public function crearPeriodoMatricula(Ciclo $ciclo, string $fechaInicio, string $fechaFin): PeriodoMatricula
    {
        $this->validarPeriodoMatriculaContraCiclo($ciclo, $fechaInicio, $fechaFin);

        return $ciclo->periodosMatricula()->create([
            'fecha_inicio' => $fechaInicio,
            'fecha_fin' => $fechaFin,
        ]);
    }

    public function validarPeriodoMatriculaContraCiclo(Ciclo $ciclo, string $fechaInicio, string $fechaFin): void
    {
        $inicio = Carbon::parse($fechaInicio);
        $fin = Carbon::parse($fechaFin);

        if ($fin->lessThan($inicio)) {
            throw ValidationException::withMessages([
                'fecha_fin' => 'La fecha de fin de matrícula debe ser posterior o igual a la de inicio.',
            ]);
        }

        $ventanaInicio = $ciclo->fecha_inicio->copy()->subDays(self::DIAS_MATRICULA_ANTICIPADA);

        if ($inicio->lessThan($ventanaInicio)) {
            throw ValidationException::withMessages([
                'fecha_inicio' => 'La matrícula no puede abrir con más de '.self::DIAS_MATRICULA_ANTICIPADA." días de anticipación al inicio del ciclo ({$ciclo->fecha_inicio->format('d/m/Y')}).",
            ]);
        }

        if ($fin->greaterThan($ciclo->fecha_fin)) {
            throw ValidationException::withMessages([
                'fecha_fin' => "La matrícula debe cerrar a más tardar el {$ciclo->fecha_fin->format('d/m/Y')}, fecha en que termina el ciclo.",
            ]);
        }
    }

    /**
     * El grupo al que pasaría un estudiante de $actual al culminar su
     * grado (ver TipoCicloEnum::siguiente()), si ya existe una fila
     * creada para ese grupo+año. Null si todavía no se ha creado (p. ej.
     * personal aún no armó el siguiente grupo).
     */
    public function siguienteCiclo(Ciclo $actual): ?Ciclo
    {
        $siguienteAnio = $actual->anio + ($actual->tipo->avanzaAlSiguienteAnio() ? 1 : 0);

        return Ciclo::query()
            ->where('tipo', $actual->tipo->siguiente())
            ->where('anio', $siguienteAnio)
            ->first();
    }
}
