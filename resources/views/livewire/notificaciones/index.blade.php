<?php

use App\Modules\Academico\Models\Ciclo;
use App\Modules\Academico\Models\Grado;
use App\Modules\Notificaciones\Models\PlantillaWhatsapp;
use App\Modules\Notificaciones\Services\CampaniaWhatsappService;
use App\Modules\Notificaciones\Services\PlantillaService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public string $tab = 'nueva';

    public string $nombre = '';

    public string $plantillaId = '';

    public string $gradoId = '';

    public string $cicloId = '';

    public bool $soloConDeuda = false;

    public function mount(): void
    {
        Gate::authorize('whatsapp.enviar');
    }

    /**
     * @return array{grado_id?: int, ciclo_id?: int, solo_con_deuda?: bool}
     */
    private function segmentoActual(): array
    {
        $segmento = [];

        if ($this->gradoId !== '') {
            $segmento['grado_id'] = (int) $this->gradoId;
        }

        if ($this->cicloId !== '') {
            $segmento['ciclo_id'] = (int) $this->cicloId;
        }

        if ($this->soloConDeuda) {
            $segmento['solo_con_deuda'] = true;
        }

        return $segmento;
    }

    public function enviarCampania(CampaniaWhatsappService $service): void
    {
        $this->validate([
            'nombre' => 'required|string|max:100',
            'plantillaId' => 'required|integer|exists:plantillas_whatsapp,id',
        ]);

        $service->crearYEnviar(
            $this->nombre,
            PlantillaWhatsapp::query()->findOrFail($this->plantillaId),
            $this->segmentoActual(),
            Auth::user(),
        );

        $this->reset(['nombre', 'plantillaId', 'gradoId', 'cicloId', 'soloConDeuda']);
        $this->tab = 'historial';
        session()->flash('status', 'Campaña creada. Los mensajes se están enviando.');
    }

    public function with(CampaniaWhatsappService $campanias, PlantillaService $plantillas): array
    {
        return [
            'plantillasActivas' => $plantillas->activas(),
            'grados' => Grado::query()->orderBy('orden')->get(),
            'ciclos' => Ciclo::query()->latest('fecha_inicio')->get(),
            'destinatariosPrevistos' => $campanias->resolverDestinatarios($this->segmentoActual())->count(),
            'campanias' => $campanias->todas(),
        ];
    }
}; ?>

