<?php

declare(strict_types=1);

namespace App\Modules\Matricula\Database\Seeders;

use App\Models\User;
use App\Modules\Academico\Enums\TipoPublicoEnum;
use App\Modules\Academico\Models\Ciclo;
use App\Modules\Academico\Models\Horario;
use App\Modules\Matricula\DTOs\RegistrarApoderadoData;
use App\Modules\Matricula\DTOs\RegistrarEstudianteData;
use App\Modules\Matricula\DTOs\RegistrarMatriculaData;
use App\Modules\Matricula\Services\MatriculaService;
use App\Shared\ValueObjects\Dni;
use App\Shared\ValueObjects\Telefono;
use Illuminate\Database\Seeder;

class MatriculaDemoSeeder extends Seeder
{
    public function run(): void
    {
        $ciclo = Ciclo::query()->where('estado', 'activo')->first();

        if (! $ciclo) {
            return;
        }

        /** @var MatriculaService $service */
        $service = app(MatriculaService::class);

        // El público (mayor/menor) ya no es una propiedad del grado, sino
        // del grupo/horario (Grupo A/B) -- se busca un horario de cada tipo
        // para saber en qué grado y sección matricular a cada estudiante
        // demo de forma coherente con su edad.
        $horarioMayor = Horario::query()->where('ciclo_id', $ciclo->id)->where('tipo_publico', TipoPublicoEnum::MAYOR)->first();
        $horarioMenor = Horario::query()->where('ciclo_id', $ciclo->id)->where('tipo_publico', TipoPublicoEnum::MENOR)->first();

        if ($horarioMayor) {
            $estudianteMayor = $service->registrarEstudiante(new RegistrarEstudianteData(
                nombres: 'Estudiante',
                apellidos: 'Demo',
                dni: new Dni('45678912'),
                fechaNacimiento: now()->subYears(34)->format('Y-m-d'),
                estadoCivil: null,
                direccion: 'Jr. Los Álamos 234, Lima',
                celular: new Telefono('987654321'),
                observaciones: null,
            ));

            $service->matricular($estudianteMayor, new RegistrarMatriculaData(
                cicloId: $ciclo->id,
                gradoId: $horarioMayor->grado_id,
                seccion: $horarioMayor->seccion,
                observaciones: null,
                registradoPor: null,
            ));

            // Vincula la cuenta de portal "estudiante@ceba.test" a esta
            // ficha real, para poder probar la vista de estudiante en Aula
            // Virtual con datos coherentes (matriculada en el mismo grado y
            // ciclo que el curso virtual del docente demo).
            $usuarioEstudiante = User::query()->where('email', 'estudiante@ceba.test')->first();
            if ($usuarioEstudiante) {
                $estudianteMayor->update(['user_id' => $usuarioEstudiante->id]);
            }
        }

        if ($horarioMenor) {
            $estudianteMenor = $service->registrarEstudiante(new RegistrarEstudianteData(
                nombres: 'Diego',
                apellidos: 'Torres Huamán',
                dni: new Dni('78912345'),
                fechaNacimiento: now()->subYears(15)->format('Y-m-d'),
                estadoCivil: null,
                direccion: 'Av. Las Flores 512, Lima',
                celular: null,
                observaciones: null,
            ));

            $service->registrarApoderado($estudianteMenor, new RegistrarApoderadoData(
                nombres: 'Marisol Huamán Ríos',
                dni: new Dni('41234567'),
                celular: new Telefono('956123478'),
                correo: 'marisol.huaman@example.com',
                direccion: 'Av. Las Flores 512, Lima',
                parentesco: 'Madre',
            ));

            $service->matricular($estudianteMenor, new RegistrarMatriculaData(
                cicloId: $ciclo->id,
                gradoId: $horarioMenor->grado_id,
                seccion: $horarioMenor->seccion,
                observaciones: null,
                registradoPor: null,
            ));
        }
    }
}
