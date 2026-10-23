<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Registro de atención - {{ $encounter->id }}</title>
    <style>
        body {
            font-family: 'Arial', sans-serif;
            font-size: 11px;
            line-height: 1.35;
            color: #333;
            margin: 0;
            padding: 18px;
        }

        .header {
            text-align: center;
            border-bottom: 2px solid #2563eb;
            padding-bottom: 10px;
            margin-bottom: 14px;
        }

        .clinic-name {
            font-size: 20px;
            font-weight: bold;
            color: #2563eb;
            margin-bottom: 2px;
        }

        .document-title {
            font-size: 13px;
            font-weight: bold;
            color: #1f2937;
            margin-top: 4px;
        }

        .document-note {
            font-size: 9px;
            color: #6b7280;
        }

        .section {
            margin-bottom: 12px;
        }

        .section-title {
            font-size: 11px;
            font-weight: bold;
            color: #1f2937;
            background: #f3f4f6;
            border-left: 3px solid #2563eb;
            padding: 3px 6px;
            margin-bottom: 6px;
        }

        .info-table {
            width: 100%;
            border-collapse: collapse;
        }

        .info-table td {
            padding: 2px 4px;
            vertical-align: top;
        }

        .info-label {
            width: 28%;
            color: #6b7280;
            font-weight: bold;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
        }

        .data-table th {
            background: #f3f4f6;
            color: #374151;
            font-size: 10px;
            text-align: left;
            padding: 4px 6px;
            border: 1px solid #d1d5db;
        }

        .data-table td {
            padding: 4px 6px;
            border: 1px solid #d1d5db;
            vertical-align: top;
        }

        .text-block {
            border: 1px solid #d1d5db;
            padding: 6px;
            white-space: pre-line;
        }

        .empty {
            color: #9ca3af;
            font-style: italic;
        }

        .signature {
            margin-top: 26px;
            page-break-inside: avoid;
        }

        .signature-box {
            width: 52%;
            border-top: 1px solid #374151;
            padding-top: 4px;
            margin-left: auto;
            text-align: center;
        }

        .signature-name {
            font-weight: bold;
        }

        .signature-meta {
            font-size: 9px;
            color: #4b5563;
        }

        .verification {
            font-size: 8px;
            color: #6b7280;
            word-break: break-all;
            margin-top: 10px;
        }

        .footer {
            margin-top: 16px;
            border-top: 1px solid #e5e7eb;
            padding-top: 6px;
            font-size: 8px;
            color: #6b7280;
            text-align: center;
        }
    </style>
</head>
<body>
@php
    $patient = $encounter->patient;
    $professional = $encounter->user;
    $record = $encounter->medicalRecord;
    $evolution = $encounter->clinicalEvolutions->first();
    $signer = $encounter->signedBy ?? $professional;
    $license = $encounter->signer_license ?: $signer?->professional_license;
    $age = $patient?->birth_date?->age;
    $modes = [
        'consultation' => 'Consulta',
        'execution' => 'Ejecución de procedimiento',
        'plan_session' => 'Sesión de plan de tratamiento',
        'urgency' => 'Urgencia sin cita',
    ];
    $diagnosisTypes = [
        'presumptive' => 'Presuntivo',
        'definitive' => 'Definitivo',
        'rule_out' => 'A descartar',
    ];
@endphp

<div class="header">
    <div class="clinic-name">OdontoSuite</div>
    <div class="document-title">REGISTRO DE ATENCIÓN ODONTOLÓGICA</div>
    <div class="document-note">
        Documento conforme a la NTS N° 139-MINSA/2018 y a la NTS N° 188-MINSA/DGIESP-2022
    </div>
</div>

<div class="section">
    <div class="section-title">I. Datos del paciente</div>
    <table class="info-table">
        <tr>
            <td class="info-label">Paciente</td>
            <td>{{ $patient?->full_name ?? '—' }}</td>
            <td class="info-label">Documento</td>
            <td>{{ $patient?->document_number ?? '—' }}</td>
        </tr>
        <tr>
            <td class="info-label">Historia clínica</td>
            <td>{{ $record?->record_number ?? '—' }}</td>
            <td class="info-label">Edad</td>
            <td>{{ $age !== null ? $age.' años' : '—' }}</td>
        </tr>
        <tr>
            <td class="info-label">Sexo</td>
            <td>{{ $patient?->gender ?? '—' }}</td>
            <td class="info-label">Fecha de nacimiento</td>
            <td>{{ $patient?->birth_date?->format('d/m/Y') ?? '—' }}</td>
        </tr>
    </table>
</div>

<div class="section">
    <div class="section-title">II. Datos de la atención</div>
    <table class="info-table">
        <tr>
            <td class="info-label">Fecha y hora de apertura</td>
            <td>{{ $encounter->opened_at?->format('d/m/Y H:i') ?? '—' }}</td>
            <td class="info-label">Fecha y hora de cierre</td>
            <td>{{ $encounter->closed_at?->format('d/m/Y H:i') ?? '—' }}</td>
        </tr>
        <tr>
            <td class="info-label">Profesional</td>
            <td>{{ $professional?->name ?? '—' }}</td>
            <td class="info-label">Colegiatura</td>
            <td>{{ $license ?: '—' }}</td>
        </tr>
        <tr>
            <td class="info-label">Modalidad</td>
            <td>{{ $modes[$encounter->mode] ?? $encounter->mode }}</td>
            <td class="info-label">Registro</td>
            <td>#{{ $encounter->id }}</td>
        </tr>
    </table>
</div>

<div class="section">
    <div class="section-title">III. Motivo de consulta</div>
    <div class="text-block">{{ $encounter->reason_for_consultation ?: '—' }}</div>