<div>
    <x-slot name="header">
        <h1 class="font-display text-2xl text-ink">Notificaciones</h1>
        <p class="mt-1 text-sm text-ink-dim">Envíos masivos de WhatsApp segmentados por grado, ciclo o estado de deuda.</p>
    </x-slot>

    @if (session('status'))
        <div class="mb-4 rounded-md border border-ok/30 bg-ok/10 px-4 py-3 text-sm text-ok">{{ session('status') }}</div>
    @endif

    <div class="mb-6 flex gap-1 border-b border-border">
        <button wire:click="$set('tab', 'nueva')" @class(['border-b-2 px-4 py-2 text-sm font-medium transition', 'border-accent text-accent' => $tab === 'nueva', 'border-transparent text-ink-faint hover:text-ink' => $tab !== 'nueva'])>
            Nueva campaña
        </button>
        <button wire:click="$set('tab', 'historial')" @class(['border-b-2 px-4 py-2 text-sm font-medium transition', 'border-accent text-accent' => $tab === 'historial', 'border-transparent text-ink-faint hover:text-ink' => $tab !== 'historial'])>
            Historial
        </button>
        <a href="{{ route('notificaciones.plantillas') }}" wire:navigate class="ml-auto self-center text-sm font-medium text-accent hover:underline">
            Gestionar plantillas →
        </a>
    </div>

    {{-- Nueva campaña --}}
    @if ($tab === 'nueva')
        <div class="max-w-xl space-y-4 rounded-lg border border-border bg-surface p-6">
            <div>
                <x-input-label for="nombre" value="Nombre de la campaña" />
                <x-text-input wire:model="nombre" id="nombre" class="mt-1 block w-full" placeholder="Ej. Recordatorio de cuotas — julio" />
                <x-input-error :messages="$errors->get('nombre')" class="mt-1" />
            </div>

            <div>
                <x-input-label for="plantillaId" value="Plantilla" />
                <select wire:model="plantillaId" id="plantillaId" class="mt-1 block w-full rounded-md border-border bg-surface text-sm text-ink focus:border-accent focus:ring-accent">
                    <option value="">Selecciona…</option>
                    @foreach ($plantillasActivas as $plantilla)
                        <option value="{{ $plantilla->id }}">{{ $plantilla->nombre }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('plantillaId')" class="mt-1" />
                @if ($plantillasActivas->isEmpty())
                    <p class="mt-1 text-xs text-warn">No hay plantillas activas. <a href="{{ route('notificaciones.plantillas') }}" wire:navigate class="underline">Crea una primero</a>.</p>
                @endif
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <x-input-label for="gradoId" value="Grado (opcional)" />
                    <select wire:model.live="gradoId" id="gradoId" class="mt-1 block w-full rounded-md border-border bg-surface text-sm text-ink focus:border-accent focus:ring-accent">
                        <option value="">Todos los grados</option>
                        @foreach ($grados as $grado)
                            <option value="{{ $grado->id }}">{{ $grado->nombre }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <x-input-label for="cicloId" value="Ciclo (opcional)" />
                    <select wire:model.live="cicloId" id="cicloId" class="mt-1 block w-full rounded-md border-border bg-surface text-sm text-ink focus:border-accent focus:ring-accent">
                        <option value="">Todos los ciclos</option>
                        @foreach ($ciclos as $ciclo)
                            <option value="{{ $ciclo->id }}">{{ $ciclo->nombre }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <label class="flex items-center gap-2 text-sm text-ink-dim">
                <input type="checkbox" wire:model.live="soloConDeuda" class="rounded border-border text-accent focus:ring-accent">
                Solo estudiantes con deuda bloqueante activa
            </label>

            <div class="rounded-md bg-surface-2 px-3 py-2 text-sm text-ink-dim">
                Este segmento alcanza a <span class="font-semibold text-ink">{{ $destinatariosPrevistos }}</span>
                {{ $destinatariosPrevistos === 1 ? 'estudiante' : 'estudiantes' }}.
            </div>

            <div class="flex justify-end">
                <x-primary-button type="button" wire:click="enviarCampania" wire:confirm="¿Enviar esta campaña a {{ $destinatariosPrevistos }} destinatarios?">
                    Enviar campaña
                </x-primary-button>
            </div>
        </div>
    @endif

    {{-- Historial --}}
    @if ($tab === 'historial')
        <div class="divide-y divide-border rounded-lg border border-border bg-surface">
            @forelse ($campanias as $campania)
                <div class="px-4 py-3 text-sm" wire:key="campania-{{ $campania->id }}">
                    <div class="flex items-center justify-between gap-4">
                        <div>
                            <p class="text-ink">{{ $campania->nombre }}</p>
                            <p class="text-xs text-ink-faint">
                                {{ $campania->plantilla->nombre }} · creada por {{ $campania->creador->name }}
                                · {{ $campania->created_at->format('d/m/Y H:i') }}
                            </p>
                        </div>
                        <span @class([
                            'rounded-full px-2 py-0.5 text-xs',
                            'bg-ok/10 text-ok' => $campania->estado->value === 'completada',
                            'bg-warn/10 text-warn' => $campania->estado->value === 'enviando',
                            'bg-ink-faint/10 text-ink-faint' => $campania->estado->value === 'borrador',
                        ])>{{ $campania->estado->label() }}</span>
                    </div>
                    <p class="mt-1 text-xs text-ink-faint">
                        {{ $campania->enviados }} enviados · {{ $campania->fallidos }} fallidos
                        de {{ $campania->total_destinatarios }} destinatarios
                    </p>
                </div>
            @empty
                <p class="px-4 py-8 text-center text-sm text-ink-faint">No se han enviado campañas todavía.</p>
            @endforelse
        </div>
    @endif
</div>
