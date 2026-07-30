<?php

use App\Models\User;
use App\Modules\Identidad\Models\AuditLog;
use App\Shared\Enums\EstadoUsuarioEnum;
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

    public function mount(): void
    {
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
    }
}; ?>

<div>
    <x-slot name="header">
        <h1 class="font-display text-2xl text-ink">Hola, {{ auth()->user()->name }}</h1>
        <p class="mt-1 text-sm text-ink-dim">Aquí tienes un resumen de lo que está activo hoy en CEBA.</p>
    </x-slot>

    <div class="space-y-6">
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

        @unless ($puedeVerUsuarios || $puedeVerAuditoria)
            <div class="rounded-lg border border-border bg-surface p-6">
                <h2 class="font-display text-lg text-ink">Bienvenido a CEBA</h2>
                <p class="mt-2 max-w-prose text-sm text-ink-dim">
                    Tu panel se irá completando a medida que se habiliten los módulos de Aula Virtual, Matrícula,
                    Evaluaciones y Pagos correspondientes a tu rol.
                </p>
            </div>
        @endunless
    </div>
</div>
