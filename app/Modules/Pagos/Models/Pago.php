<?php

declare(strict_types=1);

namespace App\Modules\Pagos\Models;

use App\Models\User;
use App\Modules\Identidad\Support\Auditable;
use App\Modules\Matricula\Models\Estudiante;
use App\Modules\Pagos\Database\Factories\PagoFactory;
use App\Modules\Pagos\Enums\EstadoPagoEnum;
use App\Modules\Pagos\Enums\MetodoPagoEnum;
use Illuminate\Database\Eloquent\Collection;
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
 * @property int $concepto_id
 * @property string|null $detalle
 * @property string|null $observacion
 * @property int|null $cuota_id
 * @property int|null $cargo_adicional_id
 * @property float $monto
 * @property MetodoPagoEnum $metodo
 * @property EstadoPagoEnum $estado
 * @property Carbon $fecha_pago
 * @property Carbon|null $fecha_aprobacion
 * @property string|null $motivo_rechazo
 * @property int|null $anulado_por
 * @property Carbon|null $fecha_anulacion
 * @property string|null $motivo_anulacion
 * @property-read Estudiante|null $estudiante
 * @property-read ConceptoPago $concepto
 * @property-read Cuota|null $cuota
 * @property-read CargoAdicional|null $cargoAdicional
 * @property-read Collection<int, PagoParte> $partes
 * @property-read Collection<int, PagoDestino> $destinos
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
        'observacion',
        'cuota_id',
        'cargo_adicional_id',
        'monto',
        'metodo',
        'estado',
        'registrado_por',
        'aprobado_por',
        'fecha_pago',
        'fecha_aprobacion',
        'motivo_rechazo',
        'anulado_por',
        'fecha_anulacion',
        'motivo_anulacion',
    ];

    protected function casts(): array
    {
        return [
            'monto' => 'decimal:2',
            'metodo' => MetodoPagoEnum::class,
            'estado' => EstadoPagoEnum::class,
            'fecha_pago' => 'date',
            'fecha_aprobacion' => 'datetime',
            'fecha_anulacion' => 'datetime',
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

    public function cargoAdicional(): BelongsTo
    {
        return $this->belongsTo(CargoAdicional::class);
    }

    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrado_por');
    }

    public function aprobadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'aprobado_por');
    }

    public function anuladoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'anulado_por');
    }

    /**
     * @return HasOne<Recibo, $this>
     */
    public function recibo(): HasOne
    {
        return $this->hasOne(Recibo::class);
    }

    /**
     * Los montos+métodos con los que se cubrió este pago -- un pago
     * "simple" tiene una sola parte; uno cubierto con más de un medio a la
     * vez (ej. efectivo + Yape) tiene varias, y su suma siempre es igual a
     * $monto. Ver PagoService::registrar().
     *
     * @return HasMany<PagoParte, $this>
     */
    public function partes(): HasMany
    {
        return $this->hasMany(PagoParte::class);
    }

    /**
     * Las cosas que cubre este pago -- una cuota, un cargo adicional, o un
     * concepto suelto del catálogo, cada uno con su propio monto. Un pago
     * "simple" tiene un solo destino (el caso de siempre, reflejado también
     * en concepto_id/cuota_id/cargo_adicional_id/detalle de este mismo
     * Pago); uno que cubre varias cuotas/conceptos a la vez tiene varios, y
     * su suma siempre es igual a $monto. Ver PagoService::registrar().
     *
     * @return HasMany<PagoDestino, $this>
     */
    public function destinos(): HasMany
    {
        return $this->hasMany(PagoDestino::class);
    }

    /**
     * El texto de "medio de pago" para mostrar en resumen (recibo, cola de
     * aprobación, historial): si el pago tiene una sola parte, usa su
     * método con nota (ej. "Yape Walter"); si tiene varias con métodos
     * distintos, se queda con el resumen agregado ("Mixto") -- el detalle
     * de cada parte con su nota ya se muestra aparte en esos casos.
     */
    public function medioPagoResumen(): string
    {
        return $this->partes->count() === 1
            ? $this->partes->first()->metodoConNota()
            : $this->metodo->label();
    }

    /**
     * El nombre de concepto para mostrar. Con un solo destino (el caso de
     * siempre) se queda igual que antes: si el pago es por un cargo
     * adicional (Convalidación, Exoneración...), usa su propio texto libre
     * en vez del concepto ancla genérico (tipo "Otro") con el que se
     * guarda -- ver PagoService::registrar()/CargoAdicional. concepto_id
     * sigue siendo obligatorio siempre, así que $this->concepto nunca es
     * null. Con varios destinos (un pago que cubre más de una cosa a la
     * vez), devuelve un resumen de todos, unidos por coma.
     */
    public function nombreConcepto(): string
    {
        if ($this->destinos->count() > 1) {
            return $this->destinos
                ->map(fn (PagoDestino $destino) => $destino->cargoAdicional !== null ? $destino->cargoAdicional->concepto : $destino->concepto->nombre)
                ->implode(', ');
        }

        if ($this->cargoAdicional !== null) {
            return $this->cargoAdicional->concepto;
        }

        return $this->concepto->nombre;
    }
}
