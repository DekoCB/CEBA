<?php

declare(strict_types=1);

namespace App\Modules\Pagos\Models;

use App\Models\User;
use App\Modules\Identidad\Support\Auditable;
use App\Modules\Matricula\Models\Estudiante;
use App\Modules\Pagos\Database\Factories\PagoFactory;
use App\Modules\Pagos\Enums\EstadoPagoEnum;
use App\Modules\Pagos\Enums\MetodoPagoEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * @property int $id
 * @property int $estudiante_id
 * @property int $concepto_id
 * @property string|null $detalle
 * @property int|null $cuota_id
 * @property float $monto
 * @property MetodoPagoEnum $metodo
 * @property EstadoPagoEnum $estado
 * @property Carbon $fecha_pago
 * @property Carbon|null $fecha_aprobacion
 * @property string|null $motivo_rechazo
 * @property-read Estudiante|null $estudiante
 * @property-read ConceptoPago $concepto
 * @property-read Cuota|null $cuota
 */
class Pago extends Model implements HasMedia
{
    /** @use HasFactory<PagoFactory> */
    use Auditable, HasFactory, InteractsWithMedia;

    protected $table = 'pagos';

    protected $fillable = [
        'estudiante_id',
        'concepto_id',
        'detalle',
        'cuota_id',
        'monto',
        'metodo',
        'estado',
        'registrado_por',
        'aprobado_por',
        'fecha_pago',
        'fecha_aprobacion',
        'motivo_rechazo',
    ];

    protected function casts(): array
    {
        return [
            'monto' => 'decimal:2',
            'metodo' => MetodoPagoEnum::class,
            'estado' => EstadoPagoEnum::class,
            'fecha_pago' => 'date',
            'fecha_aprobacion' => 'datetime',
        ];
    }

    protected static function newFactory(): PagoFactory
    {
        return PagoFactory::new();
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('comprobante')->singleFile();
    }

    public function estudiante(): BelongsTo
    {
        return $this->belongsTo(Estudiante::class);
    }

    public function concepto(): BelongsTo
    {
        return $this->belongsTo(ConceptoPago::class, 'concepto_id');
    }

    public function cuota(): BelongsTo
    {
        return $this->belongsTo(Cuota::class);
    }

    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrado_por');
    }

    public function aprobadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'aprobado_por');
    }

    /**
     * @return HasOne<Recibo, $this>
     */
    public function recibo(): HasOne
    {
        return $this->hasOne(Recibo::class);
    }
}
