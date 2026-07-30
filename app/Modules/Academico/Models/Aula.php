<?php

declare(strict_types=1);

namespace App\Modules\Academico\Models;

use App\Modules\Academico\Database\Factories\AulaFactory;
use App\Modules\Identidad\Support\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $nombre
 */
class Aula extends Model
{
    /** @use HasFactory<AulaFactory> */
    use Auditable, HasFactory;

    protected $fillable = [
        'nombre',
        'capacidad',
        'ubicacion',
        'activa',
    ];

    protected function casts(): array
    {
        return [
            'activa' => 'boolean',
        ];
    }

    protected static function newFactory(): AulaFactory
    {
        return AulaFactory::new();
    }

    public function horarios(): HasMany
    {
        return $this->hasMany(Horario::class);
    }
}
