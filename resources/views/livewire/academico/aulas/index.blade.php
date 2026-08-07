<?php

use App\Modules\Academico\Models\Aula;
use App\Modules\Academico\Services\AulaService;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public bool $mostrarModal = false;

    public ?int $editandoId = null;

    public string $nombre = '';

    public string $capacidad = '';

    public string $ubicacion = '';

    public bool $activa = true;

    public function mount(): void
    {
        Gate::authorize('academico.ver');
    }

    public function abrirModal(?int $aulaId = null): void
    {
        Gate::authorize('academico.gestionar');

        $this->resetValidation();
        $this->editandoId = $aulaId;

        if ($aulaId) {
            $aula = Aula::query()->findOrFail($aulaId);
            $this->nombre = $aula->nombre;
            $this->capacidad = (string) $aula->capacidad;
            $this->ubicacion = (string) $aula->ubicacion;
            $this->activa = $aula->activa;
        } else {
            $this->reset(['nombre', 'capacidad', 'ubicacion']);
            $this->activa = true;
        }

        $this->mostrarModal = true;
    }

    public function guardar(AulaService $service): void
    {
        Gate::authorize('academico.gestionar');

        $this->validate([
            'nombre' => 'required|string|max:50',
            'capacidad' => 'required|integer|min:1|max:200',
            'ubicacion' => 'nullable|string|max:100',
        ]);

        $datos = [
            'nombre' => $this->nombre,
            'capacidad' => (int) $this->capacidad,
            'ubicacion' => $this->ubicacion ?: null,
        ];

        if ($this->editandoId) {
            $service->actualizar(Aula::query()->findOrFail($this->editandoId), [...$datos, 'activa' => $this->activa]);
        } else {
            $service->crear($datos);
        }

        $this->mostrarModal = false;
        session()->flash('status', 'Aula guardada correctamente.');
    }

    public function with(AulaService $service): array
    {
        return ['aulas' => $service->todas()];
    }
}; ?>

<div>
    <x-slot name="header">
        <h1 class="font-display text-2xl text-ink">Aulas</h1>
        <p class="mt-1 text-sm text-ink-dim">Espacios físicos disponibles para dictar clases.</p>
    </x-slot>

    {{-- Ver academico/grados/index.blade.php: el botón no puede vivir en x-slot="header". --}}
    @can('academico.gestionar')
        <div class="mb-4 flex justify-end">
            <button wire:click="abrirModal" class="inline-flex items-center gap-2 rounded-md bg-accent px-4 py-2 text-sm font-medium text-white hover:opacity-90">
                <x-heroicon-o-plus class="h-4 w-4" />
                Nueva aula
            </button>
        </div>
    @endcan

    @if (session('status'))
        <div class="mb-4 rounded-md border border-ok/30 bg-ok/10 px-4 py-3 text-sm text-ok">{{ session('status') }}</div>
    @endif

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @forelse ($aulas as $aula)
            <div wire:key="aula-{{ $aula->id }}" class="rounded-lg border border-border bg-surface p-4">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="font-display text-lg text-ink">{{ $aula->nombre }}</p>
                        <p class="text-sm text-ink-dim">{{ $aula->ubicacion ?? 'Sin ubicación' }}</p>
                    </div>
                    <span @class(['rounded-full px-2 py-0.5 text-xs font-medium', 'bg-ok/10 text-ok' => $aula->activa, 'bg-ink-faint/10 text-ink-faint' => ! $aula->activa])>
                        {{ $aula->activa ? 'Activa' : 'Inactiva' }}
                    </span>
                </div>
                <p class="mt-3 text-sm text-ink-faint">Capacidad: <span class="text-ink">{{ $aula->capacidad }}</span> estudiantes</p>
                @can('academico.gestionar')
                    <button wire:click="abrirModal({{ $aula->id }})" class="mt-3 text-sm font-medium text-accent hover:underline">Editar</button>
                @endcan
            </div>
        @empty
            <p class="col-span-full py-8 text-center text-sm text-ink-faint">No hay aulas registradas.</p>
        @endforelse
    </div>

    <div
        x-show="$wire.mostrarModal"
        x-cloak
        class="fixed inset-0 z-50 flex items-center justify-center bg-ink/40 px-4"
        wire:click.self="$set('mostrarModal', false)"
        x-transition:enter="ease-out duration-300"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="ease-in duration-200"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
    >
        <div
            x-show="$wire.mostrarModal"
            class="w-full max-w-md rounded-lg border border-border bg-surface p-6 shadow-lg"
            x-transition:enter="ease-out duration-300"
            x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
            x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
            x-transition:leave="ease-in duration-200"
            x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
            x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
        >
                <h2 class="font-display text-lg text-ink">{{ $editandoId ? 'Editar aula' : 'Nueva aula' }}</h2>

                <form wire:submit="guardar" class="mt-4 space-y-4">
                    <div>
                        <x-input-label for="nombre" value="Nombre" />
                        <x-text-input wire:model="nombre" id="nombre" class="mt-1 block w-full" />
                        <x-input-error :messages="$errors->get('nombre')" class="mt-1" />
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <x-input-label for="capacidad" value="Capacidad" />
                            <x-text-input wire:model="capacidad" id="capacidad" type="number" min="1" class="mt-1 block w-full" />
                            <x-input-error :messages="$errors->get('capacidad')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label for="ubicacion" value="Ubicación" />
                            <x-text-input wire:model="ubicacion" id="ubicacion" class="mt-1 block w-full" />
                            <x-input-error :messages="$errors->get('ubicacion')" class="mt-1" />
                        </div>
                    </div>

                    @if ($editandoId)
                        <label class="flex items-center gap-2 text-sm text-ink-dim">
                            <input type="checkbox" wire:model="activa" class="rounded border-border text-accent focus:ring-accent">
                            Aula activa
                        </label>
                    @endif

                    <div class="flex justify-end gap-3 pt-2">
                        <x-secondary-button type="button" wire:click="$set('mostrarModal', false)">Cancelar</x-secondary-button>
                        <x-primary-button type="submit">Guardar</x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
