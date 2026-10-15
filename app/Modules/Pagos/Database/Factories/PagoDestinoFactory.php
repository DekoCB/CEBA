<?php

declare(strict_types=1);

namespace App\Modules\Pagos\Database\Factories;

use App\Modules\Pagos\Models\ConceptoPago;
use App\Modules\Pagos\Models\Pago;
use App\Modules\Pagos\Models\PagoDestino;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PagoDestino>
 */
class PagoDestinoFactory extends Factory
{
    protected $model = PagoDestino::class;

    public function definition(): array
    {
        return [
            'pago_id' => Pago::factory(),
            'concepto_id' => ConceptoPago::factory(),
            'monto' => $this->faker->randomFloat(2, 20, 200),
        ];
    }
}
