<?php

declare(strict_types=1);

namespace App\Modules\Identidad\Database\Seeders;

use App\Shared\Enums\RolEnum;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Siembra los 6 roles institucionales y la matriz de permisos base descrita
 * en el documento de arquitectura (sección 05). Los permisos siguen la
 * convención modulo.accion y son editables luego desde la UI de Roles.
 *
 * Coordinador concentra la operación de matrícula, cobranza y
 * certificaciones además de la supervisión académica: en un CEBA la misma
 * persona suele cubrir estos tres frentes, por lo que no se modelan como
 * roles institucionales separados.
 */
class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * @var array<string, list<string>>
     */
    private const MATRIZ = [
        RolEnum::DIRECCION->value => ['*'],

        RolEnum::COORDINADOR->value => [
            'academico.ver', 'academico.gestionar',
            'matricula.crear', 'matricula.ver', 'matricula.editar', 'matricula.anular',
            'aula_virtual.ver',
            'evaluaciones.ver', 'evaluaciones.publicar',
            'asistencia.ver',
            'pagos.ver', 'pagos.gestionar',
            'certificados.ver', 'certificados.emitir', 'certificados.duplicar',
            'reportes.academicos', 'reportes.matricula', 'reportes.financieros', 'reportes.certificados',
            'whatsapp.enviar',
        ],

        RolEnum::ADMINISTRATIVO->value => [
            'matricula.ver',
            'pagos.registrar',
            'asistencia.ver',
            'reportes.operativos',
            'whatsapp.enviar',
            'usuarios.ver',
            'usuarios.gestionar_sesiones',
            'auditoria.ver',
        ],

        RolEnum::TESORERIA->value => [
            'pagos.ver', 'pagos.aprobar', 'pagos.rechazar',
            'tesoreria.gestionar',
            'reportes.financieros',
        ],

        RolEnum::DOCENTE->value => [
            'aula_virtual.gestionar_propio',
            'evaluaciones.registrar',
            'asistencia.registrar',
            'reportes.propios',
        ],

        RolEnum::ESTUDIANTE->value => [
            'aula_virtual.ver_propio',
            'evaluaciones.ver_propio',
            'asistencia.ver_propio',
            'pagos.ver_propio', 'pagos.subir_comprobante',
            'certificados.solicitar',
        ],
    ];

    /**
     * @var list<string>
     */
    private const TODOS_LOS_PERMISOS = [
        'academico.ver', 'academico.gestionar',
        'matricula.crear', 'matricula.ver', 'matricula.editar', 'matricula.anular',
        'aula_virtual.ver', 'aula_virtual.gestionar_propio', 'aula_virtual.ver_propio',
        'evaluaciones.ver', 'evaluaciones.registrar', 'evaluaciones.publicar', 'evaluaciones.ver_propio',
        'asistencia.ver', 'asistencia.registrar', 'asistencia.ver_propio',
        'pagos.ver', 'pagos.registrar', 'pagos.gestionar', 'pagos.aprobar', 'pagos.rechazar',
        'pagos.ver_propio', 'pagos.subir_comprobante',
        'tesoreria.gestionar',
        'certificados.emitir', 'certificados.duplicar', 'certificados.ver', 'certificados.solicitar',
        'reportes.academicos', 'reportes.operativos', 'reportes.financieros',
        'reportes.matricula', 'reportes.certificados', 'reportes.propios', 'reportes.exportar',
        'whatsapp.enviar', 'whatsapp.ver',
        'roles.gestionar', 'auditoria.ver',
        'usuarios.ver', 'usuarios.crear', 'usuarios.editar', 'usuarios.gestionar_sesiones',
    ];

    public function run(): void
    {
        app()->make(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::TODOS_LOS_PERMISOS as $permiso) {
            Permission::findOrCreate($permiso, 'web');
        }

        foreach (self::MATRIZ as $rol => $permisos) {
            $role = Role::findOrCreate($rol, 'web');

            if ($permisos === ['*']) {
                $role->syncPermissions(self::TODOS_LOS_PERMISOS);

                continue;
            }

            $role->syncPermissions($permisos);
        }
    }
}
