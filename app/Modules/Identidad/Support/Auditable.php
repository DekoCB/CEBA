<?php

declare(strict_types=1);

namespace App\Modules\Identidad\Support;

use App\Modules\Identidad\Jobs\RegistrarAuditoriaJob;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

/**
 * Añade auditoría automática (created/updated/deleted) a cualquier modelo
 * de negocio sensible (Matricula, Pago, Calificacion, Certificado...).
 * La escritura del log se despacha a cola: nunca bloquea la request.
 */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(function ($model): void {
            self::despacharAuditoria($model, 'created', [], $model->getAttributes());
        });

        static::updated(function ($model): void {
            if ($model->wasChanged()) {
                self::despacharAuditoria($model, 'updated', $model->getOriginal(), $model->getChanges());
            }
        });

        static::deleted(function ($model): void {
            self::despacharAuditoria($model, 'deleted', $model->getAttributes(), []);
        });
    }

    /**
     * @param  array<string, mixed>  $valoresAnteriores
     * @param  array<string, mixed>  $valoresNuevos
     */
    private static function despacharAuditoria($model, string $evento, array $valoresAnteriores, array $valoresNuevos): void
    {
        RegistrarAuditoriaJob::dispatch(
            Auth::id(),
            $evento,
            $model->getMorphClass(),
            $model->getKey(),
            $valoresAnteriores,
            $valoresNuevos,
            Request::ip(),
            substr((string) Request::userAgent(), 0, 255),
        );
    }
}
