<?php

use App\Models\User;
use App\Modules\Academico\Models\Grado;
use App\Modules\Academico\Models\Horario;
use App\Modules\Asistencia\Models\Asistencia;
use App\Modules\AulaVirtual\Models\Tarea;
use App\Modules\AulaVirtual\Services\CursoVirtualService;
use App\Modules\AulaVirtual\Services\TareaService;
use App\Modules\Evaluaciones\Enums\EstadoEvaluacionEnum;
use App\Modules\Evaluaciones\Enums\NotaLetraEnum;
use App\Modules\Evaluaciones\Models\Calificacion;
use App\Modules\Evaluaciones\Models\Evaluacion;
use App\Modules\Evaluaciones\Services\EvaluacionService;
use App\Modules\Identidad\Models\AuditLog;
use App\Modules\Matricula\Models\Estudiante;
use App\Modules\Matricula\Models\Matricula;
use App\Modules\Pagos\Models\BloqueoAcceso;
use App\Modules\Pagos\Models\CuentaBancaria;
use App\Modules\Pagos\Models\Cuota;
use App\Modules\Pagos\Models\Pago;
use App\Modules\Pagos\Models\PlanPago;
use App\Modules\Pagos\Services\BloqueoAccesoService;
use App\Shared\Enums\EstadoUsuarioEnum;
use App\Shared\Enums\RolEnum;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

