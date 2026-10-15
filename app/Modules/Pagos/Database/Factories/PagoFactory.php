<?php

declare(strict_types=1);

namespace App\Modules\Pagos\Database\Factories;

use App\Modules\Matricula\Models\Estudiante;
use App\Modules\Pagos\Enums\EstadoPagoEnum;
use App\Modules\Pagos\Enums\MetodoPagoEnum;
use App\Modules\Pagos\Models\ConceptoPago;
use App\Modules\Pagos\Models\Pago;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Pago>
 */
class PagoFactory extends Factory
{
    protected $model = Pago::class;

    public function definition(): array
    {
        return [
            'estudiante_id' => Estudiante::factory(),
            'concepto_id' => ConceptoPago::factory(),
            'cuota_id' => null,
            'monto' => $this->faker->randomFloat(2, 50, 300),
            'metodo' => MetodoPagoEnum::YAPE,
            'estado' => EstadoPagoEnum::PENDIENTE,
            'fecha_pago' => now()->format('Y-m-d'),
        ];
    }

    public function aprobado(): static
    {
        return $this->state(fn () => [
            'estado' => EstadoPagoEnum::APROBADO,
            'fecha_aprobacion' => now(),
        ]);
    }

    /**
     * Todo Pago tiene al menos un PagoDestino (ver
     * 2027_02_01_090000_create_pago_destinos_table.php) -- Cuota/
     * CargoAdicional::montoPagado() ya no suman por Pago directo, sino por
     * destino, así que un Pago creado por factory sin su destino se vería
     * como "sin pagar" pase lo que pase en Pago::$monto. Se copian los
     * mismos cuota_id/cargo_adicional_id/monto que ya trae el Pago, para
     * que los tests existentes que crean Pago::factory()->create([...])
     * directo sigan viendo el saldo correcto sin enterarse de la tabla
     * nueva.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (Pago $pago) {
            $pago->destinos()->create([
                'concepto_id' => $pago->concepto_id,
                'cuota_id' => $pago->cuota_id,
                'cargo_adicional_id' => $pago->cargo_adicional_id,
                'detalle' => $pago->detalle,
                'monto' => $pago->monto,
            ]);
        });
    }
}
