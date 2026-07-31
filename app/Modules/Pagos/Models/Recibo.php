<?php

declare(strict_types=1);

namespace App\Modules\Pagos\Models;

use App\Modules\Identidad\Support\Auditable;
use App\Modules\Pagos\Database\Factories\ReciboFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * @property int $id
 * @property int $pago_id
 * @property string $numero_recibo
 * @property Carbon $emitido_en
 * @property-read Pago $pago
 */
class Recibo extends Model implements HasMedia
{
    /** @use HasFactory<ReciboFactory> */
    use Auditable, HasFactory, InteractsWithMedia;

    protected $table = 'recibos';

    protected $fillable = [
        'pago_id',
        'numero_recibo',
        'emitido_en',
    ];

    protected function casts(): array
    {
        return [
            'emitido_en' => 'datetime',
        ];
    }

    protected static function newFactory(): ReciboFactory
    {
        return ReciboFactory::new();
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('pdf')->singleFile();
    }

    public function pago(): BelongsTo
    {
        return $this->belongsTo(Pago::class);
    }
}
