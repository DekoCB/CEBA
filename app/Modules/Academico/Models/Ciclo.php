<?php

declare(strict_types=1);

namespace App\Modules\Academico\Models;

use App\Modules\Academico\Database\Factories\CicloFactory;
use App\Modules\Academico\Enums\EstadoCicloEnum;
use App\Modules\Academico\Enums\TipoCicloEnum;
use App\Modules\Identidad\Support\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $nombre
 * @property Carbon $fecha_inicio
 * @property Carbon $fecha_fin
 * @property TipoCicloEnum $tipo
 * @property EstadoCicloEnum $estado
 */
class Ciclo extends Model
{
    /** @use HasFactory<CicloFactory> */
    use Auditable, HasFactory;

    protected $fillable = [
        'nombre',
        'tipo',
        'anio',
        'fecha_inicio',
        'fecha_fin',
        'estado',
    ];

    protected function casts(): array
    {
        return [
            'tipo' => TipoCicloEnum::class,
            'estado' => EstadoCicloEnum::class,
            'fecha_inicio' => 'date',
            'fecha_fin' => 'date',
        ];
    }

    protected static function newFactory(): CicloFactory
    {
        return CicloFactory::new();
    }

    /**
     * @return HasMany<PeriodoMatricula, $this>
     */
    public function periodosMatricula(): HasMany
    {
        return $this->hasMany(PeriodoMatricula::class);
    }

    /**
     * @return HasMany<Horario, $this>
     */
    public function horarios(): HasMany
    {
        return $this->hasMany(Horario::class);
    }
}
