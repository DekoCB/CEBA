<?php

declare(strict_types=1);

namespace App\Modules\Academico\Models;

use App\Models\User;
use App\Modules\Academico\Database\Factories\HorarioFactory;
use App\Modules\Identidad\Support\Auditable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $curso_id
 * @property int $docente_id
 * @property int $ciclo_id
 * @property int $grado_id
 * @property string|null $seccion
 * @property-read Curso $curso
 * @property-read User $docente
 * @property-read Aula $aula
 * @property-read Grado $grado
 * @property-read Collection<int, HorarioDia> $dias
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
    ];

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

    /**
     * @return HasMany<HorarioDia, $this>
     */
    public function dias(): HasMany
    {
        return $this->hasMany(HorarioDia::class)->orderBy('id');
    }

    /**
     * El día (con su horario propio) que corresponde a esta fecha, o null
     * si este horario no dicta clase ese día de la semana.
     */
    public function diaParaFecha(Carbon $fecha): ?HorarioDia
    {
        return $this->dias->first(
            fn (HorarioDia $dia) => $dia->dia_semana->numeroCarbon() === $fecha->dayOfWeek,
        );
    }

    /**
     * Resumen legible de los días y horarios, p. ej. "Lunes y Miércoles
     * 18:00–20:00" cuando todos comparten el mismo horario, o
     * "Lunes 18:00–20:00, Martes 16:00–18:00" cuando difieren.
     */
    public function diasResumen(): string
    {
        $dias = $this->dias;

        if ($dias->isEmpty()) {
            return '—';
        }

        $primero = $dias->first();
        $mismoHorario = $dias->every(
            fn (HorarioDia $dia) => $dia->hora_inicio === $primero->hora_inicio && $dia->hora_fin === $primero->hora_fin,
        );

        if ($mismoHorario) {
            return $this->nombresDias($dias).' '.$this->rangoHorario($primero);
        }

        return $dias
            ->map(fn (HorarioDia $dia) => $dia->dia_semana->label().' '.$this->rangoHorario($dia))
            ->implode(', ');
    }

    /**
     * @param  Collection<int, HorarioDia>  $dias
     */
    private function nombresDias(Collection $dias): string
    {
        $nombres = $dias->map(fn (HorarioDia $dia) => $dia->dia_semana->label());

        if ($nombres->count() === 1) {
            return $nombres->first();
        }

        return $nombres->slice(0, -1)->implode(', ').' y '.$nombres->last();
    }

    private function rangoHorario(HorarioDia $dia): string
    {
        return substr($dia->hora_inicio, 0, 5).'–'.substr($dia->hora_fin, 0, 5);
    }
}
