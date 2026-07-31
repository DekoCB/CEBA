<?php

declare(strict_types=1);

namespace App\Modules\Certificados\Models;

use App\Models\User;
use App\Modules\Certificados\Database\Factories\SolicitudCertificadoFactory;
use App\Modules\Certificados\Enums\EstadoSolicitudCertificadoEnum;
use App\Modules\Identidad\Support\Auditable;
use App\Modules\Matricula\Models\Estudiante;
use App\Modules\Matricula\Models\Matricula;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $estudiante_id
 * @property int|null $matricula_id
 * @property string $motivo
 * @property EstadoSolicitudCertificadoEnum $estado
 * @property int|null $atendido_por
 * @property int|null $certificado_id
 * @property string|null $motivo_rechazo
 * @property-read Estudiante $estudiante
 * @property-read Matricula|null $matricula
 * @property-read User|null $revisor
 * @property-read Certificado|null $certificado
 */
class SolicitudCertificado extends Model
{
    /** @use HasFactory<SolicitudCertificadoFactory> */
    use Auditable, HasFactory;

    protected $table = 'solicitudes_certificado';

    protected $fillable = [
        'estudiante_id',
        'matricula_id',
        'motivo',
        'estado',
        'atendido_por',
        'certificado_id',
        'motivo_rechazo',
    ];

    protected function casts(): array
    {
        return [
            'estado' => EstadoSolicitudCertificadoEnum::class,
        ];
    }

    protected static function newFactory(): SolicitudCertificadoFactory
    {
        return SolicitudCertificadoFactory::new();
    }

    public function estudiante(): BelongsTo
    {
        return $this->belongsTo(Estudiante::class);
    }

    public function matricula(): BelongsTo
    {
        return $this->belongsTo(Matricula::class);
    }

    public function revisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'atendido_por');
    }

    public function certificado(): BelongsTo
    {
        return $this->belongsTo(Certificado::class);
    }
}
