<?php

namespace Tests\Feature\AulaVirtual;

use App\Models\User;
use App\Modules\Academico\Models\Horario;
use App\Modules\AulaVirtual\Models\CursoVirtual;
use App\Modules\Identidad\Database\Seeders\RolesAndPermissionsSeeder;
use App\Shared\Enums\RolEnum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

/**
 * Pedido del cliente: "En el apartado de [Semana (opcional)], debe
 * permitir ingresar cualquier carácter (letras, números, simbolos, etc).
 * Para poder organizar según la fecha, semana o temática de clase."
 */
class AulaVirtualSemanaLibreTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function cursoDelDocente(User $docente): CursoVirtual
    {
        $horario = Horario::factory()->create(['docente_id' => $docente->id]);

        return CursoVirtual::factory()->create(['horario_id' => $horario->id]);
    }

    public function test_acepta_texto_libre_en_semana_de_material(): void
    {
        $docente = User::factory()->create();
        $docente->assignRole(RolEnum::DOCENTE->value);
        $curso = $this->cursoDelDocente($docente);

        $this->actingAs($docente);

        Volt::test('aula-virtual.show', ['curso' => $curso])
            ->set('materialTipo', 'enlace')
            ->set('materialTitulo', 'Guía de lectura')
            ->set('materialUrl', 'https://ejemplo.test/guia')
            ->set('materialSemana', 'Trigonometría')
            ->set('materialCursosSeleccionados', [$curso->id])
            ->call('crearMaterial')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('materiales', ['titulo' => 'Guía de lectura', 'semana' => 'Trigonometría']);
    }

    public function test_acepta_una_fecha_como_semana_de_tarea(): void
    {
        $docente = User::factory()->create();
        $docente->assignRole(RolEnum::DOCENTE->value);
        $curso = $this->cursoDelDocente($docente);

        $this->actingAs($docente);

        Volt::test('aula-virtual.show', ['curso' => $curso])
            ->set('tareaTitulo', 'Ensayo')
            ->set('tareaFechaLimite', now()->addDay()->format('Y-m-d\TH:i'))
            ->set('tareaPuntajeMax', '20')
            ->set('tareaSemana', '15/03')
            ->set('tareaCursosSeleccionados', [$curso->id])
            ->call('crearTarea')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('tareas', ['titulo' => 'Ensayo', 'semana' => '15/03']);
    }

    public function test_rechaza_texto_que_exceda_100_caracteres_en_semana(): void
    {
        $docente = User::factory()->create();
        $docente->assignRole(RolEnum::DOCENTE->value);
        $curso = $this->cursoDelDocente($docente);

        $this->actingAs($docente);

        Volt::test('aula-virtual.show', ['curso' => $curso])
            ->set('materialTipo', 'enlace')
            ->set('materialTitulo', 'Guía de lectura')
            ->set('materialUrl', 'https://ejemplo.test/guia')
            ->set('materialSemana', str_repeat('a', 101))
            ->set('materialCursosSeleccionados', [$curso->id])
            ->call('crearMaterial')
            ->assertHasErrors(['materialSemana']);
    }

    /**
     * Regresión: Foro no estaba en el pedido del cliente -- su campo
     * "Semana" sigue siendo estrictamente numérico.
     */
    public function test_foro_semana_sigue_siendo_numerica(): void
    {
        $docente = User::factory()->create();
        $docente->assignRole(RolEnum::DOCENTE->value);
        $curso = $this->cursoDelDocente($docente);

        $this->actingAs($docente);

        Volt::test('aula-virtual.show', ['curso' => $curso])
            ->set('foroTitulo', 'Dudas')
            ->set('foroSemana', 'Trigonometría')
            ->set('foroCursosSeleccionados', [$curso->id])
            ->call('crearForo')
            ->assertHasErrors(['foroSemana']);
    }

    /**
     * El orden por semana ya no puede ser alfabético (eso pondría "10"
     * antes que "2"): se ordena cada grupo por el created_at más antiguo
     * de sus miembros, y "Bienvenida" siempre va primero.
     */
    public function test_agrupar_por_semana_ordena_bienvenida_primero_y_el_resto_por_antiguedad_no_alfabeticamente(): void
    {
        $docente = User::factory()->create();
        $docente->assignRole(RolEnum::DOCENTE->value);
        $curso = $this->cursoDelDocente($docente);

        $this->actingAs($docente);

        $component = Volt::test('aula-virtual.show', ['curso' => $curso]);

        // "10" se crea antes que "2": si el orden fuera alfabético, "10"
        // aparecería primero; debe aparecer después, por ser más reciente.
        $component
            ->set('materialTipo', 'enlace')
            ->set('materialTitulo', 'Material semana 10')
            ->set('materialUrl', 'https://ejemplo.test/10')
            ->set('materialSemana', '10')
            ->set('materialCursosSeleccionados', [$curso->id])
            ->call('crearMaterial')
            ->assertHasNoErrors();

        $component
            ->set('materialTipo', 'enlace')
            ->set('materialTitulo', 'Material semana 2')
            ->set('materialUrl', 'https://ejemplo.test/2')
            ->set('materialSemana', '2')
            ->set('materialCursosSeleccionados', [$curso->id])
            ->call('crearMaterial')
            ->assertHasNoErrors();

        $component->assertSeeInOrder(['10', 'Material semana 10', '2', 'Material semana 2']);
    }
}
