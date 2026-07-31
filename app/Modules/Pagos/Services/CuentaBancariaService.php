<?php

declare(strict_types=1);

namespace App\Modules\Pagos\Services;

use App\Modules\Pagos\Models\CuentaBancaria;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;

class CuentaBancariaService
{
    /**
     * @return Collection<int, CuentaBancaria>
     */
    public function listar(): Collection
    {
        return CuentaBancaria::query()->orderBy('banco')->get();
    }

    /**
     * @return Collection<int, CuentaBancaria>
     */
    public function activas(): Collection
    {
        return CuentaBancaria::query()->where('activa', true)->orderBy('banco')->get();
    }

    public function crear(string $banco, string $numeroCuenta, ?string $cci, string $titular, ?UploadedFile $qr): CuentaBancaria
    {
        /** @var CuentaBancaria $cuenta */
        $cuenta = CuentaBancaria::query()->create([
            'banco' => $banco,
            'numero_cuenta' => $numeroCuenta,
            'cci' => $cci,
            'titular' => $titular,
            'activa' => true,
        ]);

        if ($qr) {
            $cuenta->addMedia($qr)->toMediaCollection('qr');
        }

        return $cuenta;
    }

    public function actualizar(CuentaBancaria $cuenta, string $banco, string $numeroCuenta, ?string $cci, string $titular, bool $activa, ?UploadedFile $qr): CuentaBancaria
    {
        $cuenta->update([
            'banco' => $banco,
            'numero_cuenta' => $numeroCuenta,
            'cci' => $cci,
            'titular' => $titular,
            'activa' => $activa,
        ]);

        if ($qr) {
            $cuenta->addMedia($qr)->toMediaCollection('qr');
        }

        return $cuenta;
    }
}
