<?php

declare(strict_types=1);

namespace App\Modules\AulaVirtual\Services;

use App\Modules\AulaVirtual\Enums\TipoMaterialEnum;
use App\Modules\AulaVirtual\Models\CursoVirtual;
use App\Modules\AulaVirtual\Models\Material;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

class MaterialService
{
    public function crear(CursoVirtual $curso, TipoMaterialEnum $tipo, string $titulo, ?string $url, ?UploadedFile $archivo): Material
    {
        if ($tipo->requiereArchivo() && ! $archivo) {
            throw ValidationException::withMessages([
                'archivo' => "Un material de tipo «{$tipo->label()}» necesita un archivo.",
            ]);
        }

        if (! $tipo->requiereArchivo() && ! $url) {
            throw ValidationException::withMessages([
                'url' => "Un material de tipo «{$tipo->label()}» necesita una URL.",
            ]);
        }

        $material = $curso->materiales()->create([
            'tipo' => $tipo,
            'titulo' => $titulo,
            'url' => $tipo->requiereArchivo() ? null : $url,
            'orden' => $curso->materiales()->count(),
        ]);

        if ($archivo) {
            $material->addMedia($archivo)->toMediaCollection('archivo');
        }

        return $material;
    }

    public function eliminar(Material $material): void
    {
        $material->delete();
    }
}
