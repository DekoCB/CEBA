<?php

declare(strict_types=1);

namespace App\Modules\Academico\Services;

use App\Modules\Academico\Enums\TipoPublicoEnum;
use App\Modules\Academico\Models\Grado;
use Illuminate\Database\Eloquent\Collection;

class GradoService
{
    /**
     * @return Collection<int, Grado>
     */
    public function todos(): Collection
    {
        return Grado::query()->orderBy('tipo_publico')->orderBy('orden')->get();
    }

    /**
     * @param  array{nombre: string, tipo_publico: TipoPublicoEnum, orden: int}  $datos
     */
    public function crear(array $datos): Grado
    {
        return Grado::query()->create($datos);
    }

    /**
     * @param  array{nombre: string, tipo_publico: TipoPublicoEnum, orden: int, activo: bool}  $datos
     */
    public function actualizar(Grado $grado, array $datos): Grado
    {
        $grado->update($datos);

        return $grado;
    }

    public function existeOrdenParaPublico(TipoPublicoEnum $publico, int $orden, ?int $exceptoId = null): bool
    {
        return Grado::query()
            ->where('tipo_publico', $publico)
            ->where('orden', $orden)
            ->when($exceptoId, fn ($query) => $query->whereKeyNot($exceptoId))
            ->exists();
    }
}
