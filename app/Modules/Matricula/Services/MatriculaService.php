<?php

declare(strict_types=1);

namespace App\Modules\Matricula\Services;

use App\Models\User;
use App\Modules\Academico\Models\Ciclo;
use App\Modules\Academico\Models\Grado;
use App\Modules\Academico\Models\Horario;
use App\Modules\Academico\Models\PeriodoMatricula;
use App\Modules\Identidad\DTOs\CrearUsuarioData;
use App\Modules\Identidad\Services\UserManagementService;
use App\Modules\Matricula\DTOs\RegistrarApoderadoData;
use App\Modules\Matricula\DTOs\RegistrarEstudianteData;
use App\Modules\Matricula\DTOs\RegistrarMatriculaData;
use App\Modules\Matricula\Enums\EstadoCivilEnum;
use App\Modules\Matricula\Enums\EstadoEstudianteEnum;
use App\Modules\Matricula\Enums\EstadoMatriculaEnum;
use App\Modules\Matricula\Events\EstudianteMatriculado;
use App\Modules\Matricula\Models\Apoderado;
use App\Modules\Matricula\Models\Estudiante;
use App\Modules\Matricula\Models\InstitucionProcedencia;
use App\Modules\Matricula\Models\Matricula;
use App\Modules\Matricula\Repositories\Contracts\EstudianteRepositoryInterface;
use App\Modules\Matricula\Repositories\Contracts\MatriculaRepositoryInterface;
use App\Shared\Enums\RolEnum;
use App\Shared\ValueObjects\Dni;
use App\Shared\ValueObjects\Telefono;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use PhpOffice\PhpSpreadsheet\Shared\Date as FechaExcel;
use Throwable;

