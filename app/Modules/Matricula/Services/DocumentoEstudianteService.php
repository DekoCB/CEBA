<?php

declare(strict_types=1);

namespace App\Modules\Matricula\Services;

use App\Modules\Matricula\Enums\TipoDocumentoEnum;
use App\Modules\Matricula\Models\DocumentoEstudiante;
use App\Modules\Matricula\Models\Estudiante;
use Illuminate\Http\UploadedFile;

class DocumentoEstudianteService
{
    public function subir(Estudiante $estudiante, TipoDocumentoEnum $tipo, UploadedFile $archivo, ?int $subidoPor): DocumentoEstudiante
    {
        /** @var DocumentoEstudiante $documento */
        $documento = $estudiante->documentos()->updateOrCreate(
            ['tipo' => $tipo],
            ['subido_por' => $subidoPor, 'verificado' => false],
        );

        $documento->addMedia($archivo)
            ->usingFileName($tipo->value.'-'.$estudiante->id.'.'.$archivo->getClientOriginalExtension())
            ->toMediaCollection('archivo');

        return $documento;
    }

    public function verificar(DocumentoEstudiante $documento): void
    {
        $documento->update(['verificado' => true]);
    }
}
