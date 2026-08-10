@props(['categoria', 'fecha', 'titulo', 'color' => 'red'])

@php
    $tonos = [
        'red' => ['bg-red-600', 'from-red-900/40'],
        'blue' => ['bg-blue-600', 'from-blue-900/40'],
        'green' => ['bg-emerald-600', 'from-emerald-900/40'],
        'amber' => ['bg-amber-500', 'from-amber-900/40'],
        'pink' => ['bg-fuchsia-600', 'from-fuchsia-900/40'],
    ];
    [$badge, $gradiente] = $tonos[$color] ?? $tonos['red'];
@endphp

<article class="flex h-full flex-col overflow-hidden rounded-2xl border border-white/5 bg-[#1a1a1c]">
    <div @class(['relative flex h-36 shrink-0 items-center justify-center bg-gradient-to-br to-[#111113]', $gradiente])>
        <x-heroicon-o-megaphone class="h-10 w-10 text-white/20" />
        <span @class(['absolute left-3 top-3 rounded-full px-3 py-1 text-xs font-bold text-white', $badge])>
            {{ $categoria }}
        </span>
    </div>
    <div class="flex flex-1 flex-col p-5">
        <p class="flex items-center gap-1.5 text-xs text-gray-500">
            <x-heroicon-o-calendar-days class="h-4 w-4" />
            {{ $fecha }}
        </p>
        <h3 class="mt-2 font-sans text-sm font-bold leading-snug text-white">{{ $titulo }}</h3>
    </div>
</article>
