<?php

namespace Tests\Feature\Reportes;

use App\Models\User;
use App\Modules\Identidad\Database\Seeders\RolesAndPermissionsSeeder;
use App\Shared\Enums\RolEnum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class ReportesPermisosTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_coordinador_puede_ver_el_constructor_de_reportes(): void
    {
        $coordinador = User::factory()->create();
        $coordinador->assignRole(RolEnum::COORDINADOR->value);

        $this->actingAs($coordinador)
            ->get(route('reportes.index'))
            ->assertOk()
            ->assertSee('Académico');
    }

    public function test_docente_solo_ve_su_propio_tipo_de_reporte(): void
    {
        $docente = User::factory()->create();
        $docente->assignRole(RolEnum::DOCENTE->value);

        $this->actingAs($docente)
            ->get(route('reportes.index'))
            ->assertOk()
            ->assertSee('Mis evaluaciones')
            ->assertDontSee('Financiero');
    }

    public function test_un_estudiante_no_puede_ver_reportes(): void
    {
        $usuario = User::factory()->create();
        $usuario->assignRole(RolEnum::ESTUDIANTE->value);

        $this->actingAs($usuario)
            ->get(route('reportes.index'))
            ->assertForbidden();
    }

    public function test_exportar_a_pdf_no_lanza_el_error_de_livewire_con_dompdf(): void
    {
        // Regresión: Pdf::loadView(...)->download() devuelve un
        // Illuminate\Http\Response plano, que Livewire no reconoce como
        // descarga de archivo (solo StreamedResponse/BinaryFileResponse), así
        // que intentaba serializar los bytes binarios del PDF como valor de
        // retorno normal y json_encode() truena con "Malformed UTF-8
        // characters". Ver el fix en reportes/index.blade.php.
        $coordinador = User::factory()->create();
        $coordinador->assignRole(RolEnum::COORDINADOR->value);

        $this->actingAs($coordinador);

        $testable = Volt::test('reportes.index')
            ->set('tipo', 'academico')
            ->call('exportarPdf');

        // Si Livewire reconoce la respuesta como descarga de archivo (el fix),
        // la codifica como efecto "download" en vez de intentar serializarla
        // como valor de retorno normal.
        $this->assertArrayHasKey('download', $testable->effects);
        $this->assertSame('application/pdf', $testable->effects['download']['contentType']);
    }
}
