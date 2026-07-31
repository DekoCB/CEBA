<?php

use App\Modules\Pagos\Models\CuentaBancaria;
use App\Modules\Pagos\Services\CuentaBancariaService;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new #[Layout('layouts.app')] class extends Component
{
    use WithFileUploads;

    public bool $mostrarModal = false;

    public ?int $editandoId = null;

    public string $banco = '';

    public string $numeroCuenta = '';

    public string $cci = '';

    public string $titular = '';

    public bool $activa = true;

    public $qr = null;

    public function mount(): void
    {
        Gate::authorize('tesoreria.gestionar');
    }

    public function abrirModal(?int $cuentaId = null): void
    {
        $this->resetValidation();
        $this->editandoId = $cuentaId;
        $this->qr = null;

        if ($cuentaId) {
            $cuenta = CuentaBancaria::query()->findOrFail($cuentaId);
            $this->banco = $cuenta->banco;
            $this->numeroCuenta = $cuenta->numero_cuenta;
            $this->cci = (string) $cuenta->cci;
            $this->titular = $cuenta->titular;
            $this->activa = $cuenta->activa;
        } else {
            $this->reset(['banco', 'numeroCuenta', 'cci', 'titular']);
            $this->activa = true;
        }

        $this->mostrarModal = true;
    }

    public function guardar(CuentaBancariaService $service): void
    {
        $this->validate([
            'banco' => 'required|string|max:50',
            'numeroCuenta' => 'required|string|max:30',
            'cci' => 'nullable|string|max:30',
            'titular' => 'required|string|max:100',
            'qr' => 'nullable|image|max:2048',
        ]);

        if ($this->editandoId) {
            $service->actualizar(
                CuentaBancaria::query()->findOrFail($this->editandoId),
                $this->banco,
                $this->numeroCuenta,
                $this->cci ?: null,
                $this->titular,
                $this->activa,
                $this->qr,
            );
        } else {
            $service->crear($this->banco, $this->numeroCuenta, $this->cci ?: null, $this->titular, $this->qr);
        }

        $this->mostrarModal = false;
        session()->flash('status', 'Cuenta bancaria guardada correctamente.');
    }

    public function with(CuentaBancariaService $service): array
    {
        return [
            'cuentas' => $service->listar(),
        ];
    }
}; ?>

<div>
    <x-slot name="header">
        <h1 class="font-display text-2xl text-ink">Cuentas bancarias</h1>
        <p class="mt-1 text-sm text-ink-dim">Cuentas y QR que ven los estudiantes para pagar su mensualidad.</p>
    </x-slot>

    {{-- Ver academico/grados/index.blade.php: el botón no puede vivir en x-slot="header". --}}
    <div class="mb-4 flex justify-end">
        <button wire:click="abrirModal" class="inline-flex items-center gap-2 rounded-md bg-accent px-4 py-2 text-sm font-medium text-white hover:opacity-90">
            <x-heroicon-o-plus class="h-4 w-4" />
            Nueva cuenta
        </button>
    </div>

    @if (session('status'))
        <div class="mb-4 rounded-md border border-ok/30 bg-ok/10 px-4 py-3 text-sm text-ok">{{ session('status') }}</div>
    @endif

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        @forelse ($cuentas as $cuenta)
            <div class="rounded-lg border border-border bg-surface p-4">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="font-display text-lg text-ink">{{ $cuenta->banco }}</p>
                        <p class="text-sm text-ink-dim">{{ $cuenta->numero_cuenta }}</p>
                        @if ($cuenta->cci)
                            <p class="text-xs text-ink-faint">CCI {{ $cuenta->cci }}</p>
                        @endif
                        <p class="mt-1 text-xs text-ink-faint">{{ $cuenta->titular }}</p>
                    </div>
                    <span @class(['rounded-full px-2 py-0.5 text-xs font-medium', 'bg-ok/10 text-ok' => $cuenta->activa, 'bg-ink-faint/10 text-ink-faint' => ! $cuenta->activa])>
                        {{ $cuenta->activa ? 'Activa' : 'Inactiva' }}
                    </span>
                </div>
                <div class="mt-3 flex items-center justify-between">
                    @if ($cuenta->getFirstMedia('qr'))
                        <a href="{{ $cuenta->getFirstMediaUrl('qr') }}" target="_blank" class="text-xs font-medium text-accent hover:underline">Ver QR</a>
                    @else
                        <span class="text-xs text-ink-faint">Sin QR</span>
                    @endif
                    <button wire:click="abrirModal({{ $cuenta->id }})" class="text-sm font-medium text-accent hover:underline">Editar</button>
                </div>
            </div>
        @empty
            <p class="col-span-full py-8 text-center text-sm text-ink-faint">No hay cuentas bancarias registradas.</p>
        @endforelse
    </div>

    @if ($mostrarModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-ink/40 px-4" wire:click.self="$set('mostrarModal', false)">
            <div class="w-full max-w-md rounded-lg border border-border bg-surface p-6 shadow-lg">
                <h2 class="font-display text-lg text-ink">{{ $editandoId ? 'Editar cuenta' : 'Nueva cuenta' }}</h2>

                <form wire:submit="guardar" class="mt-4 space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <x-input-label for="banco" value="Banco" />
                            <x-text-input wire:model="banco" id="banco" class="mt-1 block w-full" />
                            <x-input-error :messages="$errors->get('banco')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label for="numeroCuenta" value="N.° de cuenta" />
                            <x-text-input wire:model="numeroCuenta" id="numeroCuenta" class="mt-1 block w-full" />
                            <x-input-error :messages="$errors->get('numeroCuenta')" class="mt-1" />
                        </div>
                    </div>

                    <div>
                        <x-input-label for="cci" value="CCI (opcional)" />
                        <x-text-input wire:model="cci" id="cci" class="mt-1 block w-full" />
                        <x-input-error :messages="$errors->get('cci')" class="mt-1" />
                    </div>

                    <div>
                        <x-input-label for="titular" value="Titular" />
                        <x-text-input wire:model="titular" id="titular" class="mt-1 block w-full" />
                        <x-input-error :messages="$errors->get('titular')" class="mt-1" />
                    </div>

                    <div>
                        <x-input-label for="qr" value="Imagen QR (opcional)" />
                        <input wire:model="qr" id="qr" type="file" accept="image/*" class="mt-1 block w-full text-sm text-ink-dim file:mr-3 file:rounded-md file:border-0 file:bg-surface-2 file:px-3 file:py-2 file:text-sm file:text-ink">
                        <x-input-error :messages="$errors->get('qr')" class="mt-1" />
                    </div>

                    @if ($editandoId)
                        <label class="flex items-center gap-2 text-sm text-ink-dim">
                            <input type="checkbox" wire:model="activa" class="rounded border-border text-accent focus:ring-accent">
                            Cuenta activa
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
