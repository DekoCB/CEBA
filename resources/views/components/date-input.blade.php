@props(['id' => null])

{{--
    Un <input type="date"> nativo muestra mm/dd/aaaa o dd/mm/aaaa según el
    idioma configurado en el navegador/SO del usuario, no según nuestro
    locale — no hay forma de forzar el formato desde el servidor. Este
    componente reemplaza el input nativo por un campo de texto con máscara
    dd/mm/aaaa que siempre se ve igual, y traduce a/desde Y-m-d (el formato
    que sigue esperando el resto del código en el valor de wire:model).
--}}
@php
    $wireModelo = $attributes->wire('model');
    $propiedad = $wireModelo->value();
    $enVivo = $wireModelo->hasModifier('live') ? 'true' : 'false';
@endphp

<div
    x-data="{
        texto: '',
        formatear(iso) {
            const m = String(iso ?? '').match(/^(\d{4})-(\d{2})-(\d{2})/);
            return m ? `${m[3]}/${m[2]}/${m[1]}` : '';
        },
        alEscribir(event) {
            const digitos = event.target.value.replace(/\D/g, '').slice(0, 8);
            let formateado = digitos;
            if (digitos.length > 4) {
                formateado = `${digitos.slice(0, 2)}/${digitos.slice(2, 4)}/${digitos.slice(4)}`;
            } else if (digitos.length > 2) {
                formateado = `${digitos.slice(0, 2)}/${digitos.slice(2)}`;
            }
            this.texto = formateado;

            const m = formateado.match(/^(\d{2})\/(\d{2})\/(\d{4})$/);
            this.$wire.set('{{ $propiedad }}', m ? `${m[3]}-${m[2]}-${m[1]}` : '', {{ $enVivo }});
        },
    }"
    x-effect="texto = formatear($wire.{{ $propiedad }})"
>
    <input
        type="text"
        inputmode="numeric"
        autocomplete="off"
        placeholder="dd/mm/aaaa"
        maxlength="10"
        :value="texto"
        @input="alEscribir"
        @if ($id) id="{{ $id }}" @endif
        {{ $attributes->whereDoesntStartWith('wire:model')->merge(['class' => 'rounded-md border-border bg-surface text-ink shadow-sm focus:border-accent focus:ring-accent disabled:opacity-60']) }}
    >
</div>
