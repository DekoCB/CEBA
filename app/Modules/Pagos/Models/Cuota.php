<?php

declare(strict_types=1);

namespace App\Modules\Pagos\Models;

use App\Modules\Identidad\Support\Auditable;
use App\Modules\Pagos\Database\Factories\CuotaFactory;
use App\Modules\Pagos\Enums\EstadoCuotaEnum;
use App\Modules\Pagos\Enums\EstadoPagoEnum;
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
 * @property Carbon|null $fecha_compromiso
 * @property float|null $monto_compromiso
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
        'fecha_compromiso',
        'monto_compromiso',
        'estado',
    ];

    protected function casts(): array
    {
        return [
            'monto' => 'decimal:2',
            'fecha_vencimiento' => 'date',
            'fecha_compromiso' => 'date',
            'monto_compromiso' => 'decimal:2',
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
     * Relación vieja, ya no es la fuente de verdad para montoPagado() (un
     * pago que cubre varias cuotas a la vez no fija cuota_id aquí) -- se
     * deja porque nada más la usa, pero no calcules saldos con ella.
     *
     * @return HasMany<Pago, $this>
     */
    public function pagos(): HasMany
    {
        return $this->hasMany(Pago::class);
    }

    /**
     * @return HasMany<PagoDestino, $this>
     */
    public function detallesPago(): HasMany
    {
        return $this->hasMany(PagoDestino::class);
    }

    public function estaVencida(): bool
    {
        return $this->estado === EstadoCuotaEnum::PENDIENTE && $this->fecha_vencimiento->isPast();
    }

    /**
     * Suma de los pagos ya aprobados vinculados a esta cuota -- una cuota
     * puede cubrirse en varios pagos parciales (ej. 40 hoy, 40 después), y
     * un mismo pago puede a su vez cubrir varias cuotas a la vez (ej. cuota
     * 1 completa + parte de la cuota 2), así que lo cobrado se suma por
     * destino (PagoDestino), no por Pago completo.
     */
    public function montoPagado(): float
    {
        return (float) $this->detallesPago()
            ->whereHas('pago', fn ($query) => $query->where('estado', EstadoPagoEnum::APROBADO))
            ->sum('monto');
    }

    /**
     * Lo que falta por cobrar de esta cuota. Nunca negativo: un pago que se
     * pasa del monto de la cuota no genera saldo "a favor" aquí.
     */
    public function saldoPendiente(): float
    {
        return max(0.0, (float) $this->monto - $this->montoPagado());
    }
}
