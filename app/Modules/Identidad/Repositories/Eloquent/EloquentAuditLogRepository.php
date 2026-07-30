<?php

declare(strict_types=1);

namespace App\Modules\Identidad\Repositories\Eloquent;

use App\Modules\Identidad\Models\AuditLog;
use App\Modules\Identidad\Repositories\Contracts\AuditLogRepositoryInterface;
use App\Shared\Repositories\BaseRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * @extends BaseRepository<AuditLog>
 */
class EloquentAuditLogRepository extends BaseRepository implements AuditLogRepositoryInterface
{
    public function __construct()
    {
        parent::__construct(AuditLog::class);
    }

    public function paraModelo(Model $modelo): Collection
    {
        return AuditLog::query()
            ->where('auditable_type', $modelo->getMorphClass())
            ->where('auditable_id', $modelo->getKey())
            ->latest('created_at')
            ->get();
    }
}
