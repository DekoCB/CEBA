@props(['icon', 'color' => 'red', 'title' => null, 'compact' => false])

@php
    $tonos = [
        'red' => 'bg-red-500/10 text-red-400',
        'blue' => 'bg-blue-500/10 text-blue-400',
        'green' => 'bg-emerald-500/10 text-emerald-400',
        'amber' => 'bg-amber-500/10 text-amber-400',
        'pink' => 'bg-fuchsia-500/10 text-fuchsia-400',
    ];
    $tono = $tonos[$color] ?? $tonos['red'];
    $relleno = $compact ? 'p-4' : 'p-6';
    $tamanoIcono = $compact ? 'h-9 w-9' : 'h-12 w-12';
@endphp

<div {{ $attributes->merge(['class' => "group rounded-2xl border border-white/5 bg-[#1a1a1c] {$relleno} transition duration-300 hover:-translate-y-1 hover:border-white/10 hover:shadow-xl hover:shadow-black/30"]) }}>
    <div @class(['flex shrink-0 items-center justify-center rounded-full transition-transform duration-300 group-hover:scale-110', $tamanoIcono, $tono])>
        <x-dynamic-component :component="'heroicon-o-'.$icon" @class([$compact ? 'h-5 w-5' : 'h-6 w-6']) />
    </div>

    @if ($title)
        <h3 @class(['font-sans font-bold text-white', $compact ? 'mt-3 text-sm' : 'mt-4 text-base'])>{{ $title }}</h3>
    @endif

    @if (trim($slot))
        <div class="mt-1.5 text-sm leading-relaxed text-gray-400">{{ $slot }}</div>
    @endif
</div>
