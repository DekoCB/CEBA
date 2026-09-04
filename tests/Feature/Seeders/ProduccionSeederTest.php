<?php

namespace Tests\Feature\Seeders;

use App\Models\User;
use App\Shared\Enums\EstadoUsuarioEnum;
use App\Shared\Enums\RolEnum;
use Database\Seeders\ProduccionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProduccionSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_crea_los_roles_y_la_cuenta_real_de_direccion(): void
    {
        $this->seed(ProduccionSeeder::class);

        $direccion = User::query()->where('email', 'walter.galindo@gmail.com')->first();

        $this->assertNotNull($direccion);
        $this->assertSame('Walter Galindo', $direccion->name);
        $this->assertSame('45139618', $direccion->dni);
        $this->assertSame(EstadoUsuarioEnum::ACTIVO, $direccion->estado);
        $this->assertTrue($direccion->hasRole(RolEnum::DIRECCION->value));
    }

    public function test_no_crea_ninguna_cuenta_de_ejemplo(): void
    {
        $this->seed(ProduccionSeeder::class);

        $this->assertSame(1, User::query()->count());
    }

    public function test_correrlo_dos_veces_no_duplica_la_cuenta(): void
    {
        $this->seed(ProduccionSeeder::class);
        $this->seed(ProduccionSeeder::class);

        $this->assertSame(1, User::query()->where('email', 'walter.galindo@gmail.com')->count());
    }
}
