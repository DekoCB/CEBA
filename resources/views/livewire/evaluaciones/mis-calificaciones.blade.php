<?php

use App\Modules\Evaluaciones\Services\EvaluacionService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public function mount(): void
    {
        $user = Auth::user();

        abort_unless($user->hasPermissionTo('evaluaciones.ver_propio') && $user->estudiante, 403);
    }

    public function with(EvaluacionService $service): array
    {
        $estudiante = Auth::user()->estudiante;

        return [
            'porCiclo' => $service->resumenDelEstudiantePorCiclo($estudiante),
            'miEstudianteId' => $estudiante->id,
        ];
    }
}; ?>

<div>
    <x-slot name="header">
        <h1 class="font-display text-2xl text-ink">Mis calificaciones</h1>
        <p class="mt-1 text-sm text-ink-dim">Tus notas de todos tus cursos, en un solo lugar.</p>
    </x-slot>

    <x-evaluaciones.lista-calificaciones :por-ciclo="$porCiclo" :mi-estudiante-id="$miEstudianteId" />
</div>
