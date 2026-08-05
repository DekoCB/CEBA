@props(['iconOnly' => false])

<div {{ $attributes->merge(['class' => 'flex items-center gap-2 font-display text-xl text-ink']) }}>
    <span class="flex h-8 w-8 items-center justify-center rounded-md bg-accent text-sm font-bold text-white">C</span>
    @unless($iconOnly)
        <span class="sidebar-label">CEBA</span>
    @endunless
</div>
