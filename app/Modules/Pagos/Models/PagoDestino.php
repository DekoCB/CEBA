<?php

declare(strict_types=1);

namespace App\Modules\Pagos\Models;

use App\Modules\Pagos\Database\Factories\PagoDestinoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Una cosa que un Pago cubre -- una Cuota, un CargoAdicional, o un concepto
 * suelto del catálogo sin cuota/cargo vinculado (Matrícula, Certificado...).
 * Un pago puede tener varios destinos a la vez (ej. cuota 1 completa + parte
 * de la cuota 2, en un solo pago); la suma de sus montos debe coincidir con
 * Pago::$monto. Distinto de PagoParte, que reparte por MEDIO de pago
 * (efectivo/Yape), no por concepto.
 *
 * @property int $id
 * @property int $pago_id
 * @property int $concepto_id
 * @property int|null $cuota_id
 * @property int|null $cargo_adicional_id
 * @property string|null $detalle
 * @property float $monto
 * @property-read Pago $pago
 * @property-read ConceptoPago $concepto
 * @property-read Cuota|null $cuota
 * @property-read CargoAdicional|null $cargoAdicional
 */
class PagoDestino extends Model
{
    /** @use HasFactory<PagoDestinoFactory> */
    use HasFactory;

    protected $table = 'pago_destinos';

    protected $fillable = [
        'pago_id',
        'concepto_id',
        'cuota_id',
        'cargo_adicional_id',
        'detalle',
        'monto',
    ];

    protected function casts(): array
    {
        return [
            'monto' => 'decimal:2',
        ];
    }

    protected static function newFactory(): PagoDestinoFactory
    {
        return PagoDestinoFactory::new();
    }

    /**
     * @return BelongsTo<Pago, $this>
     */
    public function pago(): BelongsTo
    {
        return $this->belongsTo(Pago::class);
    }

    /**
     * @return BelongsTo<ConceptoPago, $this>
     */
    public function concepto(): BelongsTo
    {
        return $this->belongsTo(ConceptoPago::class, 'concepto_id');
    }

    /**
     * @return BelongsTo<Cuota, $this>
     */
    public function cuota(): BelongsTo
    {
        return $this->belongsTo(Cuota::class);
    }

    /**
     * @return BelongsTo<CargoAdicional, $this>
     */
    public function cargoAdicional(): BelongsTo
    {
        return $this->belongsTo(CargoAdicional::class);
    }

    /**
     * El nombre para mostrar en el recibo/listados: si es un cargo
     * adicional, usa su propio texto libre (igual criterio que
     * Pago::nombreConcepto()); si no, el nombre del concepto del catálogo,
     * con "— Cuota N.° X de Y (Completo/Parcial)" pegado si está vinculado
     * a una cuota (antes vivía en una caja aparte del pie del recibo, que
     * solo podía representar una sola cuota por pago), y el detalle libre
     * (si lo hay) al final.
     */
    public function descripcion(): string
    {
        $nombre = $this->cargoAdicional !== null
            ? $this->cargoAdicional->concepto
            : $this->concepto->nombre;

        if ($this->cuota !== null) {
            $estado = $this->cuota->saldoPendiente() <= 0.0 ? 'Completo' : 'Parcial';
            $nombre .= " — Cuota N.° {$this->cuota->numero} de {$this->cuota->planPago->numero_cuotas} ({$estado})";
        }

        if ($this->detalle) {
            $nombre .= " — {$this->detalle}";
        }

        return $nombre;
    }
}
