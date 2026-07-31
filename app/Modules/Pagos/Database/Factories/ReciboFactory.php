<?php

declare(strict_types=1);

namespace App\Modules\Pagos\Database\Factories;

use App\Modules\Pagos\Models\Pago;
use App\Modules\Pagos\Models\Recibo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Recibo>
 */
class ReciboFactory extends Factory
{
    protected $model = Recibo::class;

    public function definition(): array
    {
        return [
            'pago_id' => Pago::factory(),
            'numero_recibo' => 'R-'.now()->format('Y').'-'.$this->faker->unique()->numerify('######'),
            'emitido_en' => now(),
        ];
    }
}
