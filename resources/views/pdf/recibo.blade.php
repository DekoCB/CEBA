<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: 'Helvetica', sans-serif; font-size: 12px; color: #1B1F27; }
        h1 { font-size: 18px; margin-bottom: 4px; }
        .subtitulo { color: #5B6472; margin-bottom: 24px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 18px; }
        td { padding: 6px 8px; vertical-align: top; }
        .etiqueta { color: #5B6472; width: 35%; font-size: 11px; text-transform: uppercase; letter-spacing: 0.03em; }
        .seccion { font-size: 13px; font-weight: bold; margin-top: 18px; margin-bottom: 6px; border-bottom: 1px solid #DBDFE6; padding-bottom: 4px; }
        .monto { font-size: 22px; font-weight: bold; margin: 18px 0; }
        .codigo { margin-top: 40px; font-size: 10px; color: #8891A0; }
    </style>
</head>
<body>
    <h1>Recibo de pago</h1>
    <p class="subtitulo">CEBA · N.° {{ $recibo->numero_recibo }}</p>

    <div class="seccion">Datos del estudiante</div>
    <table>
        <tr><td class="etiqueta">Nombres y apellidos</td><td>{{ $pago->estudiante?->nombreCompleto() ?? '—' }}</td></tr>
        <tr><td class="etiqueta">DNI</td><td>{{ $pago->estudiante?->dni ?? '—' }}</td></tr>
    </table>

    <div class="seccion">Datos del pago</div>
    <table>
        <tr><td class="etiqueta">Concepto</td><td>{{ $pago->concepto->nombre }}</td></tr>
        @if ($pago->cuota)
            <tr><td class="etiqueta">Cuota</td><td>{{ $pago->cuota->numero }} de {{ $pago->cuota->planPago->numero_cuotas }}</td></tr>
        @endif
        <tr><td class="etiqueta">Método de pago</td><td>{{ $pago->metodo->label() }}</td></tr>
        <tr><td class="etiqueta">Fecha de pago</td><td>{{ $pago->fecha_pago->format('d/m/Y') }}</td></tr>
        <tr><td class="etiqueta">Fecha de aprobación</td><td>{{ $pago->fecha_aprobacion?->format('d/m/Y H:i') }}</td></tr>
    </table>

    @if ($pago->partes->count() > 1)
        <div class="seccion">Partes del pago</div>
        <table>
            @foreach ($pago->partes as $parte)
                <tr>
                    <td class="etiqueta">{{ $parte->metodo->label() }}</td>
                    <td>S/ {{ number_format((float) $parte->monto, 2) }}</td>
                </tr>
            @endforeach
        </table>
    @endif

    <p class="monto">Monto pagado: S/ {{ number_format((float) $pago->monto, 2) }}</p>

    <p class="codigo">Recibo N.° {{ $recibo->numero_recibo }} · emitido el {{ $recibo->emitido_en->format('d/m/Y H:i') }}</p>
</body>
</html>
