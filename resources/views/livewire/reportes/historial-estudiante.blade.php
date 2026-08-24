<?php

use App\Modules\Reportes\Services\HistorialEstudianteService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public string $dni = '';

    public ?string $dniBuscado = null;

    public function mount(): void
    {
        abort_unless(Auth::user()->hasPermissionTo('reportes.historial_estudiante'), 403);
    }

    public function buscar(): void
    {
        $this->validate(['dni' => 'required|string|min:8|max:12']);

        $this->dniBuscado = $this->dni;
    }

    public function exportarPdf(HistorialEstudianteService $servicio)
    {
        abort_unless(Auth::user()->hasPermissionTo('reportes.exportar'), 403);

        $historial = $this->dniBuscado !== null ? $servicio->porDni($this->dniBuscado) : null;

        abort_if($historial === null, 404);

        // Ver reportes/index.blade.php: streamDownload() en vez de
        // Pdf::loadView(...)->download() para que Livewire reconozca la
        // respuesta como descarga de archivo.
        return response()->streamDownload(
            fn () => print (Pdf::loadView('pdf.historial-estudiante', $historial)->output()),
            "historial-{$historial['estudiante']->dni}.pdf",
            ['Content-Type' => 'application/pdf'],
        );
    }

    public function with(HistorialEstudianteService $servicio): array
    {
        return [
            'historial' => $this->dniBuscado !== null ? $servicio->porDni($this->dniBuscado) : null,
            'puedeExportar' => Auth::user()->hasPermissionTo('reportes.exportar'),
        ];
    }
}; ?>

