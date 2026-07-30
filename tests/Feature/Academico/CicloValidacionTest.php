<?php

namespace Tests\Feature\Academico;

use App\Modules\Academico\Enums\TipoCicloEnum;
use App\Modules\Academico\Models\Ciclo;
use App\Modules\Academico\Services\CicloService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CicloValidacionTest extends TestCase
{
    use RefreshDatabase;

    private function service(): CicloService
    {
        return $this->app->make(CicloService::class);
    }

    public function test_un_ciclo_ene_jun_debe_iniciar_en_enero(): void
    {
        $this->expectException(ValidationException::class);

        $this->service()->crear([
            'nombre' => 'Enero - Junio 2026',
            'tipo' => TipoCicloEnum::ENE_JUN,
            'anio' => 2026,
            'fecha_inicio' => '2026-03-01',
            'fecha_fin' => '2026-08-30',
        ]);
    }

    public function test_un_ciclo_de_menores_debe_durar_lo_declarado_en_su_tipo(): void
    {
        $this->expectException(ValidationException::class);

        $this->service()->crear([
            'nombre' => 'Ciclo corto',
            'tipo' => TipoCicloEnum::SEIS_MESES,
            'anio' => 2026,
            'fecha_inicio' => '2026-03-01',
            'fecha_fin' => '2026-05-01',
        ]);
    }

    public function test_un_ciclo_con_fechas_coherentes_se_crea_sin_problema(): void
    {
        $ciclo = $this->service()->crear([
            'nombre' => 'Enero - Junio 2026',
            'tipo' => TipoCicloEnum::ENE_JUN,
            'anio' => 2026,
            'fecha_inicio' => '2026-01-01',
            'fecha_fin' => '2026-06-30',
        ]);

        $this->assertDatabaseHas('ciclos', ['id' => $ciclo->id, 'nombre' => 'Enero - Junio 2026']);
    }

    public function test_no_permite_dos_ciclos_del_mismo_tipo_con_fechas_cruzadas(): void
    {
        $this->service()->crear([
            'nombre' => 'Enero - Junio 2026',
            'tipo' => TipoCicloEnum::ENE_JUN,
            'anio' => 2026,
            'fecha_inicio' => '2026-01-01',
            'fecha_fin' => '2026-06-30',
        ]);

        $this->expectException(ValidationException::class);

        $this->service()->crear([
            'nombre' => 'Enero - Junio 2026 (duplicado)',
            'tipo' => TipoCicloEnum::ENE_JUN,
            'anio' => 2026,
            'fecha_inicio' => '2026-01-01',
            'fecha_fin' => '2026-06-30',
        ]);
    }

    public function test_periodo_de_matricula_no_puede_abrir_con_mas_de_30_dias_de_anticipacion(): void
    {
        $ciclo = Ciclo::factory()->create([
            'fecha_inicio' => '2026-07-01',
            'fecha_fin' => '2026-12-31',
        ]);

        $this->expectException(ValidationException::class);

        $this->service()->crearPeriodoMatricula($ciclo, '2026-05-01', '2026-07-10');
    }

    public function test_periodo_de_matricula_no_puede_cerrar_despues_de_que_termina_el_ciclo(): void
    {
        $ciclo = Ciclo::factory()->create([
            'fecha_inicio' => '2026-07-01',
            'fecha_fin' => '2026-12-31',
        ]);

        $this->expectException(ValidationException::class);

        $this->service()->crearPeriodoMatricula($ciclo, '2026-06-15', '2027-01-15');
    }

    public function test_periodo_de_matricula_valido_dentro_de_la_ventana_del_ciclo(): void
    {
        $ciclo = Ciclo::factory()->create([
            'fecha_inicio' => '2026-07-01',
            'fecha_fin' => '2026-12-31',
        ]);

        $periodo = $this->service()->crearPeriodoMatricula($ciclo, '2026-06-15', '2026-07-15');

        $this->assertDatabaseHas('periodos_matricula', ['id' => $periodo->id, 'ciclo_id' => $ciclo->id]);
    }
}
