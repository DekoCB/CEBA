<?php

namespace Tests\Feature\Certificados;

use App\Models\User;
use App\Modules\Certificados\Enums\EstadoSolicitudCertificadoEnum;
use App\Modules\Certificados\Services\CertificadoService;
use App\Modules\Matricula\Models\Estudiante;
use App\Modules\Matricula\Models\Matricula;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CertificadoServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_emitir_genera_numero_correlativo_y_codigo_de_verificacion(): void
    {
        $estudiante = Estudiante::factory()->create();
        $matricula = Matricula::factory()->create(['estudiante_id' => $estudiante->id]);
        $emisor = User::factory()->create();

        $certificado = app(CertificadoService::class)->emitir($estudiante, $matricula, null, null, $emisor);

        $this->assertNotEmpty($certificado->numero);
        $this->assertStringEndsWith('-'.now()->format('Y'), $certificado->numero);
        $this->assertSame(10, mb_strlen($certificado->codigo_verificacion));
        $this->assertFalse($certificado->es_duplicado);
        $this->assertNotNull($certificado->getFirstMedia('pdf'));
    }

    public function test_numeros_correlativos_no_se_repiten_dentro_del_mismo_anio(): void
    {
        $emisor = User::factory()->create();
        $estudiante = Estudiante::factory()->create();

        $primero = app(CertificadoService::class)->emitir($estudiante, null, null, null, $emisor);
        $segundo = app(CertificadoService::class)->emitir($estudiante, null, null, null, $emisor);

        $this->assertNotSame($primero->numero, $segundo->numero);
    }

    public function test_duplicar_conserva_el_mismo_codigo_de_verificacion(): void
    {
        $estudiante = Estudiante::factory()->create();
        $emisor = User::factory()->create();

        $original = app(CertificadoService::class)->emitir($estudiante, null, null, null, $emisor);
        $duplicado = app(CertificadoService::class)->duplicar($original, 'Extravío', $emisor);

        $this->assertTrue($duplicado->es_duplicado);
        $this->assertSame($original->codigo_verificacion, $duplicado->codigo_verificacion);
        $this->assertSame($original->id, $duplicado->certificado_original_id);
        $this->assertNotSame($original->numero, $duplicado->numero);
    }

    public function test_verificar_encuentra_el_certificado_original_por_codigo(): void
    {
        $estudiante = Estudiante::factory()->create();
        $emisor = User::factory()->create();

        $certificado = app(CertificadoService::class)->emitir($estudiante, null, null, null, $emisor);

        $encontrado = app(CertificadoService::class)->verificar($certificado->codigo_verificacion);

        $this->assertNotNull($encontrado);
        $this->assertSame($certificado->id, $encontrado->id);
    }

    public function test_verificar_con_codigo_invalido_no_encuentra_nada(): void
    {
        $encontrado = app(CertificadoService::class)->verificar('CODIGOINEXISTENTE');

        $this->assertNull($encontrado);
    }

    public function test_emitir_a_partir_de_una_solicitud_la_marca_como_atendida(): void
    {
        $estudiante = Estudiante::factory()->create();
        $emisor = User::factory()->create();
        $service = app(CertificadoService::class);

        $solicitud = $service->solicitar($estudiante, null, 'Trámite laboral');
        $certificado = $service->emitir($estudiante, null, $solicitud, null, $emisor);

        $this->assertSame(EstadoSolicitudCertificadoEnum::ATENDIDA, $solicitud->refresh()->estado);
        $this->assertSame($certificado->id, $solicitud->certificado_id);
    }

    public function test_rechazar_solicitud_registra_el_motivo(): void
    {
        $estudiante = Estudiante::factory()->create();
        $revisor = User::factory()->create();
        $service = app(CertificadoService::class);

        $solicitud = $service->solicitar($estudiante, null, 'Trámite laboral');
        $service->rechazarSolicitud($solicitud, 'Deuda pendiente', $revisor);

        $solicitud->refresh();
        $this->assertSame(EstadoSolicitudCertificadoEnum::RECHAZADA, $solicitud->estado);
        $this->assertSame('Deuda pendiente', $solicitud->motivo_rechazo);
    }

    public function test_no_permite_rechazar_una_solicitud_ya_atendida(): void
    {
        $estudiante = Estudiante::factory()->create();
        $emisor = User::factory()->create();
        $service = app(CertificadoService::class);

        $solicitud = $service->solicitar($estudiante, null, 'Trámite laboral');
        $service->emitir($estudiante, null, $solicitud, null, $emisor);

        $this->expectException(ValidationException::class);
        $service->rechazarSolicitud($solicitud, 'Ya no aplica', $emisor);
    }
}
