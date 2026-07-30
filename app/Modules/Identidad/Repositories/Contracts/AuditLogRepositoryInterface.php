<?php

declare(strict_types=1);

namespace App\Modules\Identidad\Repositories\Contracts;

use App\Modules\Identidad\Models\AuditLog;
use App\Shared\Repositories\RepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * @extends RepositoryInterface<AuditLog>
 */
interface AuditLogRepositoryInterface extends RepositoryInterface
{
    /**
     * Historial de auditoría de un registro concreto, más reciente primero.
     *
     * @return Collection<int, AuditLog>
     */
    public function paraModelo(Model $modelo): Collection;
}
