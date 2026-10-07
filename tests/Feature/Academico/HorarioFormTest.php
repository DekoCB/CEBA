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

    public function test_crea_un_horario_con_la_franja_lunes_y_miercoles(): void
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
            ->set('gradosSeleccionados', [$grado->id])
            ->set('cursoId', (string) $curso->id)
            ->set('docenteId', (string) $docente->id)
            ->set('aulaId', (string) $aula->id)
            ->set('franjasSeleccionadas', ['lun_mie'])
            ->set('horaInicioHoraPorDia.lunes', '18')
            ->set('horaInicioMinutoPorDia.lunes', '00')
            ->set('horaFinHoraPorDia.lunes', '20')
            ->set('horaFinMinutoPorDia.lunes', '00')
            ->set('horaInicioHoraPorDia.miercoles', '18')
            ->set('horaInicioMinutoPorDia.miercoles', '00')
            ->set('horaFinHoraPorDia.miercoles', '20')
            ->set('horaFinMinutoPorDia.miercoles', '00')
            ->call('guardar')
            ->assertHasNoErrors();

        $horario = Horario::query()->where('curso_id', $curso->id)->firstOrFail();
        $this->assertCount(2, $horario->dias);

        $lunes = $horario->dias->firstWhere('dia_semana', DiaSemanaEnum::LUNES);
        $miercoles = $horario->dias->firstWhere('dia_semana', DiaSemanaEnum::MIERCOLES);
        $this->assertSame('18:00:00', $lunes->hora_inicio);
        $this->assertSame('20:00:00', $lunes->hora_fin);
        $this->assertSame('18:00:00', $miercoles->hora_inicio);
        $this->assertSame('20:00:00', $miercoles->hora_fin);
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
            ->set('gradosSeleccionados', [$existente->grado_id])
            ->set('cursoId', (string) Curso::factory()->create()->id)
            ->set('docenteId', (string) User::factory()->create()->id)
            ->set('aulaId', (string) $existente->aula_id)
            ->set('franjasSeleccionadas', ['lun_mie'])
            ->set('horaInicioHoraPorDia.lunes', '19')
            ->set('horaInicioMinutoPorDia.lunes', '00')
            ->set('horaFinHoraPorDia.lunes', '21')
            ->set('horaFinMinutoPorDia.lunes', '00')
            ->set('horaInicioHoraPorDia.miercoles', '19')
            ->set('horaInicioMinutoPorDia.miercoles', '00')
            ->set('horaFinHoraPorDia.miercoles', '21')
            ->set('horaFinMinutoPorDia.miercoles', '00')
            ->call('guardar')
            ->assertSee('ya está ocupada');

        $this->assertSame(1, Horario::query()->count());
    }

    public function test_no_deja_guardar_sin_elegir_ninguna_franja(): void
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
            ->assertHasErrors('franjasSeleccionadas');

        $this->assertSame(0, Horario::query()->count());
    }

    public function test_crea_un_horario_combinando_dos_franjas_con_horas_distintas_por_dia(): void
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
            ->set('gradosSeleccionados', [$grado->id])
            ->set('cursoId', (string) $curso->id)
            ->set('docenteId', (string) $docente->id)
            ->set('aulaId', (string) $aula->id)
            ->set('franjasSeleccionadas', ['lun_mie', 'mar_jue'])
            ->set('horaInicioHoraPorDia.lunes', '18')
            ->set('horaInicioMinutoPorDia.lunes', '00')
            ->set('horaFinHoraPorDia.lunes', '20')
            ->set('horaFinMinutoPorDia.lunes', '00')
            ->set('horaInicioHoraPorDia.miercoles', '18')
            ->set('horaInicioMinutoPorDia.miercoles', '00')
            ->set('horaFinHoraPorDia.miercoles', '20')
            ->set('horaFinMinutoPorDia.miercoles', '00')
            ->set('horaInicioHoraPorDia.martes', '16')
            ->set('horaInicioMinutoPorDia.martes', '00')
            ->set('horaFinHoraPorDia.martes', '18')
            ->set('horaFinMinutoPorDia.martes', '00')
            ->set('horaInicioHoraPorDia.jueves', '16')
            ->set('horaInicioMinutoPorDia.jueves', '00')
            ->set('horaFinHoraPorDia.jueves', '18')
            ->set('horaFinMinutoPorDia.jueves', '00')
            ->call('guardar')
            ->assertHasNoErrors();

        $horario = Horario::query()->where('curso_id', $curso->id)->firstOrFail();
        $this->assertCount(4, $horario->dias);

        $martes = $horario->dias->firstWhere('dia_semana', DiaSemanaEnum::MARTES);
        $lunes = $horario->dias->firstWhere('dia_semana', DiaSemanaEnum::LUNES);
        $this->assertSame('16:00:00', $martes->hora_inicio);
        $this->assertSame('18:00:00', $martes->hora_fin);
        $this->assertSame('18:00:00', $lunes->hora_inicio);
        $this->assertSame('20:00:00', $lunes->hora_fin);
    }

    public function test_arrastrar_una_tarjeta_en_la_pestana_editar_mueve_el_dia(): void
    {
        $this->actingAs($this->actorCoordinador());

        $ciclo = Ciclo::factory()->create();
        $horario = $this->app->make(HorarioService::class)->crear([
            'curso_id' => Curso::factory()->create()->id,
            'docente_id' => User::factory()->create()->id,
            'aula_id' => Aula::factory()->create()->id,
            'ciclo_id' => $ciclo->id,
            'grado_id' => Grado::factory()->create()->id,
            'dias' => [
                ['dia_semana' => DiaSemanaEnum::LUNES, 'hora_inicio' => '18:00:00', 'hora_fin' => '20:00:00'],
            ],
        ]);
        $horarioDiaId = $horario->dias->first()->id;

        Volt::test('academico.horarios.index')
            ->set('cicloFiltro', (string) $ciclo->id)
            ->set('vista', 'editar')
            ->call('moverDia', $horarioDiaId, 'sabado')
            ->assertHasNoErrors();

        // actualizar() borra y recrea las filas de "dias", así que el id
        // original ya no existe -- se relee el horario completo.
        $this->assertSame(DiaSemanaEnum::SABADO, $horario->fresh('dias')->dias->first()->dia_semana);
    }

    public function test_abrir_modal_editar_precarga_los_dias_reales_y_guardar_los_actualiza(): void
    {
        $this->actingAs($this->actorCoordinador());

        $horario = $this->app->make(HorarioService::class)->crear([
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
            ->call('abrirModalEditar', $horario->id)
            ->assertSet('diasSueltosSeleccionados', ['lunes'])
            ->assertSet('horaInicioHoraPorDia.lunes', '18')
            ->set('horaFinHoraPorDia.lunes', '21')
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertSame('21:00:00', $horario->fresh('dias')->dias->first()->hora_fin);
    }

    public function test_el_boton_editar_en_la_lista_abre_el_modal_precargado(): void
    {
        $this->actingAs($this->actorCoordinador());

        $horario = $this->app->make(HorarioService::class)->crear([
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
            ->set('cicloFiltro', (string) $horario->ciclo_id)
            ->assertSee('Editar')
            ->call('abrirModalEditar', $horario->id)
            ->assertSet('mostrarModal', true)
            ->assertSet('editandoId', $horario->id)
            ->assertSet('cursoId', (string) $horario->curso_id);
    }

    public function test_el_listado_agrupa_los_horarios_por_franja_y_luego_por_grado(): void
    {
        $this->actingAs($this->actorCoordinador());

        $ciclo = Ciclo::factory()->create();
        $gradoA = Grado::factory()->create(['nombre' => 'Grado 1 - Mayores']);
        $gradoB = Grado::factory()->create(['nombre' => 'Grado 2 - Mayores']);
        $service = $this->app->make(HorarioService::class);

        $service->crear([
            'curso_id' => Curso::factory()->create()->id,
            'docente_id' => User::factory()->create()->id,
            'aula_id' => Aula::factory()->create()->id,
            'ciclo_id' => $ciclo->id,
            'grado_id' => $gradoA->id,
            'dias' => [
                ['dia_semana' => DiaSemanaEnum::LUNES, 'hora_inicio' => '18:00:00', 'hora_fin' => '20:00:00'],
                ['dia_semana' => DiaSemanaEnum::MIERCOLES, 'hora_inicio' => '18:00:00', 'hora_fin' => '20:00:00'],
            ],
        ]);

        $service->crear([
            'curso_id' => Curso::factory()->create()->id,
            'docente_id' => User::factory()->create()->id,
            'aula_id' => Aula::factory()->create()->id,
            'ciclo_id' => $ciclo->id,
            'grado_id' => $gradoB->id,
            'dias' => [
                ['dia_semana' => DiaSemanaEnum::DOMINGO, 'hora_inicio' => '10:00:00', 'hora_fin' => '12:00:00'],
            ],
        ]);

        $html = Volt::test('academico.horarios.index')
            ->set('cicloFiltro', (string) $ciclo->id)
            ->html();

        // Se compara la PRIMERA aparición de cada texto: el listado se
        // renderiza antes que el formulario "Nuevo horario" (que repite los
        // mismos nombres de grado y franja en sus selects), así que si el
        // orden real fuera otro, alguna de estas comparaciones fallaría.
        $posicionLunMie = mb_strpos($html, 'Lunes y Miércoles');
        $posicionGradoA = mb_strpos($html, 'Grado 1 - Mayores');
        $posicionDomingo = mb_strpos($html, 'Domingo');
        $posicionGradoB = mb_strpos($html, 'Grado 2 - Mayores');

        $this->assertLessThan($posicionGradoA, $posicionLunMie);
        $this->assertLessThan($posicionDomingo, $posicionGradoA);
        $this->assertLessThan($posicionGradoB, $posicionDomingo);
    }

    /**
     * Pedido del cliente: los cursos en franja "Lunes y Miércoles" a veces
     * se dictan en forma alternada -- no necesariamente los 2 días. La hora
     * debe poder quedar sin definir para uno de ellos.
     */
    public function test_permite_guardar_un_dia_sin_horas_definidas(): void
    {
        $this->actingAs($this->actorCoordinador());

        $grado = Grado::factory()->create();

        Volt::test('academico.horarios.index')
            ->call('abrirModal')
            ->set('cicloId', (string) Ciclo::factory()->create()->id)
            ->set('gradosSeleccionados', [$grado->id])
            ->set('cursoId', (string) Curso::factory()->create()->id)
            ->set('docenteId', (string) User::factory()->create()->id)
            ->set('aulaId', (string) Aula::factory()->create()->id)
            ->set('franjasSeleccionadas', ['lun_mie'])
            ->set('horaInicioHoraPorDia.lunes', '18')
            ->set('horaInicioMinutoPorDia.lunes', '00')
            ->set('horaFinHoraPorDia.lunes', '20')
            ->set('horaFinMinutoPorDia.lunes', '00')
            ->call('guardar')
            ->assertHasNoErrors();

        $horario = Horario::query()->where('grado_id', $grado->id)->firstOrFail();
        $miercoles = $horario->dias->firstWhere('dia_semana', DiaSemanaEnum::MIERCOLES);
        $this->assertNull($miercoles->hora_inicio);
        $this->assertNull($miercoles->hora_fin);
    }

    public function test_rechaza_llenar_solo_la_hora_de_inicio_sin_la_hora_de_fin(): void
    {
        $this->actingAs($this->actorCoordinador());

        Volt::test('academico.horarios.index')
            ->call('abrirModal')
            ->set('cicloId', (string) Ciclo::factory()->create()->id)
            ->set('gradosSeleccionados', [Grado::factory()->create()->id])
            ->set('cursoId', (string) Curso::factory()->create()->id)
            ->set('docenteId', (string) User::factory()->create()->id)
            ->set('aulaId', (string) Aula::factory()->create()->id)
            ->set('franjasSeleccionadas', ['lun_mie'])
            ->set('horaInicioHoraPorDia.lunes', '18')
            ->set('horaInicioMinutoPorDia.lunes', '00')
            ->set('horaFinHoraPorDia.lunes', '20')
            ->set('horaFinMinutoPorDia.lunes', '00')
            ->set('horaInicioHoraPorDia.miercoles', '18')
            ->set('horaInicioMinutoPorDia.miercoles', '00')
            // horaFin de miércoles queda vacía a propósito
            ->call('guardar')
            ->assertHasErrors(['horaFinHoraPorDia.miercoles', 'horaFinMinutoPorDia.miercoles']);

        $this->assertSame(0, Horario::query()->count());
    }

    public function test_el_listado_muestra_sin_horario_definido_para_un_dia_sin_horas(): void
    {
        $this->actingAs($this->actorCoordinador());

        $ciclo = Ciclo::factory()->create();
        $this->app->make(HorarioService::class)->crear([
            'curso_id' => Curso::factory()->create()->id,
            'docente_id' => User::factory()->create()->id,
            'aula_id' => Aula::factory()->create()->id,
            'ciclo_id' => $ciclo->id,
            'grado_id' => Grado::factory()->create()->id,
            'dias' => [
                ['dia_semana' => DiaSemanaEnum::LUNES, 'hora_inicio' => '18:00:00', 'hora_fin' => '20:00:00'],
                ['dia_semana' => DiaSemanaEnum::MIERCOLES, 'hora_inicio' => null, 'hora_fin' => null],
            ],
        ]);

        Volt::test('academico.horarios.index')
            ->set('cicloFiltro', (string) $ciclo->id)
            ->assertSee('sin horario definido');
    }

    /**
     * Pedido del cliente: poder elegir varios grados a la vez al crear un
     * horario, para no repetir todo el formulario grado por grado.
     */
    public function test_seleccionar_varios_grados_crea_un_horario_independiente_por_cada_uno(): void
    {
        $this->actingAs($this->actorCoordinador());

        $gradoA = Grado::factory()->create();
        $gradoB = Grado::factory()->create();

        Volt::test('academico.horarios.index')
            ->call('abrirModal')
            ->set('cicloId', (string) Ciclo::factory()->create()->id)
            ->set('gradosSeleccionados', [$gradoA->id, $gradoB->id])
            ->set('cursoId', (string) Curso::factory()->create()->id)
            ->set('docenteId', (string) User::factory()->create()->id)
            ->set('aulaId', (string) Aula::factory()->create()->id)
            ->set('franjasSeleccionadas', ['lun_mie'])
            ->set('horaInicioHoraPorDia.lunes', '18')
            ->set('horaInicioMinutoPorDia.lunes', '00')
            ->set('horaFinHoraPorDia.lunes', '20')
            ->set('horaFinMinutoPorDia.lunes', '00')
            ->set('horaInicioHoraPorDia.miercoles', '18')
            ->set('horaInicioMinutoPorDia.miercoles', '00')
            ->set('horaFinHoraPorDia.miercoles', '20')
            ->set('horaFinMinutoPorDia.miercoles', '00')
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertSame(2, Horario::query()->count());
        $this->assertTrue(Horario::query()->where('grado_id', $gradoA->id)->exists());
        $this->assertTrue(Horario::query()->where('grado_id', $gradoB->id)->exists());
    }

    /**
     * Los horarios creados juntos en la misma tanda comparten a propósito
     * la misma aula/horario (es el mismo formulario, para varios grados) --
     * no deben chocar entre sí, aunque la validación de choque normal
     * (aula+día+hora, sin importar el grado) sí los habría marcado como
     * conflicto si no se excluyeran explícitamente unos a otros.
     */
    public function test_los_grados_creados_juntos_no_chocan_entre_si_aunque_compartan_aula_y_horario(): void
    {
        $this->actingAs($this->actorCoordinador());

        $gradoA = Grado::factory()->create();
        $gradoB = Grado::factory()->create();
        $gradoC = Grado::factory()->create();

        Volt::test('academico.horarios.index')
            ->call('abrirModal')
            ->set('cicloId', (string) Ciclo::factory()->create()->id)
            ->set('gradosSeleccionados', [$gradoA->id, $gradoB->id, $gradoC->id])
            ->set('cursoId', (string) Curso::factory()->create()->id)
            ->set('docenteId', (string) User::factory()->create()->id)
            ->set('aulaId', (string) Aula::factory()->create()->id)
            ->set('franjasSeleccionadas', ['lun_mie'])
            ->set('horaInicioHoraPorDia.lunes', '18')
            ->set('horaInicioMinutoPorDia.lunes', '00')
            ->set('horaFinHoraPorDia.lunes', '20')
            ->set('horaFinMinutoPorDia.lunes', '00')
            ->set('horaInicioHoraPorDia.miercoles', '18')
            ->set('horaInicioMinutoPorDia.miercoles', '00')
            ->set('horaFinHoraPorDia.miercoles', '20')
            ->set('horaFinMinutoPorDia.miercoles', '00')
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertSame(3, Horario::query()->count());
    }

    /**
     * Un horario que YA EXISTÍA de antes (no de esta misma tanda) sí sigue
     * bloqueando -- como todos los grados elegidos apuntan a la misma
     * aula/día/hora, un choque previo los afecta a todos por igual.
     */
    public function test_un_horario_ya_existente_de_antes_bloquea_a_todos_los_grados_de_la_tanda(): void
    {
        $this->actingAs($this->actorCoordinador());

        $aula = Aula::factory()->create();
        $ciclo = Ciclo::factory()->create();
        $gradoA = Grado::factory()->create();
        $gradoB = Grado::factory()->create();

        // Ya existe un horario en esa misma aula/día/hora, de antes.
        $this->app->make(HorarioService::class)->crear([
            'curso_id' => Curso::factory()->create()->id,
            'docente_id' => User::factory()->create()->id,
            'aula_id' => $aula->id,
            'ciclo_id' => $ciclo->id,
            'grado_id' => Grado::factory()->create()->id,
            'dias' => [
                ['dia_semana' => DiaSemanaEnum::LUNES, 'hora_inicio' => '18:00:00', 'hora_fin' => '20:00:00'],
            ],
        ]);

        Volt::test('academico.horarios.index')
            ->call('abrirModal')
            ->set('cicloId', (string) $ciclo->id)
            ->set('gradosSeleccionados', [$gradoA->id, $gradoB->id])
            ->set('cursoId', (string) Curso::factory()->create()->id)
            ->set('docenteId', (string) User::factory()->create()->id)
            ->set('aulaId', (string) $aula->id)
            ->set('franjasSeleccionadas', ['lun_mie'])
            ->set('horaInicioHoraPorDia.lunes', '18')
            ->set('horaInicioMinutoPorDia.lunes', '00')
            ->set('horaFinHoraPorDia.lunes', '20')
            ->set('horaFinMinutoPorDia.lunes', '00')
            ->set('horaInicioHoraPorDia.miercoles', '18')
            ->set('horaInicioMinutoPorDia.miercoles', '00')
            ->set('horaFinHoraPorDia.miercoles', '20')
            ->set('horaFinMinutoPorDia.miercoles', '00')
            ->call('guardar')
            ->assertSee('ya está ocupada');

        $this->assertSame(0, Horario::query()->where('grado_id', $gradoA->id)->count());
        $this->assertSame(0, Horario::query()->where('grado_id', $gradoB->id)->count());
    }

    public function test_editar_sigue_usando_un_solo_grado(): void
    {
        $this->actingAs($this->actorCoordinador());

        $horario = $this->app->make(HorarioService::class)->crear([
            'curso_id' => Curso::factory()->create()->id,
            'docente_id' => User::factory()->create()->id,
            'aula_id' => Aula::factory()->create()->id,
            'ciclo_id' => Ciclo::factory()->create()->id,
            'grado_id' => Grado::factory()->create()->id,
            'dias' => [
                ['dia_semana' => DiaSemanaEnum::LUNES, 'hora_inicio' => '18:00:00', 'hora_fin' => '20:00:00'],
            ],
        ]);
        $nuevoGrado = Grado::factory()->create();

        Volt::test('academico.horarios.index')
            ->call('abrirModalEditar', $horario->id)
            ->assertSet('gradoId', (string) $horario->grado_id)
            ->set('gradoId', (string) $nuevoGrado->id)
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertSame($nuevoGrado->id, $horario->fresh()->grado_id);
    }

    public function test_no_deja_guardar_sin_elegir_ningun_grado(): void
    {
        $this->actingAs($this->actorCoordinador());

        Volt::test('academico.horarios.index')
            ->call('abrirModal')
            ->set('cicloId', (string) Ciclo::factory()->create()->id)
            ->set('cursoId', (string) Curso::factory()->create()->id)
            ->set('docenteId', (string) User::factory()->create()->id)
            ->set('aulaId', (string) Aula::factory()->create()->id)
            ->set('franjasSeleccionadas', ['lun_mie'])
            ->call('guardar')
            ->assertHasErrors('gradosSeleccionados');

        $this->assertSame(0, Horario::query()->count());
    }
}
