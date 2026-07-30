<?php

declare(strict_types=1);

namespace App\Modules\AulaVirtual\Policies;

use App\Models\User;
use App\Modules\AulaVirtual\Models\CursoVirtual;
use App\Modules\Matricula\Models\Matricula;

class CursoVirtualPolicy
{
    /**
     * Dirección/Coordinador con supervisión general, el docente dueño del
     * curso, o un estudiante matriculado en el grado y ciclo del horario.
     */
    public function view(User $user, CursoVirtual $curso): bool
    {
        if ($user->hasPermissionTo('aula_virtual.ver')) {
            return true;
        }

        if ($curso->esDelDocente($user->id)) {
            return true;
        }

        return $this->estudianteMatriculado($user, $curso);
    }

    /**
     * Solo el docente dueño del curso puede administrar su contenido
     * (materiales, tareas, publicaciones, foros); Dirección puede
     * intervenir como excepción administrativa.
     */
    public function manage(User $user, CursoVirtual $curso): bool
    {
        if ($user->hasRole('direccion')) {
            return true;
        }

        return $user->hasPermissionTo('aula_virtual.gestionar_propio') && $curso->esDelDocente($user->id);
    }

    private function estudianteMatriculado(User $user, CursoVirtual $curso): bool
    {
        if (! $user->hasPermissionTo('aula_virtual.ver_propio')) {
            return false;
        }

        $estudiante = $user->estudiante;

        if (! $estudiante) {
            return false;
        }

        return Matricula::query()
            ->where('estudiante_id', $estudiante->id)
            ->where('ciclo_id', $curso->horario->ciclo_id)
            ->where('grado_id', $curso->horario->grado_id)
            ->where('estado', 'aprobada')
            ->exists();
    }
}
