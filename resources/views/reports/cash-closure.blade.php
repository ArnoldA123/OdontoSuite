<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Reporte de Cierre de Caja</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 12px; }
        .header { text-align: center; margin-bottom: 20px; }
        .section { margin-bottom: 15px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        .total { font-weight: bold; background-color: #e9ecef; }
    </style>
</head>
<body>
    <div class="header">
        <h2>Reporte de Cierre de Caja</h2>
        <p>Sesión #{{ $session->id }} - {{ $session->branch->name }}</p>
        <p>Usuario: {{ $session->user->name }}</p>
        <p>Fecha: {{ $session->closed_at->format('d/m/Y H:i') }}</p>
    </div>

    <div class="section">
        <h3>Resumen de Caja</h3>
        <table>
            <tr>
                <td>Monto de Apertura:</td>
                <td>S/ {{ number_format($session->opening_amount, 2) }}</td>
            </tr>
            <tr>
                <td>Total Ingresos:</td>
                <td>S/ {{ number_format($summary['total_income'] ?? 0, 2) }}</td>
            </tr>
            <tr>
                <td>Total Egresos:</td>
                <td>S/ {{ number_format($summary['total_expenses'] ?? 0, 2) }}</td>
            </tr>
            <tr class="total">
                <td>Monto Esperado:</td>
                <td>S/ {{ number_format($session->expected_amount, 2) }}</td>
            </tr>
            <tr class="total">
                <td>Monto Real:</td>
                <td>S/ {{ number_format($session->closing_amount, 2) }}</td>
            </tr>
            <tr class="total">
                <td>Diferencia:</td>
                <td>S/ {{ number_format($session->difference_amount, 2) }}</td>
            </tr>
        </table>
    </div>

    <div class="section">
        <h3>Transacciones</h3>
        <table>
            <thead>
                <tr>
                    <th>Hora</th>
                    <th>Paciente</th>
                    <th>Método de Pago</th>
                    <th>Monto</th>
                </tr>
            </thead>
            <tbody>
                @foreach($session->transactions as $transaction)
                <tr>
                    <td>{{ $transaction->created_at->format('H:i') }}</td>
                    <td>{{ $transaction->patient->full_name ?? 'N/A' }}</td>
                    <td>{{ $transaction->paymentMethod->name }}</td>
                    <td>S/ {{ number_format($transaction->amount, 2) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    @if($session->notes)
    <div class="section">
        <h3>Notas de Cierre</h3>
        <p>{{ $session->notes }}</p>
    </div>
    @endif

    <div class="section">
        <p style="text-align: center; margin-top: 40px;">
            _______________________________<br>
            Firma del Responsable
        </p>
    </div>
</body>
</html>
