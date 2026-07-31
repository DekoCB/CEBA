<?php

declare(strict_types=1);

namespace App\Modules\Pagos\Services;

use App\Modules\Pagos\Enums\TipoConceptoEnum;
use App\Modules\Pagos\Models\ConceptoPago;
use Illuminate\Database\Eloquent\Collection;

class ConceptoPagoService
{
    /**
     * @return Collection<int, ConceptoPago>
     */
    public function listar(): Collection
    {
        return ConceptoPago::query()->orderBy('nombre')->get();
    }

    /**
     * @return Collection<int, ConceptoPago>
     */
    public function activos(): Collection
    {
        return ConceptoPago::query()->where('activo', true)->orderBy('nombre')->get();
    }

    public function crear(string $nombre, TipoConceptoEnum $tipo, float $montoBase): ConceptoPago
    {
        return ConceptoPago::query()->create([
            'nombre' => $nombre,
            'tipo' => $tipo,
            'monto_base' => $montoBase,
            'activo' => true,
        ]);
    }

    public function actualizar(ConceptoPago $concepto, string $nombre, TipoConceptoEnum $tipo, float $montoBase, bool $activo): ConceptoPago
    {
        $concepto->update([
            'nombre' => $nombre,
            'tipo' => $tipo,
            'monto_base' => $montoBase,
            'activo' => $activo,
        ]);

        return $concepto;
    }
}
