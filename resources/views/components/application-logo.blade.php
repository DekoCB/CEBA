@props(['iconOnly' => false])

<div {{ $attributes->merge(['class' => 'flex items-center gap-2 font-display text-xl text-ink']) }}>
    <img src="{{ asset('images/Logo.png') }}" alt="CEBA" class="h-8 w-8 shrink-0 rounded-md object-contain">
    @unless($iconOnly)
        <span class="sidebar-label">CEBA</span>
    @endunless
</div>
