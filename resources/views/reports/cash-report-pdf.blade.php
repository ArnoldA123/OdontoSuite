<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Reporte de Caja - Período</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 11px; }
        .header { text-align: center; margin-bottom: 20px; border-bottom: 2px solid #333; padding-bottom: 10px; }
        .section { margin-bottom: 20px; page-break-inside: avoid; }
        .section-title { font-size: 14px; font-weight: bold; margin-bottom: 10px; background-color: #f0f0f0; padding: 5px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #ddd; padding: 6px; text-align: left; font-size: 10px; }
        th { background-color: #e9ecef; font-weight: bold; }
        .summary-box { background-color: #f8f9fa; padding: 10px; border-radius: 5px; margin-bottom: 15px; }
        .summary-item { display: inline-block; width: 48%; margin-bottom: 8px; }
        .total-row { background-color: #d1ecf1; font-weight: bold; }
        .footer { text-align: center; margin-top: 30px; font-size: 9px; color: #666; }
    </style>
</head>
<body>
    <div class="header">
        <h2>REPORTE DE CAJA - PERÍODO</h2>
        <p>Del {{ \Carbon\Carbon::parse($report['period']['start_date'])->format('d/m/Y') }}
           al {{ \Carbon\Carbon::parse($report['period']['end_date'])->format('d/m/Y') }}</p>
        <p>Generado: {{ now()->format('d/m/Y H:i:s') }}</p>
    </div>

    <div class="section">
        <div class="section-title">RESUMEN EJECUTIVO</div>
        <div class="summary-box">
            <div class="summary-item">
                <strong>Total Sesiones:</strong> {{ $report['sessions_count'] }}
            </div>
            <div class="summary-item">
                <strong>Promedio Diferencia:</strong> S/ {{ number_format($report['average_difference'] ?? 0, 2) }}
            </div>
            <div class="summary-item">
                <strong>Total Ingresos:</strong> S/ {{ number_format($report['total_income'] ?? 0, 2) }}
            </div>
            <div class="summary-item">
                <strong>Total Egresos:</strong> S/ {{ number_format($report['total_expenses'] ?? 0, 2) }}
            </div>
        </div>
    </div>

    <div class="section">
        <div class="section-title">RESUMEN DIARIO</div>
        <table>
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Sesiones</th>
                    <th>Apertura</th>
                    <th>Cierre</th>
                    <th>Diferencia</th>
                </tr>
            </thead>
            <tbody>
                @foreach($report['daily_summary'] ?? [] as $day)
                <tr>
                    <td>{{ \Carbon\Carbon::parse($day['date'])->format('d/m/Y') }}</td>
                    <td>{{ $day['sessions_count'] }}</td>
                    <td>S/ {{ number_format($day['total_opening'], 2) }}</td>
                    <td>S/ {{ number_format($day['total_closing'], 2) }}</td>
                    <td>S/ {{ number_format($day['total_difference'], 2) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="section">
        <div class="section-title">ANÁLISIS POR MÉTODO DE PAGO</div>
        <table>
            <thead>
                <tr>
                    <th>Método de Pago</th>
                    <th>Cantidad</th>
                    <th>Total</th>
                    <th>Promedio</th>
                </tr>
            </thead>
            <tbody>
                @foreach($report['payment_methods_analysis'] ?? [] as $method)
                <tr>
                    <td>{{ $method['payment_method'] }}</td>
                    <td>{{ $method['count'] }}</td>
                    <td>S/ {{ number_format($method['total'], 2) }}</td>
                    <td>S/ {{ number_format($method['average'], 2) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="footer">
        <p>Este reporte fue generado automáticamente por el Sistema OdontoSuite</p>
        <p>Página 1 de 1</p>
    </div>
</body>
</html>



























