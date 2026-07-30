<?php

declare(strict_types=1);

namespace App\Modules\Identidad\Services;

use App\Models\User;
use App\Modules\Identidad\DTOs\SesionActiva;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Gestiona las sesiones activas guardadas en la tabla `sessions`
 * (SESSION_DRIVER=database). Revocar una sesión aquí la invalida en la
 * siguiente petición de ese navegador, sin esperar a que expire.
 */
class SessionControlService
{
    /**
     * @return Collection<int, SesionActiva>
     */
    public function sesionesDe(User $usuario, string $sesionActualId): Collection
    {
        return DB::table('sessions')
            ->where('user_id', $usuario->id)
            ->orderByDesc('last_activity')
            ->get()
            ->map(fn ($sesion) => new SesionActiva(
                id: (string) $sesion->id,
                ipAddress: $sesion->ip_address,
                userAgent: $sesion->user_agent,
                lastActivity: (int) $sesion->last_activity,
                esActual: $sesion->id === $sesionActualId,
            ));
    }

    public function revocar(string $sesionId): void
    {
        DB::table('sessions')->where('id', $sesionId)->delete();
    }

    public function revocarTodasMenosActual(User $usuario, string $sesionActualId): void
    {
        DB::table('sessions')
            ->where('user_id', $usuario->id)
            ->where('id', '!=', $sesionActualId)
            ->delete();
    }
}
