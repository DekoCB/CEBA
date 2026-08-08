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

@php
    $mediaAvatar = auth()->user()->getFirstMedia('avatar');
    $avatarUrlInicial = $mediaAvatar ? $mediaAvatar->getUrl().'?v='.$mediaAvatar->updated_at->timestamp : null;
@endphp

<header class="flex h-16 items-center justify-between border-b border-border bg-surface px-4 sm:px-6">
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
                <button
                    x-data="{
                        name: @js(auth()->user()->name),
                        avatarUrl: @js($avatarUrlInicial),
                        iniciales() {
                            return this.name.trim().split(/\s+/).map(p => p[0]).slice(0, 2).join('').toUpperCase();
                        },
                    }"
                    x-on:profile-updated.window="name = $event.detail.name; avatarUrl = $event.detail.avatarUrl"
                    class="inline-flex items-center gap-2 rounded-md py-1.5 pl-1.5 pr-3 text-sm font-medium text-ink-dim transition hover:bg-surface-2 hover:text-ink focus:outline-none"
                >
                    <img x-show="avatarUrl" :src="avatarUrl" alt="" class="h-7 w-7 shrink-0 rounded-full object-cover">
                    <span x-show="! avatarUrl" x-text="iniciales()" class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-accent-soft text-xs font-bold text-accent"></span>
                    <span x-text="name"></span>
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
