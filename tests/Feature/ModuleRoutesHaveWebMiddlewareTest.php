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
            'matricula.wizard' => ['matricula.wizard'],
            'matricula.show' => ['matricula.show'],
            'aula-virtual.index' => ['aula-virtual.index'],
            'aula-virtual.show' => ['aula-virtual.show'],
            'aula-virtual.tarea' => ['aula-virtual.tarea'],
            'asistencia.index' => ['asistencia.index'],
            'asistencia.show' => ['asistencia.show'],
            'evaluaciones.index' => ['evaluaciones.index'],
            'evaluaciones.show' => ['evaluaciones.show'],
            'evaluaciones.libreta' => ['evaluaciones.libreta'],
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
