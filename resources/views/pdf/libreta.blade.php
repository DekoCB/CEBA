<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: 'Helvetica', sans-serif; font-size: 12px; color: #1B1F27; }
        h1 { font-size: 18px; margin-bottom: 4px; }
        .subtitulo { color: #5B6472; margin-bottom: 24px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 18px; }
        thead td { font-size: 10.5px; text-transform: uppercase; letter-spacing: 0.03em; color: #5B6472; border-bottom: 1px solid #DBDFE6; padding: 6px 8px; }
        tbody td { padding: 7px 8px; border-bottom: 1px solid #EEF0F3; }
        .seccion { font-size: 13px; font-weight: bold; margin-top: 18px; margin-bottom: 6px; border-bottom: 1px solid #DBDFE6; padding-bottom: 4px; }
        .etiqueta { color: #5B6472; width: 35%; font-size: 11px; text-transform: uppercase; letter-spacing: 0.03em; }
        .codigo { margin-top: 40px; font-size: 10px; color: #8891A0; }
        .sin-datos { color: #8891A0; font-style: italic; }
    </style>
</head>
<body>
    <h1>Libreta de Notas</h1>
    <p class="subtitulo">CEBA · {{ $ciclo->nombre }}</p>

    <div class="seccion">Datos del estudiante</div>
    <table>
        <tr><td class="etiqueta">Nombres y apellidos</td><td>{{ $estudiante->nombreCompleto() }}</td></tr>
        <tr><td class="etiqueta">DNI</td><td>{{ $estudiante->dni }}</td></tr>
    </table>

    <div class="seccion">Promedios por curso</div>
    <table>
        <thead>
            <tr><td>Curso</td><td>Promedio</td><td>Escala</td></tr>
        </thead>
        <tbody>
            @forelse ($cursos as $curso)
                <tr>
                    <td>{{ $curso['nombre'] }}</td>
                    <td>{{ $curso['promedio'] !== null ? number_format($curso['promedio'], 2) : '—' }}</td>
                    <td>{{ $curso['letra'] ?? '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="3" class="sin-datos">No hay cursos registrados para este ciclo.</td></tr>
            @endforelse
        </tbody>
    </table>

    @if ($cursos->isNotEmpty())
        <div class="seccion">Desglose mensual</div>
        <table>
            <thead>
                <tr><td>Curso</td><td>Mes</td><td>Promedio</td></tr>
            </thead>
            <tbody>
                @forelse ($cursos as $curso)
                    @forelse ($curso['porMes'] as $mes)
                        <tr>
                            <td>{{ $curso['nombre'] }}</td>
                            <td>{{ ucfirst($mes['mes']) }}</td>
                            <td>{{ number_format($mes['promedio'], 2) }}</td>
                        </tr>
                    @empty
                        <tr><td>{{ $curso['nombre'] }}</td><td colspan="2" class="sin-datos">Sin calificaciones registradas por mes.</td></tr>
                    @endforelse
                @empty
                @endforelse
            </tbody>
        </table>
    @endif

    <p class="codigo">Generada el {{ now()->format('d/m/Y H:i') }}</p>
</body>
</html>
