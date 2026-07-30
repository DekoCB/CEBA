<?php

declare(strict_types=1);

namespace App\Modules\AulaVirtual\Services;

use App\Modules\AulaVirtual\Models\CursoVirtual;
use App\Modules\AulaVirtual\Models\Foro;
use App\Modules\AulaVirtual\Models\ForoRespuesta;

class ForoService
{
    public function crear(CursoVirtual $curso, int $autorId, string $titulo, ?string $descripcion): Foro
    {
        return $curso->foros()->create([
            'autor_id' => $autorId,
            'titulo' => $titulo,
            'descripcion' => $descripcion,
        ]);
    }

    public function responder(Foro $foro, int $autorId, string $contenido): ForoRespuesta
    {
        return $foro->respuestas()->create([
            'autor_id' => $autorId,
            'contenido' => $contenido,
        ]);
    }
}
