<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Presupuesto - {{ $quotation->quotation_number }}</title>
    <style>
        body {
            font-family: 'Arial', sans-serif;
            font-size: 12px;
            line-height: 1.4;
            color: #333;
            margin: 0;
            padding: 20px;
        }

        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #2563eb;
            padding-bottom: 20px;
        }

        .clinic-name {
            font-size: 24px;
            font-weight: bold;
            color: #2563eb;
            margin-bottom: 5px;
        }

        .clinic-address {
            font-size: 10px;
            color: #666;
            margin-bottom: 10px;
        }

        .document-title {
            font-size: 18px;
            font-weight: bold;
            color: #1f2937;
            margin-top: 15px;
        }

        .quotation-info {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
        }

        .info-section {
            width: 48%;
        }

        .info-section h3 {
            font-size: 14px;
            font-weight: bold;
            color: #374151;
            margin-bottom: 10px;
            border-bottom: 1px solid #e5e7eb;
            padding-bottom: 5px;
        }

        .info-row {
            display: flex;
            margin-bottom: 5px;
        }

        .info-label {
            font-weight: bold;
            width: 120px;
            color: #6b7280;
        }

        .info-value {
            flex: 1;
            color: #1f2937;
        }

        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }

        .items-table th {
            background-color: #f3f4f6;
            color: #374151;
            font-weight: bold;
            padding: 10px 8px;
            text-align: left;
            border: 1px solid #d1d5db;
        }

        .items-table td {
            padding: 8px;
            border: 1px solid #d1d5db;
            vertical-align: top;
        }

        .items-table .description {
            width: 50%;
        }

        .items-table .quantity {
            width: 15%;
            text-align: center;
        }

        .items-table .unit-price {
            width: 20%;
            text-align: right;
        }

        .items-table .total {
            width: 15%;
            text-align: right;
            font-weight: bold;
        }

        .totals-section {
            margin-top: 20px;
            margin-left: auto;
            width: 300px;
        }

        .totals-table {
            width: 100%;
            border-collapse: collapse;
        }

        .totals-table td {
            padding: 8px 12px;
            border: 1px solid #d1d5db;
        }

        .totals-table .label {
            background-color: #f9fafb;
            font-weight: bold;
            text-align: right;
            width: 60%;
        }

        .totals-table .amount {
            text-align: right;
            font-weight: bold;
            width: 40%;
        }

        .total-row {
            background-color: #f3f4f6;
            font-size: 14px;
            font-weight: bold;
        }

        .terms-section {
            margin-top: 30px;
            page-break-inside: avoid;
        }

        .terms-section h3 {
            font-size: 14px;
            font-weight: bold;
            color: #374151;
            margin-bottom: 10px;
        }

        .terms-content {
            font-size: 10px;
            line-height: 1.4;
            color: #6b7280;
            border: 1px solid #e5e7eb;
            padding: 10px;
            background-color: #f9fafb;
        }

        .validity-info {
            margin-top: 20px;
            padding: 10px;
            background-color: #fef3c7;
            border: 1px solid #f59e0b;
            border-radius: 4px;
        }

        .validity-info strong {
            color: #92400e;
        }

        .footer {
            margin-top: 40px;
            text-align: center;
            font-size: 10px;
            color: #6b7280;
            border-top: 1px solid #e5e7eb;
            padding-top: 20px;
        }

        .page-break {
            page-break-before: always;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="header">
        <div class="clinic-name">OdontoSuite</div>
        <div class="clinic-address">
            Av. Principal 123, Lima, Perú<br>
            Tel: (01) 234-5678 | Email: info@odontosuite.com
        </div>
        <div class="document-title">PRESUPUESTO DENTAL</div>
    </div>

    <!-- Información del presupuesto y paciente -->
    <div class="quotation-info">
        <div class="info-section">
            <h3>Información del Presupuesto</h3>
            <div class="info-row">
                <span class="info-label">Número:</span>
                <span class="info-value">{{ $quotation->quotation_number }}</span>
            </div>
            <div class="info-row">
                <span class="info-label">Fecha:</span>
                <span class="info-value">{{ \Carbon\Carbon::parse($quotation->quotation_date)->format('d/m/Y') }}</span>
            </div>
            <div class="info-row">
                <span class="info-label">Válido hasta:</span>
                <span class="info-value">{{ \Carbon\Carbon::parse($quotation->valid_until)->format('d/m/Y') }}</span>
            </div>
            <div class="info-row">
                <span class="info-label">Estado:</span>
                <span class="info-value">
                    @switch($quotation->status)
                        @case('draft')
                            Borrador
                            @break
                        @case('sent')
                            Enviado
                            @break
                        @case('approved')
                            Aprobado
                            @break
                        @case('rejected')
                            Rechazado
                            @break
                        @default
                            {{ ucfirst($quotation->status) }}
                    @endswitch
                </span>
            </div>
        </div>

        <div class="info-section">
            <h3>Datos del Paciente</h3>
            <div class="info-row">
                <span class="info-label">Nombre:</span>
                <span class="info-value">{{ $quotation->patient->first_name }} {{ $quotation->patient->last_name }}</span>
            </div>
            <div class="info-row">
                <span class="info-label">Email:</span>
                <span class="info-value">{{ $quotation->patient->email ?? 'No especificado' }}</span>
            </div>
            <div class="info-row">
                <span class="info-label">Teléfono:</span>
                <span class="info-value">{{ $quotation->patient->phone ?? 'No especificado' }}</span>
            </div>
            <div class="info-row">
                <span class="info-label">Dirección:</span>
                <span class="info-value">{{ $quotation->patient->address ?? 'No especificada' }}</span>
            </div>
        </div>
    </div>

    <!-- Tabla de procedimientos -->
    <table class="items-table">
        <thead>
            <tr>
                <th class="description">Descripción</th>
                <th class="quantity">Cantidad</th>
                <th class="unit-price">Precio Unit.</th>
                <th class="total">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($quotation->items as $item)
            <tr>
                <td class="description">{{ $item->description }}</td>
                <td class="quantity">{{ number_format($item->quantity, 0) }}</td>
                <td class="unit-price">S/ {{ number_format($item->unit_price, 2) }}</td>
                <td class="total">S/ {{ number_format($item->total_price, 2) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <!-- Totales -->
    <div class="totals-section">
        <table class="totals-table">
            <tr>
                <td class="label">Subtotal:</td>
                <td class="amount">S/ {{ number_format($quotation->subtotal, 2) }}</td>
            </tr>
            @if($quotation->discount_amount > 0)
            <tr>
                <td class="label">Descuento ({{ $quotation->discount_percentage }}%):</td>
                <td class="amount">- S/ {{ number_format($quotation->discount_amount, 2) }}</td>
            </tr>
            @endif
            @if($quotation->tax_amount > 0)
            <tr>
                <td class="label">IGV ({{ $quotation->tax_percentage }}%):</td>
                <td class="amount">S/ {{ number_format($quotation->tax_amount, 2) }}</td>
            </tr>
            @endif
            <tr class="total-row">
                <td class="label">TOTAL:</td>
                <td class="amount">S/ {{ number_format($quotation->total_amount, 2) }}</td>
            </tr>
        </table>
    </div>

    <!-- Términos y condiciones -->
    @if($quotation->terms_conditions)
    <div class="terms-section">
        <h3>Términos y Condiciones</h3>
        <div class="terms-content">
            {!! nl2br(e($quotation->terms_conditions)) !!}
        </div>
    </div>
    @endif

    <!-- Notas adicionales -->
    @if($quotation->notes)
    <div class="terms-section">
        <h3>Notas Adicionales</h3>
        <div class="terms-content">
            {!! nl2br(e($quotation->notes)) !!}
        </div>
    </div>
    @endif

    <!-- Información de validez -->
    <div class="validity-info">
        <strong>Importante:</strong> Este presupuesto es válido hasta el {{ \Carbon\Carbon::parse($quotation->valid_until)->format('d/m/Y') }}.
        Los precios pueden variar después de esta fecha.
    </div>

    <!-- Footer -->
    <div class="footer">
        <p>Este documento fue generado el {{ now()->format('d/m/Y H:i') }} por el sistema OdontoSuite</p>
        <p>Para consultas, contactar al (01) 234-5678 o info@odontosuite.com</p>
    </div>
</body>
</html>
