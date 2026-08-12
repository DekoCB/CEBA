<?php

declare(strict_types=1);

namespace App\Modules\Certificados\Models;

use App\Modules\Identidad\Support\Auditable;
use Illuminate\Database\Eloquent\Model;

/**
 * Formato y texto del certificado de estudios, editable desde la UI de
 * administración en vez de vivir hardcodeado en resources/views/pdf. Es una
 * fila única (siempre id=1) -- no hay "varias plantillas" entre las que
 * elegir, es el formato institucional.
 *
 * @property int $id
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
        'institucion',
        'titulo',
        'cuerpo',
        'pie_nota',
        'color_acento',
    ];

    /**
     * Placeholders disponibles en $cuerpo, documentados también en la UI de
     * edición: {{estudiante}}, {{dni}}, {{detalle_matricula}} (la frase
     * completa sobre el grado/ciclo cursado, o el texto alterno si el
     * certificado no está ligado a una matrícula).
     */
    public static function actual(): self
    {
        return self::query()->firstOrCreate([], [
            'institucion' => 'Centro de Educación Básica Alternativa — CEBA',
            'titulo' => 'Certificado de estudios',
            'cuerpo' => 'Se deja constancia que {{estudiante}}, identificado(a) con DNI N.° {{dni}}, '
                .'{{detalle_matricula}} conforme a los registros académicos de la institución.',
            'pie_nota' => 'Verifique la autenticidad de este documento en la sección "Verificar certificado" del portal CEBA.',
            'color_acento' => '#137A6C',
        ]);
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
