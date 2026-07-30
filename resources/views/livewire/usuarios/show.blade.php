<?php

use App\Models\User;
use App\Modules\Identidad\DTOs\ActualizarUsuarioData;
use App\Modules\Identidad\Services\AuditService;
use App\Modules\Identidad\Services\SessionControlService;
use App\Modules\Identidad\Services\UserManagementService;
use App\Shared\Enums\EstadoUsuarioEnum;
use App\Shared\Enums\RolEnum;
use App\Shared\ValueObjects\Dni;
use App\Shared\ValueObjects\Telefono;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public User $usuario;

    public string $name = '';

    public string $email = '';

    public string $dni = '';

    public string $phone = '';

    public string $estado = '';

    public string $rol = '';

    public function mount(User $usuario): void
    {
        Gate::authorize('usuarios.ver');

        $this->usuario = $usuario;
        $this->name = $usuario->name;
        $this->email = $usuario->email;
        $this->dni = (string) $usuario->dni;
        $this->phone = (string) $usuario->phone;
        $this->estado = $usuario->estado->value;
        $this->rol = $usuario->roles->first()?->name ?? '';
    }

    public function guardarDatos(UserManagementService $service): void
    {
        Gate::authorize('usuarios.editar', $this->usuario);

        $this->validate([
            'name' => 'required|string|max:150',
            'email' => 'required|email|max:150',
            'dni' => 'required|string|min:8|max:12',
            'phone' => 'nullable|string',
            'estado' => 'required|string|in:activo,inactivo,suspendido',
        ]);

        if (! $service->emailDisponible($this->email, $this->usuario->id)) {
            $this->addError('email', 'Ya existe otro usuario con este correo.');

            return;
        }

        if (! $service->dniDisponible($this->dni, $this->usuario->id)) {
            $this->addError('dni', 'Ya existe otro usuario con este documento.');

            return;
        }

        $this->usuario = $service->actualizar($this->usuario, new ActualizarUsuarioData(
            name: $this->name,
            email: $this->email,
            dni: new Dni($this->dni),
            phone: $this->phone !== '' ? new Telefono($this->phone) : null,
            estado: EstadoUsuarioEnum::from($this->estado),
        ));

        session()->flash('status', 'Datos actualizados correctamente.');
    }

    public function cambiarRol(UserManagementService $service): void
    {
        Gate::authorize('assignRoles', $this->usuario);

        $this->validate([
            'rol' => 'required|string|in:'.implode(',', array_column(RolEnum::cases(), 'value')),
        ]);

        $service->cambiarRol($this->usuario, $this->rol);
        $this->usuario->refresh();

        session()->flash('status', 'Rol actualizado correctamente.');
    }

    public function revocarSesion(string $sesionId, SessionControlService $service): void
    {
        Gate::authorize('manageSessions', $this->usuario);

        $service->revocar($sesionId);

        session()->flash('status', 'Sesión cerrada.');
    }

    public function with(SessionControlService $sessions, AuditService $audit): array
    {
        return [
            'rolesDisponibles' => RolEnum::cases(),
            'puedeGestionarRoles' => Gate::allows('assignRoles', $this->usuario),
            'puedeGestionarSesiones' => Gate::allows('manageSessions', $this->usuario),
            'sesiones' => Gate::allows('manageSessions', $this->usuario)
                ? $sessions->sesionesDe($this->usuario, session()->getId())
                : collect(),
            'puedeVerAuditoria' => Gate::allows('auditoria.ver'),
            'historial' => Gate::allows('auditoria.ver')
                ? $audit->historialDe($this->usuario)->take(10)
                : collect(),
        ];
    }
}; ?>

