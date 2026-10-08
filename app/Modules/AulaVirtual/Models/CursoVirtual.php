<?php

declare(strict_types=1);

namespace App\Modules\AulaVirtual\Models;

use App\Modules\Academico\Models\Horario;
use App\Modules\AulaVirtual\Database\Factories\CursoVirtualFactory;
use App\Modules\Identidad\Support\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int|null $horario_id
 * @property bool $activo
 * @property int|null $curso_id
 * @property int|null $grado_id
 * @property int|null $ciclo_id
 * @property int|null $docente_id
 * @property-read Horario $horario
 */
class CursoVirtual extends Model
{
    /** @use HasFactory<CursoVirtualFactory> */
    use Auditable, HasFactory;

    protected $table = 'aula_virtual_cursos';

    protected $fillable = [
        'horario_id',
        'curso_id',
        'grado_id',
        'ciclo_id',
        'docente_id',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
        ];
    }

    protected static function newFactory(): CursoVirtualFactory
    {
        return CursoVirtualFactory::new();
    }

    /**
     * Autocompleta curso/grado/ciclo/docente desde el horario al crear, si
     * no vienen seteados explícitamente -- así cualquier camino de creación
     * (servicio, factory, seeders) obtiene esas columnas "gratis" sin tener
     * que conocer el detalle. Son la clave de deduplicación: un mismo
     * curso+grado+ciclo+docente reutiliza el mismo curso virtual en vez de
     * crear uno nuevo por cada Horario (ver CursoVirtualService::activarParaHorario()).
     */
    protected static function booted(): void
    {
        static::creating(function (CursoVirtual $cursoVirtual) {
            if ($cursoVirtual->horario_id === null) {
                return;
            }

            $horario = $cursoVirtual->relationLoaded('horario')
                ? $cursoVirtual->horario
                : Horario::find($cursoVirtual->horario_id);

            if (! $horario) {
                return;
            }

            $cursoVirtual->curso_id ??= $horario->curso_id;
            $cursoVirtual->grado_id ??= $horario->grado_id;
            $cursoVirtual->ciclo_id ??= $horario->ciclo_id;
            $cursoVirtual->docente_id ??= $horario->docente_id;
        });
    }

    public function horario(): BelongsTo
    {
        return $this->belongsTo(Horario::class);
    }

    /**
     * @return HasMany<Material, $this>
     */
    public function materiales(): HasMany
    {
        return $this->hasMany(Material::class)->orderBy('orden');
    }

    /**
     * @return HasMany<ClaseGrabada, $this>
     */
    public function clasesGrabadas(): HasMany
    {
        return $this->hasMany(ClaseGrabada::class)->orderBy('orden');
    }

    /**
     * @return HasMany<Tarea, $this>
     */
    public function tareas(): HasMany
    {
        return $this->hasMany(Tarea::class);
    }

    /**
     * @return HasMany<Publicacion, $this>
     */
    public function publicaciones(): HasMany
    {
        return $this->hasMany(Publicacion::class);
    }

    /**
     * @return HasMany<Foro, $this>
     */
    public function foros(): HasMany
    {
        return $this->hasMany(Foro::class);
    }

    public function esDelDocente(int $userId): bool
    {
        return $this->docente_id === $userId;
    }
}
