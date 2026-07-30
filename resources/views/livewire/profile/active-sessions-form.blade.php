<?php

use App\Modules\Identidad\Services\SessionControlService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Livewire\Volt\Component;

new class extends Component
{
    public function revocar(string $sesionId, SessionControlService $service): void
    {
        $service->revocar($sesionId);
    }

    public function revocarTodas(SessionControlService $service): void
    {
        $service->revocarTodasMenosActual(auth()->user(), session()->getId());

        session()->flash('status', 'sessions-revoked');
    }

    public function with(SessionControlService $service): array
    {
        return [
            'sesiones' => $service->sesionesDe(auth()->user(), session()->getId()),
        ];
    }
}; ?>

<section>
    <header class="flex items-start justify-between">
        <div>
            <h2 class="text-sm font-semibold text-ink">Sesiones activas</h2>
            <p class="mt-1 text-sm text-ink-dim">
                Los dispositivos donde tu cuenta ha iniciado sesión actualmente.
            </p>
        </div>
        <button wire:click="revocarTodas" wire:confirm="¿Cerrar todas las demás sesiones?" class="text-sm font-medium text-danger hover:underline">
            Cerrar las demás
        </button>
    </header>

    @if (session('status') === 'sessions-revoked')
        <p class="mt-2 text-sm text-ok">Se cerraron las demás sesiones.</p>
    @endif

    <div class="mt-4 divide-y divide-border">
        @foreach ($sesiones as $sesion)
            <div class="flex items-center justify-between py-3 text-sm" wire:key="sesion-{{ $sesion->id }}">
                <div>
                    <p class="text-ink">
                        {{ $sesion->ipAddress ?? 'IP desconocida' }}
                        @if ($sesion->esActual)
                            <span class="ml-2 rounded-full bg-accent-soft px-2 py-0.5 text-xs text-accent">Este dispositivo</span>
                        @endif
                    </p>
                    <p class="text-xs text-ink-faint">
                        {{ Str::limit($sesion->userAgent ?? 'Agente desconocido', 60) }}
                        · última actividad {{ Carbon::createFromTimestamp($sesion->lastActivity)->diffForHumans() }}
                    </p>
                </div>
                @unless ($sesion->esActual)
                    <button wire:click="revocar('{{ $sesion->id }}')" wire:confirm="¿Cerrar esta sesión?" class="text-sm font-medium text-danger hover:underline">
                        Cerrar
                    </button>
                @endunless
            </div>
        @endforeach
    </div>
</section>
