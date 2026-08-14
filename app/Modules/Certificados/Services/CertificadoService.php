<?php

declare(strict_types=1);

namespace App\Modules\Certificados\Services;

use App\Models\User;
use App\Modules\Certificados\Enums\EstadoSolicitudCertificadoEnum;
use App\Modules\Certificados\Models\Certificado;
use App\Modules\Certificados\Models\PlantillaCertificado;
use App\Modules\Certificados\Models\SolicitudCertificado;
use App\Modules\Matricula\Models\Estudiante;
use App\Modules\Matricula\Models\Matricula;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CertificadoService
{
    public function solicitar(Estudiante $estudiante, ?Matricula $matricula, string $motivo): SolicitudCertificado
    {
        /** @var SolicitudCertificado $solicitud */
        $solicitud = SolicitudCertificado::query()->create([
            'estudiante_id' => $estudiante->id,
            'matricula_id' => $matricula?->id,
            'motivo' => $motivo,
            'estado' => EstadoSolicitudCertificadoEnum::PENDIENTE,
        ]);

        return $solicitud;
    }

    public function emitir(
        Estudiante $estudiante,
        ?Matricula $matricula,
        ?SolicitudCertificado $solicitud,
        ?string $observaciones,
        User $emisor,
    ): Certificado {
        /** @var Certificado $certificado */
        $certificado = Certificado::query()->create([
            'estudiante_id' => $estudiante->id,
            'matricula_id' => $matricula?->id,
            'numero' => $this->siguienteNumero(),
            'codigo_verificacion' => $this->generarCodigoVerificacion(),
            'es_duplicado' => false,
            'emitido_por' => $emisor->id,
            'fecha_emision' => now(),
            'observaciones' => $observaciones,
        ]);

        $this->generarPdf($certificado);

        if ($solicitud) {
            $solicitud->update([
                'estado' => EstadoSolicitudCertificadoEnum::ATENDIDA,
                'atendido_por' => $emisor->id,
                'certificado_id' => $certificado->id,
            ]);
        }

        return $certificado;
    }

    public function duplicar(Certificado $original, ?string $observaciones, User $emisor): Certificado
    {
        $base = $original->es_duplicado ? $original->original : $original;

        /** @var Certificado $duplicado */
        $duplicado = Certificado::query()->create([
            'estudiante_id' => $base->estudiante_id,
            'matricula_id' => $base->matricula_id,
            'numero' => $this->siguienteNumero(),
            'codigo_verificacion' => $base->codigo_verificacion,
            'es_duplicado' => true,
            'certificado_original_id' => $base->id,
            'emitido_por' => $emisor->id,
            'fecha_emision' => now(),
            'observaciones' => $observaciones,
        ]);

        $this->generarPdf($duplicado);

        return $duplicado;
    }

    public function rechazarSolicitud(SolicitudCertificado $solicitud, string $motivo, User $revisor): void
    {
        $this->validarPendiente($solicitud);

        $solicitud->update([
            'estado' => EstadoSolicitudCertificadoEnum::RECHAZADA,
            'atendido_por' => $revisor->id,
            'motivo_rechazo' => $motivo,
        ]);
    }

    public function plantillaActual(): PlantillaCertificado
    {
        return PlantillaCertificado::actual();
    }

    /**
     * @param  array{institucion: string, titulo: string, cuerpo: string, pie_nota: ?string, color_acento: string}  $datos
     */
    public function guardarPlantilla(array $datos): PlantillaCertificado
    {
        $plantilla = PlantillaCertificado::actual();
        $plantilla->update($datos);

        return $plantilla->fresh();
    }

    /**
     * PDF de muestra con datos ficticios, para que quien edita la plantilla
     * vea el resultado real (mismo pipeline de renderizado que un
     * certificado de verdad) sin tener que emitir uno.
     */
    public function previsualizarPlantilla(PlantillaCertificado $plantilla): string
    {
        $certificado = new Certificado([
            'numero' => '000000-'.now()->format('Y'),
            'codigo_verificacion' => 'MUESTRAMUES',
            'es_duplicado' => false,
            'fecha_emision' => now(),
        ]);

        $certificado->setRelation('estudiante', new Estudiante([
            'nombres' => 'Estudiante',
            'apellidos' => 'De Ejemplo',
            'dni' => '00000000',
        ]));
        $certificado->setRelation('matricula', null);

        return $this->renderizarPdf($certificado, $plantilla)->output();
    }

    public function verificar(string $codigo): ?Certificado
    {
        return Certificado::query()
            ->where('codigo_verificacion', strtoupper(trim($codigo)))
            ->where('es_duplicado', false)
            ->with(['estudiante', 'matricula.grado', 'matricula.ciclo'])
            ->first();
    }

    /**
     * @return Collection<int, SolicitudCertificado>
     */
    public function solicitudesPendientes(): Collection
    {
        return SolicitudCertificado::query()
            ->where('estado', EstadoSolicitudCertificadoEnum::PENDIENTE)
            ->with(['estudiante', 'matricula'])
            ->oldest('created_at')
            ->get();
    }

    /**
     * @return Collection<int, SolicitudCertificado>
     */
    public function misSolicitudes(Estudiante $estudiante): Collection
    {
        return SolicitudCertificado::query()
            ->where('estudiante_id', $estudiante->id)
            ->with('certificado')
            ->latest('created_at')
            ->get();
    }

    /**
     * @return Collection<int, Certificado>
     */
    public function misCertificados(Estudiante $estudiante): Collection
    {
        return Certificado::query()
            ->where('estudiante_id', $estudiante->id)
            ->with(['matricula.grado', 'matricula.ciclo'])
            ->latest('fecha_emision')
            ->get();
    }

    /**
     * @return Collection<int, Certificado>
     */
    public function todos(): Collection
    {
        return Certificado::query()
            ->with(['estudiante', 'matricula.grado', 'emisor'])
            ->latest('fecha_emision')
            ->get();
    }

    private function generarPdf(Certificado $certificado): void
    {
        $certificado->load(['estudiante', 'matricula.grado', 'matricula.ciclo']);

        $pdf = $this->renderizarPdf($certificado);

        $certificado->addMediaFromString($pdf->output())
            ->usingFileName("certificado-{$certificado->numero}.pdf")
            ->toMediaCollection('pdf');
    }

    /**
     * @return \Barryvdh\DomPDF\PDF
     */
    private function renderizarPdf(Certificado $certificado, ?PlantillaCertificado $plantilla = null)
    {
        $plantilla ??= PlantillaCertificado::actual();

        return Pdf::loadView('pdf.certificado', [
            'certificado' => $certificado,
            'plantilla' => $plantilla,
            'cuerpo' => $plantilla->renderizarCuerpo($this->variablesDePlantilla($certificado)),
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function variablesDePlantilla(Certificado $certificado): array
    {
        $detalleMatricula = $certificado->matricula
            ? sprintf(
                'cursó estudios en el grado %s durante el ciclo %s (%s al %s),',
                $certificado->matricula->grado->nombre,
                $certificado->matricula->ciclo->nombre,
                $certificado->matricula->ciclo->fecha_inicio->format('d/m/Y'),
                $certificado->matricula->ciclo->fecha_fin->format('d/m/Y'),
            )
            : 'se encuentra registrado(a) en esta institución,';

        return [
            'estudiante' => $certificado->estudiante?->nombreCompleto() ?? '—',
            'dni' => $certificado->estudiante->dni ?? '—',
            'detalle_matricula' => $detalleMatricula,
            'numero' => $certificado->numero,
            'fecha_emision' => $certificado->fecha_emision->format('d/m/Y'),
            'codigo_verificacion' => $certificado->codigo_verificacion,
        ];
    }

    private function siguienteNumero(): string
    {
        $anio = now()->format('Y');
        $emitidosEsteAnio = Certificado::query()->where('numero', 'like', "%-{$anio}")->count();

        return sprintf('%06d-%s', $emitidosEsteAnio + 1, $anio);
    }

    private function generarCodigoVerificacion(): string
    {
        do {
            $codigo = Str::upper(Str::random(10));
        } while (Certificado::query()->where('codigo_verificacion', $codigo)->exists());

        return $codigo;
    }

    private function validarPendiente(SolicitudCertificado $solicitud): void
    {
        if ($solicitud->estado !== EstadoSolicitudCertificadoEnum::PENDIENTE) {
            throw ValidationException::withMessages([
                'estado' => 'Esta solicitud ya fue atendida y no puede modificarse.',
            ]);
        }
    }
}
