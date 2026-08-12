<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: 'Helvetica', sans-serif; font-size: 12px; color: #1B1F27; }
        .marco { border: 2px solid {{ $plantilla->color_acento }}; padding: 32px; }
        .institucion { text-align: center; font-size: 11px; letter-spacing: 0.08em; text-transform: uppercase; color: #5B6472; }
        h1 { text-align: center; font-size: 22px; margin: 12px 0 4px; letter-spacing: 0.04em; text-transform: uppercase; color: {{ $plantilla->color_acento }}; }
        .subtitulo { text-align: center; color: #5B6472; margin-bottom: 28px; }
        .cuerpo { font-size: 13px; line-height: 1.8; margin: 24px 0; text-align: justify; }
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
        <p class="institucion">{{ $plantilla->institucion }}</p>
        <h1>{{ $plantilla->titulo }}</h1>
        <p class="subtitulo">N.° {{ $certificado->numero }}</p>

        @if ($certificado->es_duplicado)
            <p class="duplicado">DUPLICADO</p>
        @endif

        {{--
            $cuerpo ya viene con los placeholders sustituidos por texto plano
            (PlantillaCertificado::renderizarCuerpo(), vía str_replace, nunca
            compilado como Blade/PHP). Se escapa igual antes de imprimirlo:
            así, aunque el texto tecleado por un administrativo contenga
            caracteres como < o &, se muestran tal cual en vez de romper el
            HTML del PDF -- nl2br() después de escapar preserva los saltos
            de línea que haya escrito.
        --}}
        <div class="cuerpo">{!! nl2br(e($cuerpo)) !!}</div>

        @if ($certificado->observaciones)
            <table class="datos">
                <tr><td class="etiqueta">Observaciones</td><td>{{ $certificado->observaciones }}</td></tr>
            </table>
        @endif

        <p class="pie">Emitido el {{ $certificado->fecha_emision->format('d/m/Y') }}</p>

        @if ($plantilla->pie_nota)
            <p class="codigo">
                Código de verificación: {{ $certificado->codigo_verificacion }}<br>
                {{ $plantilla->pie_nota }}
            </p>
        @endif
    </div>
</body>
</html>
