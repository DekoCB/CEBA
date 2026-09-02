@props(['class' => ''])

{{--
    Mascota del Dashboard: astronauta flotando, igual al de la imagen de
    referencia del rediseño (réplica literal, no adaptada a la paleta
    institucional). SVG a mano (formas simples) para no depender de un
    activo rasterizado externo.
--}}
<svg
    viewBox="0 0 200 230"
    fill="none"
    xmlns="http://www.w3.org/2000/svg"
    {{ $attributes->merge(['class' => $class]) }}
    aria-hidden="true"
>
    <!-- Chispas y placa/bandera flotante -->
    <circle cx="26" cy="46" r="3.5" fill="#FBBF24" opacity="0.9" />
    <circle cx="22" cy="150" r="3" fill="#F8FAFC" opacity="0.7" />
    <path d="M170 34 l3 7 l7 3 l-7 3 l-3 7 l-3 -7 l-7 -3 l7 -3 Z" fill="#FBBF24" opacity="0.9" />

    <g transform="translate(148 16) rotate(18)">
        <rect x="0" y="0" width="30" height="30" rx="7" fill="#DC2626" />
        <path d="M9 7 V23 M9 7 H20 C23 7 23 13 20 13 H9" stroke="#F8FAFC" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" fill="none" />
    </g>

    <!-- Mochila -->
    <rect x="40" y="96" width="26" height="56" rx="10" fill="#DC2626" />

    <!-- Brazo izquierdo (en reposo) -->
    <path d="M66 128 C48 136 40 154 46 174" stroke="#E5E9F0" stroke-width="16" stroke-linecap="round" />
    <circle cx="45" cy="178" r="10" fill="#F8FAFC" stroke="#B8C0D0" stroke-width="3" />

    <!-- Brazo derecho (saludando) -->
    <path d="M138 126 C158 110 166 82 154 60" stroke="#E5E9F0" stroke-width="16" stroke-linecap="round" />
    <circle cx="153" cy="54" r="10" fill="#F8FAFC" stroke="#B8C0D0" stroke-width="3" />
    <rect x="146" y="46" width="14" height="10" rx="3" fill="#DC2626" />

    <!-- Cuerpo (traje) -->
    <path
        d="M100 92 C130 92 144 116 140 150 C137 178 122 196 100 202 C78 196 63 178 60 150 C56 116 70 92 100 92 Z"
        fill="#F8FAFC"
        stroke="#C7CEDB"
        stroke-width="2"
    />
    <rect x="82" y="146" width="36" height="14" rx="7" fill="#1E293B" />
    <circle cx="100" cy="153" r="4.5" fill="#FBBF24" />

    <!-- Piernas -->
    <rect x="78" y="192" width="18" height="24" rx="8" fill="#F8FAFC" stroke="#C7CEDB" stroke-width="2" />
    <rect x="104" y="192" width="18" height="24" rx="8" fill="#F8FAFC" stroke="#C7CEDB" stroke-width="2" />

    <!-- Casco -->
    <circle cx="100" cy="62" r="42" fill="#F8FAFC" stroke="#C7CEDB" stroke-width="3" />
    <circle cx="100" cy="62" r="32" fill="#3B4CA0" />
    <path d="M78 46 C88 36 112 36 122 46 C112 54 88 54 78 46 Z" fill="#FFFFFF" opacity="0.35" />
    <circle cx="88" cy="60" r="4" fill="#0B1026" />
    <circle cx="112" cy="60" r="4" fill="#0B1026" />
    <path d="M90 72 C95 77 105 77 110 72" stroke="#0B1026" stroke-width="3.5" stroke-linecap="round" fill="none" />
</svg>
