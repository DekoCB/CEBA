<?php

declare(strict_types=1);

namespace App\Modules\Reportes\Services;

use App\Modules\Academico\Models\Ciclo;
use App\Modules\Certificados\Models\Certificado;
use App\Modules\Evaluaciones\Models\Libreta;
use App\Modules\Evaluaciones\Services\LibretaService;
use App\Modules\Matricula\Enums\EstadoMatriculaEnum;
use App\Modules\Matricula\Models\DocumentoEstudiante;
use App\Modules\Matricula\Models\Estudiante;
use App\Modules\Matricula\Models\ExamenUbicacion;
use App\Modules\Matricula\Models\Matricula;
use App\Modules\Pagos\Enums\EstadoCuotaEnum;
use App\Modules\Pagos\Models\Cuota;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;

/**
 * Junta en un solo lugar lo que hoy exige entrar módulo por módulo para
 * saber qué le falta a un estudiante: grados cursados, situación de pagos,
 * documentos (de las 3 fuentes distintas en las que viven hoy) y notas.
 * Es de solo lectura -- no reemplaza a la ficha editable de Matrícula.
 */
class HistorialEstudianteService
{
    public function __construct(
        private readonly LibretaService $libretas,
    ) {}

    /**
     * @return array{
     *     estudiante: Estudiante,
     *     matriculas: Collection<int, Matricula>,
     *     resumenPagos: array{totalPagado: float, totalPendiente: float, totalExonerado: float, cuotasVencidas: Collection<int, Cuota>},
     *     documentosSubidos: Collection<int, DocumentoEstudiante>,
     *     documentosEmitidos: Collection<int, Certificado>,
     *     libretas: Collection<int, Libreta>,
     *     notasPorCiclo: SupportCollection<int, array{ciclo: Ciclo, cursos: SupportCollection}>,
     *     examenesUbicacion: Collection<int, ExamenUbicacion>,
     * }|null
     */
    public function porDni(string $dni): ?array
    {
        $estudiante = Estudiante::query()->where('dni', $dni)->first();

        if ($estudiante === null) {
            return null;
        }

        $matriculas = $estudiante->matriculas()
            ->with(['grado', 'ciclo'])
            ->orderBy('fecha_matricula')
            ->get();

        return [
            'estudiante' => $estudiante,
            'matriculas' => $matriculas,
            'resumenPagos' => $this->resumenPagos($estudiante),
            'documentosSubidos' => $estudiante->documentos()->with('media')->get(),
            'documentosEmitidos' => Certificado::query()->where('estudiante_id', $estudiante->id)->with('media')->latest('fecha_emision')->get(),
            'libretas' => $this->libretas->misLibretas($estudiante),
            'notasPorCiclo' => $this->notasPorCiclo($estudiante, $matriculas),
            'examenesUbicacion' => $estudiante->examenesUbicacion()->with('gradoAsignado')->latest('fecha')->get(),
        ];
    }

    /**
     * Pagado/pendiente/exonerado sumados a través de TODAS las matrículas
     * del estudiante (no solo la más reciente), más el detalle de cuotas
     * vencidas -- mismo criterio que
     * BloqueoAccesoService::cuotasVencidasDe(), pero sin acotar a "las que
     * bloquean acceso": aquí interesa el cuadro completo, no la regla de
     * bloqueo.
     *
     * @return array{totalPagado: float, totalPendiente: float, totalExonerado: float, cuotasVencidas: Collection<int, Cuota>}
     */
    private function resumenPagos(Estudiante $estudiante): array
    {
        $cuotas = Cuota::query()
            ->whereHas('planPago.matricula', fn ($query) => $query->where('estudiante_id', $estudiante->id))
            ->with('planPago.matricula.grado', 'planPago.matricula.ciclo')
            ->get();

        return [
            'totalPagado' => (float) $cuotas->where('estado', EstadoCuotaEnum::PAGADO)->sum('monto'),
            'totalPendiente' => (float) $cuotas->where('estado', EstadoCuotaEnum::PENDIENTE)->sum('monto'),
            'totalExonerado' => (float) $cuotas->where('estado', EstadoCuotaEnum::EXONERADO)->sum('monto'),
            'cuotasVencidas' => $cuotas->filter(fn (Cuota $cuota) => $cuota->estaVencida())->sortBy('fecha_vencimiento')->values(),
        ];
    }

    /**
     * Notas por curso de cada ciclo donde el estudiante tuvo una matrícula
     * aprobada -- LibretaService::resumenPorCursos() ya hace el cálculo
     * por un solo ciclo, aquí se itera sobre todo el historial.
     *
     * @param  Collection<int, Matricula>  $matriculas
     * @return SupportCollection<int, array{ciclo: Ciclo, cursos: SupportCollection}>
     */
    private function notasPorCiclo(Estudiante $estudiante, $matriculas): SupportCollection
    {
        return $matriculas
            ->filter(fn (Matricula $matricula) => $matricula->estado === EstadoMatriculaEnum::APROBADA)
            ->map(fn (Matricula $matricula) => [
                'ciclo' => $matricula->ciclo,
                'cursos' => $this->libretas->resumenPorCursos($estudiante, $matricula->ciclo),
            ])
            ->filter(fn (array $entrada) => $entrada['cursos']->isNotEmpty())
            ->values();
    }
}
