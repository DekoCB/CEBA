<?php

declare(strict_types=1);

namespace App\Modules\Identidad\Services;

use App\Models\User;
use App\Modules\Identidad\DTOs\ActualizarUsuarioData;
use App\Modules\Identidad\DTOs\CrearUsuarioData;
use App\Modules\Identidad\Repositories\Contracts\UserRepositoryInterface;
use App\Shared\Enums\EstadoUsuarioEnum;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserManagementService
{
    public function __construct(
        private readonly UserRepositoryInterface $usuarios,
    ) {}

    public function listar(?string $termino, ?string $rol, int $perPage = 15): LengthAwarePaginator
    {
        return $this->usuarios->buscar($termino, $rol, $perPage);
    }

    public function crear(CrearUsuarioData $data): User
    {
        return DB::transaction(function () use ($data) {
            /** @var User $usuario */
            $usuario = $this->usuarios->create([
                'name' => $data->name,
                'email' => $data->email,
                'dni' => $data->dni->valor(),
                'phone' => $data->phone?->numero(),
                'password' => Hash::make($data->password),
                'estado' => EstadoUsuarioEnum::ACTIVO,
                'email_verified_at' => now(),
            ]);

            $usuario->assignRole($data->rol->value);

            return $usuario;
        });
    }

    public function actualizar(User $usuario, ActualizarUsuarioData $data): User
    {
        return $this->usuarios->update($usuario, [
            'name' => $data->name,
            'email' => $data->email,
            'dni' => $data->dni->valor(),
            'phone' => $data->phone?->numero(),
            'estado' => $data->estado,
        ]);
    }

    public function cambiarRol(User $usuario, string $rol): void
    {
        $usuario->syncRoles([$rol]);
    }

    public function emailDisponible(string $email, ?int $exceptoId = null): bool
    {
        return ! $this->usuarios->existeEmail($email, $exceptoId);
    }

    public function dniDisponible(string $dni, ?int $exceptoId = null): bool
    {
        return ! $this->usuarios->existeDni($dni, $exceptoId);
    }
}
