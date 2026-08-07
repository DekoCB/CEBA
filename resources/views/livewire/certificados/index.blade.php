<?php

use App\Models\User;
use App\Modules\Certificados\Models\Certificado;
use App\Modules\Certificados\Models\SolicitudCertificado;
use App\Modules\Certificados\Services\CertificadoService;
use App\Modules\Matricula\Models\Estudiante;
use App\Modules\Matricula\Models\Matricula;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public string $tab = 'solicitudes';

    // Emitir directo
    public string $terminoBusqueda = '';

    public ?int $estudianteSeleccionadoId = null;

    public string $estudianteSeleccionadoNombre = '';

    public string $matriculaId = '';

    public string $observaciones = '';

    // Rechazo de solicitud
    /** @var array<int, string> */
    public array $motivoRechazo = [];

    // Duplicados
    /** @var array<int, string> */
    public array $observacionesDuplicado = [];

    public function mount(): void
    {
        $user = Auth::user();

        abort_unless($user->hasAnyPermission(['certificados.ver', 'certificados.emitir']), 403);

        $this->tab = $user->hasPermissionTo('certificados.emitir') ? 'solicitudes' : 'historial';
    }

    public function seleccionarEstudiante(int $estudianteId, string $nombre): void
    {
        $this->estudianteSeleccionadoId = $estudianteId;
        $this->estudianteSeleccionadoNombre = $nombre;
        $this->terminoBusqueda = '';
    }

    public function emitir(CertificadoService $service): void
    {
        abort_unless(Auth::user()->hasPermissionTo('certificados.emitir'), 403);

        $this->validate([
            'estudianteSeleccionadoId' => 'required|integer|exists:estudiantes,id',
            'matriculaId' => 'nullable|integer|exists:matriculas,id',
            'observaciones' => 'nullable|string|max:500',
        ]);

        $estudiante = Estudiante::query()->findOrFail($this->estudianteSeleccionadoId);
        $matricula = $this->matriculaId !== '' ? Matricula::query()->findOrFail($this->matriculaId) : null;

        $service->emitir($estudiante, $matricula, null, $this->observaciones ?: null, Auth::user());

        $this->reset(['estudianteSeleccionadoId', 'estudianteSeleccionadoNombre', 'matriculaId', 'observaciones']);
        session()->flash('status', 'Certificado emitido.');
    }

    public function emitirDeSolicitud(int $solicitudId, CertificadoService $service): void
    {
        abort_unless(Auth::user()->hasPermissionTo('certificados.emitir'), 403);

        $solicitud = SolicitudCertificado::query()->findOrFail($solicitudId);

        $service->emitir($solicitud->estudiante, $solicitud->matricula, $solicitud, null, Auth::user());

        session()->flash('status', 'Certificado emitido a partir de la solicitud.');
    }

    public function rechazarSolicitud(int $solicitudId, CertificadoService $service): void
    {
        abort_unless(Auth::user()->hasPermissionTo('certificados.emitir'), 403);

        $this->validate(['motivoRechazo.'.$solicitudId => 'required|string|max:255']);

        $service->rechazarSolicitud(
            SolicitudCertificado::query()->findOrFail($solicitudId),
            $this->motivoRechazo[$solicitudId],
            Auth::user(),
        );

        unset($this->motivoRechazo[$solicitudId]);
        session()->flash('status', 'Solicitud rechazada.');
    }

    public function duplicar(int $certificadoId, CertificadoService $service): void
    {
        abort_unless(Auth::user()->hasPermissionTo('certificados.duplicar'), 403);

        $service->duplicar(
            Certificado::query()->findOrFail($certificadoId),
            $this->observacionesDuplicado[$certificadoId] ?? null,
            Auth::user(),
        );

        unset($this->observacionesDuplicado[$certificadoId]);
        session()->flash('status', 'Duplicado emitido.');
    }

    public function with(CertificadoService $certificados): array
    {
        $user = Auth::user();
        $puedeEmitir = $user->hasPermissionTo('certificados.emitir');
        $puedeDuplicar = $user->hasPermissionTo('certificados.duplicar');
        $puedeVerHistorial = $user->hasAnyPermission(['certificados.ver', 'certificados.emitir']);

        $resultadosBusqueda = collect();
        $matriculasDelEstudiante = collect();
        if ($puedeEmitir) {
            if ($this->terminoBusqueda !== '') {
                $resultadosBusqueda = Estudiante::query()
                    ->where(function ($query) {
                        $termino = $this->terminoBusqueda;
                        $query->where('nombres', 'like', "%{$termino}%")
                            ->orWhere('apellidos', 'like', "%{$termino}%")
                            ->orWhere('dni', 'like', "%{$termino}%");
                    })
                    ->limit(8)
                    ->get();
            }

            if ($this->estudianteSeleccionadoId) {
                $matriculasDelEstudiante = Matricula::query()
                    ->where('estudiante_id', $this->estudianteSeleccionadoId)
                    ->with(['grado', 'ciclo'])
                    ->latest('fecha_matricula')
                    ->get();
            }
        }

        return [
            'puedeEmitir' => $puedeEmitir,
            'puedeDuplicar' => $puedeDuplicar,
            'puedeVerHistorial' => $puedeVerHistorial,
            'solicitudesPendientes' => $puedeEmitir ? $certificados->solicitudesPendientes() : collect(),
            'historial' => $puedeVerHistorial ? $certificados->todos() : collect(),
            'resultadosBusqueda' => $resultadosBusqueda,
            'matriculasDelEstudiante' => $matriculasDelEstudiante,
        ];
    }
}; ?>

