<?php

declare(strict_types=1);

namespace App\Modules\Pagos\Models;

use App\Modules\Identidad\Support\Auditable;
use App\Modules\Pagos\Database\Factories\CuotaFactory;
use App\Modules\Pagos\Enums\EstadoCuotaEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $plan_pago_id
 * @property int $numero
 * @property float $monto
 * @property Carbon $fecha_vencimiento
 * @property EstadoCuotaEnum $estado
 * @property-read PlanPago $planPago
 */
class Cuota extends Model
{
    /** @use HasFactory<CuotaFactory> */
    use Auditable, HasFactory;

    protected $table = 'cuotas';

    protected $fillable = [
        'plan_pago_id',
        'numero',
        'monto',
        'fecha_vencimiento',
        'estado',
    ];

    protected function casts(): array
    {
        return [
            'monto' => 'decimal:2',
            'fecha_vencimiento' => 'date',
            'estado' => EstadoCuotaEnum::class,
        ];
    }

    protected static function newFactory(): CuotaFactory
    {
        return CuotaFactory::new();
    }

    public function planPago(): BelongsTo
    {
        return $this->belongsTo(PlanPago::class);
    }

    /**
     * @return HasMany<Pago, $this>
     */
    public function pagos(): HasMany
    {
        return $this->hasMany(Pago::class);
    }

    public function estaVencida(): bool
    {
        return $this->estado === EstadoCuotaEnum::PENDIENTE && $this->fecha_vencimiento->isPast();
    }
}
