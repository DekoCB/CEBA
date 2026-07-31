<nav class="flex min-h-0 flex-1 flex-col gap-1 overflow-y-auto px-3 py-4">
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

    @can('matricula.ver')
        <p class="mt-4 px-3 text-xs font-semibold uppercase tracking-wide text-ink-faint">
            Matrícula
        </p>

        <a
            href="{{ route('matricula.index') }}"
            wire:navigate
            @class([
                'flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium transition',
                'bg-accent-soft text-accent' => request()->routeIs('matricula.*'),
                'text-ink-dim hover:bg-surface-2 hover:text-ink' => ! request()->routeIs('matricula.*'),
            ])
        >
            <x-heroicon-o-identification class="h-5 w-5 shrink-0" />
            Estudiantes
        </a>
    @endcan

    @canany(['aula_virtual.ver', 'aula_virtual.gestionar_propio', 'aula_virtual.ver_propio'])
        <p class="mt-4 px-3 text-xs font-semibold uppercase tracking-wide text-ink-faint">
            Aula Virtual
        </p>

        <a
            href="{{ route('aula-virtual.index') }}"
            wire:navigate
            @class([
                'flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium transition',
                'bg-accent-soft text-accent' => request()->routeIs('aula-virtual.*'),
                'text-ink-dim hover:bg-surface-2 hover:text-ink' => ! request()->routeIs('aula-virtual.*'),
            ])
        >
            <x-heroicon-o-computer-desktop class="h-5 w-5 shrink-0" />
            Cursos virtuales
        </a>
    @endcanany

    @canany(['asistencia.ver', 'asistencia.registrar', 'asistencia.ver_propio'])
        <a
            href="{{ route('asistencia.index') }}"
            wire:navigate
            @class([
                'flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium transition',
                'bg-accent-soft text-accent' => request()->routeIs('asistencia.*'),
                'text-ink-dim hover:bg-surface-2 hover:text-ink' => ! request()->routeIs('asistencia.*'),
            ])
        >
            <x-heroicon-o-clipboard-document-check class="h-5 w-5 shrink-0" />
            Asistencia
        </a>
    @endcanany

    @canany(['evaluaciones.ver', 'evaluaciones.registrar', 'evaluaciones.ver_propio'])
        <a
            href="{{ route('evaluaciones.index') }}"
            wire:navigate
            @class([
                'flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium transition',
                'bg-accent-soft text-accent' => request()->routeIs('evaluaciones.*'),
                'text-ink-dim hover:bg-surface-2 hover:text-ink' => ! request()->routeIs('evaluaciones.*'),
            ])
        >
            <x-heroicon-o-pencil-square class="h-5 w-5 shrink-0" />
            Evaluaciones
        </a>
    @endcanany

    @canany(['pagos.ver', 'pagos.registrar', 'pagos.gestionar', 'pagos.aprobar', 'pagos.rechazar', 'pagos.ver_propio', 'tesoreria.gestionar'])
        <p class="mt-4 px-3 text-xs font-semibold uppercase tracking-wide text-ink-faint">
            Pagos
        </p>

        @canany(['pagos.ver', 'pagos.registrar', 'pagos.gestionar', 'pagos.aprobar', 'pagos.rechazar'])
            <a
                href="{{ route('pagos.index') }}"
                wire:navigate
                @class([
                    'flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium transition',
                    'bg-accent-soft text-accent' => request()->routeIs('pagos.index'),
                    'text-ink-dim hover:bg-surface-2 hover:text-ink' => ! request()->routeIs('pagos.index'),
                ])
            >
                <x-heroicon-o-banknotes class="h-5 w-5 shrink-0" />
                Cobranza
            </a>
        @endcanany

        {{-- pagos.ver_propio también lo tiene Dirección vía '*', pero mi-cuenta
             exige además una ficha de Estudiante: sin ella el enlace 403ea. --}}
        @if (auth()->user()->can('pagos.ver_propio') && auth()->user()->estudiante)
            <a
                href="{{ route('pagos.mi-cuenta') }}"
                wire:navigate
                @class([
                    'flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium transition',
                    'bg-accent-soft text-accent' => request()->routeIs('pagos.mi-cuenta'),
                    'text-ink-dim hover:bg-surface-2 hover:text-ink' => ! request()->routeIs('pagos.mi-cuenta'),
                ])
            >
                <x-heroicon-o-credit-card class="h-5 w-5 shrink-0" />
                Mi estado de cuenta
            </a>
        @endif

        @can('pagos.gestionar')
            <a
                href="{{ route('pagos.conceptos') }}"
                wire:navigate
                @class([
                    'flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium transition',
                    'bg-accent-soft text-accent' => request()->routeIs('pagos.conceptos'),
                    'text-ink-dim hover:bg-surface-2 hover:text-ink' => ! request()->routeIs('pagos.conceptos'),
                ])
            >
                <x-heroicon-o-tag class="h-5 w-5 shrink-0" />
                Conceptos de pago
            </a>
        @endcan

        @can('tesoreria.gestionar')
            <a
                href="{{ route('pagos.cuentas-bancarias') }}"
                wire:navigate
                @class([
                    'flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium transition',
                    'bg-accent-soft text-accent' => request()->routeIs('pagos.cuentas-bancarias'),
                    'text-ink-dim hover:bg-surface-2 hover:text-ink' => ! request()->routeIs('pagos.cuentas-bancarias'),
                ])
            >
                <x-heroicon-o-building-library class="h-5 w-5 shrink-0" />
                Cuentas bancarias
            </a>
        @endcan
    @endcanany

    @canany(['certificados.ver', 'certificados.emitir', 'certificados.duplicar', 'certificados.solicitar'])
        <p class="mt-4 px-3 text-xs font-semibold uppercase tracking-wide text-ink-faint">
            Certificados
        </p>

        @canany(['certificados.ver', 'certificados.emitir'])
            <a
                href="{{ route('certificados.index') }}"
                wire:navigate
                @class([
                    'flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium transition',
                    'bg-accent-soft text-accent' => request()->routeIs('certificados.index'),
                    'text-ink-dim hover:bg-surface-2 hover:text-ink' => ! request()->routeIs('certificados.index'),
                ])
            >
                <x-heroicon-o-document-check class="h-5 w-5 shrink-0" />
                Certificados
            </a>
        @endcanany

        {{-- certificados.solicitar también lo tiene Dirección vía '*', pero
             mis-certificados exige además una ficha de Estudiante. --}}
        @if (auth()->user()->can('certificados.solicitar') && auth()->user()->estudiante)
            <a
                href="{{ route('certificados.mis-certificados') }}"
                wire:navigate
                @class([
                    'flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium transition',
                    'bg-accent-soft text-accent' => request()->routeIs('certificados.mis-certificados'),
                    'text-ink-dim hover:bg-surface-2 hover:text-ink' => ! request()->routeIs('certificados.mis-certificados'),
                ])
            >
                <x-heroicon-o-document-text class="h-5 w-5 shrink-0" />
                Mis certificados
            </a>
        @endif
    @endcanany

    @can('academico.ver')
        <p class="mt-4 px-3 text-xs font-semibold uppercase tracking-wide text-ink-faint">
            Académico
        </p>

        @foreach ([
            ['route' => 'academico.grados.index', 'prefix' => 'academico.grados.*', 'label' => 'Grados', 'icon' => 'academic-cap'],
            ['route' => 'academico.ciclos.index', 'prefix' => 'academico.ciclos.*', 'label' => 'Ciclos', 'icon' => 'arrow-path'],
            ['route' => 'academico.cursos.index', 'prefix' => 'academico.cursos.*', 'label' => 'Cursos', 'icon' => 'book-open'],
            ['route' => 'academico.aulas.index', 'prefix' => 'academico.aulas.*', 'label' => 'Aulas', 'icon' => 'building-office-2'],
            ['route' => 'academico.horarios.index', 'prefix' => 'academico.horarios.*', 'label' => 'Horarios', 'icon' => 'clock'],
        ] as $enlace)
            <a
                href="{{ route($enlace['route']) }}"
                wire:navigate
                @class([
                    'flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium transition',
                    'bg-accent-soft text-accent' => request()->routeIs($enlace['prefix']),
                    'text-ink-dim hover:bg-surface-2 hover:text-ink' => ! request()->routeIs($enlace['prefix']),
                ])
            >
                <x-dynamic-component :component="'heroicon-o-'.$enlace['icon']" class="h-5 w-5 shrink-0" />
                {{ $enlace['label'] }}
            </a>
        @endforeach
    @endcan

    @canany(['reportes.academicos', 'reportes.matricula', 'reportes.financieros', 'reportes.certificados', 'reportes.operativos', 'reportes.propios'])
        <a
            href="{{ route('reportes.index') }}"
            wire:navigate
            @class([
                'mt-4 flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium transition',
                'bg-accent-soft text-accent' => request()->routeIs('reportes.*'),
                'text-ink-dim hover:bg-surface-2 hover:text-ink' => ! request()->routeIs('reportes.*'),
            ])
        >
            <x-heroicon-o-chart-bar class="h-5 w-5 shrink-0" />
            Reportes
        </a>
    @endcanany

    @canany(['whatsapp.ver', 'whatsapp.enviar'])
        <a
            href="{{ route('notificaciones.index') }}"
            wire:navigate
            @class([
                'mt-4 flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium transition',
                'bg-accent-soft text-accent' => request()->routeIs('notificaciones.*'),
                'text-ink-dim hover:bg-surface-2 hover:text-ink' => ! request()->routeIs('notificaciones.*'),
            ])
        >
            <x-heroicon-o-chat-bubble-left-right class="h-5 w-5 shrink-0" />
            Notificaciones
        </a>
    @endcanany

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
