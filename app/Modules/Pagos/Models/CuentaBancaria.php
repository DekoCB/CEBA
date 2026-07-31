<?php

declare(strict_types=1);

namespace App\Modules\Pagos\Models;

use App\Modules\Identidad\Support\Auditable;
use App\Modules\Pagos\Database\Factories\CuentaBancariaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * @property int $id
 * @property string $banco
 * @property string $numero_cuenta
 * @property string|null $cci
 * @property string $titular
 * @property bool $activa
 */
class CuentaBancaria extends Model implements HasMedia
{
    /** @use HasFactory<CuentaBancariaFactory> */
    use Auditable, HasFactory, InteractsWithMedia;

    protected $table = 'cuentas_bancarias';

    protected $fillable = [
        'banco',
        'numero_cuenta',
        'cci',
        'titular',
        'activa',
    ];

    protected function casts(): array
    {
        return [
            'activa' => 'boolean',
        ];
    }

    protected static function newFactory(): CuentaBancariaFactory
    {
        return CuentaBancariaFactory::new();
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('qr')->singleFile();
    }
}
