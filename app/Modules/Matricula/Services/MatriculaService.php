<?php

declare(strict_types=1);

namespace App\Modules\Matricula\Services;

use App\Modules\Academico\Enums\TipoPublicoEnum;
use App\Modules\Academico\Models\Ciclo;
use App\Modules\Academico\Models\Grado;
use App\Modules\Academico\Models\PeriodoMatricula;
use App\Modules\Matricula\DTOs\RegistrarApoderadoData;
use App\Modules\Matricula\DTOs\RegistrarEstudianteData;
use App\Modules\Matricula\DTOs\RegistrarMatriculaData;
use App\Modules\Matricula\Enums\EstadoEstudianteEnum;
use App\Modules\Matricula\Enums\EstadoMatriculaEnum;
use App\Modules\Matricula\Events\EstudianteMatriculado;
use App\Modules\Matricula\Models\Apoderado;
use App\Modules\Matricula\Models\Estudiante;
use App\Modules\Matricula\Models\InstitucionProcedencia;
use App\Modules\Matricula\Models\Matricula;
use App\Modules\Matricula\Repositories\Contracts\EstudianteRepositoryInterface;
use App\Modules\Matricula\Repositories\Contracts\MatriculaRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MatriculaService
{
    public function __construct(
        private readonly EstudianteRepositoryInterface $estudiantes,
        private readonly MatriculaRepositoryInterface $matriculas,
    ) {}

    public function listarEstudiantes(?string $termino, ?string $estado, int $perPage = 15): LengthAwarePaginator
    {
        return $this->estudiantes->buscar($termino, $estado, $perPage);
    }

    public function dniDisponible(string $dni, ?int $exceptoId = null): bool
    {
        return ! $this->estudiantes->existeDni($dni, $exceptoId);
    }

    public static function esMenorDeEdad(string $fechaNacimiento): bool
    {
        return Carbon::parse($fechaNacimiento)->age < 18;
    }

    public function registrarEstudiante(RegistrarEstudianteData $data): Estudiante
    {
        return $this->estudiantes->create([
            'nombres' => $data->nombres,
            'apellidos' => $data->apellidos,
            'dni' => $data->dni->valor(),
            'fecha_nacimiento' => $data->fechaNacimiento,
            'es_menor_edad' => self::esMenorDeEdad($data->fechaNacimiento),
            'estado_civil' => $data->estadoCivil,
            'direccion' => $data->direccion,
            'celular' => $data->celular?->numero(),
            'email' => $data->email,
            'observaciones' => $data->observaciones,
            'estado' => EstadoEstudianteEnum::ACTIVO,
        ]);
    }

    public function registrarApoderado(Estudiante $estudiante, RegistrarApoderadoData $data): Apoderado
    {
        if (! $estudiante->es_menor_edad) {
            throw ValidationException::withMessages([
                'apoderado' => 'Solo los estudiantes menores de edad requieren datos de apoderado.',
            ]);
        }

        return $estudiante->apoderado()->updateOrCreate([], [
            'nombres' => $data->nombres,
            'dni' => $data->dni->valor(),
            'celular' => $data->celular->numero(),
            'correo' => $data->correo,
            'direccion' => $data->direccion,
            'parentesco' => $data->parentesco,
        ]);
    }

    /**
     * @param  array{nombre_colegio: string, ubicacion: ?string, anio_egreso: ?int}  $datos
     */
    public function registrarInstitucionProcedencia(Estudiante $estudiante, array $datos): InstitucionProcedencia
    {
        return $estudiante->institucionProcedencia()->updateOrCreate([], $datos);
    }

    public function matricular(Estudiante $estudiante, RegistrarMatriculaData $data): Matricula
    {
        $ciclo = Ciclo::query()->findOrFail($data->cicloId);
        $grado = Grado::query()->findOrFail($data->gradoId);

        $this->validarGradoCoherenteConEdad($estudiante, $grado);
        $this->validarPeriodoDeMatriculaAbierto($ciclo);

        if ($this->matriculas->existeParaEstudianteYCiclo($estudiante->id, $ciclo->id)) {
            throw ValidationException::withMessages([
                'ciclo' => 'Este estudiante ya tiene una matrícula registrada para este ciclo.',
            ]);
        }

        return DB::transaction(function () use ($estudiante, $ciclo, $grado, $data) {
            $matricula = $this->matriculas->create([
                'estudiante_id' => $estudiante->id,
                'ciclo_id' => $ciclo->id,
                'grado_id' => $grado->id,
                'fecha_matricula' => now(),
                'estado' => EstadoMatriculaEnum::APROBADA,
                'observaciones' => $data->observaciones,
                'registrado_por' => $data->registradoPor,
            ]);

            $estudiante->update(['grado_actual_id' => $grado->id]);

            event(new EstudianteMatriculado($matricula));

            return $matricula;
        });
    }

    private function validarGradoCoherenteConEdad(Estudiante $estudiante, Grado $grado): void
    {
        $publicoEsperado = $estudiante->es_menor_edad ? TipoPublicoEnum::MENOR : TipoPublicoEnum::MAYOR;

        if ($grado->tipo_publico !== $publicoEsperado) {
            throw ValidationException::withMessages([
                'grado' => "El grado «{$grado->nombre}» es para {$grado->tipo_publico->label()}, pero el estudiante es {$publicoEsperado->label()}.",
            ]);
        }
    }

    private function validarPeriodoDeMatriculaAbierto(Ciclo $ciclo): void
    {
        $hoy = Carbon::today();

        $periodoAbierto = PeriodoMatricula::query()
            ->where('ciclo_id', $ciclo->id)
            ->where('estado', 'abierto')
            ->where('fecha_inicio', '<=', $hoy)
            ->where('fecha_fin', '>=', $hoy)
            ->exists();

        if (! $periodoAbierto) {
            throw ValidationException::withMessages([
                'ciclo' => "No hay un periodo de matrícula abierto hoy para el ciclo «{$ciclo->nombre}».",
            ]);
        }
    }
}
