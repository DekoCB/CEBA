@props(['curso'])

{{--
    Portada del curso: por ahora un ícono representativo (según el nombre
    del curso, ver Curso::iconoRepresentativo()) sobre un bloque de color.
    Cuando el curso tenga una imagen propia, basta con anteponer aquí un
    @if($curso->imagen_url) <img ...> @else ... sin tocar los sitios donde
    se usa este componente (aula-virtual/index y evaluaciones/index).
--}}
<div {{ $attributes->merge(['class' => 'flex h-28 items-center justify-center overflow-hidden rounded-md bg-accent-soft']) }}>
    <x-dynamic-component :component="'heroicon-o-'.$curso->iconoRepresentativo()" class="h-10 w-10 text-accent" />
</div>