new #[Layout('layouts.app')] class extends Component
{
    public bool $puedeVerUsuarios = false;

    public bool $puedeVerAuditoria = false;

    public int $totalUsuarios = 0;

    public int $usuariosActivos = 0;

    public int $totalRoles = 0;

    public int $totalPermisos = 0;

    /** @var array<int, AuditLog> */
    public array $actividadReciente = [];

    public bool $esDocente = false;

    public int $misHorarios = 0;

    public int $tareasPorCalificar = 0;

    /** @var array<int, array{label: string, valor: float}> */
    public array $asistenciaPorCurso = [];

    /** @var array<int, array{label: string, valor: float}> */
    public array $distribucionNotas = [];

    public bool $esEstudianteConFicha = false;

    public bool $estoyBloqueado = false;

    public ?Cuota $proximaCuota = null;

    /** @var array<int, string> */
    public array $rendimientoMensualLabels = [];

    /** @var array<int, float> */
    public array $rendimientoMensualDatos = [];

    public int $misCursosVirtuales = 0;

    public int $misTareasPendientes = 0;

    /** @var Collection<int, Tarea> */
    public Collection $misTareasLista;

    /** @var SupportCollection<int, array<string, mixed>> */
    public SupportCollection $misCalificacionesPorCiclo;

    public ?float $promedioGeneralActual = null;

    public ?int $miEstudianteId = null;

    public bool $esCoordinador = false;

    public int $estudiantesActivos = 0;

    public int $docentesActivos = 0;

    public int $estudiantesBloqueados = 0;

    public int $evaluacionesSinPublicar = 0;

    public int $matriculasSinPlanDePago = 0;

    /** @var array<int, array{label: string, valor: float}> */
    public array $asistenciaPorGrado = [];

    public bool $esTesoreria = false;

    public bool $esAdministrativo = false;

    public int $pagosPendientesAprobacion = 0;

    public float $ingresosDelMes = 0.0;

    public int $cuentasBancariasActivas = 0;

    /** @var array<int, string> */
    public array $ingresosSemanasLabels = [];

    /** @var array<int, float> */
    public array $ingresosSemanasDatos = [];

    /**
     * @var array<int, array{pill: string, color: string, texto: string}>
     */
    public array $notificaciones = [];

    public function mount(BloqueoAccesoService $bloqueos, CursoVirtualService $cursosVirtuales, TareaService $tareas, EvaluacionService $evaluaciones): void
    {
        $user = Auth::user();

        $this->misTareasLista = new Collection;
        $this->misCalificacionesPorCiclo = collect();

        $this->puedeVerUsuarios = Gate::allows('usuarios.ver');
        $this->puedeVerAuditoria = Gate::allows('auditoria.ver');

        if ($this->puedeVerUsuarios) {
            $this->totalUsuarios = User::query()->count();
            $this->usuariosActivos = User::query()->where('estado', EstadoUsuarioEnum::ACTIVO)->count();
            $this->totalRoles = Role::query()->count();
            $this->totalPermisos = Permission::query()->count();
        }

        if ($this->puedeVerAuditoria) {
            $this->actividadReciente = AuditLog::query()
                ->with('user:id,name')
                ->latest('created_at')
                ->limit(8)
                ->get()
                ->all();
        }

        if ($user->hasRole(RolEnum::DOCENTE->value)) {
            $this->esDocente = true;
            $this->misHorarios = Horario::query()->where('docente_id', $user->id)->count();
            $this->tareasPorCalificar = Tarea::query()
                ->whereHas('cursoVirtual.horario', fn ($query) => $query->where('docente_id', $user->id))
                ->withCount(['entregas' => fn ($query) => $query->whereIn('estado', ['entregado', 'tarde'])])
                ->get()
                ->sum('entregas_count');
            $this->asistenciaPorCurso = $this->calcularAsistenciaPorCursoDelDocente($user->id);
            $this->distribucionNotas = $this->calcularDistribucionNotasDelDocente($user->id);
        }

        if (Gate::allows('aula_virtual.ver_propio') && $user->estudiante) {
            $estudiante = $user->estudiante;
            $this->esEstudianteConFicha = true;
            $this->estoyBloqueado = $bloqueos->estaBloqueado($estudiante);

            $this->proximaCuota = Cuota::query()
                ->where('estado', 'pendiente')
                ->whereHas('planPago.matricula', fn ($query) => $query->where('estudiante_id', $estudiante->id))
                ->orderBy('fecha_vencimiento')
                ->first();

            $misCursos = $cursosVirtuales->delEstudiante($estudiante);
            $this->misCursosVirtuales = $misCursos->count();

            $this->misTareasPendientes = Tarea::query()
                ->whereIn('curso_virtual_id', $misCursos->pluck('id'))
                ->whereDoesntHave('entregas', fn ($query) => $query->where('estudiante_id', $estudiante->id))
                ->count();

            $this->miEstudianteId = $estudiante->id;
            $this->misTareasLista = $tareas->delEstudiante($estudiante);
            $this->misCalificacionesPorCiclo = $evaluaciones->resumenDelEstudiantePorCiclo($estudiante);
            $this->promedioGeneralActual = $this->misCalificacionesPorCiclo->first()['promedioGeneral'] ?? null;

            [$this->rendimientoMensualLabels, $this->rendimientoMensualDatos] = $this->calcularRendimientoMensualDelEstudiante($estudiante);
        }

        if (Gate::allows('academico.gestionar')) {
            $this->esCoordinador = true;
            $this->estudiantesActivos = Estudiante::query()->where('estado', 'activo')->count();
            $this->docentesActivos = User::role(RolEnum::DOCENTE->value)->count();
            $this->estudiantesBloqueados = BloqueoAcceso::query()->where('activo', true)->count();
            $this->evaluacionesSinPublicar = Evaluacion::query()->where('estado', 'borrador')->count();
            $this->matriculasSinPlanDePago = Matricula::query()
                ->where('estado', 'aprobada')
                ->whereNotIn('id', PlanPago::query()->pluck('matricula_id'))
                ->count();
            $this->asistenciaPorGrado = $this->calcularAsistenciaPorGrado();
        }

        if (Gate::allows('pagos.aprobar')) {
            $this->esTesoreria = true;
            $this->pagosPendientesAprobacion = Pago::query()->where('estado', 'pendiente')->count();
            $this->ingresosDelMes = (float) Pago::query()
                ->where('estado', 'aprobado')
                ->whereMonth('fecha_aprobacion', now()->month)
                ->whereYear('fecha_aprobacion', now()->year)
                ->sum('monto');
            $this->cuentasBancariasActivas = CuentaBancaria::query()->where('activa', true)->count();
        }

        $this->esAdministrativo = Gate::allows('pagos.registrar');

        if ($this->esCoordinador || $this->esTesoreria || $this->esAdministrativo) {
            [$this->ingresosSemanasLabels, $this->ingresosSemanasDatos] = $this->calcularIngresosPorSemana();
        }

        $this->notificaciones = $this->construirNotificaciones();
    }

    /**
     * @return array{0: array<int, string>, 1: array<int, float>}
     */
    private function calcularIngresosPorSemana(): array
    {
        $labels = [];
        $datos = [];

        for ($semanasAtras = 7; $semanasAtras >= 0; $semanasAtras--) {
            $inicio = now()->subWeeks($semanasAtras)->startOfWeek();
            $fin = now()->subWeeks($semanasAtras)->endOfWeek();

            $labels[] = $inicio->format('d/m');
            $datos[] = (float) Pago::query()
                ->where('estado', 'aprobado')
                ->whereBetween('fecha_aprobacion', [$inicio, $fin])
                ->sum('monto');
        }

        return [$labels, $datos];
    }

    /**
     * @return array{0: array<int, string>, 1: array<int, float>}
     */
    private function calcularRendimientoMensualDelEstudiante(Estudiante $estudiante): array
    {
        $labels = [];
        $datos = [];

        for ($mesesAtras = 5; $mesesAtras >= 0; $mesesAtras--) {
            $inicio = now()->subMonths($mesesAtras)->startOfMonth();
            $fin = now()->subMonths($mesesAtras)->endOfMonth();

            $labels[] = ucfirst($inicio->translatedFormat('M'));
            $datos[] = (float) round(
                Calificacion::query()
                    ->where('estudiante_id', $estudiante->id)
                    ->whereHas('evaluacion', function ($query) use ($inicio, $fin) {
                        $query->where('estado', EstadoEvaluacionEnum::PUBLICADA)
                            ->whereBetween('fecha', [$inicio, $fin]);
                    })
                    ->avg('nota_numerica') ?? 0,
                1
            );
        }

        return [$labels, $datos];
    }

    /**
     * @return array<int, array{label: string, valor: float}>
     */
    private function calcularAsistenciaPorGrado(): array
    {
        return Grado::query()
            ->orderBy('orden')
            ->get()
            ->map(function (Grado $grado) {
                $registros = Asistencia::query()->whereHas('horario', fn ($query) => $query->where('grado_id', $grado->id));
                $total = $registros->count();

                if ($total === 0) {
                    return null;
                }

                $positivos = (clone $registros)->whereIn('estado', ['presente', 'justificado'])->count();

                return ['label' => $grado->nombre, 'valor' => round($positivos / $total * 100, 1)];
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{label: string, valor: float}>
     */
    private function calcularAsistenciaPorCursoDelDocente(int $docenteId): array
    {
        return Horario::query()
            ->where('docente_id', $docenteId)
            ->with('curso')
            ->get()
            ->groupBy('curso_id')
            ->map(function ($horariosDelCurso) {
                $registros = Asistencia::query()->whereIn('horario_id', $horariosDelCurso->pluck('id'));
                $total = $registros->count();

                if ($total === 0) {
                    return null;
                }

                $positivos = (clone $registros)->whereIn('estado', ['presente', 'justificado'])->count();

                return ['label' => $horariosDelCurso->first()->curso->nombre, 'valor' => round($positivos / $total * 100, 1)];
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{label: string, valor: float}>
     */
    private function calcularDistribucionNotasDelDocente(int $docenteId): array
    {
        $conteos = ['AD' => 0, 'A' => 0, 'B' => 0, 'C' => 0];

        Calificacion::query()
            ->whereHas('evaluacion.horario', fn ($query) => $query->where('docente_id', $docenteId))
            ->pluck('nota_numerica')
            ->each(function ($notaNumerica) use (&$conteos) {
                $conteos[NotaLetraEnum::desde((float) $notaNumerica)->value]++;
            });

        return collect($conteos)
            ->map(fn ($valor, $label) => ['label' => $label, 'valor' => $valor])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{pill: string, color: string, texto: string}>
     */
    private function construirNotificaciones(): array
    {
        $notificaciones = [];

        if ($this->esCoordinador) {
            if ($this->estudiantesBloqueados > 0) {
                $notificaciones[] = ['pill' => 'deuda', 'color' => 'danger', 'texto' => "{$this->estudiantesBloqueados} estudiantes bloqueados por deuda"];
            }

            if ($this->matriculasSinPlanDePago > 0) {
                $notificaciones[] = ['pill' => 'matrícula', 'color' => 'info', 'texto' => "{$this->matriculasSinPlanDePago} matrículas sin plan de pago"];
            }

            if ($this->evaluacionesSinPublicar > 0) {
                $notificaciones[] = ['pill' => 'evaluación', 'color' => 'gold', 'texto' => "{$this->evaluacionesSinPublicar} evaluaciones sin publicar"];
            }
        }

        if ($this->esTesoreria && $this->pagosPendientesAprobacion > 0) {
            $notificaciones[] = ['pill' => 'pago', 'color' => 'warn', 'texto' => "{$this->pagosPendientesAprobacion} pagos por aprobar"];
        }

        if ($this->esDocente && $this->tareasPorCalificar > 0) {
            $notificaciones[] = ['pill' => 'tarea', 'color' => 'warn', 'texto' => "{$this->tareasPorCalificar} entregas por calificar"];
        }

        return $notificaciones;
    }
}; ?>

<div>
    <x-slot name="header">
        <h1 class="font-display text-2xl text-ink">Hola, {{ auth()->user()->name }}</h1>
        <p class="mt-1 text-sm text-ink-dim">Aquí tienes un resumen de lo que está activo hoy en CEBA.</p>
    </x-slot>

    <div class="space-y-6">
        @if ($esTesoreria)
            <div class="grid grid-cols-2 gap-4 lg:grid-cols-3">
                <a href="{{ route('pagos.index') }}" wire:navigate class="rounded-lg border border-border bg-surface p-4 transition hover:border-accent">
                    <p class="font-mono text-xs uppercase tracking-wide text-ink-faint">Pagos por aprobar</p>
                    <p class="mt-1 font-display text-2xl {{ $pagosPendientesAprobacion > 0 ? 'text-warn' : 'text-ink' }}">{{ $pagosPendientesAprobacion }}</p>
                </a>
                <div class="rounded-lg border border-border bg-surface p-4">
                    <p class="font-mono text-xs uppercase tracking-wide text-ink-faint">Ingresos aprobados este mes</p>
                    <p class="mt-1 font-display text-2xl text-ok">S/ {{ number_format($ingresosDelMes, 2) }}</p>
                </div>
                <a href="{{ route('pagos.cuentas-bancarias') }}" wire:navigate class="rounded-lg border border-border bg-surface p-4 transition hover:border-accent">
                    <p class="font-mono text-xs uppercase tracking-wide text-ink-faint">Cuentas bancarias activas</p>
                    <p class="mt-1 font-display text-2xl text-ink">{{ $cuentasBancariasActivas }}</p>
                </a>
            </div>
        @endif

        @if ($esCoordinador)
            <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
                <div class="rounded-lg border border-border bg-surface p-4">
                    <p class="font-mono text-xs uppercase tracking-wide text-ink-faint">Estudiantes activos</p>
                    <p class="mt-1 font-display text-2xl text-ink">{{ $estudiantesActivos }}</p>
                </div>
                <div class="rounded-lg border border-border bg-surface p-4">
                    <p class="font-mono text-xs uppercase tracking-wide text-ink-faint">Docentes</p>
                    <p class="mt-1 font-display text-2xl text-ink">{{ $docentesActivos }}</p>
                </div>
                <a href="{{ route('evaluaciones.index') }}" wire:navigate class="rounded-lg border border-border bg-surface p-4 transition hover:border-accent">
                    <p class="font-mono text-xs uppercase tracking-wide text-ink-faint">Evaluaciones sin publicar</p>
                    <p class="mt-1 font-display text-2xl {{ $evaluacionesSinPublicar > 0 ? 'text-warn' : 'text-ink' }}">{{ $evaluacionesSinPublicar }}</p>
                </a>
                <a href="{{ route('pagos.index') }}" wire:navigate class="rounded-lg border border-border bg-surface p-4 transition hover:border-accent">
                    <p class="font-mono text-xs uppercase tracking-wide text-ink-faint">Estudiantes bloqueados por deuda</p>
                    <p class="mt-1 font-display text-2xl {{ $estudiantesBloqueados > 0 ? 'text-danger' : 'text-ink' }}">{{ $estudiantesBloqueados }}</p>
                </a>
            </div>

        @endif

        @if ($esCoordinador || $esTesoreria || $esAdministrativo)
            <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
                <div class="rounded-lg border border-border bg-surface p-4 lg:col-span-2">
                    <h2 class="mb-3 text-sm font-semibold text-ink">Ingresos aprobados — últimas 8 semanas</h2>
                    <x-chart-canvas
                        type="line"
                        :labels="$ingresosSemanasLabels"
                        :data="$ingresosSemanasDatos"
                        label="Ingresos (S/)"
                    />
                </div>

                <div class="rounded-lg border border-border bg-surface">
                    <div class="border-b border-border px-4 py-3">
                        <h2 class="text-sm font-semibold text-ink">Notificaciones</h2>
                    </div>
                    <div class="divide-y divide-border">
                        @forelse ($notificaciones as $notificacion)
                            @php
                                $estiloPill = match ($notificacion['color']) {
                                    'danger' => 'bg-danger/10 text-danger',
                                    'warn' => 'bg-warn/10 text-warn',
                                    'info' => 'bg-info/10 text-info',
                                    'gold' => 'bg-gold/10 text-gold',
                                    default => 'bg-ink-faint/10 text-ink-faint',
                                };
                            @endphp
                            <div class="flex items-center gap-3 px-4 py-3 text-sm">
                                <span class="shrink-0 rounded-full px-2 py-0.5 font-mono text-xs uppercase tracking-wide {{ $estiloPill }}">
                                    {{ $notificacion['pill'] }}
                                </span>
                                <span class="text-ink-dim">{{ $notificacion['texto'] }}</span>
                            </div>
                        @empty
                            <p class="px-4 py-6 text-center text-sm text-ink-faint">Todo al día. No hay notificaciones pendientes.</p>
                        @endforelse
                    </div>
                </div>
            </div>
        @endif

        @if ($esCoordinador && count($asistenciaPorGrado) > 0)
            <div class="rounded-lg border border-border bg-surface p-4">
                <h2 class="mb-3 text-sm font-semibold text-ink">Asistencia por grado (% presente/justificado)</h2>
                <x-chart-canvas
                    type="bar"
                    :labels="collect($asistenciaPorGrado)->pluck('label')->all()"
                    :data="collect($asistenciaPorGrado)->pluck('valor')->all()"
                    label="% Asistencia"
                    color="#5B8DEF"
                />
            </div>
        @endif

        @if ($esDocente)
            <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
                <a href="{{ route('asistencia.index') }}" wire:navigate class="rounded-lg border border-border bg-surface p-4 transition hover:border-accent">
                    <p class="font-mono text-xs uppercase tracking-wide text-ink-faint">Mis horarios</p>
                    <p class="mt-1 font-display text-2xl text-ink">{{ $misHorarios }}</p>
                </a>
                <a href="{{ route('aula-virtual.index') }}" wire:navigate class="rounded-lg border border-border bg-surface p-4 transition hover:border-accent">
                    <p class="font-mono text-xs uppercase tracking-wide text-ink-faint">Tareas por calificar</p>
                    <p class="mt-1 font-display text-2xl {{ $tareasPorCalificar > 0 ? 'text-warn' : 'text-ink' }}">{{ $tareasPorCalificar }}</p>
                </a>
                <a href="{{ route('evaluaciones.index') }}" wire:navigate class="rounded-lg border border-border bg-surface p-4 transition hover:border-accent">
                    <p class="font-mono text-xs uppercase tracking-wide text-ink-faint">Evaluaciones</p>
                    <p class="mt-1 font-display text-sm text-accent">Registrar notas →</p>
                </a>
                <a href="{{ route('asistencia.index') }}" wire:navigate class="rounded-lg border border-border bg-surface p-4 transition hover:border-accent">
                    <p class="font-mono text-xs uppercase tracking-wide text-ink-faint">Asistencia</p>
                    <p class="mt-1 font-display text-sm text-accent">Tomar asistencia →</p>
                </a>
            </div>
        @endif

        @if ($esDocente && (count($asistenciaPorCurso) > 0 || array_sum(collect($distribucionNotas)->pluck('valor')->all()) > 0))
            <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                @if (array_sum(collect($distribucionNotas)->pluck('valor')->all()) > 0)
                    <div class="rounded-lg border border-border bg-surface p-4">
                        <h2 class="mb-3 text-sm font-semibold text-ink">Distribución de notas de mis evaluaciones</h2>
                        <x-chart-canvas
                            type="line"
                            :labels="collect($distribucionNotas)->pluck('label')->all()"
                            :data="collect($distribucionNotas)->pluck('valor')->all()"
                            label="Estudiantes"
                        />
                    </div>
                @endif

                @if (count($asistenciaPorCurso) > 0)
                    <div class="rounded-lg border border-border bg-surface p-4">
                        <h2 class="mb-3 text-sm font-semibold text-ink">Asistencia de mis cursos (% presente/justificado)</h2>
                        <x-chart-canvas
                            type="bar"
                            :labels="collect($asistenciaPorCurso)->pluck('label')->all()"
                            :data="collect($asistenciaPorCurso)->pluck('valor')->all()"
                            label="% Asistencia"
                            color="#5B8DEF"
                        />
                    </div>
                @endif
            </div>
        @endif

        @if ($esEstudianteConFicha)
            @if ($estoyBloqueado)
                <div class="rounded-lg border border-danger/30 bg-danger/10 px-4 py-3 text-sm text-danger">
                    Tienes cuotas vencidas sin pagar y tu libreta de notas no está disponible.
                    <a href="{{ route('pagos.mi-cuenta') }}" wire:navigate class="underline">Regulariza tu deuda aquí →</a>
                </div>
            @endif

            <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-5">
                <a href="{{ route('asistencia.marcar') }}" wire:navigate class="rounded-lg border border-accent/30 bg-accent-soft p-4 transition hover:border-accent">
                    <p class="font-mono text-xs uppercase tracking-wide text-accent">Marcar asistencia</p>
                    <p class="mt-1 font-display text-sm text-accent">Con tu DNI →</p>
                </a>
                <a href="{{ route('pagos.mi-cuenta') }}" wire:navigate class="rounded-lg border border-border bg-surface p-4 transition hover:border-accent">
                    <p class="font-mono text-xs uppercase tracking-wide text-ink-faint">Próxima cuota</p>
                    @if ($proximaCuota)
                        <p class="mt-1 font-display text-2xl text-ink">S/ {{ number_format((float) $proximaCuota->monto, 2) }}</p>
                        <p class="text-xs text-ink-faint">vence {{ $proximaCuota->fecha_vencimiento->format('d/m/Y') }}</p>
                    @else
                        <p class="mt-1 font-display text-lg text-ok">Al día</p>
                    @endif
                </a>
                <a href="{{ route('aula-virtual.index') }}" wire:navigate class="rounded-lg border border-border bg-surface p-4 transition hover:border-accent">
                    <p class="font-mono text-xs uppercase tracking-wide text-ink-faint">Mis cursos virtuales</p>
                    <p class="mt-1 font-display text-2xl text-ink">{{ $misCursosVirtuales }}</p>
                </a>
                <button type="button" x-data x-on:click="$dispatch('open-modal', 'mis-tareas')" class="rounded-lg border border-border bg-surface p-4 text-left transition hover:border-accent">
                    <p class="font-mono text-xs uppercase tracking-wide text-ink-faint">Tareas pendientes</p>
                    <p class="mt-1 font-display text-2xl {{ $misTareasPendientes > 0 ? 'text-warn' : 'text-ink' }}">{{ $misTareasPendientes }}</p>
                </button>
                <button type="button" x-data x-on:click="$dispatch('open-modal', 'mis-calificaciones')" class="rounded-lg border border-border bg-surface p-4 text-left transition hover:border-accent">
                    <p class="font-mono text-xs uppercase tracking-wide text-ink-faint">Mis notas</p>
                    @if ($promedioGeneralActual !== null)
                        <p class="mt-1 font-display text-2xl text-ink">{{ number_format($promedioGeneralActual, 2) }}</p>
                    @else
                        <p class="mt-1 font-display text-sm text-accent">Ver notas →</p>
                    @endif
                </button>
            </div>

            @if (array_sum($rendimientoMensualDatos) > 0)
                <div class="rounded-lg border border-border bg-surface p-4">
                    <h2 class="mb-3 text-sm font-semibold text-ink">Mi rendimiento — promedio de notas por mes</h2>
                    <x-chart-canvas
                        type="line"
                        :labels="$rendimientoMensualLabels"
                        :data="$rendimientoMensualDatos"
                        label="Promedio"
                    />
                </div>
            @endif

            <x-modal name="mis-tareas" max-width="2xl">
                <div class="flex items-center justify-between border-b border-border px-6 py-4">
                    <h2 class="font-display text-lg text-ink">Mis tareas</h2>
                    <button type="button" x-on:click="$dispatch('close')" class="rounded-md p-1.5 text-ink-faint transition hover:bg-surface-2 hover:text-ink" aria-label="Cerrar">
                        <x-heroicon-o-x-mark class="h-5 w-5" />
                    </button>
                </div>
                <div class="max-h-[75vh] overflow-y-auto p-6">
                    <x-aula-virtual.lista-tareas :tareas="$misTareasLista" />
                </div>
            </x-modal>

            <x-modal name="mis-calificaciones" max-width="2xl">
                <div class="flex items-center justify-between border-b border-border px-6 py-4">
                    <h2 class="font-display text-lg text-ink">Mis calificaciones</h2>
                    <button type="button" x-on:click="$dispatch('close')" class="rounded-md p-1.5 text-ink-faint transition hover:bg-surface-2 hover:text-ink" aria-label="Cerrar">
                        <x-heroicon-o-x-mark class="h-5 w-5" />
                    </button>
                </div>
                <div class="max-h-[75vh] overflow-y-auto p-6">
                    <x-evaluaciones.lista-calificaciones :por-ciclo="$misCalificacionesPorCiclo" :mi-estudiante-id="$miEstudianteId" />
                </div>
            </x-modal>
        @endif

        @if ($puedeVerUsuarios)
            <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
                <div class="rounded-lg border border-border bg-surface p-4">
                    <p class="font-mono text-xs uppercase tracking-wide text-ink-faint">Usuarios totales</p>
                    <p class="mt-1 font-display text-2xl text-ink">{{ $totalUsuarios }}</p>
                </div>
                <div class="rounded-lg border border-border bg-surface p-4">
                    <p class="font-mono text-xs uppercase tracking-wide text-ink-faint">Usuarios activos</p>
                    <p class="mt-1 font-display text-2xl text-ok">{{ $usuariosActivos }}</p>
                </div>
                <div class="rounded-lg border border-border bg-surface p-4">
                    <p class="font-mono text-xs uppercase tracking-wide text-ink-faint">Roles configurados</p>
                    <p class="mt-1 font-display text-2xl text-ink">{{ $totalRoles }}</p>
                </div>
                <div class="rounded-lg border border-border bg-surface p-4">
                    <p class="font-mono text-xs uppercase tracking-wide text-ink-faint">Permisos totales</p>
                    <p class="mt-1 font-display text-2xl text-ink">{{ $totalPermisos }}</p>
                </div>
            </div>
        @endif

        @if ($puedeVerAuditoria)
            <div class="rounded-lg border border-border bg-surface">
                <div class="border-b border-border px-4 py-3">
                    <h2 class="text-sm font-semibold text-ink">Actividad reciente</h2>
                </div>
                <div class="divide-y divide-border">
                    @forelse ($actividadReciente as $entrada)
                        <div class="flex items-center justify-between px-4 py-3 text-sm">
                            <div>
                                <span class="font-medium text-ink">{{ $entrada->user?->name ?? 'Sistema' }}</span>
                                <span class="text-ink-dim">
                                    {{ match ($entrada->event) {
                                        'created' => 'creó',
                                        'updated' => 'actualizó',
                                        'deleted' => 'eliminó',
                                        default => $entrada->event,
                                    } }}
                                </span>
                                <span class="font-mono text-ink-faint">{{ class_basename($entrada->auditable_type) }} #{{ $entrada->auditable_id }}</span>
                            </div>
                            <span class="text-xs text-ink-faint">{{ $entrada->created_at?->diffForHumans() }}</span>
                        </div>
                    @empty
                        <p class="px-4 py-6 text-center text-sm text-ink-faint">Todavía no hay actividad registrada.</p>
                    @endforelse
                </div>
                @if (count($actividadReciente) > 0)
                    <div class="border-t border-border px-4 py-3">
                        <a href="{{ route('auditoria.index') }}" wire:navigate class="text-sm font-medium text-accent hover:underline">
                            Ver historial completo →
                        </a>
                    </div>
                @endif
            </div>
        @endif

        @unless ($puedeVerUsuarios || $puedeVerAuditoria || $esDocente || $esEstudianteConFicha || $esCoordinador || $esTesoreria || $esAdministrativo)
            <div class="rounded-lg border border-border bg-surface p-6">
                <h2 class="font-display text-lg text-ink">Bienvenido a CEBA</h2>
                <p class="mt-2 max-w-prose text-sm text-ink-dim">
                    Tu panel se irá completando a medida que se habiliten los módulos correspondientes a tu rol.
                </p>
            </div>
        @endunless
    </div>
</div>
