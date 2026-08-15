<?php

declare(strict_types=1);

namespace App\Modules\Academico\Models;

use App\Models\User;
use App\Modules\Academico\Database\Factories\HorarioFactory;
use App\Modules\Academico\Enums\DiaSemanaEnum;
use App\Modules\Identidad\Support\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $curso_id
 * @property int $docente_id
 * @property int $ciclo_id
 * @property int $grado_id
 * @property string|null $seccion
 * @property string $hora_inicio
 * @property string $hora_fin
 * @property DiaSemanaEnum $dia_semana
 * @property-read Curso $curso
 * @property-read User $docente
 * @property-read Aula $aula
 * @property-read Grado $grado
 */
class Horario extends Model
{
    /** @use HasFactory<HorarioFactory> */
    use Auditable, HasFactory;

    protected $fillable = [
        'curso_id',
        'docente_id',
        'aula_id',
        'ciclo_id',
        'grado_id',
        'seccion',
        'dia_semana',
        'hora_inicio',
        'hora_fin',
    ];

    protected function casts(): array
    {
        return [
            'dia_semana' => DiaSemanaEnum::class,
        ];
    }

    protected static function newFactory(): HorarioFactory
    {
        return HorarioFactory::new();
    }

    public function curso(): BelongsTo
    {
        return $this->belongsTo(Curso::class);
    }

    public function docente(): BelongsTo
    {
        return $this->belongsTo(User::class, 'docente_id');
    }

    public function aula(): BelongsTo
    {
        return $this->belongsTo(Aula::class);
    }

    /**
     * @return BelongsTo<Ciclo, $this>
     */
    public function ciclo(): BelongsTo
    {
        return $this->belongsTo(Ciclo::class);
    }

    public function grado(): BelongsTo
    {
        return $this->belongsTo(Grado::class);
    }
}