<div>
    <x-slot name="header">
        <h1 class="font-display text-2xl text-ink">Certificados</h1>
        <p class="mt-1 text-sm text-ink-dim">Solicitudes de estudiantes, emisión y duplicados de certificados de estudios.</p>
    </x-slot>

    @if (session('status'))
        <div class="mb-4 rounded-md border border-ok/30 bg-ok/10 px-4 py-3 text-sm text-ok">
            {{ session('status') }}
        </div>
    @endif

    <div class="mb-6 flex gap-1 border-b border-border">
        @if ($puedeEmitir)
            <button wire:click="$set('tab', 'solicitudes')" @class(['border-b-2 px-4 py-2 text-sm font-medium transition', 'border-accent text-accent' => $tab === 'solicitudes', 'border-transparent text-ink-faint hover:text-ink' => $tab !== 'solicitudes'])>
                Solicitudes
                @if ($solicitudesPendientes->isNotEmpty())
                    <span class="ml-1 rounded-full bg-warn/15 px-1.5 py-0.5 text-xs text-warn">{{ $solicitudesPendientes->count() }}</span>
                @endif
            </button>
            <button wire:click="$set('tab', 'emitir')" @class(['border-b-2 px-4 py-2 text-sm font-medium transition', 'border-accent text-accent' => $tab === 'emitir', 'border-transparent text-ink-faint hover:text-ink' => $tab !== 'emitir'])>
                Emitir certificado
            </button>
        @endif
        @if ($puedeVerHistorial)
            <button wire:click="$set('tab', 'historial')" @class(['border-b-2 px-4 py-2 text-sm font-medium transition', 'border-accent text-accent' => $tab === 'historial', 'border-transparent text-ink-faint hover:text-ink' => $tab !== 'historial'])>
                Historial
            </button>
        @endif
    </div>

    {{-- Solicitudes pendientes --}}
    @if ($tab === 'solicitudes' && $puedeEmitir)
        <div class="divide-y divide-border rounded-lg border border-border bg-surface">
            @forelse ($solicitudesPendientes as $solicitud)
                <div class="px-4 py-4 text-sm">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-ink">{{ $solicitud->estudiante->nombreCompleto() }}</p>
                            <p class="text-xs text-ink-faint">
                                {{ $solicitud->motivo }}
                                @if ($solicitud->matricula)
                                    · {{ $solicitud->matricula->grado->nombre }} · {{ $solicitud->matricula->ciclo->nombre }}
                                @endif
                            </p>
                            <p class="text-xs text-ink-faint">Solicitado el {{ $solicitud->created_at->format('d/m/Y') }}</p>
                        </div>
                    </div>

                    <div class="mt-3 flex flex-wrap items-center gap-2">
                        <x-secondary-button type="button" wire:click="emitirDeSolicitud({{ $solicitud->id }})" wire:confirm="¿Emitir el certificado para esta solicitud?">
                            Emitir certificado
                        </x-secondary-button>
                        <input
                            type="text"
                            wire:model="motivoRechazo.{{ $solicitud->id }}"
                            placeholder="Motivo de rechazo…"
                            class="w-52 rounded-md border-border bg-surface text-xs text-ink placeholder:text-ink-faint focus:border-accent focus:ring-accent"
                        >
                        <button type="button" wire:click="rechazarSolicitud({{ $solicitud->id }})" class="text-xs font-medium text-danger hover:underline">Rechazar</button>
                        <x-input-error :messages="$errors->get('motivoRechazo.'.$solicitud->id)" class="mt-0" />
                    </div>
                </div>
            @empty
                <p class="px-4 py-8 text-center text-sm text-ink-faint">No hay solicitudes pendientes.</p>
            @endforelse
        </div>
    @endif

    {{-- Emitir certificado directo --}}
    @if ($tab === 'emitir' && $puedeEmitir)
        <div class="max-w-xl space-y-4 rounded-lg border border-border bg-surface p-6">
            <div>
                <x-input-label value="Estudiante" />
                @if ($estudianteSeleccionadoId)
                    <div class="mt-1 flex items-center justify-between rounded-md bg-accent-soft px-3 py-2 text-sm text-accent">
                        {{ $estudianteSeleccionadoNombre }}
                        <button type="button" wire:click="$set('estudianteSeleccionadoId', null)" class="text-xs underline">Cambiar</button>
                    </div>
                @else
                    <x-text-input wire:model.live.debounce.300ms="terminoBusqueda" class="mt-1 block w-full" placeholder="Buscar por nombre, apellido o DNI…" />
                    @if ($resultadosBusqueda->isNotEmpty())
                        <div class="mt-1 divide-y divide-border rounded-md border border-border bg-surface">
                            @foreach ($resultadosBusqueda as $estudiante)
                                <button
                                    type="button"
                                    wire:click="seleccionarEstudiante({{ $estudiante->id }}, '{{ addslashes($estudiante->nombreCompleto()) }}')"
                                    class="block w-full px-3 py-2 text-left text-sm hover:bg-surface-2"
                                >
                                    {{ $estudiante->nombreCompleto() }} <span class="text-ink-faint">· {{ $estudiante->dni }}</span>
                                </button>
                            @endforeach
                        </div>
                    @endif
                @endif
                <x-input-error :messages="$errors->get('estudianteSeleccionadoId')" class="mt-1" />
            </div>

            @if ($estudianteSeleccionadoId)
                <div>
                    <x-input-label for="matriculaId" value="Matrícula (opcional)" />
                    <x-select-input
                        wire:model="matriculaId"
                        id="matriculaId"
                        class="mt-1 block w-full"
                        :options="collect($matriculasDelEstudiante)->mapWithKeys(fn ($matricula) => [$matricula->id => $matricula->grado->nombre.' · '.$matricula->ciclo->nombre])->prepend('Sin vincular a una matrícula específica', '')"
                    />
                    <x-input-error :messages="$errors->get('matriculaId')" class="mt-1" />
                </div>
            @endif

            <div>
                <x-input-label for="observaciones" value="Observaciones (opcional)" />
                <textarea wire:model="observaciones" id="observaciones" rows="2" class="mt-1 block w-full rounded-md border-border bg-surface text-sm text-ink focus:border-accent focus:ring-accent"></textarea>
                <x-input-error :messages="$errors->get('observaciones')" class="mt-1" />
            </div>

            <div class="flex justify-end">
                <x-primary-button type="button" wire:click="emitir">Emitir certificado</x-primary-button>
            </div>
        </div>
    @endif

    {{-- Historial --}}
    @if ($tab === 'historial' && $puedeVerHistorial)
        <div class="divide-y divide-border rounded-lg border border-border bg-surface">
            @forelse ($historial as $certificado)
                <div class="flex flex-wrap items-center justify-between gap-4 px-4 py-3 text-sm">
                    <div>
                        <p class="text-ink">
                            {{ $certificado->estudiante->nombreCompleto() }}
                            @if ($certificado->es_duplicado)
                                <span class="ml-1 rounded-full bg-warn/10 px-2 py-0.5 text-xs text-warn">Duplicado</span>
                            @endif
                        </p>
                        <p class="text-xs text-ink-faint">
                            N.° {{ $certificado->numero }} · código {{ $certificado->codigo_verificacion }}
                            @if ($certificado->matricula)
                                · {{ $certificado->matricula->grado->nombre }}
                            @endif
                            · {{ $certificado->fecha_emision->format('d/m/Y') }}
                        </p>
                    </div>
                    <div class="flex items-center gap-3">
                        @if ($certificado->getFirstMedia('pdf'))
                            <a href="{{ $certificado->getFirstMediaUrl('pdf') }}" target="_blank" class="text-xs font-medium text-accent hover:underline">Ver PDF</a>
                        @endif
                        @if ($puedeDuplicar)
                            <button type="button" wire:click="duplicar({{ $certificado->id }})" wire:confirm="¿Emitir un duplicado de este certificado?" class="text-xs font-medium text-ink-dim hover:underline">
                                Duplicar
                            </button>
                        @endif
                    </div>
                </div>
            @empty
                <p class="px-4 py-8 text-center text-sm text-ink-faint">No hay certificados emitidos.</p>
            @endforelse
        </div>
    @endif
</div>
