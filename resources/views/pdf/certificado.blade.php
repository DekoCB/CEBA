<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: 'Helvetica', sans-serif; font-size: 12px; color: #1B1F27; }
        .marco { border: 2px solid #137A6C; padding: 32px; }
        .institucion { text-align: center; font-size: 11px; letter-spacing: 0.08em; text-transform: uppercase; color: #5B6472; }
        h1 { text-align: center; font-size: 22px; margin: 12px 0 4px; letter-spacing: 0.04em; text-transform: uppercase; }
        .subtitulo { text-align: center; color: #5B6472; margin-bottom: 28px; }
        .cuerpo { font-size: 13px; line-height: 1.8; margin: 24px 0; text-align: justify; }
        .nombre { font-weight: bold; font-size: 15px; }
        .duplicado { text-align: center; color: #B8791A; font-weight: bold; letter-spacing: 0.08em; margin-bottom: 12px; }
        table.datos { width: 100%; border-collapse: collapse; margin-top: 24px; }
        table.datos td { padding: 4px 0; vertical-align: top; }
        .etiqueta { color: #5B6472; width: 30%; font-size: 11px; text-transform: uppercase; letter-spacing: 0.03em; }
        .pie { margin-top: 48px; text-align: center; }
        .codigo { margin-top: 32px; font-size: 10px; color: #8891A0; text-align: center; }
    </style>
</head>
<body>
    <div class="marco">
        <p class="institucion">Centro de Educación Básica Alternativa — CEBA</p>
        <h1>Certificado de estudios</h1>
        <p class="subtitulo">N.° {{ $certificado->numero }}</p>

        @if ($certificado->es_duplicado)
            <p class="duplicado">DUPLICADO</p>
        @endif

        <div class="cuerpo">
            Se deja constancia que
            <span class="nombre">{{ $certificado->estudiante->nombreCompleto() }}</span>,
            identificado(a) con DNI N.° {{ $certificado->estudiante->dni }},
            @if ($certificado->matricula)
                cursó estudios en el grado <strong>{{ $certificado->matricula->grado->nombre }}</strong>
                durante el ciclo <strong>{{ $certificado->matricula->ciclo->nombre }}</strong>
                ({{ $certificado->matricula->ciclo->fecha_inicio->format('d/m/Y') }}
                al {{ $certificado->matricula->ciclo->fecha_fin->format('d/m/Y') }}),
            @else
                se encuentra registrado(a) en esta institución,
            @endif
            conforme a los registros académicos de la institución.
        </div>

        @if ($certificado->observaciones)
            <table class="datos">
                <tr><td class="etiqueta">Observaciones</td><td>{{ $certificado->observaciones }}</td></tr>
            </table>
        @endif

        <p class="pie">Emitido el {{ $certificado->fecha_emision->format('d/m/Y') }}</p>

        <p class="codigo">
            Código de verificación: {{ $certificado->codigo_verificacion }}<br>
            Verifique la autenticidad de este documento en la sección "Verificar certificado" del portal CEBA.
        </p>
    </div>
</body>
</html>
