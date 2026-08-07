<?php

use App\Modules\Academico\Models\Curso;
use App\Modules\Academico\Models\Grado;
use App\Modules\Academico\Services\CursoService;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app')] class extends Component
{
    use WithPagination;

    public bool $mostrarModal = false;

    public ?int $editandoId = null;

    public string $nombre = '';

    public string $codigo = '';

    public string $gradoId = '';

    public string $horas = '';

    public bool $activo = true;

    public function mount(): void
    {
        Gate::authorize('academico.ver');
    }

    public function abrirModal(?int $cursoId = null): void
    {
        Gate::authorize('academico.gestionar');

        $this->resetValidation();
        $this->editandoId = $cursoId;

        if ($cursoId) {
            $curso = Curso::query()->findOrFail($cursoId);
            $this->nombre = $curso->nombre;
            $this->codigo = $curso->codigo;
            $this->gradoId = (string) $curso->grado_id;
            $this->horas = (string) $curso->horas;
            $this->activo = $curso->activo;
        } else {
            $this->reset(['nombre', 'codigo', 'gradoId', 'horas']);
            $this->activo = true;
        }

        $this->mostrarModal = true;
    }

    public function guardar(CursoService $service): void
    {
        Gate::authorize('academico.gestionar');

        $this->validate([
            'nombre' => 'required|string|max:100',
            'codigo' => 'required|string|max:20',
            'gradoId' => 'required|integer|exists:grados,id',
            'horas' => 'required|integer|min:1|max:500',
        ]);

        if (! $service->codigoDisponible($this->codigo, $this->editandoId)) {
            $this->addError('codigo', 'Ya existe un curso con este código.');

            return;
        }

        $datos = [
            'nombre' => $this->nombre,
            'codigo' => strtoupper($this->codigo),
            'grado_id' => (int) $this->gradoId,
            'horas' => (int) $this->horas,
        ];

        if ($this->editandoId) {
            $service->actualizar(Curso::query()->findOrFail($this->editandoId), [...$datos, 'activo' => $this->activo]);
        } else {
            $service->crear($datos);
        }

        $this->mostrarModal = false;
        session()->flash('status', 'Curso guardado correctamente.');
    }

    public function with(CursoService $service): array
    {
        return [
            'cursos' => $service->listar(),
            'grados' => Grado::query()->orderBy('nombre')->get(),
        ];
    }
}; ?>

<div>
    <x-slot name="header">
        <h1 class="font-display text-2xl text-ink">Cursos</h1>
        <p class="mt-1 text-sm text-ink-dim">Catálogo de cursos por grado.</p>
    </x-slot>

    {{-- Ver academico/grados/index.blade.php: el botón no puede vivir en x-slot="header". --}}
    @can('academico.gestionar')
        <div class="mb-4 flex justify-end">
            <button wire:click="abrirModal" class="inline-flex items-center gap-2 rounded-md bg-accent px-4 py-2 text-sm font-medium text-white hover:opacity-90">
                <x-heroicon-o-plus class="h-4 w-4" />
                Nuevo curso
            </button>
        </div>
    @endcan

    @if (session('status'))
        <div class="mb-4 rounded-md border border-ok/30 bg-ok/10 px-4 py-3 text-sm text-ok">{{ session('status') }}</div>
    @endif

    <div class="overflow-hidden rounded-lg border border-border bg-surface">
        <table class="min-w-full divide-y divide-border text-sm">
            <thead class="bg-surface-2">
                <tr>
                    <th class="px-4 py-3 text-left font-mono text-xs uppercase tracking-wide text-ink-faint">Código</th>
                    <th class="px-4 py-3 text-left font-mono text-xs uppercase tracking-wide text-ink-faint">Nombre</th>
                    <th class="px-4 py-3 text-left font-mono text-xs uppercase tracking-wide text-ink-faint">Grado</th>
                    <th class="px-4 py-3 text-left font-mono text-xs uppercase tracking-wide text-ink-faint">Horas</th>
                    <th class="px-4 py-3 text-left font-mono text-xs uppercase tracking-wide text-ink-faint">Estado</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-border">
                @forelse ($cursos as $curso)
                    <tr wire:key="curso-{{ $curso->id }}">
                        <td class="px-4 py-3 font-mono text-ink-dim">{{ $curso->codigo }}</td>
                        <td class="px-4 py-3 font-medium text-ink">{{ $curso->nombre }}</td>
                        <td class="px-4 py-3 text-ink-dim">{{ $curso->grado?->nombre }}</td>
                        <td class="px-4 py-3 text-ink-dim">{{ $curso->horas }}</td>
                        <td class="px-4 py-3">
                            <span @class(['rounded-full px-2 py-0.5 text-xs font-medium', 'bg-ok/10 text-ok' => $curso->activo, 'bg-ink-faint/10 text-ink-faint' => ! $curso->activo])>
                                {{ $curso->activo ? 'Activo' : 'Inactivo' }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right">
                            @can('academico.gestionar')
                                <button wire:click="abrirModal({{ $curso->id }})" class="text-sm font-medium text-accent hover:underline">Editar</button>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-8 text-center text-sm text-ink-faint">No hay cursos registrados.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $cursos->links() }}</div>

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
                <h2 class="font-display text-lg text-ink">{{ $editandoId ? 'Editar curso' : 'Nuevo curso' }}</h2>

                <form wire:submit="guardar" class="mt-4 space-y-4">
                    <div>
                        <x-input-label for="nombre" value="Nombre" />
                        <x-text-input wire:model="nombre" id="nombre" class="mt-1 block w-full" />
                        <x-input-error :messages="$errors->get('nombre')" class="mt-1" />
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <x-input-label for="codigo" value="Código" />
                            <x-text-input wire:model="codigo" id="codigo" class="mt-1 block w-full uppercase" />
                            <x-input-error :messages="$errors->get('codigo')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label for="horas" value="Horas" />
                            <x-text-input wire:model="horas" id="horas" type="number" min="1" class="mt-1 block w-full" />
                            <x-input-error :messages="$errors->get('horas')" class="mt-1" />
                        </div>
                    </div>

                    <div>
                        <x-input-label for="gradoId" value="Grado" />
                        <select wire:model="gradoId" id="gradoId" class="mt-1 block w-full rounded-md border-border bg-surface text-sm text-ink focus:border-accent focus:ring-accent">
                            <option value="">Selecciona…</option>
                            @foreach ($grados as $grado)
                                <option value="{{ $grado->id }}">{{ $grado->nombre }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('gradoId')" class="mt-1" />
                    </div>

                    @if ($editandoId)
                        <label class="flex items-center gap-2 text-sm text-ink-dim">
                            <input type="checkbox" wire:model="activo" class="rounded border-border text-accent focus:ring-accent">
                            Curso activo
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
