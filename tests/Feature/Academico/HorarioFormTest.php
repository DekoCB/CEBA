<?php

namespace Tests\Feature\Academico;

use App\Models\User;
use App\Modules\Academico\Enums\DiaSemanaEnum;
use App\Modules\Academico\Models\Aula;
use App\Modules\Academico\Models\Ciclo;
use App\Modules\Academico\Models\Curso;
use App\Modules\Academico\Models\Grado;
use App\Modules\Academico\Models\Horario;
use App\Modules\Academico\Services\HorarioService;
use App\Modules\Identidad\Database\Seeders\RolesAndPermissionsSeeder;
use App\Shared\Enums\RolEnum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class HorarioFormTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function actorCoordinador(): User
    {
        $coordinador = User::factory()->create();
        $coordinador->assignRole(RolEnum::COORDINADOR->value);

        return $coordinador;
    }

    public function test_crea_un_horario_con_dos_dias_y_horario_propio_por_dia(): void
    {
        $this->actingAs($this->actorCoordinador());

        $curso = Curso::factory()->create();
        $docente = User::factory()->create();
        $docente->assignRole(RolEnum::DOCENTE->value);
        $aula = Aula::factory()->create();
        $ciclo = Ciclo::factory()->create();
        $grado = Grado::factory()->create();

        Volt::test('academico.horarios.index')
            ->call('abrirModal')
            ->set('cicloId', (string) $ciclo->id)
            ->set('gradoId', (string) $grado->id)
            ->set('cursoId', (string) $curso->id)
            ->set('docenteId', (string) $docente->id)
            ->set('aulaId', (string) $aula->id)
            ->set('diasSeleccionados', ['lunes', 'jueves'])
            ->set('horaInicioPorDia.lunes', '18:00')
            ->set('horaFinPorDia.lunes', '20:00')
            ->set('horaInicioPorDia.jueves', '16:00')
            ->set('horaFinPorDia.jueves', '18:00')
            ->call('guardar')
            ->assertHasNoErrors();

        $horario = Horario::query()->where('curso_id', $curso->id)->firstOrFail();
        $this->assertCount(2, $horario->dias);

        $lunes = $horario->dias->firstWhere('dia_semana', DiaSemanaEnum::LUNES);
        $jueves = $horario->dias->firstWhere('dia_semana', DiaSemanaEnum::JUEVES);
        $this->assertSame('18:00:00', $lunes->hora_inicio);
        $this->assertSame('20:00:00', $lunes->hora_fin);
        $this->assertSame('16:00:00', $jueves->hora_inicio);
        $this->assertSame('18:00:00', $jueves->hora_fin);
    }

    public function test_muestra_el_mensaje_de_choque_de_aula_al_guardar(): void
    {
        $this->actingAs($this->actorCoordinador());

        $existente = $this->app->make(HorarioService::class)->crear([
            'curso_id' => Curso::factory()->create()->id,
            'docente_id' => User::factory()->create()->id,
            'aula_id' => Aula::factory()->create()->id,
            'ciclo_id' => Ciclo::factory()->create()->id,
            'grado_id' => Grado::factory()->create()->id,
            'dias' => [
                ['dia_semana' => DiaSemanaEnum::LUNES, 'hora_inicio' => '18:00:00', 'hora_fin' => '20:00:00'],
            ],
        ]);

        Volt::test('academico.horarios.index')
            ->call('abrirModal')
            ->set('cicloId', (string) $existente->ciclo_id)
            ->set('gradoId', (string) $existente->grado_id)
            ->set('cursoId', (string) Curso::factory()->create()->id)
            ->set('docenteId', (string) User::factory()->create()->id)
            ->set('aulaId', (string) $existente->aula_id)
            ->set('diasSeleccionados', ['lunes'])
            ->set('horaInicioPorDia.lunes', '19:00')
            ->set('horaFinPorDia.lunes', '21:00')
            ->call('guardar')
            ->assertSee('ya está ocupada');

        $this->assertSame(1, Horario::query()->count());
    }

    public function test_no_deja_guardar_sin_elegir_ningun_dia(): void
    {
        $this->actingAs($this->actorCoordinador());

        Volt::test('academico.horarios.index')
            ->call('abrirModal')
            ->set('cicloId', (string) Ciclo::factory()->create()->id)
            ->set('gradoId', (string) Grado::factory()->create()->id)
            ->set('cursoId', (string) Curso::factory()->create()->id)
            ->set('docenteId', (string) User::factory()->create()->id)
            ->set('aulaId', (string) Aula::factory()->create()->id)
            ->call('guardar')
            ->assertHasErrors('diasSeleccionados');

        $this->assertSame(0, Horario::query()->count());
    }
}
