@props(['iconOnly' => false])

<div {{ $attributes->merge(['class' => 'flex items-center gap-2 font-display text-ink']) }}>
    <img src="{{ asset('images/Logo.png') }}" alt="CEBA Peruano Británico" class="h-8 w-8 shrink-0 rounded-md object-contain">
    @unless($iconOnly)
        <span class="sidebar-label sidebar-label-brand text-sm leading-tight">CEBA Peruano Británico</span>
    @endunless
</div>
