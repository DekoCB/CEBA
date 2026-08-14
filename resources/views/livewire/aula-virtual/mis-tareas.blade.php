<?php

use App\Modules\AulaVirtual\Services\TareaService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public function mount(): void
    {
        $user = Auth::user();

        abort_unless($user->hasPermissionTo('aula_virtual.ver_propio') && $user->estudiante, 403);
    }

    public function with(TareaService $service): array
    {
        return [
            'tareas' => $service->delEstudiante(Auth::user()->estudiante),
        ];
    }
}; ?>

<div>
    <x-slot name="header">
        <h1 class="font-display text-2xl text-ink">Mis tareas</h1>
        <p class="mt-1 text-sm text-ink-dim">Todas las tareas de tus cursos, en un solo lugar.</p>
    </x-slot>

    <x-aula-virtual.lista-tareas :tareas="$tareas" />
</div>
