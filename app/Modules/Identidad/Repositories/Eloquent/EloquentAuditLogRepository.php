<?php

declare(strict_types=1);

namespace App\Modules\Identidad\Repositories\Eloquent;

use App\Modules\Identidad\Models\AuditLog;
use App\Modules\Identidad\Repositories\Contracts\AuditLogRepositoryInterface;
use App\Shared\Repositories\BaseRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * @extends BaseRepository<AuditLog>
 */
class EloquentAuditLogRepository extends BaseRepository implements AuditLogRepositoryInterface
{
    protected function query(): Builder
    {
        return AuditLog::query();
    }

    public function paraModelo(Model $modelo): Collection
    {
        return AuditLog::query()
            ->with('user:id,name')
            ->where('auditable_type', $modelo->getMorphClass())
            ->where('auditable_id', $modelo->getKey())
            // created_at es un timestamp de resolución de segundo: dos
            // cambios en el mismo segundo (p. ej. en pruebas, o ediciones
            // seguidas) empatan ahí, así que el id (autoincremental) desempata
            // y garantiza el orden cronológico real.
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();
    }

    public function buscar(?string $evento, ?string $tipoModelo, int $perPage = 20): LengthAwarePaginator
    {
        return AuditLog::query()
            ->with('user:id,name')
            ->when($evento, fn ($query) => $query->where('event', $evento))
            ->when($tipoModelo, fn ($query) => $query->where('auditable_type', $tipoModelo))
            ->latest('created_at')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function tiposDeModeloAuditados(): array
    {
        return AuditLog::query()
            ->distinct()
            ->orderBy('auditable_type')
            ->pluck('auditable_type')
            ->all();
    }
}
