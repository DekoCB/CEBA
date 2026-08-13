<?php

namespace Tests\Feature\Academico;

use App\Models\User;
use App\Modules\Academico\Enums\DiaSemanaEnum;
use App\Modules\Academico\Models\Aula;
use App\Modules\Academico\Models\Ciclo;
use App\Modules\Academico\Models\Curso;
use App\Modules\Academico\Models\Grado;
use App\Modules\Academico\Services\HorarioService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class HorarioTraslapeTest extends TestCase
{
    use RefreshDatabase;

    private function service(): HorarioService
    {
        return $this->app->make(HorarioService::class);
    }

    private function datosBase(): array
    {
        return [
            'curso_id' => Curso::factory()->create()->id,
            'docente_id' => User::factory()->create()->id,
            'aula_id' => Aula::factory()->create()->id,
            'ciclo_id' => Ciclo::factory()->create()->id,
            'grado_id' => Grado::factory()->create()->id,
            'dia_semana' => DiaSemanaEnum::LUN_MIE,
            'hora_inicio' => '18:00:00',
            'hora_fin' => '20:00:00',
        ];
    }

    public function test_no_permite_dos_horarios_que_se_crucen_en_la_misma_aula(): void
    {
        $base = $this->datosBase();
        $this->service()->crear($base);

        $this->expectException(ValidationException::class);

        $this->service()->crear([
            ...$base,
            'curso_id' => Curso::factory()->create()->id,
            'docente_id' => User::factory()->create()->id,
            'hora_inicio' => '19:00:00',
            'hora_fin' => '21:00:00',
        ]);
    }

    public function test_no_permite_al_mismo_docente_en_dos_aulas_a_la_misma_hora(): void
    {
        $base = $this->datosBase();
        $this->service()->crear($base);

        $this->expectException(ValidationException::class);

        $this->service()->crear([
            ...$base,
            'curso_id' => Curso::factory()->create()->id,
            'aula_id' => Aula::factory()->create()->id,
            'hora_inicio' => '19:30:00',
            'hora_fin' => '21:00:00',
        ]);
    }

    public function test_permite_horarios_en_dias_distintos_aunque_se_crucen_en_hora(): void
    {
        $base = $this->datosBase();
        $this->service()->crear($base);

        $horario = $this->service()->crear([
            ...$base,
            'curso_id' => Curso::factory()->create()->id,
            'docente_id' => User::factory()->create()->id,
            'dia_semana' => DiaSemanaEnum::MAR_JUE,
        ]);

        $this->assertDatabaseHas('horarios', ['id' => $horario->id]);
    }

    public function test_permite_horarios_consecutivos_sin_cruce_real(): void
    {
        $base = $this->datosBase();
        $this->service()->crear($base);

        $horario = $this->service()->crear([
            ...$base,
            'curso_id' => Curso::factory()->create()->id,
            'docente_id' => User::factory()->create()->id,
            'hora_inicio' => '20:00:00',
            'hora_fin' => '22:00:00',
        ]);

        $this->assertDatabaseHas('horarios', ['id' => $horario->id]);
    }

    public function test_permite_dos_secciones_del_mismo_grado_y_persiste_la_seccion(): void
    {
        $base = $this->datosBase();
        $horarioA = $this->service()->crear([...$base, 'seccion' => 'A']);

        $horarioB = $this->service()->crear([
            ...$base,
            'curso_id' => Curso::factory()->create()->id,
            'docente_id' => User::factory()->create()->id,
            'aula_id' => Aula::factory()->create()->id,
            'dia_semana' => DiaSemanaEnum::MAR_JUE,
            'seccion' => 'B',
        ]);

        $this->assertSame('A', $horarioA->fresh()->seccion);
        $this->assertSame('B', $horarioB->fresh()->seccion);
    }

    public function test_rechaza_hora_fin_anterior_o_igual_a_hora_inicio(): void
    {
        $this->expectException(ValidationException::class);

        $this->service()->crear([
            ...$this->datosBase(),
            'hora_inicio' => '18:00:00',
            'hora_fin' => '18:00:00',
        ]);
    }
}
