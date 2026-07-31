<?php

declare(strict_types=1);

namespace App\Modules\Notificaciones\Services;

use App\Modules\Notificaciones\Models\MensajeWhatsapp;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class MensajeWhatsappService
{
    /**
     * @param  array{tipo?: string, estado?: string, campania_id?: int}  $filtros
     */
    public function listar(array $filtros, int $porPagina = 20): LengthAwarePaginator
    {
        return MensajeWhatsapp::query()
            ->with(['estudiante', 'campania'])
            ->when($filtros['tipo'] ?? null, fn ($query, $tipo) => $query->where('tipo', $tipo))
            ->when($filtros['estado'] ?? null, fn ($query, $estado) => $query->where('estado', $estado))
            ->when($filtros['campania_id'] ?? null, fn ($query, $campaniaId) => $query->where('campania_id', $campaniaId))
            ->latest('id')
            ->paginate($porPagina);
    }
}
