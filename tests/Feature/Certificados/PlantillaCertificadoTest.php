<?php

namespace Tests\Feature\Certificados;

use App\Models\User;
use App\Modules\Certificados\Models\PlantillaCertificado;
use App\Modules\Certificados\Services\CertificadoService;
use App\Modules\Matricula\Models\Estudiante;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlantillaCertificadoTest extends TestCase
{
    use RefreshDatabase;

    public function test_actual_crea_la_fila_unica_con_valores_por_defecto_si_no_existe(): void
    {
        $this->assertDatabaseCount('plantilla_certificados', 0);

        $plantilla = PlantillaCertificado::actual();

        $this->assertDatabaseCount('plantilla_certificados', 1);
        $this->assertSame('Certificado de estudios', $plantilla->titulo);
        $this->assertSame('#137A6C', $plantilla->color_acento);
    }

    public function test_actual_siempre_devuelve_la_misma_fila(): void
    {
        $primera = PlantillaCertificado::actual();
        $segunda = PlantillaCertificado::actual();

        $this->assertSame($primera->id, $segunda->id);
        $this->assertDatabaseCount('plantilla_certificados', 1);
    }

    public function test_guardar_plantilla_persiste_los_cambios(): void
    {
        $service = app(CertificadoService::class);

        $service->guardarPlantilla([
            'institucion' => 'CEBA Peruano Británico',
            'titulo' => 'Constancia de estudios',
            'cuerpo' => 'Certificamos que {{estudiante}} ({{dni}}) {{detalle_matricula}}',
            'pie_nota' => 'Nota al pie personalizada.',
            'color_acento' => '#FF0000',
        ]);

        $plantilla = PlantillaCertificado::actual();
        $this->assertSame('CEBA Peruano Británico', $plantilla->institucion);
        $this->assertSame('Constancia de estudios', $plantilla->titulo);
        $this->assertSame('#FF0000', $plantilla->color_acento);
    }

    public function test_renderizar_cuerpo_sustituye_los_placeholders_provistos(): void
    {
        $plantilla = PlantillaCertificado::actual();

        $resultado = $plantilla->renderizarCuerpo([
            'estudiante' => 'Juan Pérez Ríos',
            'dni' => '87654321',
            'detalle_matricula' => 'cursó el grado 3ro de Secundaria,',
        ]);

        $this->assertStringContainsString('Juan Pérez Ríos', $resultado);
        $this->assertStringContainsString('87654321', $resultado);
        $this->assertStringContainsString('cursó el grado 3ro de Secundaria,', $resultado);
    }

    public function test_renderizar_cuerpo_no_ejecuta_sintaxis_blade_o_php_del_texto_editado(): void
    {
        // Regresión de seguridad: el texto que un administrativo escribe en
        // la plantilla NUNCA debe compilarse como Blade/PHP (eso sería
        // ejecución de código arbitrario) -- solo str_replace de
        // placeholders. Si alguien escribe algo con pinta de Blade, debe
        // quedar tal cual, como texto plano.
        $service = app(CertificadoService::class);
        $service->guardarPlantilla([
            'institucion' => 'CEBA',
            'titulo' => 'Certificado',
            'cuerpo' => 'Resultado: {{ 7 * 7 }} y @php echo "hackeado"; @endphp para {{estudiante}}.',
            'pie_nota' => null,
            'color_acento' => '#137A6C',
        ]);

        $plantilla = PlantillaCertificado::actual();
        $resultado = $plantilla->renderizarCuerpo(['estudiante' => 'Ana']);

        // Si se hubiera compilado como Blade/PHP, el resultado sería
        // "Resultado: 49 y hackeado para Ana." -- en vez de eso, el texto
        // literal debe quedar intacto, probando que nunca se ejecutó.
        $this->assertStringContainsString('{{ 7 * 7 }}', $resultado);
        $this->assertStringContainsString('@php echo "hackeado"; @endphp', $resultado);
        $this->assertStringNotContainsString('Resultado: 49 y hackeado', $resultado);
    }

    public function test_emitir_certificado_genera_pdf_valido_usando_la_plantilla_personalizada(): void
    {
        $service = app(CertificadoService::class);
        $service->guardarPlantilla([
            'institucion' => 'CEBA Personalizado',
            'titulo' => 'Título Personalizado',
            'cuerpo' => 'Cuerpo personalizado para {{estudiante}}.',
            'pie_nota' => null,
            'color_acento' => '#123456',
        ]);

        $estudiante = Estudiante::factory()->create();
        $emisor = User::factory()->create();

        $certificado = $service->emitir($estudiante, null, null, null, $emisor);

        $this->assertNotNull($certificado->getFirstMedia('pdf'));
    }

    public function test_previsualizar_plantilla_devuelve_un_pdf_valido_sin_emitir_certificados(): void
    {
        $plantilla = PlantillaCertificado::actual();

        $pdf = app(CertificadoService::class)->previsualizarPlantilla($plantilla);

        $this->assertStringStartsWith('%PDF-', $pdf);
        $this->assertDatabaseCount('certificados', 0);
    }
}
