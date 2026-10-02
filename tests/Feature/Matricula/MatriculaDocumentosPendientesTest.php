<?php

namespace Tests\Feature\Matricula;

use App\Models\User;
use App\Modules\Identidad\Database\Seeders\RolesAndPermissionsSeeder;
use App\Modules\Matricula\Models\Estudiante;
use App\Shared\Enums\RolEnum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Volt;
use Tests\TestCase;

/**
 * Pedido del cliente: al matricular por carga masiva no se pueden subir el
 * certificado ni las fotos de DNI -- debe poder subirse después, desde la
 * ficha del estudiante. La carga masiva en sí nunca bloqueó nada (no
 * referencia documentos); el hueco real es que la ficha solo listaba
 * documentos ya subidos, sin forma de subir los que faltan.
 */
class MatriculaDocumentosPendientesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_un_estudiante_sin_documentos_muestra_los_tipos_pendientes_de_subir(): void
    {
        $usuario = User::factory()->create();
        $usuario->assignRole(RolEnum::COORDINADOR->value);

        $estudiante = Estudiante::factory()->create(['es_menor_edad' => false]);

        $this->actingAs($usuario);

        Volt::test('matricula.show', ['estudiante' => $estudiante])
            ->assertSee('DNI del estudiante — pendiente de subir')
            ->assertSee('Certificado de estudios — pendiente de subir')
            ->assertSee('Constancia — pendiente de subir')
            ->assertSee('Otro documento — pendiente de subir');
    }

    public function test_dni_apoderado_no_se_ofrece_para_un_estudiante_mayor_de_edad(): void
    {
        $usuario = User::factory()->create();
        $usuario->assignRole(RolEnum::COORDINADOR->value);

        $estudiante = Estudiante::factory()->create(['es_menor_edad' => false]);

        $this->actingAs($usuario);

        Volt::test('matricula.show', ['estudiante' => $estudiante])
            ->assertDontSee('DNI del apoderado — pendiente de subir');
    }

    public function test_dni_apoderado_se_ofrece_para_un_estudiante_menor_de_edad(): void
    {
        $usuario = User::factory()->create();
        $usuario->assignRole(RolEnum::COORDINADOR->value);

        $estudiante = Estudiante::factory()->menorDeEdad()->create();

        $this->actingAs($usuario);

        Volt::test('matricula.show', ['estudiante' => $estudiante])
            ->assertSee('DNI del apoderado — pendiente de subir');
    }

    public function test_subir_un_documento_pendiente_lo_crea_y_lo_saca_de_la_lista_de_faltantes_desde_la_pagina_completa(): void
    {
        Storage::fake('public');

        $usuario = User::factory()->create();
        $usuario->assignRole(RolEnum::COORDINADOR->value);

        $estudiante = Estudiante::factory()->create(['es_menor_edad' => false]);

        $this->actingAs($usuario);

        Volt::test('matricula.show', ['estudiante' => $estudiante])
            ->set('documentoNuevo.certificado_estudios', UploadedFile::fake()->create('certificado.pdf', 100, 'application/pdf'))
            ->call('subirDocumento', 'certificado_estudios')
            ->assertHasNoErrors()
            ->assertDontSee('Certificado de estudios — pendiente de subir');

        $documento = $estudiante->documentos()->where('tipo', 'certificado_estudios')->first();
        $this->assertNotNull($documento);
        $this->assertNotNull($documento->getFirstMedia('archivo'));
    }

    public function test_subir_un_documento_pendiente_desde_el_modal_de_ficha(): void
    {
        Storage::fake('public');

        $usuario = User::factory()->create();
        $usuario->assignRole(RolEnum::COORDINADOR->value);

        $estudiante = Estudiante::factory()->create(['es_menor_edad' => false]);

        $this->actingAs($usuario);

        Volt::test('matricula.ficha-modal')
            ->call('abrir', $estudiante->id)
            ->set('documentoNuevo.constancia', UploadedFile::fake()->create('constancia.pdf', 100, 'application/pdf'))
            ->call('subirDocumento', 'constancia')
            ->assertHasNoErrors();

        $this->assertNotNull($estudiante->documentos()->where('tipo', 'constancia')->first());
    }

    public function test_subir_documento_exige_un_archivo_valido(): void
    {
        $usuario = User::factory()->create();
        $usuario->assignRole(RolEnum::COORDINADOR->value);

        $estudiante = Estudiante::factory()->create(['es_menor_edad' => false]);

        $this->actingAs($usuario);

        Volt::test('matricula.show', ['estudiante' => $estudiante])
            ->call('subirDocumento', 'certificado_estudios')
            ->assertHasErrors(['documentoNuevo.certificado_estudios']);
    }

    public function test_no_se_puede_subir_documento_sin_el_permiso_de_editar_matricula(): void
    {
        Storage::fake('public');

        $usuario = User::factory()->create();
        $usuario->assignRole(RolEnum::ADMINISTRATIVO->value);

        $estudiante = Estudiante::factory()->create(['es_menor_edad' => false]);

        $this->actingAs($usuario);

        Volt::test('matricula.show', ['estudiante' => $estudiante])
            ->set('documentoNuevo.certificado_estudios', UploadedFile::fake()->create('certificado.pdf', 100, 'application/pdf'))
            ->call('subirDocumento', 'certificado_estudios')
            ->assertForbidden();
    }
}
