<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Comprobante - {{ $receiptData['receipt_number'] }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            margin: 0;
            padding: 20px;
            color: #333;
        }

        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #2563eb;
            padding-bottom: 20px;
        }

        .header h1 {
            font-size: 24px;
            margin: 0;
            color: #1e40af;
            font-weight: bold;
        }

        .header p {
            margin: 5px 0;
            font-size: 11px;
            color: #6b7280;
        }

        .receipt-info {
            display: flex;
            justify-content: space-between;
            margin-bottom: 20px;
            background: #f8fafc;
            padding: 15px;
            border-radius: 8px;
        }

        .receipt-info div {
            flex: 1;
        }

        .receipt-info strong {
            color: #374151;
        }

        .patient-info {
            background: #f1f5f9;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .patient-info h3 {
            margin: 0 0 10px 0;
            color: #1e40af;
            font-size: 14px;
        }

        .patient-details {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }

        .transaction-details {
            margin-bottom: 20px;
        }

        .transaction-details h3 {
            margin: 0 0 15px 0;
            color: #1e40af;
            font-size: 14px;
        }

        .details-table {
            width: 100%;
            border-collapse: collapse;
            border: 1px solid #d1d5db;
        }

        .details-table th {
            background: #e5e7eb;
            padding: 10px;
            text-align: left;
            font-weight: bold;
            color: #374151;
        }

        .details-table td {
            padding: 10px;
            border-top: 1px solid #d1d5db;
        }

        .amount-cell {
            text-align: right;
            font-weight: bold;
        }

        .subtotal-row {
            background: #f9fafb;
        }

        .discount-row {
            background: #fef2f2;
            color: #dc2626;
        }

        .tax-row {
            background: #f0f9ff;
        }

        .total-section {
            background: #dbeafe;
            border: 2px solid #3b82f6;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            margin: 20px 0;
        }

        .total-section .total-label {
            font-size: 18px;
            font-weight: bold;
            color: #1e40af;
            margin-bottom: 5px;
        }

        .total-section .total-amount {
            font-size: 24px;
            font-weight: bold;
            color: #1e40af;
        }

        .payment-info {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }

        .payment-info div {
            background: #f8fafc;
            padding: 10px;
            border-radius: 6px;
        }

        .payment-info strong {
            color: #374151;
        }

        .notes {
            background: #f8fafc;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .notes h3 {
            margin: 0 0 10px 0;
            color: #1e40af;
            font-size: 14px;
        }

        .qr-section {
            text-align: center;
            margin: 30px 0;
        }

        .qr-placeholder {
            display: inline-block;
            width: 100px;
            height: 100px;
            background: #e5e7eb;
            border: 2px solid #d1d5db;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 10px;
        }

        .qr-placeholder span {
            font-size: 10px;
            color: #6b7280;
        }

        .footer {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #d1d5db;
            font-size: 10px;
            color: #6b7280;
        }

        .footer p {
            margin: 5px 0;
        }

        @media print {
            body {
                margin: 0;
                padding: 15px;
            }

            .header {
                margin-bottom: 20px;
            }

            .total-section {
                page-break-inside: avoid;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="header">
        <h1>{{ $receiptData['clinic']['name'] }}</h1>
        <p>{{ $receiptData['clinic']['address'] }}</p>
        <p>Tel: {{ $receiptData['clinic']['phone'] }} | RUC: {{ $receiptData['clinic']['ruc'] }}</p>
    </div>

    <!-- Receipt Info -->
    <div class="receipt-info">
        <div>
            <strong>N° Comprobante:</strong><br>
            {{ $receiptData['receipt_number'] }}
        </div>
        <div>
            <strong>Fecha:</strong><br>
            {{ $receiptData['date'] }}
        </div>
    </div>

    <!-- Patient Info -->
    <div class="patient-info">
        <h3>Datos del Paciente</h3>
        <div class="patient-details">
            <div>
                <strong>Nombre:</strong><br>
                {{ $receiptData['transaction']['patient']['name'] }} {{ $receiptData['transaction']['patient']['last_name'] }}
            </div>
            <div>
                <strong>DNI:</strong><br>
                {{ $receiptData['transaction']['patient']['dni'] }}
            </div>
            <div>
                <strong>Email:</strong><br>
                {{ $receiptData['transaction']['patient']['email'] }}
            </div>
            <div>
                <strong>Teléfono:</strong><br>
                {{ $receiptData['transaction']['patient']['phone'] }}
            </div>
        </div>
    </div>

    <!-- Transaction Details -->
    <div class="transaction-details">
        <h3>Detalle de la Transacción</h3>
        <table class="details-table">
            <thead>
                <tr>
                    <th>Descripción</th>
                    <th style="text-align: right;">Monto</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>{{ $receiptData['transaction']['description'] }}</td>
                    <td class="amount-cell">{{ number_format($receiptData['transaction']['amount'], 2) }}</td>
                </tr>

                @if($receiptData['subtotal'] && $receiptData['subtotal'] != $receiptData['transaction']['amount'])
                <tr class="subtotal-row">
                    <td>Subtotal:</td>
                    <td class="amount-cell">{{ number_format($receiptData['subtotal'], 2) }}</td>
                </tr>
                @endif

                @if($receiptData['discount'] > 0)
                <tr class="discount-row">
                    <td>Descuento:</td>
                    <td class="amount-cell">-{{ number_format($receiptData['discount'], 2) }}</td>
                </tr>
                @endif

                @if($receiptData['transaction']['tax_amount'] > 0)
                <tr class="tax-row">
                    <td>IGV (18%):</td>
                    <td class="amount-cell">{{ number_format($receiptData['transaction']['tax_amount'], 2) }}</td>
                </tr>
                @endif
            </tbody>
        </table>
    </div>

    <!-- Total -->
    <div class="total-section">
        <div class="total-label">TOTAL A PAGAR</div>
        <div class="total-amount">S/ {{ number_format($receiptData['total'], 2) }}</div>
    </div>

    <!-- Payment Info -->
    <div class="payment-info">
        <div>
            <strong>Método de Pago:</strong><br>
            {{ $receiptData['payment_method'] }}
        </div>
        <div>
            <strong>Referencia:</strong><br>
            {{ $receiptData['transaction']['reference_number'] ?: 'N/A' }}
        </div>
    </div>

    <!-- Notes -->
    @if($receiptData['transaction']['notes'])
    <div class="notes">
        <h3>Notas</h3>
        <p>{{ $receiptData['transaction']['notes'] }}</p>
    </div>
    @endif

    <!-- QR Code -->
    <div class="qr-section">
        <div class="qr-placeholder">
            <span>QR Code</span>
        </div>
        <p>Código QR para verificación</p>
    </div>

    <!-- Footer -->
    <div class="footer">
        <p>Gracias por su preferencia</p>
        <p>Este comprobante es válido para efectos fiscales</p>
        <p>Generado el {{ date('d/m/Y H:i:s') }}</p>
    </div>
</body>
</html>

