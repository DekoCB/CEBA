<?php

declare(strict_types=1);

namespace App\Modules\Academico\Models;

use App\Modules\Academico\Database\Factories\CursoFactory;
use App\Modules\Identidad\Support\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $grado_id
 * @property string $nombre
 * @property string $codigo
 */
class Curso extends Model
{
    /** @use HasFactory<CursoFactory> */
    use Auditable, HasFactory;

    protected $fillable = [
        'nombre',
        'codigo',
        'grado_id',
        'horas',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
        ];
    }

    protected static function newFactory(): CursoFactory
    {
        return CursoFactory::new();
    }

    public function grado(): BelongsTo
    {
        return $this->belongsTo(Grado::class);
    }

    public function horarios(): HasMany
    {
        return $this->hasMany(Horario::class);
    }

    /**
     * Ícono Heroicon (sin el prefijo "heroicon-o-") que representa al curso
     * mientras no exista una imagen propia. Ver <x-curso-portada>.
     */
    public function iconoRepresentativo(): string
    {
        $nombre = str_replace(
            ['á', 'é', 'í', 'ó', 'ú'],
            ['a', 'e', 'i', 'o', 'u'],
            mb_strtolower($this->nombre),
        );

        return match (true) {
            str_contains($nombre, 'matemat') => 'calculator',
            str_contains($nombre, 'comunicac') => 'chat-bubble-left-right',
            str_contains($nombre, 'ingles'), str_contains($nombre, 'idioma') => 'language',
            str_contains($nombre, 'ciencias sociales'), str_contains($nombre, 'historia'), str_contains($nombre, 'geografia') => 'globe-americas',
            str_contains($nombre, 'ciencia y tecnolog'), str_contains($nombre, 'ciencias naturales') => 'beaker',
            str_contains($nombre, 'computo'), str_contains($nombre, 'computacion'), str_contains($nombre, 'informatica') => 'computer-desktop',
            str_contains($nombre, 'arte') => 'paint-brush',
            str_contains($nombre, 'musica') => 'musical-note',
            str_contains($nombre, 'educacion fisica'), str_contains($nombre, 'deporte') => 'trophy',
            str_contains($nombre, 'religio') => 'academic-cap',
            default => 'book-open',
        };
    }
}
