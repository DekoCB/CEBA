<?php

use App\Modules\Identidad\DTOs\CrearUsuarioData;
use App\Modules\Identidad\Services\UserManagementService;
use App\Shared\Enums\RolEnum;
use App\Shared\ValueObjects\Dni;
use App\Shared\ValueObjects\Telefono;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app')] class extends Component
{
    use WithPagination;

    public string $termino = '';

    public string $filtroRol = '';

    public bool $mostrarModal = false;

    #[Validate('required|string|max:150')]
    public string $name = '';

    #[Validate('required|email|max:150')]
    public string $email = '';

    #[Validate('required|string|min:8|max:12')]
    public string $dni = '';

    #[Validate('nullable|string')]
    public string $phone = '';

    #[Validate('required|string|min:8')]
    public string $password = '';

    #[Validate('required|string')]
    public string $rol = '';

    public function mount(): void
    {
        Gate::authorize('usuarios.ver');
    }

    public function updatingTermino(): void
    {
        $this->resetPage();
    }

    public function updatingFiltroRol(): void
    {
        $this->resetPage();
    }

    public function abrirModal(): void
    {
        Gate::authorize('usuarios.crear');

        $this->reset(['name', 'email', 'dni', 'phone', 'password', 'rol']);
        $this->resetValidation();
        $this->mostrarModal = true;
    }

    public function crear(UserManagementService $service): void
    {
        Gate::authorize('usuarios.crear');

        $this->validate([
            'name' => 'required|string|max:150',
            'email' => 'required|email|max:150',
            'dni' => 'required|string|min:8|max:12',
            'phone' => 'nullable|string',
            'password' => 'required|string|min:8',
            'rol' => 'required|string|in:'.implode(',', array_column($this->rolesCreables(), 'value')),
        ]);

        if (! $service->emailDisponible($this->email)) {
            $this->addError('email', 'Ya existe un usuario con este correo.');

            return;
        }

        if (! $service->dniDisponible($this->dni)) {
            $this->addError('dni', 'Ya existe un usuario con este documento.');

            return;
        }

        $service->crear(new CrearUsuarioData(
            name: $this->name,
            email: $this->email,
            dni: new Dni($this->dni),
            phone: $this->phone !== '' ? new Telefono($this->phone) : null,
            password: $this->password,
            rol: RolEnum::from($this->rol),
        ));

        $this->mostrarModal = false;
        session()->flash('status', 'Usuario creado correctamente.');
    }

    /**
     * @return list<RolEnum>
     */
    private function rolesCreables(): array
    {
        return array_values(array_filter(RolEnum::cases(), fn (RolEnum $rol) => $rol !== RolEnum::ESTUDIANTE));
    }

    public function with(UserManagementService $service): array
    {
        return [
            'usuarios' => $service->listar($this->termino ?: null, $this->filtroRol ?: null),
            'rolesDisponibles' => $this->rolesCreables(),
        ];
    }
}; ?>

