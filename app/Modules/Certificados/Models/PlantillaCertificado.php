<?php

declare(strict_types=1);

namespace App\Modules\Certificados\Models;

use App\Modules\Certificados\Enums\TipoDocumentoEnum;
use App\Modules\Identidad\Support\Auditable;
use Illuminate\Database\Eloquent\Model;

/**
 * Formato y texto de cada tipo de documento (certificado de estudios y las
 * constancias), editable desde la UI de administración en vez de vivir
 * hardcodeado en resources/views/pdf. Una fila por tipo -- no hay "varias
 * plantillas" entre las que elegir para un mismo tipo, es el formato
 * institucional de ese documento.
 *
 * @property int $id
 * @property TipoDocumentoEnum $tipo
 * @property string $institucion
 * @property string $titulo
 * @property string $cuerpo
 * @property string|null $pie_nota
 * @property string $color_acento
 */
class PlantillaCertificado extends Model
{
    use Auditable;

    protected $table = 'plantilla_certificados';

    protected $fillable = [
        'tipo',
        'institucion',
        'titulo',
        'cuerpo',
        'pie_nota',
        'color_acento',
    ];

    protected function casts(): array
    {
        return [
            'tipo' => TipoDocumentoEnum::class,
        ];
    }

    /**
     * Placeholders disponibles en $cuerpo, documentados también en la UI de
     * edición: {{estudiante}}, {{dni}}, {{detalle_matricula}} (la frase
     * completa sobre el grado/ciclo cursado, o el texto alterno si el
     * documento no está ligado a una matrícula).
     */
    public static function paraTipo(TipoDocumentoEnum $tipo): self
    {
        return self::query()->firstOrCreate(['tipo' => $tipo], self::valoresPorDefecto($tipo));
    }

    /**
     * @return array{institucion: string, titulo: string, cuerpo: string, pie_nota: string, color_acento: string}
     */
    private static function valoresPorDefecto(TipoDocumentoEnum $tipo): array
    {
        $institucion = 'Centro de Educación Básica Alternativa — CEBA';
        $pieNota = 'Verifique la autenticidad de este documento en la sección "Verificar certificado" del portal CEBA.';
        $colorAcento = '#137A6C';

        return match ($tipo) {
            TipoDocumentoEnum::CERTIFICADO_ESTUDIOS => [
                'institucion' => $institucion,
                'titulo' => 'Certificado de estudios',
                'cuerpo' => 'Se deja constancia que {{estudiante}}, identificado(a) con DNI N.° {{dni}}, '
                    .'{{detalle_matricula}} conforme a los registros académicos de la institución.',
                'pie_nota' => $pieNota,
                'color_acento' => $colorAcento,
            ],
            TipoDocumentoEnum::CONSTANCIA_ESTUDIOS => [
                'institucion' => $institucion,
                'titulo' => 'Constancia de estudios',
                'cuerpo' => 'Se deja constancia que {{estudiante}}, identificado(a) con DNI N.° {{dni}}, '
                    .'{{detalle_matricula}} y mantiene un vínculo académico vigente con la institución.',
                'pie_nota' => $pieNota,
                'color_acento' => $colorAcento,
            ],
            TipoDocumentoEnum::CONSTANCIA_VACANTE => [
                'institucion' => $institucion,
                'titulo' => 'Constancia de vacante',
                'cuerpo' => 'Se deja constancia que existe una vacante disponible para {{estudiante}}, '
                    .'identificado(a) con DNI N.° {{dni}}, {{detalle_matricula}}.',
                'pie_nota' => $pieNota,
                'color_acento' => $colorAcento,
            ],
            TipoDocumentoEnum::CONSTANCIA_BUENA_CONDUCTA => [
                'institucion' => $institucion,
                'titulo' => 'Constancia de buena conducta',
                'cuerpo' => 'Se deja constancia que {{estudiante}}, identificado(a) con DNI N.° {{dni}}, '
                    .'{{detalle_matricula}} y no registra incidencias disciplinarias durante su permanencia en la institución.',
                'pie_nota' => $pieNota,
                'color_acento' => $colorAcento,
            ],
            TipoDocumentoEnum::LIBRETA_NOTAS => [
                'institucion' => $institucion,
                'titulo' => 'Libreta de notas',
                'cuerpo' => '',
                'pie_nota' => $pieNota,
                'color_acento' => $colorAcento,
            ],
        };
    }

    /**
     * Sustituye placeholders {{clave}} por el valor correspondiente en
     * $variables. Las claves sin valor provisto quedan sin reemplazar --
     * mismo comportamiento que PlantillaWhatsapp::renderizar().
     *
     * @param  array<string, string>  $variables
     */
    public function renderizarCuerpo(array $variables): string
    {
        $cuerpo = $this->cuerpo;

        foreach ($variables as $clave => $valor) {
            $cuerpo = str_replace('{{'.$clave.'}}', $valor, $cuerpo);
        }

        return $cuerpo;
    }
}
