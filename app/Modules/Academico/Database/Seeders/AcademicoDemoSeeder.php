<?php

declare(strict_types=1);

namespace App\Modules\Academico\Database\Seeders;

use App\Models\User;
use App\Modules\Academico\Enums\DiaSemanaEnum;
use App\Modules\Academico\Enums\EstadoCicloEnum;
use App\Modules\Academico\Enums\TipoCicloEnum;
use App\Modules\Academico\Enums\TipoPublicoEnum;
use App\Modules\Academico\Models\Aula;
use App\Modules\Academico\Models\Ciclo;
use App\Modules\Academico\Models\Curso;
use App\Modules\Academico\Models\Grado;
use App\Modules\Academico\Models\Horario;
use Illuminate\Database\Seeder;

class AcademicoDemoSeeder extends Seeder
{
    public function run(): void
    {
        $grados = collect([
            ['nombre' => 'Grado 1 - Mayores', 'tipo_publico' => TipoPublicoEnum::MAYOR, 'orden' => 1],
            ['nombre' => 'Grado 2 - Mayores', 'tipo_publico' => TipoPublicoEnum::MAYOR, 'orden' => 2],
            ['nombre' => 'Grado 1 - Menores', 'tipo_publico' => TipoPublicoEnum::MENOR, 'orden' => 1],
        ])->map(fn (array $datos) => Grado::query()->create($datos));

        $aulas = collect([
            ['nombre' => 'Aula 1', 'capacidad' => 30, 'ubicacion' => 'Piso 1'],
            ['nombre' => 'Aula 2', 'capacidad' => 25, 'ubicacion' => 'Piso 1'],
        ])->map(fn (array $datos) => Aula::query()->create($datos));

        $cursoComunicacion = Curso::query()->create([
            'nombre' => 'Comunicación',
            'codigo' => 'COM-101',
            'grado_id' => $grados[0]->id,
            'horas' => 80,
        ]);

        Curso::query()->create([
            'nombre' => 'Matemática',
            'codigo' => 'MAT-101',
            'grado_id' => $grados[0]->id,
            'horas' => 80,
        ]);

        $anio = (int) now()->format('Y');
        $mesActual = (int) now()->format('n');

        // Ciclo activo del semestre en curso, para que haya datos con los
        // que probar el resto de módulos desde ya.
        if ($mesActual >= 7) {
            $ciclo = Ciclo::query()->create([
                'nombre' => "Julio - Diciembre {$anio}",
                'tipo' => TipoCicloEnum::JUL_DIC,
                'anio' => $anio,
                'fecha_inicio' => "{$anio}-07-01",
                'fecha_fin' => "{$anio}-12-31",
                'estado' => EstadoCicloEnum::ACTIVO,
            ]);
        } else {
            $ciclo = Ciclo::query()->create([
                'nombre' => "Enero - Junio {$anio}",
                'tipo' => TipoCicloEnum::ENE_JUN,
                'anio' => $anio,
                'fecha_inicio' => "{$anio}-01-01",
                'fecha_fin' => "{$anio}-06-30",
                'estado' => EstadoCicloEnum::ACTIVO,
            ]);
        }

        // Centrado en "hoy" (no en el inicio del ciclo) para que el periodo
        // quede abierto sin importar en qué punto del ciclo se corra el seeder.
        $ciclo->periodosMatricula()->create([
            'fecha_inicio' => now()->subDays(10),
            'fecha_fin' => now()->addDays(20),
        ]);

        $docente = User::query()->where('email', 'docente@ceba.test')->first();

        if ($docente) {
            $horario = Horario::query()->create([
                'curso_id' => $cursoComunicacion->id,
                'docente_id' => $docente->id,
                'aula_id' => $aulas[0]->id,
                'ciclo_id' => $ciclo->id,
                'grado_id' => $grados[0]->id,
            ]);

            $horario->dias()->createMany([
                ['dia_semana' => DiaSemanaEnum::LUNES, 'hora_inicio' => '18:00:00', 'hora_fin' => '20:00:00'],
                ['dia_semana' => DiaSemanaEnum::MIERCOLES, 'hora_inicio' => '18:00:00', 'hora_fin' => '20:00:00'],
            ]);
        }
    }
}