class MatriculaService
{
    public function __construct(
        private readonly EstudianteRepositoryInterface $estudiantes,
        private readonly MatriculaRepositoryInterface $matriculas,
        private readonly UserManagementService $usuarios,
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

    /**
     * Además de la ficha del estudiante, esto le crea (o reutiliza, si ya
     * existe una con el mismo DNI) su cuenta de acceso al sistema: correo
     * {dni}@ceba.test y contraseña inicial igual a su DNI -- el estudiante
     * puede cambiarla después desde su perfil. No hay ningún paso de
     * formulario para esto: se calcula siempre a partir del DNI.
     */
    public function registrarEstudiante(RegistrarEstudianteData $data): Estudiante
    {
        return DB::transaction(function () use ($data) {
            $email = $data->dni->correoInstitucional();

            $usuario = User::query()->where('dni', $data->dni->valor())->first();

            if ($usuario) {
                if (! $usuario->hasRole(RolEnum::ESTUDIANTE->value)) {
                    $usuario->assignRole(RolEnum::ESTUDIANTE->value);
                }
            } else {
                $usuario = $this->usuarios->crear(new CrearUsuarioData(
                    name: "{$data->nombres} {$data->apellidos}",
                    email: $email,
                    dni: $data->dni,
                    phone: $data->celular,
                    password: $data->dni->valor(),
                    rol: RolEnum::ESTUDIANTE,
                ));
            }

            return $this->estudiantes->create([
                'user_id' => $usuario->id,
                'nombres' => $data->nombres,
                'apellidos' => $data->apellidos,
                'dni' => $data->dni->valor(),
                'fecha_nacimiento' => $data->fechaNacimiento,
                'es_menor_edad' => self::esMenorDeEdad($data->fechaNacimiento),
                'estado_civil' => $data->estadoCivil,
                'direccion' => $data->direccion,
                'celular' => $data->celular?->numero(),
                'email' => $email,
                'observaciones' => $data->observaciones,
                'estado' => EstadoEstudianteEnum::ACTIVO,
            ]);
        });
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

        $this->validarPeriodoDeMatriculaAbierto($ciclo);

        if ($this->matriculas->existeParaEstudianteYCiclo($estudiante->id, $ciclo->id)) {
            throw ValidationException::withMessages([
                'ciclo' => 'Este estudiante ya tiene una matrícula registrada para este ciclo.',
            ]);
        }

        $fechaMatricula = now();
        $fechaFinEstudio = $fechaMatricula->clone()->addMonths($estudiante->es_menor_edad ? 8 : 6);

        return DB::transaction(function () use ($estudiante, $ciclo, $grado, $data, $fechaMatricula, $fechaFinEstudio) {
            $matricula = $this->matriculas->create([
                'estudiante_id' => $estudiante->id,
                'ciclo_id' => $ciclo->id,
                'grado_id' => $grado->id,
                'fecha_matricula' => $fechaMatricula,
                'fecha_fin_estudio' => $fechaFinEstudio,
                'estado' => EstadoMatriculaEnum::APROBADA,
                'observaciones' => $data->observaciones,
                'registrado_por' => $data->registradoPor,
            ]);

            $estudiante->update(['grado_actual_id' => $grado->id]);

            event(new EstudianteMatriculado($matricula));

            return $matricula;
        });
    }

    /**
     * Reasigna la fecha en que culmina el periodo de estudio de esta
     * matrícula, por ejemplo desde la ficha del estudiante cuando se
     * necesita ajustarla manualmente (p. ej. un grado doble). Es
     * independiente del ciclo/horario: no afecta a qué grupo ni horario
     * pertenece el estudiante.
     */
    public function reasignarFechaFinEstudio(Matricula $matricula, string $fecha): Matricula
    {
        $matricula->update(['fecha_fin_estudio' => $fecha]);

        return $matricula;
    }

    /**
     * Reasigna a qué horario específico (curso + docente + día/hora)
     * apunta la matrícula, por ejemplo desde la ficha del estudiante. Es
     * puramente informativo/de referencia -- solo exige que el horario
     * pertenezca al mismo grado y ciclo de la matrícula.
     */
    public function reasignarHorario(Matricula $matricula, ?int $horarioId): Matricula
    {
        if ($horarioId === null) {
            $matricula->update(['horario_id' => null]);

            return $matricula;
        }

        $horario = Horario::query()
            ->where('grado_id', $matricula->grado_id)
            ->where('ciclo_id', $matricula->ciclo_id)
            ->find($horarioId);

        if ($horario === null) {
            throw ValidationException::withMessages([
                'horario' => 'El horario seleccionado no pertenece al grado y ciclo de esta matrícula.',
            ]);
        }

        $matricula->update(['horario_id' => $horario->id]);

        return $matricula;
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

    /**
     * Crea estudiantes (y su apoderado si son menores de edad) a partir de
     * las filas de un Excel. Cada fila se procesa en su propia transacción:
     * una fila inválida no afecta a las demás, y su error se reporta con el
     * número de fila tal como aparece en el archivo (encabezado = fila 1).
     *
     * @param  Collection<int, Collection<string, mixed>>  $filas
     * @return array{exitosos: int, errores: list<array{fila: int, mensaje: string}>}
     */
    public function registrarEstudiantesDesdeFilas(Collection $filas): array
    {
        $exitosos = 0;
        $errores = [];

        foreach ($filas as $indice => $fila) {
            try {
                DB::transaction(function () use ($fila): void {
                    $nombres = $this->celdaObligatoria($fila, 'nombres');
                    $apellidos = $this->celdaObligatoria($fila, 'apellidos');
                    $dniTexto = $this->celdaObligatoria($fila, 'dni');
                    $fechaNacimientoValor = $fila->get('fecha_nacimiento');

                    if ($fechaNacimientoValor === null || trim((string) $fechaNacimientoValor) === '') {
                        throw new InvalidArgumentException('La fecha de nacimiento es obligatoria.');
                    }

                    if (! $this->dniDisponible($dniTexto)) {
                        throw new InvalidArgumentException("Ya existe un estudiante registrado con el DNI {$dniTexto}.");
                    }

                    $estadoCivilTexto = $this->celdaOpcional($fila, 'estado_civil');
                    $estadoCivil = null;
                    if ($estadoCivilTexto !== null) {
                        $estadoCivil = EstadoCivilEnum::tryFrom(Str::lower($estadoCivilTexto));
                        if ($estadoCivil === null) {
                            throw new InvalidArgumentException("Estado civil «{$estadoCivilTexto}» no reconocido (usa: soltero, casado, conviviente, divorciado, viudo).");
                        }
                    }

                    $celularTexto = $this->celdaOpcional($fila, 'celular');

                    $estudiante = $this->registrarEstudiante(new RegistrarEstudianteData(
                        nombres: $nombres,
                        apellidos: $apellidos,
                        dni: new Dni($dniTexto),
                        fechaNacimiento: $this->parsearFecha($fechaNacimientoValor),
                        estadoCivil: $estadoCivil,
                        direccion: $this->celdaOpcional($fila, 'direccion'),
                        celular: $celularTexto !== null ? new Telefono($celularTexto) : null,
                        observaciones: $this->celdaOpcional($fila, 'observaciones'),
                    ));

                    if ($estudiante->es_menor_edad) {
                        $this->registrarApoderado($estudiante, new RegistrarApoderadoData(
                            nombres: $this->celdaObligatoria($fila, 'apoderado_nombres', 'El estudiante es menor de edad: el nombre del apoderado es obligatorio.'),
                            dni: new Dni($this->celdaObligatoria($fila, 'apoderado_dni', 'El estudiante es menor de edad: el DNI del apoderado es obligatorio.')),
                            celular: new Telefono($this->celdaObligatoria($fila, 'apoderado_celular', 'El estudiante es menor de edad: el celular del apoderado es obligatorio.')),
                            correo: $this->celdaOpcional($fila, 'apoderado_correo'),
                            direccion: $this->celdaOpcional($fila, 'apoderado_direccion'),
                            parentesco: $this->celdaObligatoria($fila, 'apoderado_parentesco', 'El estudiante es menor de edad: el parentesco del apoderado es obligatorio.'),
                        ));
                    }
                });

                $exitosos++;
            } catch (Throwable $e) {
                $errores[] = ['fila' => $indice + 2, 'mensaje' => $this->mensajeDeError($e)];
            }
        }

        return ['exitosos' => $exitosos, 'errores' => $errores];
    }

    /**
     * Matricula estudiantes ya existentes (identificados por DNI) en un
     * mismo ciclo, cada uno en el grado indicado en su fila. Reutiliza
     * matricular(), así que cada fila respeta las mismas validaciones que
     * una matrícula individual (grado coherente con la edad, periodo de
     * matrícula abierto, sin duplicados).
     *
     * @param  Collection<int, Collection<string, mixed>>  $filas
     * @return array{exitosos: int, errores: list<array{fila: int, mensaje: string}>}
     */
    public function matricularDesdeFilas(int $cicloId, Collection $filas, ?int $registradoPor): array
    {
        $exitosos = 0;
        $errores = [];

        foreach ($filas as $indice => $fila) {
            try {
                $dniTexto = (new Dni($this->celdaObligatoria($fila, 'dni')))->valor();
                $nombreGrado = $this->celdaObligatoria($fila, 'grado');

                $estudiante = Estudiante::query()->where('dni', $dniTexto)->first();
                if (! $estudiante) {
                    throw new InvalidArgumentException("No existe ningún estudiante registrado con el DNI {$dniTexto}.");
                }

                $grado = Grado::query()->where('nombre', $nombreGrado)->first();
                if (! $grado) {
                    throw new InvalidArgumentException("No existe el grado «{$nombreGrado}».");
                }

                $this->matricular($estudiante, new RegistrarMatriculaData(
                    cicloId: $cicloId,
                    gradoId: $grado->id,
                    observaciones: $this->celdaOpcional($fila, 'observaciones'),
                    registradoPor: $registradoPor,
                ));

                $exitosos++;
            } catch (Throwable $e) {
                $errores[] = ['fila' => $indice + 2, 'mensaje' => $this->mensajeDeError($e)];
            }
        }

        return ['exitosos' => $exitosos, 'errores' => $errores];
    }

    /**
     * @param  Collection<string, mixed>  $fila
     */
    private function celdaOpcional(Collection $fila, string $clave): ?string
    {
        $valor = $fila->get($clave);

        if ($valor === null) {
            return null;
        }

        $valor = trim((string) $valor);

        return $valor === '' ? null : $valor;
    }

    /**
     * @param  Collection<string, mixed>  $fila
     */
    private function celdaObligatoria(Collection $fila, string $clave, ?string $mensaje = null): string
    {
        $valor = $this->celdaOpcional($fila, $clave);

        if ($valor === null) {
            throw new InvalidArgumentException($mensaje ?? "La columna «{$clave}» es obligatoria.");
        }

        return $valor;
    }

    private function parsearFecha(mixed $valor): string
    {
        if ($valor instanceof \DateTimeInterface) {
            return Carbon::instance($valor)->format('Y-m-d');
        }

        if (is_numeric($valor)) {
            return Carbon::instance(FechaExcel::excelToDateTimeObject((float) $valor))->format('Y-m-d');
        }

        $texto = trim((string) $valor);

        foreach (['d/m/Y', 'Y-m-d', 'd-m-Y'] as $formato) {
            try {
                return Carbon::createFromFormat($formato, $texto)->format('Y-m-d');
            } catch (Throwable) {
                continue;
            }
        }

        throw new InvalidArgumentException("La fecha «{$texto}» no tiene un formato reconocible (usa dd/mm/aaaa).");
    }

    private function mensajeDeError(Throwable $e): string
    {
        if ($e instanceof ValidationException) {
            return $e->validator->errors()->first();
        }

        return $e->getMessage();
    }
}