<div>
    <x-slot name="header">
        <a href="{{ route('reportes.index') }}" wire:navigate class="text-sm text-ink-faint hover:text-ink">← Reportes</a>
        <h1 class="mt-1 font-display text-2xl text-ink">Historial del estudiante</h1>
        <p class="mt-1 text-sm text-ink-dim">Busca por DNI para ver grados cursados, pagos, documentos y notas en un solo lugar.</p>
    </x-slot>

    <div class="space-y-6">
        <form wire:submit="buscar" class="flex flex-wrap items-end gap-3 rounded-lg border border-border bg-surface p-4">
            <div>
                <x-input-label for="dni" value="DNI del estudiante" />
                <x-text-input wire:model="dni" id="dni" class="mt-1 block w-48" placeholder="12345678" />
                <x-input-error :messages="$errors->get('dni')" class="mt-1" />
            </div>
            <x-primary-button type="submit">Buscar</x-primary-button>

            @if ($historial && $puedeExportar)
                <x-secondary-button type="button" wire:click="exportarPdf" class="ml-auto">Exportar PDF</x-secondary-button>
            @endif
        </form>

        @if ($dniBuscado === null)
            <p class="rounded-lg border border-border bg-surface px-4 py-8 text-center text-sm text-ink-faint">
                Ingresa un DNI para ver el historial del estudiante.
            </p>
        @elseif (! $historial)
            <p class="rounded-lg border border-border bg-surface px-4 py-8 text-center text-sm text-ink-faint">
                No se encontró ningún estudiante con el DNI «{{ $dniBuscado }}».
            </p>
        @else
            @php $estudiante = $historial['estudiante']; @endphp

            <div class="rounded-lg border border-border bg-surface p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="font-display text-lg text-ink">{{ $estudiante->nombreCompleto() }}</h2>
                        <p class="text-sm text-ink-dim">DNI {{ $estudiante->dni }} · {{ $estudiante->fecha_nacimiento->format('d/m/Y') }} · {{ $estudiante->es_menor_edad ? 'Menor de edad' : 'Mayor de edad' }}</p>
                    </div>
                    <span @class([
                        'rounded-full px-2 py-0.5 text-xs font-medium',
                        'bg-ok/10 text-ok' => $estudiante->estado->value === 'activo',
                        'bg-ink-faint/10 text-ink-faint' => $estudiante->estado->value !== 'activo',
                    ])>
                        {{ $estudiante->estado->label() }}
                    </span>
                </div>
            </div>

            <div class="rounded-lg border border-border bg-surface p-6">
                <h2 class="text-sm font-semibold text-ink">Grados cursados</h2>
                <div class="mt-4 divide-y divide-border">
                    @forelse ($historial['matriculas'] as $matricula)
                        <div class="flex items-center justify-between py-3 text-sm">
                            <div>
                                <p class="text-ink">{{ $matricula->grado->nombre }} · {{ $matricula->ciclo->nombre }}</p>
                                <p class="text-ink-faint">
                                    Matriculado el {{ $matricula->fecha_matricula->format('d/m/Y') }}
                                    @if ($matricula->fecha_fin_estudio)
                                        · Fin de estudios: {{ $matricula->fecha_fin_estudio->format('d/m/Y') }}
                                    @endif
                                </p>
                            </div>
                            <span @class([
                                'rounded-full px-2 py-0.5 text-xs font-medium',
                                'bg-ok/10 text-ok' => $matricula->estado->value === 'aprobada',
                                'bg-warn/10 text-warn' => in_array($matricula->estado->value, ['pendiente', 'observada'], true),
                                'bg-danger/10 text-danger' => $matricula->estado->value === 'anulada',
                            ])>
                                {{ $matricula->estado->label() }}
                            </span>
                        </div>
                    @empty
                        <p class="py-4 text-sm text-ink-faint">Sin matrículas registradas.</p>
                    @endforelse
                </div>
            </div>

            <div class="rounded-lg border border-border bg-surface p-6">
                <h2 class="text-sm font-semibold text-ink">Situación de pagos</h2>
                <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <div class="rounded-md bg-ok/10 p-3">
                        <p class="text-xs font-medium uppercase tracking-wide text-ok">Pagado</p>
                        <p class="mt-1 text-lg font-semibold text-ink">S/ {{ number_format($historial['resumenPagos']['totalPagado'], 2) }}</p>
                    </div>
                    <div class="rounded-md bg-warn/10 p-3">
                        <p class="text-xs font-medium uppercase tracking-wide text-warn">Pendiente</p>
                        <p class="mt-1 text-lg font-semibold text-ink">S/ {{ number_format($historial['resumenPagos']['totalPendiente'], 2) }}</p>
                    </div>
                    <div class="rounded-md bg-surface-2 p-3">
                        <p class="text-xs font-medium uppercase tracking-wide text-ink-faint">Exonerado</p>
                        <p class="mt-1 text-lg font-semibold text-ink">S/ {{ number_format($historial['resumenPagos']['totalExonerado'], 2) }}</p>
                    </div>
                </div>

                @if ($historial['resumenPagos']['cuotasVencidas']->isNotEmpty())
                    <h3 class="mt-4 text-xs font-semibold uppercase tracking-wide text-danger">Cuotas vencidas</h3>
                    <div class="mt-2 divide-y divide-border">
                        @foreach ($historial['resumenPagos']['cuotasVencidas'] as $cuota)
                            <div class="flex items-center justify-between py-2 text-sm">
                                <span class="text-ink-dim">Cuota {{ $cuota->numero }} · {{ $cuota->planPago->matricula?->grado->nombre }} · {{ $cuota->planPago->matricula?->ciclo->nombre }}</span>
                                <span class="text-danger">S/ {{ number_format((float) $cuota->monto, 2) }} · venció {{ $cuota->fecha_vencimiento->format('d/m/Y') }}</span>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            <div class="rounded-lg border border-border bg-surface p-6">
                <h2 class="text-sm font-semibold text-ink">Documentos</h2>

                <h3 class="mt-4 text-xs font-semibold uppercase tracking-wide text-ink-faint">Subidos al matricularse</h3>
                <div class="mt-2 divide-y divide-border">
                    @forelse ($historial['documentosSubidos'] as $documento)
                        <div class="flex items-center justify-between py-2 text-sm">
                            <span class="text-ink">{{ $documento->tipo->label() }}</span>
                            <span @class(['rounded-full px-2 py-0.5 text-xs font-medium', 'bg-ok/10 text-ok' => $documento->verificado, 'bg-warn/10 text-warn' => ! $documento->verificado])>
                                {{ $documento->verificado ? 'Verificado' : 'Pendiente' }}
                            </span>
                        </div>
                    @empty
                        <p class="py-2 text-sm text-ink-faint">No se han subido documentos.</p>
                    @endforelse
                </div>

                <h3 class="mt-4 text-xs font-semibold uppercase tracking-wide text-ink-faint">Emitidos por CEBA</h3>
                <div class="mt-2 divide-y divide-border">
                    @forelse ($historial['documentosEmitidos'] as $certificado)
                        <div class="flex items-center justify-between py-2 text-sm">
                            <span class="text-ink">{{ $certificado->tipo->label() }} · N.° {{ $certificado->numero }}</span>
                            <span class="text-ink-faint">{{ $certificado->fecha_emision->format('d/m/Y') }} · {{ $certificado->entregado_en ? 'Entregado' : 'Sin entregar' }}</span>
                        </div>
                    @empty
                        <p class="py-2 text-sm text-ink-faint">No se han emitido certificados ni constancias.</p>
                    @endforelse
                </div>

                <h3 class="mt-4 text-xs font-semibold uppercase tracking-wide text-ink-faint">Libretas generadas</h3>
                <div class="mt-2 divide-y divide-border">
                    @forelse ($historial['libretas'] as $libreta)
                        <div class="flex items-center justify-between py-2 text-sm">
                            <span class="text-ink">{{ $libreta->ciclo->nombre }}</span>
                            <span class="text-ink-faint">{{ $libreta->generado_en?->format('d/m/Y') }} · {{ $libreta->entregado_en ? 'Entregada' : 'Sin entregar' }}</span>
                        </div>
                    @empty
                        <p class="py-2 text-sm text-ink-faint">No se han generado libretas.</p>
                    @endforelse
                </div>
            </div>

            <div class="rounded-lg border border-border bg-surface p-6">
                <h2 class="text-sm font-semibold text-ink">Exámenes y notas</h2>

                @if ($historial['examenesUbicacion']->isNotEmpty())
                    <h3 class="mt-4 text-xs font-semibold uppercase tracking-wide text-ink-faint">Exámenes de ubicación</h3>
                    <div class="mt-2 divide-y divide-border">
                        @foreach ($historial['examenesUbicacion'] as $examen)
                            <div class="py-2 text-sm">
                                <p class="text-ink">{{ $examen->fecha->format('d/m/Y') }} · S/ {{ number_format((float) $examen->costo, 2) }}</p>
                                <p class="text-ink-faint">Resultado: {{ $examen->resultado ?? '—' }} @if ($examen->gradoAsignado) · Grado asignado: {{ $examen->gradoAsignado->nombre }} @endif</p>
                            </div>
                        @endforeach
                    </div>
                @endif

                <h3 class="mt-4 text-xs font-semibold uppercase tracking-wide text-ink-faint">Notas por ciclo</h3>
                @forelse ($historial['notasPorCiclo'] as $entrada)
                    <p class="mt-3 text-sm font-medium text-ink">{{ $entrada['ciclo']->nombre }}</p>
                    <div class="mt-1 grid grid-cols-1 gap-1 sm:grid-cols-2">
                        @foreach ($entrada['cursos'] as $curso)
                            <div class="flex items-center justify-between rounded-md bg-surface-2 px-3 py-1.5 text-xs">
                                <span class="text-ink-dim">{{ $curso['nombre'] }}</span>
                                <span class="text-ink">{{ $curso['promedio'] !== null ? number_format($curso['promedio'], 1) : '—' }} @if ($curso['letra']) ({{ $curso['letra'] }}) @endif</span>
                            </div>
                        @endforeach
                    </div>
                @empty
                    <p class="mt-2 text-sm text-ink-faint">Sin notas registradas todavía.</p>
                @endforelse
            </div>
        @endif
    </div>
</div>
