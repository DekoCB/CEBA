@php
    $item = function (string $routeName, string $label, string $icon) {
        $active = request()->routeIs($routeName.'*');

        return compact('routeName', 'label', 'icon', 'active');
    };
@endphp

<nav class="flex flex-1 flex-col gap-1 px-3 py-4">
    <a
        href="{{ route('dashboard') }}"
        wire:navigate
        @class([
            'flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium transition',
            'bg-accent-soft text-accent' => request()->routeIs('dashboard'),
            'text-ink-dim hover:bg-surface-2 hover:text-ink' => ! request()->routeIs('dashboard'),
        ])
    >
        <x-heroicon-o-squares-2x2 class="h-5 w-5 shrink-0" />
        Dashboard
    </a>

    @canany(['usuarios.ver', 'roles.gestionar', 'auditoria.ver'])
        <p class="mt-4 px-3 text-xs font-semibold uppercase tracking-wide text-ink-faint">
            Administración
        </p>

        @can('usuarios.ver')
            <a
                href="{{ route('usuarios.index') }}"
                wire:navigate
                @class([
                    'flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium transition',
                    'bg-accent-soft text-accent' => request()->routeIs('usuarios.*'),
                    'text-ink-dim hover:bg-surface-2 hover:text-ink' => ! request()->routeIs('usuarios.*'),
                ])
            >
                <x-heroicon-o-users class="h-5 w-5 shrink-0" />
                Usuarios
            </a>
        @endcan

        @can('roles.gestionar')
            <a
                href="{{ route('roles.index') }}"
                wire:navigate
                @class([
                    'flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium transition',
                    'bg-accent-soft text-accent' => request()->routeIs('roles.*'),
                    'text-ink-dim hover:bg-surface-2 hover:text-ink' => ! request()->routeIs('roles.*'),
                ])
            >
                <x-heroicon-o-shield-check class="h-5 w-5 shrink-0" />
                Roles y permisos
            </a>
        @endcan

        @can('auditoria.ver')
            <a
                href="{{ route('auditoria.index') }}"
                wire:navigate
                @class([
                    'flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium transition',
                    'bg-accent-soft text-accent' => request()->routeIs('auditoria.*'),
                    'text-ink-dim hover:bg-surface-2 hover:text-ink' => ! request()->routeIs('auditoria.*'),
                ])
            >
                <x-heroicon-o-clipboard-document-list class="h-5 w-5 shrink-0" />
                Auditoría
            </a>
        @endcan
    @endcanany

    <div class="mt-auto pt-4">
        <a
            href="{{ route('profile') }}"
            wire:navigate
            @class([
                'flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium transition',
                'bg-accent-soft text-accent' => request()->routeIs('profile'),
                'text-ink-dim hover:bg-surface-2 hover:text-ink' => ! request()->routeIs('profile'),
            ])
        >
            <x-heroicon-o-user-circle class="h-5 w-5 shrink-0" />
            Mi perfil
        </a>
    </div>
</nav>
