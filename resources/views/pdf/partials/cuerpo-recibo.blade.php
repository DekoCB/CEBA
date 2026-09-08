{{-- @var \App\Modules\Pagos\Enums\SerieReciboEnum $serie --}}
<table class="encabezado">
    <tr>
        <td class="logo-celda"><img src="{{ public_path('images/Logo.png') }}" alt="CEBA Peruano Británico"></td>
        <td>
            <p class="colegio-nombre">CEBA<span>PERUANO BRITÁNICO</span></p>
            <p class="colegio-subtitulo">Centro de Educación Básica Alternativa · Nivel Secundaria — No Escolarizado</p>
        </td>
    </tr>
</table>
<hr class="regla">

<div class="titulo-recibo">
    <h1>{{ $serie->titulo() }}</h1>
    <p>N.° {{ $serie->numeroCompleto($recibo->numero_recibo) }}</p>
</div>

<table class="fechas">
    <tr>
        <td>
            <p class="etiqueta">Fecha de emisión</p>
            <table class="caja">
                <tr><th>Día</th><th>Mes</th><th>Año</th></tr>
                <tr>
                    <td>{{ $recibo->emitido_en->format('d') }}</td>
                    <td>{{ $recibo->emitido_en->format('m') }}</td>
                    <td>{{ $recibo->emitido_en->format('Y') }}</td>
                </tr>
            </table>
        </td>
        <td>
            <p class="etiqueta">Fecha de pago</p>
            <table class="caja">
                <tr><th>Día</th><th>Mes</th><th>Año</th></tr>
                <tr>
                    <td>{{ $pago->fecha_pago->format('d') }}</td>
                    <td>{{ $pago->fecha_pago->format('m') }}</td>
                    <td>{{ $pago->fecha_pago->format('Y') }}</td>
                </tr>
            </table>
        </td>
    </tr>
</table>

<table class="alumno">
    <tr><td class="etiqueta">Alumno(a)</td><td class="valor">{{ $pago->estudiante?->nombreCompleto() ?? '—' }}</td></tr>
    <tr><td class="etiqueta">DNI</td><td class="valor">{{ $pago->estudiante?->dni ?? '—' }}</td></tr>
</table>

<table class="conceptos">
    <thead>
        <tr>
            <th class="col-cant">Cant.</th>
            <th>Descripción</th>
            <th class="col-monto">P. Unit.</th>
            <th class="col-monto">Total</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td class="col-cant">1</td>
            <td>{{ $pago->concepto->nombre }}{{ $pago->detalle ? ' — '.$pago->detalle : '' }}</td>
            <td class="col-monto">S/ {{ number_format((float) $pago->monto, 2) }}</td>
            <td class="col-monto">S/ {{ number_format((float) $pago->monto, 2) }}</td>
        </tr>
    </tbody>
</table>

@if ($pago->partes->count() > 1)
    <table class="conceptos">
        <thead>
            <tr><th colspan="2">Partes del pago</th></tr>
        </thead>
        <tbody>
            @foreach ($pago->partes as $parte)
                <tr>
                    <td>{{ $parte->metodo->label() }}</td>
                    <td class="col-monto">S/ {{ number_format((float) $parte->monto, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif

<div class="observacion">
    <p class="etiqueta">Observación</p>
    <div class="caja-texto">{{ $pago->observacion ?: '—' }}</div>
</div>

<table class="pie">
    <tr>
        <td>
            <p class="etiqueta">Cuota</p>
            @if ($pago->cuota)
                N.° {{ $pago->cuota->numero }} de {{ $pago->cuota->planPago->numero_cuotas }}
                ({{ (float) $pago->monto >= (float) $pago->cuota->monto ? 'Completo' : 'Parcial' }})
            @else
                —
            @endif
        </td>
        <td>
            <p class="etiqueta">Grupo</p>
            {{ $pago->cuota?->planPago?->matricula?->ciclo?->nombre ?? '—' }}
        </td>
        <td>
            <p class="etiqueta">Medio de pago</p>
            {{ $pago->metodo->label() }}
        </td>
    </tr>
</table>

<p class="codigo">{{ $serie->titulo() }} N.° {{ $serie->numeroCompleto($recibo->numero_recibo) }} · emitido el {{ $recibo->emitido_en->format('d/m/Y H:i') }}</p>