<div class="max-w-3xl space-y-6">
    <x-slot name="header">
        <a href="{{ route('usuarios.index') }}" wire:navigate class="text-sm text-ink-faint hover:text-ink">← Usuarios</a>
        <h1 class="mt-1 font-display text-2xl text-ink">{{ $usuario->name }}</h1>
    </x-slot>

    @if (session('status'))
        <div class="rounded-md border border-ok/30 bg-ok/10 px-4 py-3 text-sm text-ok">
            {{ session('status') }}
        </div>
    @endif

    <div class="rounded-lg border border-border bg-surface p-6">
        <h2 class="text-sm font-semibold text-ink">Datos personales</h2>

        <form wire:submit="guardarDatos" class="mt-4 space-y-4">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <x-input-label for="name" value="Nombre completo" />
                    <x-text-input wire:model="name" id="name" class="mt-1 block w-full" :disabled="Gate::denies('usuarios.editar', $usuario)" />
                    <x-input-error :messages="$errors->get('name')" class="mt-1" />
                </div>
                <div>
                    <x-input-label for="email" value="Correo" />
                    <x-text-input wire:model="email" id="email" type="email" class="mt-1 block w-full" :disabled="Gate::denies('usuarios.editar', $usuario)" />
                    <x-input-error :messages="$errors->get('email')" class="mt-1" />
                </div>
                <div>
                    <x-input-label for="dni" value="DNI" />
                    <x-text-input wire:model="dni" id="dni" class="mt-1 block w-full" :disabled="Gate::denies('usuarios.editar', $usuario)" />
                    <x-input-error :messages="$errors->get('dni')" class="mt-1" />
                </div>
                <div>
                    <x-input-label for="phone" value="Celular" />
                    <x-text-input wire:model="phone" id="phone" class="mt-1 block w-full" :disabled="Gate::denies('usuarios.editar', $usuario)" />
                    <x-input-error :messages="$errors->get('phone')" class="mt-1" />
                </div>
                <div>
                    <x-input-label for="estado" value="Estado" />
                    <select wire:model="estado" id="estado" class="mt-1 block w-full rounded-md border-border bg-surface text-sm text-ink focus:border-accent focus:ring-accent" @disabled(Gate::denies('usuarios.editar', $usuario))>
                        @foreach (\App\Shared\Enums\EstadoUsuarioEnum::cases() as $estadoOpcion)
                            <option value="{{ $estadoOpcion->value }}">{{ $estadoOpcion->label() }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            @can('usuarios.editar', $usuario)
                <div class="flex justify-end">
                    <x-primary-button type="submit">Guardar cambios</x-primary-button>
                </div>
            @endcan
        </form>
    </div>

    @if ($puedeGestionarRoles)
        <div class="rounded-lg border border-border bg-surface p-6">
            <h2 class="text-sm font-semibold text-ink">Rol institucional</h2>
            <form wire:submit="cambiarRol" class="mt-4 flex items-end gap-3">
                <div class="flex-1">
                    <select wire:model="rol" class="block w-full rounded-md border-border bg-surface text-sm text-ink focus:border-accent focus:ring-accent">
                        @foreach ($rolesDisponibles as $rolOpcion)
                            <option value="{{ $rolOpcion->value }}">{{ $rolOpcion->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <x-primary-button type="submit">Actualizar rol</x-primary-button>
            </form>
        </div>
    @endif

    @if ($puedeGestionarSesiones)
        <div class="rounded-lg border border-border bg-surface p-6">
            <h2 class="text-sm font-semibold text-ink">Sesiones activas</h2>
            <div class="mt-4 divide-y divide-border">
                @forelse ($sesiones as $sesion)
                    <div class="flex items-center justify-between py-3 text-sm">
                        <div>
                            <p class="text-ink">
                                {{ $sesion->ipAddress ?? 'IP desconocida' }}
                                @if ($sesion->esActual)
                                    <span class="ml-2 rounded-full bg-accent-soft px-2 py-0.5 text-xs text-accent">Sesión actual</span>
                                @endif
                            </p>
                            <p class="text-xs text-ink-faint">
                                {{ \Illuminate\Support\Str::limit($sesion->userAgent ?? 'Agente desconocido', 60) }}
                                · última actividad {{ \Illuminate\Support\Carbon::createFromTimestamp($sesion->lastActivity)->diffForHumans() }}
                            </p>
                        </div>
                        @unless ($sesion->esActual)
                            <button
                                wire:click="revocarSesion('{{ $sesion->id }}')"
                                wire:confirm="¿Cerrar esta sesión?"
                                class="text-sm font-medium text-danger hover:underline"
                            >
                                Cerrar sesión
                            </button>
                        @endunless
                    </div>
                @empty
                    <p class="py-4 text-sm text-ink-faint">No hay sesiones activas registradas.</p>
                @endforelse
            </div>
        </div>
    @endif

    @if ($puedeVerAuditoria)
        <div class="rounded-lg border border-border bg-surface p-6">
            <h2 class="text-sm font-semibold text-ink">Actividad reciente de esta cuenta</h2>
            <div class="mt-4 divide-y divide-border">
                @forelse ($historial as $entrada)
                    <div class="flex items-center justify-between py-3 text-sm">
                        <span class="text-ink-dim">
                            {{ match ($entrada->event) {
                                'created' => 'Cuenta creada',
                                'updated' => 'Datos actualizados',
                                'deleted' => 'Cuenta eliminada',
                                default => $entrada->event,
                            } }}
                        </span>
                        <span class="text-xs text-ink-faint">{{ $entrada->created_at?->diffForHumans() }}</span>
                    </div>
                @empty
                    <p class="py-4 text-sm text-ink-faint">Sin actividad registrada todavía.</p>
                @endforelse
            </div>
        </div>
    @endif
</div>
