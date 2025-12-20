<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            line-height: 1.4;
            color: #333;
            margin: 0;
            padding: 20px;
        }

        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #333;
            padding-bottom: 20px;
        }

        .title {
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 10px;
        }

        .subtitle {
            font-size: 14px;
            color: #666;
        }

        .info {
            margin-bottom: 20px;
            font-size: 10px;
            color: #666;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
            vertical-align: top;
        }

        th {
            background-color: #f5f5f5;
            font-weight: bold;
            font-size: 11px;
        }

        td {
            font-size: 10px;
        }

        .footer {
            margin-top: 30px;
            text-align: center;
            font-size: 10px;
            color: #666;
            border-top: 1px solid #ddd;
            padding-top: 10px;
        }

        .page-break {
            page-break-before: always;
        }

        .text-center {
            text-align: center;
        }

        .text-right {
            text-align: right;
        }

        .font-bold {
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="title">{{ $title }}</div>
        <div class="subtitle">OdontoSuite - Sistema de Gestión Odontológica</div>
    </div>

    <div class="info">
        <strong>Generado el:</strong> {{ $generatedAt }}<br>
        <strong>Total de registros:</strong> {{ count($data) }}
    </div>

    @if(count($data) > 0)
        <table>
            <thead>
                <tr>
                    @foreach($headings as $heading)
                        <th>{{ $heading }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach($data as $row)
                    <tr>
                        @foreach($headings as $index => $heading)
                            <td>
                                @php
                                    $keys = array_keys($row);
                                    $key = $keys[$index] ?? null;
                                @endphp
                                @if($key !== null && isset($row[$key]))
                                    {{ $row[$key] }}
                                @else
                                    -
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <div class="text-center" style="margin-top: 50px; color: #666;">
            <p>No hay datos disponibles para el período seleccionado.</p>
        </div>
    @endif

    <div class="footer">
        <p>Este reporte fue generado automáticamente por el sistema OdontoSuite.</p>
        <p>Para más información, contacte al administrador del sistema.</p>
    </div>
</body>
</html>
