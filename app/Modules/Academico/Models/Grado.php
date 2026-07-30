<?php

declare(strict_types=1);

namespace App\Modules\Academico\Models;

use App\Modules\Academico\Database\Factories\GradoFactory;
use App\Modules\Academico\Enums\TipoPublicoEnum;
use App\Modules\Identidad\Support\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Grado extends Model
{
    /** @use HasFactory<GradoFactory> */
    use Auditable, HasFactory;

    protected $fillable = [
        'nombre',
        'tipo_publico',
        'orden',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'tipo_publico' => TipoPublicoEnum::class,
            'activo' => 'boolean',
        ];
    }

    protected static function newFactory(): GradoFactory
    {
        return GradoFactory::new();
    }

    public function cursos(): HasMany
    {
        return $this->hasMany(Curso::class);
    }

    public function horarios(): HasMany
    {
        return $this->hasMany(Horario::class);
    }
}
