<?php

declare(strict_types=1);

namespace App\Modules\Evaluaciones\Models;

use App\Modules\Academico\Models\Horario;
use App\Modules\Evaluaciones\Database\Factories\EvaluacionFactory;
use App\Modules\Evaluaciones\Enums\EstadoEvaluacionEnum;
use App\Modules\Identidad\Support\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $horario_id
 * @property string $nombre
 * @property Carbon $fecha
 * @property string|null $enlace_externo
 * @property EstadoEvaluacionEnum $estado
 * @property-read Horario $horario
 */
class Evaluacion extends Model
{
    /** @use HasFactory<EvaluacionFactory> */
    use Auditable, HasFactory;

    protected $table = 'evaluaciones';

    protected $fillable = [
        'horario_id',
        'nombre',
        'fecha',
        'enlace_externo',
        'estado',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'estado' => EstadoEvaluacionEnum::class,
        ];
    }

    protected static function newFactory(): EvaluacionFactory
    {
        return EvaluacionFactory::new();
    }

    public function horario(): BelongsTo
    {
        return $this->belongsTo(Horario::class);
    }

    /**
     * @return HasMany<Calificacion, $this>
     */
    public function calificaciones(): HasMany
    {
        return $this->hasMany(Calificacion::class);
    }

    public function estaPublicada(): bool
    {
        return $this->estado === EstadoEvaluacionEnum::PUBLICADA;
    }

    public function tieneEnlaceExterno(): bool
    {
        return $this->enlace_externo !== null;
    }
}
