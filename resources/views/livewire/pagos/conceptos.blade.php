<?php

use App\Modules\Pagos\Enums\TipoConceptoEnum;
use App\Modules\Pagos\Models\ConceptoPago;
use App\Modules\Pagos\Services\ConceptoPagoService;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public bool $mostrarModal = false;

    public ?int $editandoId = null;

    public string $nombre = '';

    public string $tipo = '';

    public string $montoBase = '';

    public bool $activo = true;

    public function mount(): void
    {
        Gate::authorize('pagos.gestionar');
    }

    public function abrirModal(?int $conceptoId = null): void
    {
        $this->resetValidation();
        $this->editandoId = $conceptoId;

        if ($conceptoId) {
            $concepto = ConceptoPago::query()->findOrFail($conceptoId);
            $this->nombre = $concepto->nombre;
            $this->tipo = $concepto->tipo->value;
            $this->montoBase = (string) $concepto->monto_base;
            $this->activo = $concepto->activo;
        } else {
            $this->reset(['nombre', 'tipo', 'montoBase']);
            $this->activo = true;
        }

        $this->mostrarModal = true;
    }

    public function guardar(ConceptoPagoService $service): void
    {
        $this->validate([
            'nombre' => 'required|string|max:100',
            'tipo' => 'required|string|in:'.implode(',', array_column(TipoConceptoEnum::cases(), 'value')),
            'montoBase' => 'required|numeric|min:0',
        ]);

        $tipo = TipoConceptoEnum::from($this->tipo);

        if ($this->editandoId) {
            $service->actualizar(ConceptoPago::query()->findOrFail($this->editandoId), $this->nombre, $tipo, (float) $this->montoBase, $this->activo);
        } else {
            $service->crear($this->nombre, $tipo, (float) $this->montoBase);
        }

        $this->mostrarModal = false;
        session()->flash('status', 'Concepto de pago guardado correctamente.');
    }

    public function with(ConceptoPagoService $service): array
    {
        return [
            'conceptos' => $service->listar(),
            'tipos' => TipoConceptoEnum::cases(),
        ];
    }
}; ?>

<div>
    <x-slot name="header">
        <h1 class="font-display text-2xl text-ink">Conceptos de pago</h1>
        <p class="mt-1 text-sm text-ink-dim">Catálogo de conceptos facturables: matrícula, mensualidad, certificados y más.</p>
    </x-slot>

    {{-- Ver academico/grados/index.blade.php: el botón no puede vivir en x-slot="header". --}}
    <div class="mb-4 flex justify-end">
        <button wire:click="abrirModal" class="inline-flex items-center gap-2 rounded-md bg-accent px-4 py-2 text-sm font-medium text-white hover:opacity-90">
            <x-heroicon-o-plus class="h-4 w-4" />
            Nuevo concepto
        </button>
    </div>

    @if (session('status'))
        <div class="mb-4 rounded-md border border-ok/30 bg-ok/10 px-4 py-3 text-sm text-ok">{{ session('status') }}</div>
    @endif

    <div class="overflow-hidden rounded-lg border border-border bg-surface">
        <table class="min-w-full divide-y divide-border text-sm">
            <thead class="bg-surface-2">
                <tr>
                    <th class="px-4 py-3 text-left font-mono text-xs uppercase tracking-wide text-ink-faint">Nombre</th>
                    <th class="px-4 py-3 text-left font-mono text-xs uppercase tracking-wide text-ink-faint">Tipo</th>
                    <th class="px-4 py-3 text-left font-mono text-xs uppercase tracking-wide text-ink-faint">Monto base</th>
                    <th class="px-4 py-3 text-left font-mono text-xs uppercase tracking-wide text-ink-faint">Estado</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-border">
                @forelse ($conceptos as $concepto)
                    <tr wire:key="concepto-{{ $concepto->id }}">
                        <td class="px-4 py-3 font-medium text-ink">{{ $concepto->nombre }}</td>
                        <td class="px-4 py-3 text-ink-dim">{{ $concepto->tipo->label() }}</td>
                        <td class="px-4 py-3 font-mono text-ink-dim">S/ {{ number_format((float) $concepto->monto_base, 2) }}</td>
                        <td class="px-4 py-3">
                            <span @class(['rounded-full px-2 py-0.5 text-xs font-medium', 'bg-ok/10 text-ok' => $concepto->activo, 'bg-ink-faint/10 text-ink-faint' => ! $concepto->activo])>
                                {{ $concepto->activo ? 'Activo' : 'Inactivo' }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <button wire:click="abrirModal({{ $concepto->id }})" class="text-sm font-medium text-accent hover:underline">Editar</button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-8 text-center text-sm text-ink-faint">No hay conceptos de pago registrados.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($mostrarModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-ink/40 px-4" wire:click.self="$set('mostrarModal', false)">
            <div class="w-full max-w-md rounded-lg border border-border bg-surface p-6 shadow-lg">
                <h2 class="font-display text-lg text-ink">{{ $editandoId ? 'Editar concepto' : 'Nuevo concepto' }}</h2>

                <form wire:submit="guardar" class="mt-4 space-y-4">
                    <div>
                        <x-input-label for="nombre" value="Nombre" />
                        <x-text-input wire:model="nombre" id="nombre" class="mt-1 block w-full" />
                        <x-input-error :messages="$errors->get('nombre')" class="mt-1" />
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <x-input-label for="tipo" value="Tipo" />
                            <select wire:model="tipo" id="tipo" class="mt-1 block w-full rounded-md border-border bg-surface text-sm text-ink focus:border-accent focus:ring-accent">
                                <option value="">Selecciona…</option>
                                @foreach ($tipos as $tipoOpcion)
                                    <option value="{{ $tipoOpcion->value }}">{{ $tipoOpcion->label() }}</option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('tipo')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label for="montoBase" value="Monto base (S/)" />
                            <x-text-input wire:model="montoBase" id="montoBase" type="number" step="0.01" min="0" class="mt-1 block w-full" />
                            <x-input-error :messages="$errors->get('montoBase')" class="mt-1" />
                        </div>
                    </div>

                    @if ($editandoId)
                        <label class="flex items-center gap-2 text-sm text-ink-dim">
                            <input type="checkbox" wire:model="activo" class="rounded border-border text-accent focus:ring-accent">
                            Concepto activo
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