</div>

<div class="section">
    <div class="section-title">IV. Examen clínico</div>
    <div class="text-block">{{ $evolution?->objective ?: '—' }}</div>
</div>

<div class="section">
    <div class="section-title">V. Diagnóstico (CIE-10)</div>
    @if ($encounter->diagnoses->isEmpty())
        <div class="text-block empty">Sin diagnóstico codificado.</div>
    @else
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width: 14%">Código</th>
                    <th style="width: 52%">Descripción</th>
                    <th style="width: 16%">Tipo</th>
                    <th style="width: 18%">Observación</th>
                </tr>
            </thead>
            <tbody>
            @foreach ($encounter->diagnoses as $diagnosis)
                <tr>
                    <td>{{ $diagnosis->cie_code }}</td>
                    <td>{{ $diagnosis->name }}</td>
                    <td>{{ $diagnosisTypes[$diagnosis->type] ?? $diagnosis->type }}</td>
                    <td>{{ $diagnosis->notes ?: '—' }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @endif
</div>

<div class="section">
    <div class="section-title">VI. Tratamiento</div>
    <div class="text-block">{{ $evolution?->plan ?: '—' }}</div>
    @if ($evolution?->procedures_performed)
        <p><strong>Procedimientos realizados:</strong> {{ $evolution->procedures_performed }}</p>
    @endif
    @if ($evolution?->prescriptions)
        <p><strong>Prescripciones:</strong> {{ $evolution->prescriptions }}</p>
    @endif
    @if ($evolution?->recommendations)
        <p><strong>Recomendaciones:</strong> {{ $evolution->recommendations }}</p>
    @endif
    @if ($encounter->treatmentPlan?->items?->isNotEmpty())
        <table class="data-table" style="margin-top: 6px;">
            <thead>
                <tr>
                    <th style="width: 56%">Procedimiento del plan</th>
                    <th style="width: 12%">Pieza</th>
                    <th style="width: 16%">Estado</th>
                    <th style="width: 16%">Cantidad</th>
                </tr>
            </thead>
            <tbody>
            @foreach ($encounter->treatmentPlan->items as $item)
                <tr>
                    <td>{{ $item->procedure_name }}</td>
                    <td>{{ $item->dentalPiece?->fdi_number ?? $item->dental_piece_id ?? '—' }}</td>
                    <td>{{ $item->status }}</td>
                    <td>{{ $item->quantity }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @endif
    @if ($encounter->procedureMaterials->isNotEmpty())
        <table class="data-table" style="margin-top: 6px;">
            <thead>
                <tr>
                    <th style="width: 60%">Material consumido</th>
                    <th style="width: 20%">Cantidad</th>
                    <th style="width: 20%">Lote</th>
                </tr>
            </thead>
            <tbody>
            @foreach ($encounter->procedureMaterials as $material)
                <tr>
                    <td>{{ $material->product?->name ?? 'Insumo #'.$material->product_id }}</td>
                    <td>{{ rtrim(rtrim(number_format((float) $material->quantity_used, 3, '.', ''), '0'), '.') }}</td>
                    <td>{{ $material->batch_number ?: '—' }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @endif
</div>

<div class="section">
    <div class="section-title">VII. Próxima cita</div>
    @if ($nextAppointment !== null)
        <div class="text-block">
            {{ $nextAppointment->preferred_from?->format('d/m/Y') ?? 'Fecha por coordinar' }}
            @if ($nextAppointment->preferred_from)
                {{ $nextAppointment->preferred_from->format('H:i') }}
            @endif
            @if ($nextAppointment->notes)
                — {{ $nextAppointment->notes }}
            @endif
        </div>
    @else
        <div class="text-block">{{ $evolution?->next_appointment_notes ?: 'Sin próxima cita indicada.' }}</div>
    @endif
</div>

<div class="section">
    <div class="section-title">VIII. Odontograma (hallazgos del acto)</div>
    @if ($encounter->odontogramRecords->isEmpty())
        <div class="text-block empty">Sin hallazgos registrados en este acto.</div>
    @else
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width: 12%">Pieza</th>
                    <th style="width: 14%">Superficie</th>
                    <th style="width: 40%">Hallazgo</th>
                    <th style="width: 34%">Diagnóstico</th>
                </tr>
            </thead>
            <tbody>
            @foreach ($encounter->odontogramRecords as $finding)
                <tr>
                    <td>{{ $finding->dentalPiece?->fdi_number ?? $finding->dental_piece_id ?? '—' }}</td>
                    <td>{{ $finding->toothSurface?->code ?? '—' }}</td>
                    <td>{{ $finding->condition_name ?: ($finding->condition_code ?: '—') }}</td>
                    <td>{{ $finding->diagnosis ?: '—' }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @endif
</div>

<div class="signature">
    <div class="signature-box">
        <div class="signature-name">{{ $signer?->name ?? '—' }}</div>
        <div class="signature-meta">
            Profesional responsable
            @if ($license)
                · Colegiatura {{ $license }}
            @endif
        </div>
        <div class="signature-meta">
            Firmado el {{ $encounter->signed_at?->format('d/m/Y H:i') ?? '—' }}
        </div>
    </div>
</div>

<div class="verification">
    Verificación de integridad:
    {{ $encounter->signature_hash ?: 'registro sin firma registrada' }}
    @if ($encounter->signature_hash)
        — {{ $signatureVerified ? 'el contenido coincide con lo firmado.' : 'el contenido no coincide con lo firmado.' }}
    @endif
</div>

<div class="footer">
    Documento generado el {{ now()->format('d/m/Y H:i') }} por el sistema OdontoSuite.
</div>
</body>
</html>
