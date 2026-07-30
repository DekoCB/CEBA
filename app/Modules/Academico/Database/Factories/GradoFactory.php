<?php

declare(strict_types=1);

namespace App\Modules\Academico\Database\Factories;

use App\Modules\Academico\Enums\TipoPublicoEnum;
use App\Modules\Academico\Models\Grado;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Grado>
 */
class GradoFactory extends Factory
{
    protected $model = Grado::class;

    public function definition(): array
    {
        return [
            'nombre' => 'Grado '.$this->faker->word().' '.$this->faker->randomNumber(5),
            'tipo_publico' => $this->faker->randomElement(TipoPublicoEnum::cases()),
            'orden' => $this->faker->unique()->numberBetween(1, 250),
            'activo' => true,
        ];
    }
}
