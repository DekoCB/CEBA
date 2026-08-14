<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Regresión: las rutas de un módulo (registradas vía loadRoutesFrom en su
 * ServiceProvider) NO heredan el grupo de middleware "web" automáticamente
 * como sí lo hace routes/web.php. Sin "web" no hay StartSession, así que la
 * ruta nunca ve al usuario autenticado y queda en loop con /login. Ver
 * App\Shared\Providers\ModuleServiceProvider::loadWebRoutesFrom().
 */
class ModuleRoutesHaveWebMiddlewareTest extends TestCase
{
    /**
     * @return array<string, array{string}>
     */
    public static function moduleRouteNames(): array
    {
        return [
            'landing' => ['landing'],
            'dashboard' => ['dashboard'],
            'usuarios.index' => ['usuarios.index'],
            'usuarios.show' => ['usuarios.show'],
            'roles.index' => ['roles.index'],
            'auditoria.index' => ['auditoria.index'],
            'academico.grados.index' => ['academico.grados.index'],
            'academico.aulas.index' => ['academico.aulas.index'],
            'academico.cursos.index' => ['academico.cursos.index'],
            'academico.ciclos.index' => ['academico.ciclos.index'],
            'academico.ciclos.show' => ['academico.ciclos.show'],
            'academico.horarios.index' => ['academico.horarios.index'],
            'matricula.index' => ['matricula.index'],
            'matricula.carga-masiva-estudiantes' => ['matricula.carga-masiva-estudiantes'],
            'matricula.carga-masiva' => ['matricula.carga-masiva'],
            'matricula.show' => ['matricula.show'],
            'aula-virtual.index' => ['aula-virtual.index'],
            'aula-virtual.mis-tareas' => ['aula-virtual.mis-tareas'],
            'aula-virtual.show' => ['aula-virtual.show'],
            'aula-virtual.tarea' => ['aula-virtual.tarea'],
            'asistencia.index' => ['asistencia.index'],
            'asistencia.show' => ['asistencia.show'],
            'evaluaciones.index' => ['evaluaciones.index'],
            'evaluaciones.show' => ['evaluaciones.show'],
            'evaluaciones.libreta' => ['evaluaciones.libreta'],
            'evaluaciones.mis-calificaciones' => ['evaluaciones.mis-calificaciones'],
            'incidencias.index' => ['incidencias.index'],
            'pagos.index' => ['pagos.index'],
            'pagos.mi-cuenta' => ['pagos.mi-cuenta'],
            'pagos.conceptos' => ['pagos.conceptos'],
            'pagos.cuentas-bancarias' => ['pagos.cuentas-bancarias'],
            'certificados.index' => ['certificados.index'],
            'certificados.mis-certificados' => ['certificados.mis-certificados'],
            'certificados.verificar' => ['certificados.verificar'],
            'reportes.index' => ['reportes.index'],
            'notificaciones.index' => ['notificaciones.index'],
            'notificaciones.plantillas' => ['notificaciones.plantillas'],
        ];
    }

    #[DataProvider('moduleRouteNames')]
    public function test_module_route_has_web_middleware(string $routeName): void
    {
        $route = Route::getRoutes()->getByName($routeName);

        $this->assertNotNull($route, "La ruta con nombre [{$routeName}] no existe.");
        $this->assertContains(
            'web',
            $route->gatherMiddleware(),
            "La ruta [{$routeName}] no tiene el grupo de middleware \"web\" — la sesión nunca se iniciará y quedará en loop con /login."
        );
    }
}
