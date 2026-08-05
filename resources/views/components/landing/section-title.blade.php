@props(['subtitle' => null, 'align' => 'center'])

<div @class(['mb-12 max-w-2xl', 'mx-auto text-center' => $align === 'center'])>
    <h2 class="font-sans text-3xl font-extrabold text-white sm:text-4xl">{{ $slot }}</h2>
    <div @class(['mt-4 h-1 w-16 rounded-full bg-red-600', 'mx-auto' => $align === 'center'])></div>
    @if ($subtitle)
        <p class="mt-4 text-base text-gray-400">{{ $subtitle }}</p>
    @endif
</div>