<div>
    <x-slot name="header">
        <h1 class="font-display text-2xl text-ink">Usuarios</h1>
        <p class="mt-1 text-sm text-ink-dim">Cuentas del personal y su rol institucional.</p>
    </x-slot>

    {{-- Ver academico/grados/index.blade.php: el botón no puede vivir en x-slot="header". --}}
    @can('usuarios.crear')
        <div class="mb-4 flex justify-end">
            <button
                wire:click="abrirModal"
                class="inline-flex items-center gap-2 rounded-md bg-accent px-4 py-2 text-sm font-medium text-white hover:opacity-90"
            >
                <x-heroicon-o-plus class="h-4 w-4" />
                Nuevo usuario
            </button>
        </div>
    @endcan

    @if (session('status'))
        <div class="mb-4 rounded-md border border-ok/30 bg-ok/10 px-4 py-3 text-sm text-ok">
            {{ session('status') }}
        </div>
    @endif

    <div class="mb-4 flex flex-col gap-3 sm:flex-row">
        <input
            type="search"
            wire:model.live.debounce.300ms="termino"
            placeholder="Buscar por nombre, correo o DNI…"
            class="w-full rounded-md border-border bg-surface text-sm text-ink placeholder:text-ink-faint focus:border-accent focus:ring-accent sm:max-w-xs"
        >
        <select
            wire:model.live="filtroRol"
            class="w-full rounded-md border-border bg-surface text-sm text-ink focus:border-accent focus:ring-accent sm:max-w-xs"
        >
            <option value="">Todos los roles</option>
            @foreach ($rolesDisponibles as $rolOpcion)
                <option value="{{ $rolOpcion->value }}">{{ $rolOpcion->label() }}</option>
            @endforeach
        </select>
    </div>

    <div class="overflow-hidden rounded-lg border border-border bg-surface">
        <table class="min-w-full divide-y divide-border text-sm">
            <thead class="bg-surface-2">
                <tr>
                    <th class="px-4 py-3 text-left font-mono text-xs uppercase tracking-wide text-ink-faint">Nombre</th>
                    <th class="px-4 py-3 text-left font-mono text-xs uppercase tracking-wide text-ink-faint">Correo</th>
                    <th class="px-4 py-3 text-left font-mono text-xs uppercase tracking-wide text-ink-faint">DNI</th>
                    <th class="px-4 py-3 text-left font-mono text-xs uppercase tracking-wide text-ink-faint">Rol</th>
                    <th class="px-4 py-3 text-left font-mono text-xs uppercase tracking-wide text-ink-faint">Estado</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-border">
                @forelse ($usuarios as $usuario)
                    <tr wire:key="usuario-{{ $usuario->id }}">
                        <td class="px-4 py-3 font-medium text-ink">{{ $usuario->name }}</td>
                        <td class="px-4 py-3 text-ink-dim">{{ $usuario->email }}</td>
                        <td class="px-4 py-3 font-mono text-ink-dim">{{ $usuario->dni ?? '—' }}</td>
                        <td class="px-4 py-3">
                            @foreach ($usuario->roles as $rolAsignado)
                                <span class="rounded-full bg-surface-2 px-2 py-0.5 text-xs text-ink-dim">{{ ucfirst($rolAsignado->name) }}</span>
                            @endforeach
                        </td>
                        <td class="px-4 py-3">
                            <span @class([
                                'rounded-full px-2 py-0.5 text-xs font-medium',
                                'bg-ok/10 text-ok' => $usuario->estado->value === 'activo',
                                'bg-ink-faint/10 text-ink-faint' => $usuario->estado->value !== 'activo',
                            ])>
                                {{ ucfirst($usuario->estado->value) }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('usuarios.show', $usuario) }}" wire:navigate class="text-sm font-medium text-accent hover:underline">
                                Ver
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-8 text-center text-sm text-ink-faint">No se encontraron usuarios.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $usuarios->links() }}
    </div>

    @if ($mostrarModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-ink/40 px-4" wire:click.self="$set('mostrarModal', false)">
            <div class="w-full max-w-md rounded-lg border border-border bg-surface p-6 shadow-lg">
                <h2 class="font-display text-lg text-ink">Nuevo usuario</h2>

                <form wire:submit="crear" class="mt-4 space-y-4">
                    <div>
                        <x-input-label for="name" value="Nombre completo" />
                        <x-text-input wire:model="name" id="name" class="mt-1 block w-full" />
                        <x-input-error :messages="$errors->get('name')" class="mt-1" />
                    </div>

                    <div>
                        <x-input-label for="email" value="Correo" />
                        <x-text-input wire:model="email" id="email" type="email" class="mt-1 block w-full" />
                        <x-input-error :messages="$errors->get('email')" class="mt-1" />
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <x-input-label for="dni" value="DNI" />
                            <x-text-input wire:model="dni" id="dni" class="mt-1 block w-full" />
                            <x-input-error :messages="$errors->get('dni')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label for="phone" value="Celular" />
                            <x-text-input wire:model="phone" id="phone" class="mt-1 block w-full" />
                            <x-input-error :messages="$errors->get('phone')" class="mt-1" />
                        </div>
                    </div>

                    <div>
                        <x-input-label for="password" value="Contraseña temporal" />
                        <x-text-input wire:model="password" id="password" type="password" class="mt-1 block w-full" />
                        <x-input-error :messages="$errors->get('password')" class="mt-1" />
                    </div>

                    <div>
                        <x-input-label for="rol" value="Rol" />
                        <select wire:model="rol" id="rol" class="mt-1 block w-full rounded-md border-border bg-surface text-sm text-ink focus:border-accent focus:ring-accent">
                            <option value="">Selecciona un rol…</option>
                            @foreach ($rolesDisponibles as $rolOpcion)
                                <option value="{{ $rolOpcion->value }}">{{ $rolOpcion->label() }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('rol')" class="mt-1" />
                    </div>

                    <div class="flex justify-end gap-3 pt-2">
                        <x-secondary-button type="button" wire:click="$set('mostrarModal', false)">Cancelar</x-secondary-button>
                        <x-primary-button type="submit">Crear usuario</x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
