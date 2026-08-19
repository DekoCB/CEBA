<?php

declare(strict_types=1);

namespace App\Modules\Matricula\Models;

use App\Models\User;
use App\Modules\Academico\Models\Ciclo;
use App\Modules\Academico\Models\Grado;
use App\Modules\Academico\Models\Horario;
use App\Modules\Identidad\Support\Auditable;
use App\Modules\Matricula\Database\Factories\MatriculaFactory;
use App\Modules\Matricula\Enums\EstadoMatriculaEnum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * @property int $id
 * @property int $estudiante_id
 * @property int $ciclo_id
 * @property int $grado_id
 * @property int|null $horario_id
 * @property Carbon $fecha_matricula
 * @property EstadoMatriculaEnum $estado
 * @property-read Estudiante|null $estudiante
 * @property-read Ciclo $ciclo
 * @property-read Grado $grado
 * @property-read Horario|null $horario
 */
class Matricula extends Model implements HasMedia
{
    /** @use HasFactory<MatriculaFactory> */
    use Auditable, HasFactory, InteractsWithMedia, SoftDeletes;

    protected $fillable = [
        'estudiante_id',
        'ciclo_id',
        'grado_id',
        'horario_id',
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

    public function horario(): BelongsTo
    {
        return $this->belongsTo(Horario::class);
    }

    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrado_por');
    }

    /**
     * Las matrículas que cuentan para un horario dado: mismo grado y
     * ciclo, aprobadas, y -- si ese horario declara una sección propia
     * (Grupo A/B) -- solo las que no eligieron ninguna sección (les
     * aplica cualquier horario de su grado) o que eligieron esa misma
     * sección; nunca las de la sección contraria. Si el horario consultado
     * no tiene sección (un curso del grado sin dividir en grupos), no se
     * filtra en absoluto: cualquier matrícula del grado y ciclo cuenta, sin
     * importar a qué horario específico haya quedado apuntando su
     * horario_id por otro curso que sí esté dividido.
     */
    public function scopeDelHorario(Builder $query, Horario $horario): Builder
    {
        $query->where('grado_id', $horario->grado_id)
            ->where('ciclo_id', $horario->ciclo_id)
            ->where('estado', 'aprobada');

        if ($horario->seccion !== null) {
            $query->where(function (Builder $query) use ($horario) {
                $query->whereNull('horario_id')
                    ->orWhereHas('horario', fn (Builder $query) => $query->where('seccion', $horario->seccion));
            });
        }

        return $query;
    }
}
