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
     * El docente dueño del curso administra su contenido (materiales,
     * tareas, publicaciones, foros); Dirección y Coordinador pueden
     * intervenir en cualquier curso, dada su supervisión curricular.
     */
    public function manage(User $user, CursoVirtual $curso): bool
    {
        if ($user->hasRole('direccion') || $user->hasRole('coordinador')) {
            return true;
        }

        return $user->hasPermissionTo('aula_virtual.gestionar_propio') && $curso->esDelDocente($user->id);
    }

    /**
     * Si el horario pertenece a una sección (Grupo A/B) real, solo cuenta
     * como matriculado el estudiante de esa misma sección -- sin este
     * chequeo, cualquier estudiante del grado/ciclo podía entrar al aula
     * virtual de la OTRA sección con solo conocer su URL, aunque nunca se
     * le mostrara el enlace (ver Matricula::scopeDelHorario()).
     */
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
            ->delHorario($curso->horario)
            ->exists();
    }
}
