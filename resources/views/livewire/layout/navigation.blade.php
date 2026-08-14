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

<div>
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
            <span class="hidden rounded-full bg-surface-2 px-3 py-1 font-display text-xs font-medium text-ink-dim sm:inline-block">
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
                        class="inline-flex items-center gap-2 rounded-md py-1.5 pl-1.5 pr-3 font-display text-sm font-medium text-ink-dim transition hover:bg-surface-2 hover:text-ink focus:outline-none"
                    >
                        <img x-show="avatarUrl" :src="avatarUrl" alt="" class="h-7 w-7 shrink-0 rounded-full object-cover">
                        <span x-show="! avatarUrl" x-text="iniciales()" class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-accent-soft text-xs font-bold text-accent"></span>
                        <span x-text="name"></span>
                        <x-heroicon-o-chevron-down class="h-4 w-4" />
                    </button>
                </x-slot>

                <x-slot name="content">
                    <button type="button" x-on:click="$dispatch('open-modal', 'mi-perfil')" class="w-full text-start">
                        <x-dropdown-link>
                            Mi perfil
                        </x-dropdown-link>
                    </button>

                    <button wire:click="logout" class="w-full text-start">
                        <x-dropdown-link>
                            Cerrar sesión
                        </x-dropdown-link>
                    </button>
                </x-slot>
            </x-dropdown>
        </div>
    </header>

    <x-modal name="mi-perfil" :tv="true" max-width="2xl">
        <div class="flex items-center justify-between border-b border-border px-6 py-4">
            <h2 class="font-display text-lg text-ink">Mi perfil</h2>
            <button type="button" x-on:click="$dispatch('close')" class="rounded-md p-1.5 text-ink-faint transition hover:bg-surface-2 hover:text-ink" aria-label="Cerrar">
                <x-heroicon-o-x-mark class="h-5 w-5" />
            </button>
        </div>

        <div class="max-h-[75vh] space-y-6 overflow-y-auto p-6">
            <div class="rounded-lg border border-border bg-surface-2 p-4 sm:p-6">
                <div class="max-w-xl">
                    <livewire:profile.update-profile-information-form />
                </div>
            </div>

            <div class="rounded-lg border border-border bg-surface-2 p-4 sm:p-6">
                <div class="max-w-xl">
                    <livewire:profile.update-password-form />
                </div>
            </div>

            <div class="rounded-lg border border-border bg-surface-2 p-4 sm:p-6">
                <livewire:profile.active-sessions-form />
            </div>

            <div class="rounded-lg border border-border bg-surface-2 p-4 sm:p-6">
                <livewire:profile.two-factor-authentication-form />
            </div>

            <div class="rounded-lg border border-border bg-surface-2 p-4 sm:p-6">
                <div class="max-w-xl">
                    <livewire:profile.delete-user-form />
                </div>
            </div>
        </div>
    </x-modal>
</div>
