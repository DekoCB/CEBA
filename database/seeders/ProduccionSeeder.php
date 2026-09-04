<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use App\Modules\Identidad\Database\Seeders\RolesAndPermissionsSeeder;
use App\Shared\Enums\EstadoUsuarioEnum;
use App\Shared\Enums\RolEnum;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Seeder para el primer despliegue en producción: solo los roles/permisos
 * base y la cuenta real de Dirección, sin nada de datos de ejemplo. A
 * diferencia de DatabaseSeeder (pensado para desarrollo local, mezcla lo
 * anterior con estudiantes/pagos/evaluaciones ficticios vía
 * DemoRobustoSeeder y compañía), este es el único seeder que corresponde
 * correr contra la base de datos real del colegio.
 *
 * Uso (una sola vez, tras el primer `migrate --force` en Hostinger):
 *   php artisan db:seed --class=Database\\Seeders\\ProduccionSeeder --force
 *
 * La contraseña se genera al azar y se imprime una sola vez en la consola
 * -- no queda guardada en ningún lado más que en el hash de la BD. Cámbiala
 * apenas inicies sesión, o usa "¿Olvidó su contraseña?" en vez de la
 * impresa si el correo saliente (sección 2 de docs/DESPLIEGUE.md) ya está
 * configurado.
 */
class ProduccionSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(RolesAndPermissionsSeeder::class);

        if (User::query()->where('email', 'walter.galindo@gmail.com')->exists()) {
            $this->command->warn('La cuenta de Dirección ya existe -- no se vuelve a crear.');

            return;
        }

        $contrasenaTemporal = Str::password(24);

        $direccion = User::query()->create([
            'name' => 'Walter Galindo',
            'email' => 'walter.galindo@gmail.com',
            'dni' => '45139618',
            'password' => Hash::make($contrasenaTemporal),
            'email_verified_at' => now(),
            'estado' => EstadoUsuarioEnum::ACTIVO,
        ]);
        $direccion->assignRole(RolEnum::DIRECCION->value);

        $this->command->info('Cuenta de Dirección creada: walter.galindo@gmail.com');
        $this->command->warn("Contraseña temporal (cámbiala al iniciar sesión): {$contrasenaTemporal}");
    }
}
