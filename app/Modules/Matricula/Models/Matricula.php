<?php

declare(strict_types=1);

namespace App\Modules\Matricula\Models;

use App\Models\User;
use App\Modules\Academico\Models\Ciclo;
use App\Modules\Academico\Models\Grado;
use App\Modules\Identidad\Support\Auditable;
use App\Modules\Matricula\Database\Factories\MatriculaFactory;
use App\Modules\Matricula\Enums\EstadoMatriculaEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * @property int $estudiante_id
 * @property int $ciclo_id
 * @property int $grado_id
 * @property Carbon $fecha_matricula
 * @property EstadoMatriculaEnum $estado
 * @property-read Estudiante $estudiante
 * @property-read Ciclo $ciclo
 * @property-read Grado $grado
 */
class Matricula extends Model implements HasMedia
{
    /** @use HasFactory<MatriculaFactory> */
    use Auditable, HasFactory, InteractsWithMedia, SoftDeletes;

    protected $fillable = [
        'estudiante_id',
        'ciclo_id',
        'grado_id',
        'fecha_matricula',
        'estado',
        'observaciones',
        'registrado_por',
    ];

    protected function casts(): array
    {
        return [
            'fecha_matricula' => 'date',
            'estado' => EstadoMatriculaEnum::class,
        ];
    }

    protected static function newFactory(): MatriculaFactory
    {
        return MatriculaFactory::new();
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('ficha')->singleFile();
        $this->addMediaCollection('constancia')->singleFile();
    }

    public function estudiante(): BelongsTo
    {
        return $this->belongsTo(Estudiante::class);
    }

    public function ciclo(): BelongsTo
    {
        return $this->belongsTo(Ciclo::class);
    }

    public function grado(): BelongsTo
    {
        return $this->belongsTo(Grado::class);
    }

    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrado_por');
    }
}
