<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: 'Helvetica', sans-serif; font-size: 11px; color: #1B1F27; }

        table.encabezado { width: 100%; border-collapse: collapse; margin-bottom: 6px; }
        table.encabezado td { vertical-align: middle; }
        .logo-celda { width: 78px; padding-right: 14px; }
        .logo-celda img { width: 70px; }
        .colegio-nombre { font-size: 16px; font-weight: bold; color: #12225C; margin: 0; line-height: 1.15; }
        .colegio-nombre span { display: block; font-size: 22px; }
        .colegio-subtitulo { font-size: 9.5px; letter-spacing: 0.04em; text-transform: uppercase; color: #5B6472; margin: 2px 0 0; }

        hr.regla { border: none; border-top: 2px solid #12225C; margin: 6px 0 16px; }

        .titulo-recibo { text-align: center; margin-bottom: 18px; }
        .titulo-recibo h1 { font-size: 16px; letter-spacing: 0.05em; text-transform: uppercase; color: #12225C; margin: 0; }
        .titulo-recibo p { margin: 2px 0 0; font-size: 11px; color: #5B6472; }

        table.fechas { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
        table.fechas td { width: 50%; vertical-align: top; padding: 0 8px 0 0; }
        table.fechas .etiqueta { font-size: 9px; text-transform: uppercase; letter-spacing: 0.03em; color: #5B6472; margin: 0 0 4px; }
        table.caja { width: 100%; border-collapse: collapse; }
        table.caja th, table.caja td { border: 1px solid #C7CEDB; text-align: center; padding: 5px 4px; font-size: 10px; }
        table.caja th { background: #F0F2F5; color: #5B6472; font-weight: normal; text-transform: uppercase; font-size: 8px; }

        table.alumno { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
        table.alumno td { padding: 4px 0; font-size: 11px; }
        table.alumno .etiqueta { color: #5B6472; width: 18%; text-transform: uppercase; letter-spacing: 0.03em; font-size: 9px; }
        table.alumno .valor { font-weight: bold; border-bottom: 1px solid #C7CEDB; padding-bottom: 3px; }

        table.conceptos { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
        table.conceptos th { background: #F0F2F5; text-align: left; padding: 6px 8px; font-size: 9px; text-transform: uppercase; letter-spacing: 0.03em; color: #5B6472; border: 1px solid #C7CEDB; }
        table.conceptos td { padding: 8px; border: 1px solid #C7CEDB; }
        table.conceptos .col-cant { width: 10%; text-align: center; }
        table.conceptos .col-monto { width: 18%; text-align: right; white-space: nowrap; }

        .observacion { margin-bottom: 16px; }
        .observacion .etiqueta { font-size: 9px; text-transform: uppercase; letter-spacing: 0.03em; color: #5B6472; margin: 0 0 4px; }
        .observacion .caja-texto { border: 1px solid #C7CEDB; border-radius: 3px; min-height: 28px; padding: 6px 8px; font-size: 10.5px; }

        table.pie { width: 100%; border-collapse: collapse; margin-bottom: 30px; }
        table.pie td { width: 33.33%; padding: 4px 0; font-size: 10.5px; }
        table.pie .etiqueta { color: #5B6472; font-size: 9px; text-transform: uppercase; letter-spacing: 0.03em; }

        .codigo { margin-top: 30px; font-size: 9px; color: #8891A0; text-align: center; }
    </style>
</head>
<body>
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
        <h1>Recibo de pago</h1>
        <p>N.° {{ $recibo->numero_recibo }}</p>
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
        <div class="caja-texto">{{ $pago->detalle ?: '—' }}</div>
    </div>

    <table class="pie">
        <tr>
            <td>
                <p class="etiqueta">Cuota</p>
                {{ $pago->cuota ? "N.° {$pago->cuota->numero} de {$pago->cuota->planPago->numero_cuotas}" : '—' }}
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

    <p class="codigo">Recibo N.° {{ $recibo->numero_recibo }} · emitido el {{ $recibo->emitido_en->format('d/m/Y H:i') }}</p>
</body>
</html>
