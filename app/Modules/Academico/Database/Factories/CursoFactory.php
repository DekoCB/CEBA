<?php

declare(strict_types=1);

namespace App\Modules\Academico\Database\Factories;

use App\Modules\Academico\Models\Curso;
use App\Modules\Academico\Models\Grado;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Curso>
 */
class CursoFactory extends Factory
{
    protected $model = Curso::class;

    public function definition(): array
    {
        return [
            'nombre' => $this->faker->randomElement(['Comunicación', 'Matemática', 'Ciencias Sociales', 'Ciencia y Tecnología', 'Inglés']),
            'codigo' => strtoupper($this->faker->unique()->bothify('CUR-###')),
            'grado_id' => Grado::factory(),
            'horas' => $this->faker->numberBetween(60, 120),
            'activo' => true,
        ];
    }
}
