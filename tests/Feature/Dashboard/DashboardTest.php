<?php

namespace Tests\Feature\Dashboard;

use App\Models\User;
use App\Modules\Academico\Models\Curso;
use App\Modules\Academico\Models\Grado;
use App\Modules\Academico\Models\Horario;
use App\Modules\Asistencia\Models\Asistencia;
use App\Modules\Evaluaciones\Models\Calificacion;
use App\Modules\Evaluaciones\Models\Evaluacion;
use App\Modules\Identidad\Database\Seeders\RolesAndPermissionsSeeder;
use App\Modules\Matricula\Models\Estudiante;
use App\Modules\Matricula\Models\Matricula;
use App\Modules\Pagos\Models\Cuota;
use App\Modules\Pagos\Models\Pago;
use App\Modules\Pagos\Models\PlanPago;
use App\Modules\Pagos\Services\BloqueoAccesoService;
use App\Shared\Enums\RolEnum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_direccion_carga_el_dashboard_sin_errores(): void
    {
        $direccion = User::factory()->create();
        $direccion->assignRole(RolEnum::DIRECCION->value);

        $this->actingAs($direccion)
            ->get(route('dashboard'))
            ->assertOk();
    }

    public function test_docente_ve_sus_horarios_en_el_dashboard(): void
    {
        $docente = User::factory()->create();
        $docente->assignRole(RolEnum::DOCENTE->value);
        Horario::factory()->count(2)->create(['docente_id' => $docente->id]);

        $this->actingAs($docente)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Mis horarios')
            ->assertSee('2');
    }

    public function test_docente_ve_la_asistencia_de_sus_cursos(): void
    {
        $docente = User::factory()->create();
        $docente->assignRole(RolEnum::DOCENTE->value);
        $curso = Curso::factory()->create(['nombre' => 'Matematica']);
        $horario = Horario::factory()->create(['docente_id' => $docente->id, 'curso_id' => $curso->id]);
        Asistencia::factory()->create(['horario_id' => $horario->id, 'estado' => 'presente']);

        $this->actingAs($docente)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Asistencia de mis cursos')
            ->assertSee('Matematica');
    }

    public function test_docente_ve_la_distribucion_de_notas_de_sus_evaluaciones(): void
    {
        $docente = User::factory()->create();
        $docente->assignRole(RolEnum::DOCENTE->value);
        $horario = Horario::factory()->create(['docente_id' => $docente->id]);
        $evaluacion = Evaluacion::factory()->create(['horario_id' => $horario->id]);
        Calificacion::factory()->create(['evaluacion_id' => $evaluacion->id, 'nota_numerica' => 18]);

        $this->actingAs($docente)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Distribución de notas');
    }

    public function test_docente_sin_asistencia_ni_notas_no_ve_los_graficos(): void
    {
        $docente = User::factory()->create();
        $docente->assignRole(RolEnum::DOCENTE->value);
        Horario::factory()->create(['docente_id' => $docente->id]);

        $this->actingAs($docente)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Asistencia de mis cursos')
            ->assertDontSee('Distribución de notas');
    }

    public function test_estudiante_al_dia_ve_su_proxima_cuota(): void
    {
        $usuario = User::factory()->create();
        $usuario->assignRole(RolEnum::ESTUDIANTE->value);
        $estudiante = Estudiante::factory()->create(['user_id' => $usuario->id]);
        $matricula = Matricula::factory()->create(['estudiante_id' => $estudiante->id]);
        $plan = PlanPago::factory()->create(['matricula_id' => $matricula->id]);
        Cuota::factory()->create(['plan_pago_id' => $plan->id, 'numero' => 1, 'monto' => 100]);

        $this->actingAs($usuario)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Próxima cuota')
            ->assertSee('100.00')
            ->assertDontSee('no está disponible');
    }

    public function test_estudiante_ve_su_rendimiento_mensual_en_notas(): void
    {
        $usuario = User::factory()->create();
        $usuario->assignRole(RolEnum::ESTUDIANTE->value);
        $estudiante = Estudiante::factory()->create(['user_id' => $usuario->id]);
        $evaluacion = Evaluacion::factory()->publicada()->create(['fecha' => now()->format('Y-m-d')]);
        Calificacion::factory()->create([
            'evaluacion_id' => $evaluacion->id,
            'estudiante_id' => $estudiante->id,
            'nota_numerica' => 16,
        ]);

        $this->actingAs($usuario)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Mi rendimiento');
    }

    public function test_estudiante_sin_notas_no_ve_el_grafico_de_rendimiento(): void
    {
        $usuario = User::factory()->create();
        $usuario->assignRole(RolEnum::ESTUDIANTE->value);
        Estudiante::factory()->create(['user_id' => $usuario->id]);

        $this->actingAs($usuario)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Mi rendimiento');
    }

    public function test_estudiante_no_ve_notas_de_evaluaciones_sin_publicar_en_su_rendimiento(): void
    {
        $usuario = User::factory()->create();
        $usuario->assignRole(RolEnum::ESTUDIANTE->value);
        $estudiante = Estudiante::factory()->create(['user_id' => $usuario->id]);
        $evaluacion = Evaluacion::factory()->create(['fecha' => now()->format('Y-m-d')]);
        Calificacion::factory()->create([
            'evaluacion_id' => $evaluacion->id,
            'estudiante_id' => $estudiante->id,
            'nota_numerica' => 16,
        ]);

        $this->actingAs($usuario)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Mi rendimiento');
    }

    public function test_estudiante_bloqueado_ve_el_aviso_de_deuda(): void
    {
        $usuario = User::factory()->create();
        $usuario->assignRole(RolEnum::ESTUDIANTE->value);
        $estudiante = Estudiante::factory()->create(['user_id' => $usuario->id]);
        $matricula = Matricula::factory()->create(['estudiante_id' => $estudiante->id]);
        $plan = PlanPago::factory()->create(['matricula_id' => $matricula->id]);
        Cuota::factory()->vencida()->create(['plan_pago_id' => $plan->id, 'numero' => 1]);
        Cuota::factory()->vencida()->create(['plan_pago_id' => $plan->id, 'numero' => 2]);

        $this->app->make(BloqueoAccesoService::class)->evaluarYDesbloquear($estudiante);

        $this->actingAs($usuario)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('no está disponible');
    }

    public function test_coordinador_ve_el_resumen_academico(): void
    {
        $coordinador = User::factory()->create();
        $coordinador->assignRole(RolEnum::COORDINADOR->value);
        Estudiante::factory()->count(3)->create(['estado' => 'activo']);

        $this->actingAs($coordinador)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Estudiantes activos')
            ->assertSee('Evaluaciones sin publicar')
            ->assertSee('Ingresos aprobados')
            ->assertSee('Notificaciones');
    }

    public function test_tesoreria_ve_la_cola_de_aprobacion(): void
    {
        $tesoreria = User::factory()->create();
        $tesoreria->assignRole(RolEnum::TESORERIA->value);
        Pago::factory()->count(2)->create(['estado' => 'pendiente']);

        $this->actingAs($tesoreria)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Pagos por aprobar')
            ->assertSee('2')
            ->assertSee('Ingresos aprobados')
            ->assertSee('pago');
    }

    public function test_administrativo_ve_el_grafico_de_ingresos(): void
    {
        $administrativo = User::factory()->create();
        $administrativo->assignRole(RolEnum::ADMINISTRATIVO->value);
        Pago::factory()->create(['estado' => 'aprobado', 'monto' => 250, 'fecha_aprobacion' => now()]);

        $this->actingAs($administrativo)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Ingresos aprobados');
    }

    public function test_administrativo_no_ve_la_cola_de_aprobacion_de_tesoreria(): void
    {
        $administrativo = User::factory()->create();
        $administrativo->assignRole(RolEnum::ADMINISTRATIVO->value);

        $this->actingAs($administrativo)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Pagos por aprobar');
    }

    public function test_coordinador_sin_alertas_ve_notificaciones_vacias(): void
    {
        $coordinador = User::factory()->create();
        $coordinador->assignRole(RolEnum::COORDINADOR->value);

        $this->actingAs($coordinador)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Todo al día. No hay notificaciones pendientes.');
    }

    public function test_coordinador_ve_asistencia_por_grado_cuando_hay_registros(): void
    {
        $coordinador = User::factory()->create();
        $coordinador->assignRole(RolEnum::COORDINADOR->value);

        $grado = Grado::factory()->create(['nombre' => '1ro de Secundaria']);
        $horario = Horario::factory()->create(['grado_id' => $grado->id]);
        Asistencia::factory()->create([
            'horario_id' => $horario->id,
            'estado' => 'presente',
        ]);

        $this->actingAs($coordinador)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Asistencia por grado')
            ->assertSee('1ro de Secundaria');
    }

    public function test_un_usuario_sin_rol_reconocido_ve_el_mensaje_de_bienvenida(): void
    {
        $usuario = User::factory()->create();

        $this->actingAs($usuario)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Bienvenido a CEBA');
    }
}
