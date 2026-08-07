<?php

use App\Modules\Matricula\Enums\EstadoEstudianteEnum;
use App\Modules\Matricula\Services\MatriculaService;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app')] class extends Component
{
    use WithPagination;

    public string $termino = '';

    public string $estadoFiltro = '';

    public bool $mostrarWizard = false;

    public function mount(): void
    {
        Gate::authorize('matricula.ver');
    }

    #[On('wizard-cerrado')]
    public function cerrarWizard(): void
    {
        $this->mostrarWizard = false;
    }

    #[On('matricula-registrada')]
    public function matriculaRegistrada(int $estudianteId, string $nombre): void
    {
        $this->mostrarWizard = false;
        session()->flash('status', "Matrícula de {$nombre} registrada correctamente.");
        session()->flash('estudianteRegistradoId', $estudianteId);
    }

    public function updatingTermino(): void
    {
        $this->resetPage();
    }

    public function updatingEstadoFiltro(): void
    {
        $this->resetPage();
    }

    public function with(MatriculaService $service): array
    {
        return [
            'estudiantes' => $service->listarEstudiantes($this->termino ?: null, $this->estadoFiltro ?: null),
            'estados' => EstadoEstudianteEnum::cases(),
        ];
    }
}; ?>

<div>
    <x-slot name="header">
        <h1 class="font-display text-2xl text-ink">Matrícula</h1>
        <p class="mt-1 text-sm text-ink-dim">Estudiantes registrados y su estado.</p>
    </x-slot>

    {{-- Ver academico/grados/index.blade.php: el botón no puede vivir en x-slot="header". --}}
    @can('matricula.crear')
        <div class="mb-4 flex justify-end">
            <button type="button" wire:click="$set('mostrarWizard', true)" class="inline-flex items-center gap-2 rounded-md bg-accent px-4 py-2 text-sm font-medium text-white hover:opacity-90">
                <x-heroicon-o-plus class="h-4 w-4" />
                Nueva matrícula
            </button>
        </div>
    @endcan

    @if (session('status'))
        <div class="mb-4 flex items-center justify-between rounded-md border border-ok/30 bg-ok/10 px-4 py-3 text-sm text-ok">
            <span>{{ session('status') }}</span>
            @if (session('estudianteRegistradoId'))
                <a href="{{ route('matricula.show', session('estudianteRegistradoId')) }}" wire:navigate class="font-medium underline">
                    Ver ficha →
                </a>
            @endif
        </div>
    @endif

    @if ($mostrarWizard)
        <livewire:matricula.wizard wire:key="wizard-nueva-matricula" />
    @endif

    <div class="mb-4 flex flex-col gap-3 sm:flex-row">
        <input
            type="search"
            wire:model.live.debounce.300ms="termino"
            placeholder="Buscar por nombre, apellido o DNI…"
            class="w-full rounded-md border-border bg-surface text-sm text-ink placeholder:text-ink-faint focus:border-accent focus:ring-accent sm:max-w-xs"
        >
        <x-select-input
            wire:model.live="estadoFiltro"
            class="w-full sm:max-w-xs"
            :options="collect($estados)->mapWithKeys(fn ($estado) => [$estado->value => $estado->label()])->prepend('Todos los estados', '')"
        />
    </div>

    <div class="overflow-hidden rounded-lg border border-border bg-surface">
        <table class="min-w-full divide-y divide-border text-sm">
            <thead class="bg-surface-2">
                <tr>
                    <th class="px-4 py-3 text-left font-mono text-xs uppercase tracking-wide text-ink-faint">DNI</th>
                    <th class="px-4 py-3 text-left font-mono text-xs uppercase tracking-wide text-ink-faint">Nombre</th>
                    <th class="px-4 py-3 text-left font-mono text-xs uppercase tracking-wide text-ink-faint">Grado actual</th>
                    <th class="px-4 py-3 text-left font-mono text-xs uppercase tracking-wide text-ink-faint">Estado</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-border">
                @forelse ($estudiantes as $estudiante)
                    <tr wire:key="estudiante-{{ $estudiante->id }}">
                        <td class="px-4 py-3 font-mono text-ink-dim">{{ $estudiante->dni }}</td>
                        <td class="px-4 py-3 font-medium text-ink">{{ $estudiante->nombreCompleto() }}</td>
                        <td class="px-4 py-3 text-ink-dim">{{ $estudiante->gradoActual?->nombre ?? '—' }}</td>
                        <td class="px-4 py-3">
                            <span @class([
                                'rounded-full px-2 py-0.5 text-xs font-medium',
                                'bg-ok/10 text-ok' => $estudiante->estado->value === 'activo',
                                'bg-ink-faint/10 text-ink-faint' => $estudiante->estado->value !== 'activo',
                            ])>
                                {{ $estudiante->estado->label() }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('matricula.show', $estudiante) }}" wire:navigate class="text-sm font-medium text-accent hover:underline">Ver</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-8 text-center text-sm text-ink-faint">No se encontraron estudiantes.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $estudiantes->links() }}</div>
</div>
