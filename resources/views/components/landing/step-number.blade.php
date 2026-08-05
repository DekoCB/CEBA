@props(['number', 'icon', 'color' => 'red', 'title'])

@php
    $tonos = [
        'red' => 'bg-red-600',
        'blue' => 'bg-blue-600',
        'green' => 'bg-emerald-600',
        'amber' => 'bg-amber-500',
        'pink' => 'bg-fuchsia-600',
    ];
    $tono = $tonos[$color] ?? $tonos['red'];
@endphp

<div {{ $attributes->merge(['class' => 'flex flex-col items-center text-center sm:items-start sm:text-left']) }}>
    <div class="flex items-center gap-3">
        <span @class(['flex h-10 w-10 shrink-0 items-center justify-center rounded-full font-sans text-sm font-extrabold text-white', $tono])>
            {{ $number }}
        </span>
        <x-dynamic-component :component="'heroicon-o-'.$icon" class="hidden h-6 w-6 text-gray-500 sm:block" />
    </div>
    <h3 class="mt-3 font-sans text-sm font-bold text-white">{{ $title }}</h3>
    <p class="mt-1 text-xs leading-relaxed text-gray-400">{{ $slot }}</p>
</div>
