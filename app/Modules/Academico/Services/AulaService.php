<?php

declare(strict_types=1);

namespace App\Modules\Academico\Services;

use App\Modules\Academico\Models\Aula;
use Illuminate\Database\Eloquent\Collection;

class AulaService
{
    /**
     * @return Collection<int, Aula>
     */
    public function todas(): Collection
    {
        return Aula::query()->orderBy('nombre')->get();
    }

    /**
     * @param  array{nombre: string, capacidad: int, ubicacion: ?string}  $datos
     */
    public function crear(array $datos): Aula
    {
        return Aula::query()->create($datos);
    }

    /**
     * @param  array{nombre: string, capacidad: int, ubicacion: ?string, activa: bool}  $datos
     */
    public function actualizar(Aula $aula, array $datos): Aula
    {
        $aula->update($datos);

        return $aula;
    }
}
