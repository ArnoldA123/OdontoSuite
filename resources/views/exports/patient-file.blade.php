<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ficha del Paciente - {{ $patient->first_name ?? '' }} {{ $patient->last_name ?? '' }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 10pt;
            color: #333;
            line-height: 1.4;
        }
        .header {
            background-color: #2563eb;
            color: white;
            padding: 20px;
            text-align: center;
            margin-bottom: 20px;
        }
        .header h1 {
            font-size: 18pt;
            margin-bottom: 5px;
        }
        .header p {
            font-size: 9pt;
        }
        .section {
            margin-bottom: 20px;
            page-break-inside: avoid;
        }
        .section-title {
            background-color: #f3f4f6;
            padding: 8px 12px;
            font-weight: bold;
            font-size: 11pt;
            border-left: 4px solid #2563eb;
            margin-bottom: 10px;
        }
        .info-grid {
            display: table;
            width: 100%;
            margin-bottom: 15px;
        }
        .info-row {
            display: table-row;
        }
        .info-label {
            display: table-cell;
            font-weight: bold;
            padding: 5px 10px;
            width: 30%;
            vertical-align: top;
        }
        .info-value {
            display: table-cell;
            padding: 5px 10px;
            width: 70%;
        }
        .table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }
        .table th {
            background-color: #f3f4f6;
            padding: 8px;
            text-align: left;
            font-weight: bold;
            border: 1px solid #ddd;
        }
        .table td {
            padding: 6px 8px;
            border: 1px solid #ddd;
        }
        .table tr:nth-child(even) {
            background-color: #f9fafb;
        }
        .badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 3px;
            font-size: 8pt;
            font-weight: bold;
        }
        .badge-success {
            background-color: #10b981;
            color: white;
        }
        .badge-warning {
            background-color: #f59e0b;
            color: white;
        }
        .badge-danger {
            background-color: #ef4444;
            color: white;
        }
        .text-center {
            text-align: center;
        }
        .text-right {
            text-align: right;
        }
        .mt-10 {
            margin-top: 10px;
        }
        .mb-10 {
            margin-bottom: 10px;
        }
        .alert {
            padding: 10px;
            margin: 10px 0;
            border-left: 4px solid #f59e0b;
            background-color: #fef3c7;
        }
        .footer {
            margin-top: 30px;
            padding-top: 10px;
            border-top: 1px solid #ddd;
            font-size: 8pt;
            color: #666;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>FICHA CLÍNICA DEL PACIENTE</h1>
        <p>Generado el: {{ $generatedAt }}</p>
    </div>

    <!-- Datos Personales -->
    <div class="section">
        <div class="section-title">DATOS PERSONALES</div>
        <div class="info-grid">
            <div class="info-row">
                <div class="info-label">Nombre Completo:</div>
                <div class="info-value">{{ $patient->first_name }} {{ $patient->last_name }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">DNI:</div>
                <div class="info-value">{{ $patient->document_number ?? 'N/A' }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Email:</div>
                <div class="info-value">{{ $patient->email ?? 'N/A' }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Teléfono:</div>
                <div class="info-value">{{ $patient->phone ?? 'N/A' }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Fecha de Nacimiento:</div>
                <div class="info-value">{{ $patient->birth_date ? $patient->birth_date->format('d/m/Y') : 'N/A' }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Género:</div>
                <div class="info-value">{{ ucfirst($patient->gender ?? 'N/A') }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Dirección:</div>
                <div class="info-value">{{ $patient->address ?? 'N/A' }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Contacto de Emergencia:</div>
                <div class="info-value">
                    @if($patient->emergency_contact_name)
                        {{ $patient->emergency_contact_name }} - {{ $patient->emergency_contact_phone }}
                    @else
                        N/A
                    @endif
                </div>
            </div>
            <div class="info-row">
                <div class="info-label">Estado:</div>
                <div class="info-value">
                    <span class="badge {{ $patient->is_active ? 'badge-success' : 'badge-danger' }}">
                        {{ $patient->is_active ? 'Activo' : 'Inactivo' }}
                    </span>
                </div>
            </div>
        </div>
    </div>

    <!-- Antecedentes Médicos -->
    @if($patient->medical_history || $patient->allergies)
    <div class="section">
        <div class="section-title">ANTECEDENTES MÉDICOS</div>
        @if($patient->allergies)
        <div class="alert">
            <strong>ALERGIAS:</strong> {{ $patient->allergies }}
        </div>
        @endif
        @if($patient->medical_history)
        <div class="info-grid">
            <div class="info-row">
                <div class="info-label">Historial Médico:</div>
                <div class="info-value">{{ $patient->medical_history }}</div>
            </div>
        </div>
        @endif
    </div>
    @endif

    <!-- Odontograma -->
    @if($patient->odontograms && $patient->odontograms->count() > 0)
    <div class="section">
        <div class="section-title">ODONTOGRAMA</div>
        @foreach($patient->odontograms as $odontogram)
        <div class="mb-10">
            <p><strong>Versión:</strong> {{ $odontogram->version }} | <strong>Fecha:</strong> {{ $odontogram->created_at->format('d/m/Y') }}</p>
            @if($odontogram->records && $odontogram->records->count() > 0)
            <table class="table">
                <thead>
                    <tr>
                        <th>Pieza Dental (FDI)</th>
                        <th>Superficie</th>
                        <th>Condición</th>
                        <th>Diagnóstico</th>
                        <th>Fecha</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($odontogram->records as $record)
                    <tr>
                        <td>{{ $record->dentalPiece->fdi_number ?? 'N/A' }} - {{ $record->dentalPiece->name ?? 'N/A' }}</td>
                        <td>{{ $record->toothSurface->surface_name ?? 'N/A' }}</td>
                        <td>{{ $record->condition_name }} ({{ $record->condition_code }})</td>
                        <td>{{ $record->diagnosis ?? 'N/A' }}</td>
                        <td>{{ $record->created_at->format('d/m/Y') }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            @endif
        </div>
        @endforeach
    </div>
    @endif

    <!-- Citas -->
    @if($patient->appointments && $patient->appointments->count() > 0)
    <div class="section">
        <div class="section-title">HISTORIAL DE CITAS (Últimas {{ $patient->appointments->count() }})</div>
        <table class="table">
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Hora</th>
                    <th>Tipo</th>
                    <th>Profesional</th>
                    <th>Estado</th>
                </tr>
            </thead>
            <tbody>
                @foreach($patient->appointments as $appointment)
                <tr>
                    <td>{{ $appointment->scheduled_at->format('d/m/Y') }}</td>
                    <td>{{ $appointment->scheduled_at->format('H:i') }}</td>
                    <td>{{ $appointment->appointmentType->name ?? 'N/A' }}</td>
                    <td>{{ $appointment->user->name ?? 'N/A' }}</td>
                    <td>{{ ucfirst($appointment->status) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    <!-- Planes de Tratamiento -->
    @if($patient->treatmentPlans && $patient->treatmentPlans->count() > 0)
    <div class="section">
        <div class="section-title">PLANES DE TRATAMIENTO</div>
        @foreach($patient->treatmentPlans as $plan)
        <div class="mb-10">
            <p><strong>{{ $plan->title }}</strong> - {{ $plan->plan_number }}</p>
            <p><strong>Estado:</strong> {{ ucfirst($plan->status) }} | <strong>Costo Total:</strong> S/ {{ number_format($plan->final_cost ?? $plan->total_cost, 2) }}</p>
            @if($plan->description)
            <p>{{ $plan->description }}</p>
            @endif
        </div>
        @endforeach
    </div>
    @endif

    <!-- Presupuestos -->
    @if($patient->quotations && $patient->quotations->count() > 0)
    <div class="section">
        <div class="section-title">PRESUPUESTOS</div>
        <table class="table">
            <thead>
                <tr>
                    <th>Número</th>
                    <th>Fecha</th>
                    <th>Total</th>
                    <th>Estado</th>
                </tr>
            </thead>
            <tbody>
                @foreach($patient->quotations as $quotation)
                <tr>
                    <td>{{ $quotation->quotation_number }}</td>
                    <td>{{ $quotation->quotation_date->format('d/m/Y') }}</td>
                    <td class="text-right">S/ {{ number_format($quotation->total_amount, 2) }}</td>
                    <td>{{ ucfirst($quotation->status) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    <!-- Historias Clínicas -->
    @if($patient->medicalRecords && $patient->medicalRecords->count() > 0)
    <div class="section">
        <div class="section-title">HISTORIAS CLÍNICAS</div>
        @foreach($patient->medicalRecords as $record)
        <div class="mb-10">
            <p><strong>HC-{{ $record->record_number ?? $record->id }}</strong> - Primera visita: {{ $record->first_visit_date->format('d/m/Y') }}</p>
            @if($record->chief_complaint)
            <p><strong>Motivo de consulta:</strong> {{ $record->chief_complaint }}</p>
            @endif
            @if($record->diagnosis)
            <p><strong>Diagnóstico:</strong> {{ $record->diagnosis }}</p>
            @endif
            @if($record->evolutions && $record->evolutions->count() > 0)
            <p><strong>Evoluciones:</strong> {{ $record->evolutions->count() }} registro(s)</p>
            @endif
        </div>
        @endforeach
    </div>
    @endif

    <!-- Registros de Especialidades -->
    @if(($patient->endodonticsRecords && $patient->endodonticsRecords->count() > 0) ||
        ($patient->implantologyRecords && $patient->implantologyRecords->count() > 0) ||
        ($patient->orthodonticsRecords && $patient->orthodonticsRecords->count() > 0) ||
        ($patient->rehabilitationRecords && $patient->rehabilitationRecords->count() > 0) ||
        ($patient->oralSurgeryRecords && $patient->oralSurgeryRecords->count() > 0))
    <div class="section">
        <div class="section-title">REGISTROS DE ESPECIALIDADES</div>
        @if($patient->endodonticsRecords && $patient->endodonticsRecords->count() > 0)
        <p><strong>Endodoncia:</strong> {{ $patient->endodonticsRecords->count() }} registro(s)</p>
        @endif
        @if($patient->implantologyRecords && $patient->implantologyRecords->count() > 0)
        <p><strong>Implantología:</strong> {{ $patient->implantologyRecords->count() }} registro(s)</p>
        @endif
        @if($patient->orthodonticsRecords && $patient->orthodonticsRecords->count() > 0)
        <p><strong>Ortodoncia:</strong> {{ $patient->orthodonticsRecords->count() }} registro(s)</p>
        @endif
        @if($patient->rehabilitationRecords && $patient->rehabilitationRecords->count() > 0)
        <p><strong>Rehabilitación:</strong> {{ $patient->rehabilitationRecords->count() }} registro(s)</p>
        @endif
        @if($patient->oralSurgeryRecords && $patient->oralSurgeryRecords->count() > 0)
        <p><strong>Cirugía Oral:</strong> {{ $patient->oralSurgeryRecords->count() }} registro(s)</p>
        @endif
    </div>
    @endif

    <!-- Historial de Auditoría -->
    @if($patient->auditLogs && $patient->auditLogs->count() > 0)
    <div class="section">
        <div class="section-title">HISTORIAL DE CAMBIOS</div>
        <table class="table">
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Usuario</th>
                    <th>Acción</th>
                    <th>Cambios</th>
                </tr>
            </thead>
            <tbody>
                @foreach($patient->auditLogs as $log)
                <tr>
                    <td>{{ $log->created_at->format('d/m/Y H:i') }}</td>
                    <td>{{ $log->user->name ?? 'N/A' }}</td>
                    <td>{{ ucfirst(str_replace('_', ' ', $log->action)) }}</td>
                    <td>
                        @php
                            $changes = $log->getChanges();
                        @endphp
                        @if(count($changes) > 0)
                            {{ count($changes) }} campo(s) modificado(s)
                        @else
                            Sin cambios registrados
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    <!-- Notas -->
    @if($patient->notes)
    <div class="section">
        <div class="section-title">NOTAS ADICIONALES</div>
        <p>{{ $patient->notes }}</p>
    </div>
    @endif

    <div class="footer">
        <p>Este documento fue generado automáticamente por el sistema OdontoSuite</p>
        <p>Paciente ID: {{ $patient->id }} | Generado el: {{ $generatedAt }}</p>
    </div>
</body>
</html>

