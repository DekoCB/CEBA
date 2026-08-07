<?php

use App\Modules\Reportes\Exports\ReporteExport;
use App\Modules\Reportes\Services\ReporteService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Facades\Excel;

new #[Layout('layouts.app')] class extends Component
{
    /**
     * @var array<string, array{permiso: string, label: string}>
     */
    private const TIPOS = [
        'matricula' => ['permiso' => 'reportes.matricula', 'label' => 'Matrícula'],
        'academico' => ['permiso' => 'reportes.academicos', 'label' => 'Académico'],
        'financiero' => ['permiso' => 'reportes.financieros', 'label' => 'Financiero'],
        'certificados' => ['permiso' => 'reportes.certificados', 'label' => 'Certificados'],
        'operativo' => ['permiso' => 'reportes.operativos', 'label' => 'Operativo (asistencia)'],
        'propio' => ['permiso' => 'reportes.propios', 'label' => 'Mis evaluaciones'],
    ];

    public string $tipo = '';

    public string $desde = '';

    public string $hasta = '';

    public function mount(): void
    {
        $user = Auth::user();

        abort_unless($user->hasAnyPermission(array_column(self::TIPOS, 'permiso')), 403);

        $this->tipo = collect(self::TIPOS)
            ->keys()
            ->first(fn (string $tipo) => $user->hasPermissionTo(self::TIPOS[$tipo]['permiso'])) ?? '';
    }

    public function exportarExcel(ReporteService $reportes)
    {
        return $this->exportar($reportes, 'excel');
    }

    public function exportarCsv(ReporteService $reportes)
    {
        return $this->exportar($reportes, 'csv');
    }

    public function exportarPdf(ReporteService $reportes)
    {
        return $this->exportar($reportes, 'pdf');
    }

    private function exportar(ReporteService $reportes, string $formato)
    {
        abort_unless(Auth::user()->hasPermissionTo('reportes.exportar'), 403);

        ['columnas' => $columnas, 'filas' => $filas] = $this->generarReporte($reportes);
        $nombreArchivo = "reporte-{$this->tipo}-".now()->format('Y-m-d');

        return match ($formato) {
            'excel' => Excel::download(new ReporteExport($columnas, $filas), "{$nombreArchivo}.xlsx"),
            'csv' => Excel::download(new ReporteExport($columnas, $filas), "{$nombreArchivo}.csv", ExcelFormat::CSV),
            'pdf' => Pdf::loadView('pdf.reporte', [
                'titulo' => self::TIPOS[$this->tipo]['label'] ?? 'Reporte',
                'columnas' => $columnas,
                'filas' => $filas,
            ])->download("{$nombreArchivo}.pdf"),
        };
    }

    /**
     * @return array{columnas: list<string>, filas: list<array<int, string|int|float>>}
     */
    private function generarReporte(ReporteService $reportes): array
    {
        $desde = $this->desde ?: null;
        $hasta = $this->hasta ?: null;

        return match ($this->tipo) {
            'matricula' => $reportes->matricula($desde, $hasta),
            'academico' => $reportes->academico($desde, $hasta),
            'financiero' => $reportes->financiero($desde, $hasta),
            'certificados' => $reportes->certificados($desde, $hasta),
            'operativo' => $reportes->operativo($desde, $hasta),
            'propio' => $reportes->propio(Auth::user(), $desde, $hasta),
            default => ['columnas' => [], 'filas' => []],
        };
    }

    public function with(ReporteService $reportes): array
    {
        $user = Auth::user();

        $tiposDisponibles = collect(self::TIPOS)
            ->filter(fn (array $config) => $user->hasPermissionTo($config['permiso']))
            ->map(fn (array $config, string $tipo) => ['value' => $tipo, 'label' => $config['label']])
            ->values();

        $reporte = $this->tipo !== '' && $user->hasPermissionTo(self::TIPOS[$this->tipo]['permiso'] ?? 'sin-permiso')
            ? $this->generarReporte($reportes)
            : ['columnas' => [], 'filas' => []];

        return [
            'tiposDisponibles' => $tiposDisponibles,
            'reporte' => $reporte,
            'puedeExportar' => $user->hasPermissionTo('reportes.exportar'),
        ];
    }
}; ?>

<div>
    <x-slot name="header">
        <h1 class="font-display text-2xl text-ink">Reportes</h1>
        <p class="mt-1 text-sm text-ink-dim">Construye un reporte, revisa la vista previa y expórtalo a Excel, CSV o PDF.</p>
    </x-slot>

    <div class="space-y-4">
        <div class="flex flex-wrap items-end gap-4 rounded-lg border border-border bg-surface p-4">
            <div>
                <x-input-label for="tipo" value="Tipo de reporte" />
                <x-select-input
                    wire:model.live="tipo"
                    id="tipo"
                    class="mt-1 block w-56"
                    :options="collect($tiposDisponibles)->mapWithKeys(fn ($opcion) => [$opcion['value'] => $opcion['label']])"
                />
            </div>
            <div>
                <x-input-label for="desde" value="Desde" />
                <x-date-input wire:model.live="desde" id="desde" class="mt-1 block" />
            </div>
            <div>
                <x-input-label for="hasta" value="Hasta" />
                <x-date-input wire:model.live="hasta" id="hasta" class="mt-1 block" />
            </div>

            @if ($puedeExportar)
                <div class="ml-auto flex gap-2">
                    <x-secondary-button type="button" wire:click="exportarExcel">Excel</x-secondary-button>
                    <x-secondary-button type="button" wire:click="exportarCsv">CSV</x-secondary-button>
                    <x-secondary-button type="button" wire:click="exportarPdf">PDF</x-secondary-button>
                </div>
            @endif
        </div>

        <div class="overflow-x-auto rounded-lg border border-border bg-surface">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-border bg-surface-2">
                    <tr>
                        @foreach ($reporte['columnas'] as $columna)
                            <th class="whitespace-nowrap px-4 py-2 font-mono text-xs uppercase tracking-wide text-ink-faint">{{ $columna }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    @forelse ($reporte['filas'] as $fila)
                        <tr>
                            @foreach ($fila as $valor)
                                <td class="whitespace-nowrap px-4 py-2 text-ink">{{ $valor }}</td>
                            @endforeach
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ max(count($reporte['columnas']), 1) }}" class="px-4 py-8 text-center text-sm text-ink-faint">
                                No hay datos para los filtros seleccionados.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
