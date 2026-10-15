<?php

declare(strict_types=1);

namespace App\Modules\Pagos\Models;

use App\Modules\Identidad\Support\Auditable;
use App\Modules\Matricula\Models\Estudiante;
use App\Modules\Pagos\Database\Factories\CargoAdicionalFactory;
use App\Modules\Pagos\Enums\EstadoCuotaEnum;
use App\Modules\Pagos\Enums\EstadoPagoEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Un cobro futuro puntual para un estudiante en particular (Convalidación,
 * Exoneración, Recuperación, Visación, etc.) -- concepto y monto libres,
 * a diferencia del catálogo compartido ConceptoPago. Mismo patrón que
 * Cuota: montoPagado()/saldoPendiente() se calculan desde los Pagos
 * aprobados reales, nunca desde el monto nominal.
 *
 * @property int $id
 * @property int $estudiante_id
 * @property string $concepto
 * @property float $monto
 * @property EstadoCuotaEnum $estado
 * @property Carbon|null $fecha_compromiso
 * @property float|null $monto_compromiso
 * @property int|null $registrado_por
 * @property-read Estudiante $estudiante
 */
class CargoAdicional extends Model
{
    /** @use HasFactory<CargoAdicionalFactory> */
    use Auditable, HasFactory;

    protected $table = 'cargos_adicionales';

    protected $fillable = [
        'estudiante_id',
        'concepto',
        'monto',
        'estado',
        'fecha_compromiso',
        'monto_compromiso',
        'registrado_por',
    ];

    protected function casts(): array
    {
        return [
            'monto' => 'decimal:2',
            'estado' => EstadoCuotaEnum::class,
            'fecha_compromiso' => 'date',
            'monto_compromiso' => 'decimal:2',
        ];
    }

    protected static function newFactory(): CargoAdicionalFactory
    {
        return CargoAdicionalFactory::new();
    }

    public function estudiante(): BelongsTo
    {
        return $this->belongsTo(Estudiante::class);
    }

    /**
     * Relación vieja, ya no es la fuente de verdad para montoPagado() -- ver
     * el mismo comentario en Cuota::pagos().
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

    /**
     * Suma de los pagos ya aprobados vinculados a este cargo -- puede
     * cubrirse en varios pagos parciales, igual que Cuota::montoPagado(), y
     * un mismo pago puede cubrir este cargo junto con otras cosas a la vez.
     */
    public function montoPagado(): float
    {
        return (float) $this->detallesPago()
            ->whereHas('pago', fn ($query) => $query->where('estado', EstadoPagoEnum::APROBADO))
            ->sum('monto');
    }

    /**
     * Lo que falta por cobrar de este cargo. Nunca negativo.
     */
    public function saldoPendiente(): float
    {
        return max(0.0, (float) $this->monto - $this->montoPagado());
    }
}
