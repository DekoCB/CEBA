<?php

namespace Database\Seeders;

use App\Models\User;
use App\Modules\Academico\Database\Seeders\AcademicoDemoSeeder;
use App\Modules\Identidad\Database\Seeders\RolesAndPermissionsSeeder;
use App\Shared\Enums\EstadoUsuarioEnum;
use App\Shared\Enums\RolEnum;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(RolesAndPermissionsSeeder::class);

        $direccion = User::factory()->create([
            'name' => 'Administración CEBA',
            'email' => 'direccion@ceba.test',
            'dni' => '00000001',
            'estado' => EstadoUsuarioEnum::ACTIVO,
        ]);
        $direccion->assignRole(RolEnum::DIRECCION->value);

        $docente = User::factory()->create([
            'name' => 'Docente Demo',
            'email' => 'docente@ceba.test',
            'dni' => '00000002',
            'estado' => EstadoUsuarioEnum::ACTIVO,
        ]);
        $docente->assignRole(RolEnum::DOCENTE->value);

        $estudiante = User::factory()->create([
            'name' => 'Estudiante Demo',
            'email' => 'estudiante@ceba.test',
            'dni' => '00000003',
            'estado' => EstadoUsuarioEnum::ACTIVO,
        ]);
        $estudiante->assignRole(RolEnum::ESTUDIANTE->value);

        $this->call(AcademicoDemoSeeder::class);
    }
}
