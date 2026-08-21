<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: 'Helvetica', sans-serif; font-size: 12px; color: #1B1F27; }

        table.encabezado { width: 100%; border-collapse: collapse; margin-bottom: 6px; }
        table.encabezado td { vertical-align: middle; }
        .logo-celda { width: 78px; padding-right: 14px; }
        .logo-celda img { width: 70px; }
        .institucion { font-size: 10px; letter-spacing: 0.05em; text-transform: uppercase; color: #5B6472; margin: 0 0 2px; }
        .colegio-nombre { font-size: 18px; font-weight: bold; color: #12225C; margin: 0; line-height: 1.15; }
        .colegio-nombre span { display: block; font-size: 25px; }
        .colegio-subtitulo { font-size: 10.5px; letter-spacing: 0.04em; text-transform: uppercase; color: #5B6472; margin: 2px 0 0; }

        hr.regla { border: none; border-top: 2px solid #12225C; margin: 8px 0 22px; }

        h1 { text-align: center; font-size: 20px; margin: 0 0 22px; letter-spacing: 0.05em; text-transform: uppercase; color: {{ $plantilla->color_acento }}; }
        .duplicado { text-align: center; color: #B8791A; font-weight: bold; letter-spacing: 0.08em; margin: -12px 0 16px; }

        p { margin: 0 0 12px; text-align: justify; line-height: 1.6; }
        .hace-constar { font-weight: bold; text-decoration: underline; margin: 18px 0 12px; }
        .cuerpo { font-size: 12.5px; line-height: 1.8; margin: 0 0 20px; text-align: justify; }

        .contacto { margin: 20px 0 28px; font-size: 11.5px; }
        .contacto p { margin: 0 0 4px; text-align: left; }

        p.atentamente { margin-bottom: 4px; }
        p.cierre { text-align: right; margin-top: 0; }

        .firma { margin-top: 64px; text-align: center; }
        .firma .linea { border-top: 1px solid #1B1F27; width: 220px; margin: 0 auto; }
        .firma p { margin: 4px 0 0; text-align: center; }
        .firma .nombre { font-weight: bold; }

        .pie-codigo { margin-top: 18px; font-size: 10px; color: #8891A0; }
        .verificacion { margin-top: 20px; font-size: 9.5px; color: #8891A0; text-align: center; }
    </style>
</head>
<body>
    <table class="encabezado">
        <tr>
            <td class="logo-celda"><img src="{{ public_path('images/Logo.png') }}" alt="CEBA Peruano Británico"></td>
            <td>
                @if ($plantilla->institucion)
                    <p class="institucion">{{ $plantilla->institucion }}</p>
                @endif
                <p class="colegio-nombre">CEBA<span>PERUANO BRITÁNICO</span></p>
                <p class="colegio-subtitulo">Centro de Educación Básica Alternativa</p>
            </td>
        </tr>
    </table>
    <hr class="regla">

    <h1>{{ $plantilla->titulo }}</h1>

    @if ($certificado->es_duplicado)
        <p class="duplicado">DUPLICADO</p>
    @endif

    <p>
        El Director del Colegio EBA "Peruano Británico" con código modular N.° 1503390, del departamento de
        Lima, distrito de San Juan de Lurigancho — UGEL 05.
    </p>

    <p class="hace-constar">HACE CONSTAR QUE:</p>

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
        <p><strong>Observaciones:</strong> {{ $certificado->observaciones }}</p>
    @endif

    <div class="contacto">
        <p>Se puede poner en contacto con el Colegio:</p>
        <p>Celular: 978-351-141</p>
        <p>Correo electrónico: cebapperuanobritanico.ugel05@gmail.com</p>
    </div>

    <p class="atentamente">Atentamente,</p>
    <p class="cierre">San Juan de Lurigancho, {{ $certificado->fecha_emision->translatedFormat('d \d\e F \d\e Y') }}</p>

    <div class="firma">
        <div class="linea"></div>
        <p class="nombre">LIC. ALCIDES MARLO PAICIC</p>
        <p>DIRECTOR</p>
        <p>CEBA PERUANO BRITÁNICO</p>
    </div>

    <p class="pie-codigo">AMP.DIR/PB-EBA · N.° {{ $certificado->numero }}</p>

    @if ($plantilla->pie_nota)
        <p class="verificacion">
            Código de verificación: {{ $certificado->codigo_verificacion }}<br>
            {{ $plantilla->pie_nota }}
        </p>
    @endif
</body>
</html>
