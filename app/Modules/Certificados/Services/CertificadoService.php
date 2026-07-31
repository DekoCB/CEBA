<?php

declare(strict_types=1);

namespace App\Modules\Certificados\Services;

use App\Models\User;
use App\Modules\Certificados\Enums\EstadoSolicitudCertificadoEnum;
use App\Modules\Certificados\Models\Certificado;
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

        $pdf = Pdf::loadView('pdf.certificado', ['certificado' => $certificado]);

        $certificado->addMediaFromString($pdf->output())
            ->usingFileName("certificado-{$certificado->numero}.pdf")
            ->toMediaCollection('pdf');
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
