@props(['variant' => 'primary', 'href' => '#', 'icon' => null, 'target' => null])

@php
    $variantes = [
        'primary' => 'bg-red-600 text-white hover:bg-red-700',
        'secondary' => 'border border-white/20 text-white hover:bg-white/5',
        'whatsapp' => 'bg-emerald-600 text-white hover:bg-emerald-700',
        'dark' => 'bg-black/30 text-white hover:bg-black/50',
    ];
    $clases = $variantes[$variant] ?? $variantes['primary'];
@endphp

<a
    href="{{ $href }}"
    @if ($target) target="{{ $target }}" rel="noopener" @endif
    {{ $attributes->merge(['class' => "inline-flex items-center justify-center gap-2 rounded-xl px-6 py-3 text-sm font-bold transition {$clases}"]) }}
>
    @if ($icon)
        <x-dynamic-component :component="'heroicon-o-'.$icon" class="h-5 w-5 shrink-0" />
    @endif
    {{ $slot }}
</a>
