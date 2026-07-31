<?php

declare(strict_types=1);

namespace App\Modules\Pagos\Models;

use App\Modules\Identidad\Support\Auditable;
use App\Modules\Pagos\Database\Factories\ConceptoPagoFactory;
use App\Modules\Pagos\Enums\TipoConceptoEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $nombre
 * @property TipoConceptoEnum $tipo
 * @property float $monto_base
 * @property bool $activo
 */
class ConceptoPago extends Model
{
    /** @use HasFactory<ConceptoPagoFactory> */
    use Auditable, HasFactory;

    protected $table = 'conceptos_pago';

    protected $fillable = [
        'nombre',
        'tipo',
        'monto_base',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'tipo' => TipoConceptoEnum::class,
            'monto_base' => 'decimal:2',
            'activo' => 'boolean',
        ];
    }

    protected static function newFactory(): ConceptoPagoFactory
    {
        return ConceptoPagoFactory::new();
    }
}
