<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        @include('partials.theme-boot-script')

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased">
        <div x-data="{ sidebarOpen: false }" class="flex h-dvh overflow-hidden bg-bg">
            <!-- Sidebar (desktop) -->
            <aside class="hidden w-64 shrink-0 flex-col border-r border-border bg-surface md:flex">
                <div class="flex h-16 items-center border-b border-border px-4">
                    <a href="{{ route('dashboard') }}" wire:navigate>
                        <x-application-logo />
                    </a>
                </div>
                <x-sidebar-nav />
            </aside>

            <!-- Sidebar (mobile off-canvas) -->
            <div
                x-show="sidebarOpen"
                x-cloak
                class="fixed inset-0 z-40 bg-ink/40 md:hidden"
                @click="sidebarOpen = false"
                x-transition.opacity
            ></div>
            <aside
                x-show="sidebarOpen"
                x-cloak
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="-translate-x-full"
                x-transition:enter-end="translate-x-0"
                x-transition:leave="transition ease-in duration-150"
                x-transition:leave-start="translate-x-0"
                x-transition:leave-end="-translate-x-full"
                class="fixed inset-y-0 left-0 z-50 flex w-64 flex-col border-r border-border bg-surface md:hidden"
            >
                <div class="flex h-16 items-center justify-between border-b border-border px-4">
                    <x-application-logo />
                    <button @click="sidebarOpen = false" class="rounded-md p-2 text-ink-faint hover:bg-surface-2">
                        <x-heroicon-o-x-mark class="h-5 w-5" />
                    </button>
                </div>
                <x-sidebar-nav />
            </aside>

            <!-- Content column -->
            <div class="flex flex-1 flex-col overflow-hidden">
                <livewire:layout.navigation />

                @if (isset($header))
                    <header class="border-b border-border bg-surface">
                        <div class="px-4 py-6 sm:px-6">
                            {{ $header }}
                        </div>
                    </header>
                @endif

                <main class="flex-1 overflow-y-auto p-4 sm:p-6">
                    {{ $slot }}
                </main>
            </div>
        </div>
    </body>
</html>
