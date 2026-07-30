<?php

use App\Modules\Academico\Enums\TipoPublicoEnum;
use App\Modules\Academico\Models\Grado;
use App\Modules\Academico\Services\GradoService;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public bool $mostrarModal = false;

    public ?int $editandoId = null;

    public string $nombre = '';

    public string $tipoPublico = '';

    public string $orden = '';

    public bool $activo = true;

    public function mount(): void
    {
        Gate::authorize('academico.ver');
    }

    public function abrirModal(?int $gradoId = null): void
    {
        Gate::authorize('academico.gestionar');

        $this->resetValidation();
        $this->editandoId = $gradoId;

        if ($gradoId) {
            $grado = Grado::query()->findOrFail($gradoId);
            $this->nombre = $grado->nombre;
            $this->tipoPublico = $grado->tipo_publico->value;
            $this->orden = (string) $grado->orden;
            $this->activo = $grado->activo;
        } else {
            $this->reset(['nombre', 'tipoPublico', 'orden']);
            $this->activo = true;
        }

        $this->mostrarModal = true;
    }

    public function guardar(GradoService $service): void
    {
        Gate::authorize('academico.gestionar');

        $this->validate([
            'nombre' => 'required|string|max:100',
            'tipoPublico' => 'required|string|in:'.implode(',', array_column(TipoPublicoEnum::cases(), 'value')),
            'orden' => 'required|integer|min:1|max:10',
        ]);

        $publico = TipoPublicoEnum::from($this->tipoPublico);

        if ($service->existeOrdenParaPublico($publico, (int) $this->orden, $this->editandoId)) {
            $this->addError('orden', "Ya existe un grado con el orden {$this->orden} para {$publico->label()}.");

            return;
        }

        $datos = [
            'nombre' => $this->nombre,
            'tipo_publico' => $publico,
            'orden' => (int) $this->orden,
        ];

        if ($this->editandoId) {
            $service->actualizar(Grado::query()->findOrFail($this->editandoId), [...$datos, 'activo' => $this->activo]);
        } else {
            $service->crear($datos);
        }

        $this->mostrarModal = false;
        session()->flash('status', 'Grado guardado correctamente.');
    }

    public function with(GradoService $service): array
    {
        return [
            'grados' => $service->todos(),
            'tiposPublico' => TipoPublicoEnum::cases(),
        ];
    }
}; ?>

<div>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="font-display text-2xl text-ink">Grados</h1>
                <p class="mt-1 text-sm text-ink-dim">Niveles que cursan los estudiantes, según sean mayores o menores de edad.</p>
            </div>
            @can('academico.gestionar')
                <button wire:click="abrirModal" class="inline-flex items-center gap-2 rounded-md bg-accent px-4 py-2 text-sm font-medium text-white hover:opacity-90">
                    <x-heroicon-o-plus class="h-4 w-4" />
                    Nuevo grado
                </button>
            @endcan
        </div>
    </x-slot>

    @if (session('status'))
        <div class="mb-4 rounded-md border border-ok/30 bg-ok/10 px-4 py-3 text-sm text-ok">{{ session('status') }}</div>
    @endif

    <div class="overflow-hidden rounded-lg border border-border bg-surface">
        <table class="min-w-full divide-y divide-border text-sm">
            <thead class="bg-surface-2">
                <tr>
                    <th class="px-4 py-3 text-left font-mono text-xs uppercase tracking-wide text-ink-faint">Nombre</th>
                    <th class="px-4 py-3 text-left font-mono text-xs uppercase tracking-wide text-ink-faint">Público</th>
                    <th class="px-4 py-3 text-left font-mono text-xs uppercase tracking-wide text-ink-faint">Orden</th>
                    <th class="px-4 py-3 text-left font-mono text-xs uppercase tracking-wide text-ink-faint">Estado</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-border">
                @forelse ($grados as $grado)
                    <tr wire:key="grado-{{ $grado->id }}">
                        <td class="px-4 py-3 font-medium text-ink">{{ $grado->nombre }}</td>
                        <td class="px-4 py-3 text-ink-dim">{{ $grado->tipo_publico->label() }}</td>
                        <td class="px-4 py-3 font-mono text-ink-dim">{{ $grado->orden }}</td>
                        <td class="px-4 py-3">
                            <span @class(['rounded-full px-2 py-0.5 text-xs font-medium', 'bg-ok/10 text-ok' => $grado->activo, 'bg-ink-faint/10 text-ink-faint' => ! $grado->activo])>
                                {{ $grado->activo ? 'Activo' : 'Inactivo' }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right">
                            @can('academico.gestionar')
                                <button wire:click="abrirModal({{ $grado->id }})" class="text-sm font-medium text-accent hover:underline">Editar</button>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-8 text-center text-sm text-ink-faint">No hay grados registrados.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($mostrarModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-ink/40 px-4" wire:click.self="$set('mostrarModal', false)">
            <div class="w-full max-w-md rounded-lg border border-border bg-surface p-6 shadow-lg">
                <h2 class="font-display text-lg text-ink">{{ $editandoId ? 'Editar grado' : 'Nuevo grado' }}</h2>

                <form wire:submit="guardar" class="mt-4 space-y-4">
                    <div>
                        <x-input-label for="nombre" value="Nombre" />
                        <x-text-input wire:model="nombre" id="nombre" class="mt-1 block w-full" />
                        <x-input-error :messages="$errors->get('nombre')" class="mt-1" />
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <x-input-label for="tipoPublico" value="Público" />
                            <select wire:model="tipoPublico" id="tipoPublico" class="mt-1 block w-full rounded-md border-border bg-surface text-sm text-ink focus:border-accent focus:ring-accent">
                                <option value="">Selecciona…</option>
                                @foreach ($tiposPublico as $tipo)
                                    <option value="{{ $tipo->value }}">{{ $tipo->label() }}</option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('tipoPublico')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label for="orden" value="Orden" />
                            <x-text-input wire:model="orden" id="orden" type="number" min="1" max="10" class="mt-1 block w-full" />
                            <x-input-error :messages="$errors->get('orden')" class="mt-1" />
                        </div>
                    </div>

                    @if ($editandoId)
                        <label class="flex items-center gap-2 text-sm text-ink-dim">
                            <input type="checkbox" wire:model="activo" class="rounded border-border text-accent focus:ring-accent">
                            Grado activo
                        </label>
                    @endif

                    <div class="flex justify-end gap-3 pt-2">
                        <x-secondary-button type="button" wire:click="$set('mostrarModal', false)">Cancelar</x-secondary-button>
                        <x-primary-button type="submit">Guardar</x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
