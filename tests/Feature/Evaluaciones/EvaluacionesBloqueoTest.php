<?php

namespace Tests\Feature\Evaluaciones;

use App\Models\User;
use App\Modules\Academico\Models\Horario;
use App\Modules\Evaluaciones\Services\EvaluacionService;
use App\Modules\Identidad\Database\Seeders\RolesAndPermissionsSeeder;
use App\Modules\Matricula\Models\Estudiante;
use App\Modules\Matricula\Models\Matricula;
use App\Modules\Pagos\Models\Cuota;
use App\Modules\Pagos\Models\PlanPago;
use App\Modules\Pagos\Services\BloqueoAccesoService;
use App\Shared\Enums\RolEnum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EvaluacionesBloqueoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function estudianteBloqueado(): array
    {
        $usuario = User::factory()->create();
        $usuario->assignRole(RolEnum::ESTUDIANTE->value);
        $estudiante = Estudiante::factory()->create(['user_id' => $usuario->id]);

        $horario = Horario::factory()->create();
        $matricula = Matricula::factory()->create([
            'estudiante_id' => $estudiante->id,
            'grado_id' => $horario->grado_id,
            'ciclo_id' => $horario->ciclo_id,
        ]);
        $plan = PlanPago::factory()->create(['matricula_id' => $matricula->id]);
        Cuota::factory()->vencida()->create(['plan_pago_id' => $plan->id, 'numero' => 1]);
        Cuota::factory()->vencida()->create(['plan_pago_id' => $plan->id, 'numero' => 2]);

        $this->app->make(BloqueoAccesoService::class)->evaluarYDesbloquear($estudiante);

        return [$usuario, $estudiante, $horario];
    }

    public function test_un_estudiante_bloqueado_no_ve_sus_notas(): void
    {
        [$usuario, $estudiante, $horario] = $this->estudianteBloqueado();

        $evaluacion = $this->app->make(EvaluacionService::class)->crear($horario, 'Evaluación mensual', now()->format('Y-m-d'));
        $this->app->make(EvaluacionService::class)->calificar($evaluacion, $estudiante, 18.0, null, null);
        $this->app->make(EvaluacionService::class)->publicar($evaluacion);

        $this->actingAs($usuario)
            ->get(route('evaluaciones.show', $horario))
            ->assertOk()
            ->assertSee('no están disponibles')
            ->assertDontSee('18.00');
    }

    public function test_un_estudiante_no_bloqueado_si_ve_sus_notas(): void
    {
        $usuario = User::factory()->create();
        $usuario->assignRole(RolEnum::ESTUDIANTE->value);
        $estudiante = Estudiante::factory()->create(['user_id' => $usuario->id]);

        $horario = Horario::factory()->create();
        Matricula::factory()->create([
            'estudiante_id' => $estudiante->id,
            'grado_id' => $horario->grado_id,
            'ciclo_id' => $horario->ciclo_id,
        ]);

        $evaluacion = $this->app->make(EvaluacionService::class)->crear($horario, 'Evaluación mensual', now()->format('Y-m-d'));
        $this->app->make(EvaluacionService::class)->calificar($evaluacion, $estudiante, 18.0, null, null);
        $this->app->make(EvaluacionService::class)->publicar($evaluacion);

        $this->actingAs($usuario)
            ->get(route('evaluaciones.show', $horario))
            ->assertOk()
            ->assertSee('18.00')
            ->assertDontSee('no están disponibles');
    }

    public function test_un_estudiante_bloqueado_no_puede_generar_su_libreta(): void
    {
        [$usuario, $estudiante, $horario] = $this->estudianteBloqueado();
        $ciclo = $horario->ciclo;

        $this->actingAs($usuario)
            ->get(route('evaluaciones.libreta', ['estudiante' => $estudiante, 'ciclo' => $ciclo]))
            ->assertOk()
            ->assertSee('libreta no está disponible');
    }
}
