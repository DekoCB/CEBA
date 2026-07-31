<?php

declare(strict_types=1);

namespace App\Modules\Certificados\Models;

use App\Models\User;
use App\Modules\Certificados\Database\Factories\CertificadoFactory;
use App\Modules\Identidad\Support\Auditable;
use App\Modules\Matricula\Models\Estudiante;
use App\Modules\Matricula\Models\Matricula;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * @property int $id
 * @property int $estudiante_id
 * @property int|null $matricula_id
 * @property string $numero
 * @property string $codigo_verificacion
 * @property bool $es_duplicado
 * @property int|null $certificado_original_id
 * @property int $emitido_por
 * @property Carbon $fecha_emision
 * @property string|null $observaciones
 * @property-read Estudiante $estudiante
 * @property-read Matricula|null $matricula
 * @property-read Certificado|null $original
 * @property-read User $emisor
 */
class Certificado extends Model implements HasMedia
{
    /** @use HasFactory<CertificadoFactory> */
    use Auditable, HasFactory, InteractsWithMedia;

    protected $table = 'certificados';

    protected $fillable = [
        'estudiante_id',
        'matricula_id',
        'numero',
        'codigo_verificacion',
        'es_duplicado',
        'certificado_original_id',
        'emitido_por',
        'fecha_emision',
        'observaciones',
    ];

    protected function casts(): array
    {
        return [
            'es_duplicado' => 'boolean',
            'fecha_emision' => 'datetime',
        ];
    }

    protected static function newFactory(): CertificadoFactory
    {
        return CertificadoFactory::new();
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('pdf')->singleFile();
    }

    public function estudiante(): BelongsTo
    {
        return $this->belongsTo(Estudiante::class);
    }

    public function matricula(): BelongsTo
    {
        return $this->belongsTo(Matricula::class);
    }

    public function original(): BelongsTo
    {
        return $this->belongsTo(self::class, 'certificado_original_id');
    }

    public function duplicados(): HasMany
    {
        return $this->hasMany(self::class, 'certificado_original_id');
    }

    public function emisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'emitido_por');
    }

    public function solicitud(): HasOne
    {
        return $this->hasOne(SolicitudCertificado::class);
    }
}
