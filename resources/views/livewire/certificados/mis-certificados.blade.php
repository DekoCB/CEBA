<?php

use App\Modules\Certificados\Services\CertificadoService;
use App\Modules\Matricula\Models\Matricula;
use App\Modules\Pagos\Services\BloqueoAccesoService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public string $matriculaId = '';

    public string $motivo = '';

    public function mount(): void
    {
        $user = Auth::user();

        abort_unless($user->hasPermissionTo('certificados.solicitar') && $user->estudiante, 403);
    }

    public function solicitar(CertificadoService $service, BloqueoAccesoService $bloqueos): void
    {
        $estudiante = Auth::user()->estudiante;
        abort_unless($estudiante !== null, 403);
        abort_if($bloqueos->tieneCuotasVencidasEnCicloActual($estudiante), 403);

        $this->validate([
            'matriculaId' => 'nullable|integer|exists:matriculas,id',
            'motivo' => 'required|string|max:500',
        ]);

        $matricula = $this->matriculaId !== '' ? Matricula::query()->findOrFail($this->matriculaId) : null;

        $service->solicitar($estudiante, $matricula, $this->motivo);

        $this->reset(['matriculaId', 'motivo']);
        session()->flash('status', 'Solicitud enviada. Te avisaremos cuando tu certificado esté listo.');
    }

    public function with(CertificadoService $certificados, BloqueoAccesoService $bloqueos): array
    {
        $estudiante = Auth::user()->estudiante;

        return [
            'misCertificados' => $certificados->misCertificados($estudiante),
            'misSolicitudes' => $certificados->misSolicitudes($estudiante),
            'matriculas' => Matricula::query()
                ->where('estudiante_id', $estudiante->id)
                ->with(['grado', 'ciclo'])
                ->latest('fecha_matricula')
                ->get(),
            'tieneDeudaCicloActual' => $bloqueos->tieneCuotasVencidasEnCicloActual($estudiante),
        ];
    }
}; ?>

<div class="max-w-3xl space-y-6">
    <x-slot name="header">
        <h1 class="font-display text-2xl text-ink">Mis certificados</h1>
        <p class="mt-1 text-sm text-ink-dim">Solicita un certificado de estudios y revisa los que ya se te emitieron.</p>
    </x-slot>

    @if (session('status'))
        <div class="rounded-md border border-ok/30 bg-ok/10 px-4 py-3 text-sm text-ok">
            {{ session('status') }}
        </div>
    @endif

    <div class="rounded-lg border border-border bg-surface p-6">
        <h2 class="text-sm font-semibold text-ink">Solicitar certificado</h2>

        @if ($tieneDeudaCicloActual)
            <div class="mt-4 rounded-lg border border-danger/30 bg-danger/10 px-4 py-4 text-sm text-danger">
                <p class="font-medium">No puedes solicitar un certificado por ahora.</p>
                <p class="mt-1">Tienes cuotas vencidas del ciclo actual. Regulariza tu deuda en
                    <a href="{{ route('pagos.mi-cuenta') }}" wire:navigate class="underline">Mi estado de cuenta</a>
                    o comunícate con Cobranza para un compromiso de pago.</p>
            </div>
        @else
            <form wire:submit="solicitar" class="mt-4 space-y-4">
                <div>
                    <x-input-label for="matriculaId" value="Matrícula (opcional)" />
                    <x-select-input
                        wire:model="matriculaId"
                        id="matriculaId"
                        class="mt-1 block w-full"
                        :options="collect($matriculas)->mapWithKeys(fn ($matricula) => [$matricula->id => $matricula->grado->nombre.' · '.$matricula->ciclo->nombre])->prepend('Sin vincular a una matrícula específica', '')"
                    />
                    <x-input-error :messages="$errors->get('matriculaId')" class="mt-1" />
                </div>
                <div>
                    <x-input-label for="motivo" value="Motivo de la solicitud" />
                    <textarea wire:model="motivo" id="motivo" rows="2" placeholder="Ej. trámite laboral, continuación de estudios…" class="mt-1 block w-full rounded-md border-border bg-surface text-sm text-ink focus:border-accent focus:ring-accent"></textarea>
                    <x-input-error :messages="$errors->get('motivo')" class="mt-1" />
                </div>
                <div class="flex justify-end">
                    <x-primary-button type="submit">Solicitar</x-primary-button>
                </div>
            </form>
        @endif
    </div>

    <div class="rounded-lg border border-border bg-surface p-6">
        <h2 class="text-sm font-semibold text-ink">Mis certificados emitidos</h2>
        <div class="mt-4 divide-y divide-border">
            @forelse ($misCertificados as $certificado)
                <div class="flex items-center justify-between py-3 text-sm">
                    <div>
                        <p class="text-ink">
                            N.° {{ $certificado->numero }}
                            @if ($certificado->es_duplicado)
                                <span class="ml-1 rounded-full bg-warn/10 px-2 py-0.5 text-xs text-warn">Duplicado</span>
                            @endif
                        </p>
                        <p class="text-xs text-ink-faint">
                            @if ($certificado->matricula)
                                {{ $certificado->matricula->grado->nombre }} ·
                            @endif
                            emitido el {{ $certificado->fecha_emision->format('d/m/Y') }}
                        </p>
                    </div>
                    @if ($certificado->getFirstMedia('pdf'))
                        <a href="{{ $certificado->getFirstMediaUrl('pdf') }}" target="_blank" class="text-xs font-medium text-accent hover:underline">Ver PDF</a>
                    @endif
                </div>
            @empty
                <p class="py-4 text-sm text-ink-faint">Todavía no tienes certificados emitidos.</p>
            @endforelse
        </div>
    </div>

    <div class="rounded-lg border border-border bg-surface p-6">
        <h2 class="text-sm font-semibold text-ink">Mis solicitudes</h2>
        <div class="mt-4 divide-y divide-border">
            @forelse ($misSolicitudes as $solicitud)
                <div class="py-3 text-sm">
                    <div class="flex items-center justify-between gap-4">
                        <p class="text-ink">{{ $solicitud->motivo }}</p>
                        <span @class([
                            'rounded-full px-2 py-0.5 text-xs',
                            'bg-ok/10 text-ok' => $solicitud->estado->value === 'atendida',
                            'bg-warn/10 text-warn' => $solicitud->estado->value === 'pendiente',
                            'bg-danger/10 text-danger' => $solicitud->estado->value === 'rechazada',
                        ])>{{ $solicitud->estado->label() }}</span>
                    </div>
                    <p class="text-xs text-ink-faint">Solicitado el {{ $solicitud->created_at->format('d/m/Y') }}</p>
                    @if ($solicitud->estado->value === 'rechazada' && $solicitud->motivo_rechazo)
                        <p class="text-xs text-danger">{{ $solicitud->motivo_rechazo }}</p>
                    @endif
                </div>
            @empty
                <p class="py-4 text-sm text-ink-faint">No has solicitado certificados todavía.</p>
            @endforelse
        </div>
    </div>
</div>
