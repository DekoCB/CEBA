<?php

use App\Livewire\Actions\Logout;
use Livewire\Volt\Component;

new class extends Component
{
    /**
     * Log the current user out of the application.
     */
    public function logout(Logout $logout): void
    {
        $logout();

        $this->redirect('/', navigate: true);
    }
}; ?>

<header class="shell-chrome flex h-16 items-center justify-between border-b border-border bg-surface px-4 sm:px-6">
    <div class="flex items-center gap-3">
        <button
            @click="sidebarOpen = ! sidebarOpen"
            class="rounded-md p-2 text-ink-dim hover:bg-surface-2 hover:text-ink md:hidden"
            aria-label="Abrir menú"
        >
            <x-heroicon-o-bars-3 class="h-6 w-6" />
        </button>

        <a href="{{ route('dashboard') }}" wire:navigate class="md:hidden">
            <x-application-logo />
        </a>
    </div>

    <div class="flex items-center gap-2">
        <span class="hidden rounded-full bg-surface-2 px-3 py-1 text-xs font-medium text-ink-dim sm:inline-block">
            {{ auth()->user()->roles->first()?->name ? ucfirst(auth()->user()->roles->first()->name) : 'Sin rol' }}
        </span>

        <x-theme-toggle />

        <x-dropdown align="right" width="48">
            <x-slot name="trigger">
                <button class="inline-flex items-center gap-2 rounded-md px-3 py-2 text-sm font-medium text-ink-dim transition hover:bg-surface-2 hover:text-ink focus:outline-none">
                    <span x-data="{{ json_encode(['name' => auth()->user()->name]) }}" x-text="name" x-on:profile-updated.window="name = $event.detail.name"></span>
                    <x-heroicon-o-chevron-down class="h-4 w-4" />
                </button>
            </x-slot>

            <x-slot name="content">
                <x-dropdown-link :href="route('profile')" wire:navigate>
                    Mi perfil
                </x-dropdown-link>

                <button wire:click="logout" class="w-full text-start">
                    <x-dropdown-link>
                        Cerrar sesión
                    </x-dropdown-link>
                </button>
            </x-slot>
        </x-dropdown>
    </div>
</header>
