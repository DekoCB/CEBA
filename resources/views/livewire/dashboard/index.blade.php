<?php

use App\Models\User;
use App\Modules\Academico\Models\Horario;
use App\Modules\AulaVirtual\Models\Tarea;
use App\Modules\AulaVirtual\Services\CursoVirtualService;
use App\Modules\Evaluaciones\Models\Evaluacion;
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

    public bool $esEstudianteConFicha = false;

    public bool $estoyBloqueado = false;

    public ?Cuota $proximaCuota = null;

    public int $misCursosVirtuales = 0;

    public int $misTareasPendientes = 0;

    public bool $esCoordinador = false;

    public int $estudiantesActivos = 0;

    public int $docentesActivos = 0;

    public int $estudiantesBloqueados = 0;

    public int $evaluacionesSinPublicar = 0;

    public int $matriculasSinPlanDePago = 0;

    public bool $esTesoreria = false;

    public int $pagosPendientesAprobacion = 0;

    public float $ingresosDelMes = 0.0;

    public int $cuentasBancariasActivas = 0;

    public function mount(BloqueoAccesoService $bloqueos, CursoVirtualService $cursosVirtuales): void
    {
        $user = Auth::user();

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

            @if ($matriculasSinPlanDePago > 0)
                <a href="{{ route('pagos.index') }}" wire:navigate class="block rounded-lg border border-warn/30 bg-warn/10 px-4 py-3 text-sm text-warn hover:underline">
                    {{ $matriculasSinPlanDePago }} {{ $matriculasSinPlanDePago === 1 ? 'matrícula todavía no tiene' : 'matrículas todavía no tienen' }} un plan de pago asignado este ciclo →
                </a>
            @endif
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

        @if ($esEstudianteConFicha)
            @if ($estoyBloqueado)
                <div class="rounded-lg border border-danger/30 bg-danger/10 px-4 py-3 text-sm text-danger">
                    Tienes cuotas vencidas sin pagar y tu libreta de notas no está disponible.
                    <a href="{{ route('pagos.mi-cuenta') }}" wire:navigate class="underline">Regulariza tu deuda aquí →</a>
                </div>
            @endif

            <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
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
                <a href="{{ route('aula-virtual.index') }}" wire:navigate class="rounded-lg border border-border bg-surface p-4 transition hover:border-accent">
                    <p class="font-mono text-xs uppercase tracking-wide text-ink-faint">Tareas pendientes</p>
                    <p class="mt-1 font-display text-2xl {{ $misTareasPendientes > 0 ? 'text-warn' : 'text-ink' }}">{{ $misTareasPendientes }}</p>
                </a>
                <a href="{{ route('evaluaciones.index') }}" wire:navigate class="rounded-lg border border-border bg-surface p-4 transition hover:border-accent">
                    <p class="font-mono text-xs uppercase tracking-wide text-ink-faint">Mis notas</p>
                    <p class="mt-1 font-display text-sm text-accent">Ver evaluaciones →</p>
                </a>
            </div>
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

        @unless ($puedeVerUsuarios || $puedeVerAuditoria || $esDocente || $esEstudianteConFicha || $esCoordinador || $esTesoreria)
            <div class="rounded-lg border border-border bg-surface p-6">
                <h2 class="font-display text-lg text-ink">Bienvenido a CEBA</h2>
                <p class="mt-2 max-w-prose text-sm text-ink-dim">
                    Tu panel se irá completando a medida que se habiliten los módulos correspondientes a tu rol.
                </p>
            </div>
        @endunless
    </div>
</div>
