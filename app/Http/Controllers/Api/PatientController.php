<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Patient;
use App\Models\AuditLog;
use App\Services\PatientExportService;
use App\Events\PatientCreated;
use App\Events\PatientUpdated;
use App\Events\PatientDeleted;
use App\Listeners\ClearDashboardCache;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class PatientController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        // Construir query base para contar totales (sin filtros de búsqueda)
        $baseQuery = Patient::query();

        // Aplicar filtro de búsqueda si existe
        $searchQuery = Patient::select([
            'id',
            'first_name',
            'last_name',
            'document_number',
            'email',
            'phone',
            'birth_date',
            'gender',
            'is_active',
            'branch_id',
            'created_at',
            'updated_at'
        ]);

        // Search by name, email, phone or document_number (DNI)
        if ($request->has('search')) {
            $search = $request->get('search');
            $searchQuery->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('document_number', 'like', "%{$search}%");
            });

            // Aplicar misma búsqueda a baseQuery para contar totales
            $baseQuery->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('document_number', 'like', "%{$search}%");
            });
        }

        // Multi-tenant: filtrar por branch_id si se envía
        if ($request->has('branch_id')) {
            $searchQuery->where('branch_id', $request->input('branch_id'));
            $baseQuery->where('branch_id', $request->input('branch_id'));
        }

        // Calcular totales de activos e inactivos (antes de aplicar filtro de estado)
        $totalActive = (clone $baseQuery)->where('is_active', true)->count();
        $totalInactive = (clone $baseQuery)->where('is_active', false)->count();

        // Filter by active status - Solo aplicar filtro si se envía explícitamente
        // Por defecto mostrar TODOS los pacientes (activos e inactivos)
        if ($request->has('active')) {
            $searchQuery->where('is_active', $request->boolean('active'));
        }

        $perPage = $request->get('per_page', 15);
        $perPage = min($perPage, 100); // Limit max per page to 100

        $patients = $searchQuery->orderBy('last_name')->orderBy('first_name')->paginate($perPage);

        return response()->json([
            'data' => $patients->items(),
            'meta' => [
                'current_page' => $patients->currentPage(),
                'last_page' => $patients->lastPage(),
                'per_page' => $patients->perPage(),
                'total' => $patients->total(),
                'active_count' => $totalActive,
                'inactive_count' => $totalInactive,
            ],
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'document_number' => 'nullable|string|max:20|unique:patients,document_number',
            'email' => 'nullable|email|unique:patients,email',
            'phone' => 'nullable|string|max:20',
            'birth_date' => 'nullable|date|before:today',
            'gender' => 'nullable|in:male,female,other',
            'address' => 'nullable|string|max:500',
            'emergency_contact_name' => 'nullable|string|max:255',
            'emergency_contact_phone' => 'nullable|string|max:20',
            'medical_history' => 'nullable|string',
            'allergies' => 'nullable|string',
            'notes' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        // Asegurar que is_active sea true por defecto si no se proporciona
        $validated['is_active'] = $validated['is_active'] ?? true;

        $patient = Patient::create($validated);

        // Emitir evento de WebSocket (el listener se encargará de la auditoría)
        event(new PatientCreated($patient));
        
        // Limpiar cache del dashboard
        ClearDashboardCache::handle();

        return response()->json([
            'data' => $patient,
            'meta' => [
                'message' => 'Paciente creado exitosamente',
            ],
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Patient $patient): JsonResponse
    {
        // Cargar todas las relaciones necesarias de forma eficiente para evitar N+1 queries
        $patient->load([
            'appointments' => function ($query) {
                $query->with([
                    'appointmentType:id,name,default_duration_minutes,price',
                    'user:id,name,specialty',
                    'dentalChair:id,name,code'
                ])->orderBy('scheduled_at', 'desc')->limit(10);
            },
            'waitingLists' => function ($query) {
                $query->with([
                    'appointmentType:id,name',
                    'preferredUser:id,name'
                ])->orderBy('created_at', 'desc');
            },
            'auditLogs' => function ($query) {
                $query->with('user:id,name,email')
                      ->orderBy('created_at', 'desc')
                      ->limit(50);
            },
            'treatmentPlans' => function ($query) {
                $query->with(['items.dentalPiece', 'createdBy:id,name'])
                      ->orderBy('created_at', 'desc')
                      ->limit(5);
            },
            'quotations' => function ($query) {
                $query->with(['items', 'createdBy:id,name'])
                      ->orderBy('created_at', 'desc')
                      ->limit(5);
            }
        ]);

        return response()->json([
            'data' => $patient,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Patient $patient): JsonResponse
    {
        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'document_number' => [
                'nullable',
                'string',
                'max:20',
                Rule::unique('patients', 'document_number')->ignore($patient->id),
            ],
            'email' => [
                'nullable',
                'email',
                Rule::unique('patients', 'email')->ignore($patient->id),
            ],
            'phone' => 'nullable|string|max:20',
            'birth_date' => 'nullable|date|before:today',
            'gender' => 'nullable|in:male,female,other',
            'address' => 'nullable|string|max:500',
            'emergency_contact_name' => 'nullable|string|max:255',
            'emergency_contact_phone' => 'nullable|string|max:20',
            'medical_history' => 'nullable|string',
            'allergies' => 'nullable|string',
            'notes' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        // Capture old values for audit (only relevant fields)
        $oldValues = [
            'first_name' => $patient->first_name,
            'last_name' => $patient->last_name,
            'document_number' => $patient->document_number,
            'email' => $patient->email,
            'phone' => $patient->phone,
            'birth_date' => $patient->birth_date ? $patient->birth_date->format('Y-m-d') : null,
            'gender' => $patient->gender,
            'address' => $patient->address,
            'emergency_contact_name' => $patient->emergency_contact_name,
            'emergency_contact_phone' => $patient->emergency_contact_phone,
            'medical_history' => $patient->medical_history,
            'allergies' => $patient->allergies,
            'notes' => $patient->notes,
            'is_active' => $patient->is_active,
        ];

        $patient->update($validated);
        $patient->refresh();

        // Emitir evento de WebSocket (el listener se encargará de la auditoría)
        event(new PatientUpdated($patient, $oldValues));
        
        // Limpiar cache del dashboard
        ClearDashboardCache::handle();

        return response()->json([
            'data' => $patient,
            'meta' => [
                'message' => 'Paciente actualizado exitosamente',
            ],
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Patient $patient): JsonResponse
    {
        // Check if patient has appointments
        if ($patient->appointments()->count() > 0) {
            return response()->json([
                'message' => 'No se puede eliminar un paciente que tiene citas programadas.',
                'errors' => [
                    'patient' => ['No se puede eliminar un paciente que tiene citas programadas.'],
                ],
            ], 422);
        }

        // Capture patient data for audit before deletion
        $patientData = $patient->toArray();
        $patientId = $patient->id;

        $patient->delete();

        // Emitir evento de WebSocket (el listener se encargará de la auditoría)
        event(new PatientDeleted($patientId, $patientData));
        
        // Limpiar cache del dashboard
        ClearDashboardCache::handle();

        return response()->json([
            'data' => null,
            'meta' => [
                'message' => 'Paciente eliminado exitosamente',
            ],
        ]);
    }

    /**
     * Search patients by name, document, email or phone.
     * Acepta tanto ?q= como ?search= para compatibilidad.
     */
    public function search(Request $request): JsonResponse
    {
        $term = $request->get('q') ?? $request->get('search');

        $request->merge(['search' => $term]);

        $request->validate([
            'search' => 'required|string|min:2',
        ], [
            'search.required' => 'Debes escribir al menos 2 caracteres',
            'search.min' => 'Mínimo 2 caracteres para buscar',
        ]);

        $patients = Patient::where('is_active', true)
            ->where(function ($query) use ($term) {
                $query->where('first_name', 'like', "%{$term}%")
                    ->orWhere('last_name', 'like', "%{$term}%")
                    ->orWhere('email', 'like', "%{$term}%")
                    ->orWhere('phone', 'like', "%{$term}%")
                    ->orWhere('document_number', 'like', "%{$term}%");
            })
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->limit(20)
            ->get(['id', 'first_name', 'last_name', 'email', 'phone', 'document_number', 'birth_date']);

        $data = $patients->map(function ($p) {
            $age = null;
            if ($p->birth_date) {
                try {
                    $age = \Carbon\Carbon::parse($p->birth_date)->age;
                } catch (\Throwable $e) {
                    $age = null;
                }
            }
            return [
                'id' => $p->id,
                'first_name' => $p->first_name,
                'last_name' => $p->last_name,
                'dni' => $p->document_number,
                'document_number' => $p->document_number,
                'email' => $p->email,
                'phone' => $p->phone,
                'age' => $age,
            ];
        });

        return response()->json([
            'data' => $data,
        ]);
    }

    /**
     * Export patient file to PDF or ZIP.
     */
    public function export(Request $request, Patient $patient): \Illuminate\Http\Response|JsonResponse
    {
        // For synchronous export (small files), use service directly
        // For large exports, dispatch job and return status
        $format = $request->get('format', 'pdf');
        $useQueue = $request->boolean('async', false);

        if ($useQueue) {
            // Dispatch job for async export
            \App\Jobs\ExportPatientFileJob::dispatch($patient->id, $format, Auth::id());

            return response()->json([
                'message' => 'La exportación se está procesando. Se notificará cuando esté lista.',
                'data' => [
                    'patient_id' => $patient->id,
                    'format' => $format,
                    'status' => 'processing',
                ],
            ], 202);
        }

        // Synchronous export (existing logic)
        return $this->exportSync($request, $patient);
    }

    /**
     * Synchronous export method (for backward compatibility)
     */
    private function exportSync(Request $request, Patient $patient): \Illuminate\Http\Response|JsonResponse
    {
        $request->validate([
            'format' => 'required|in:pdf,zip',
        ]);

        try {
            $exportService = new PatientExportService();
            $format = $request->get('format');

            if ($format === 'pdf') {
                $pdfContent = $exportService->exportToPdf($patient->id);
                $filename = 'ficha_paciente_' . $patient->id . '_' . now()->format('Y-m-d_His') . '.pdf';

                return response($pdfContent, 200, [
                    'Content-Type' => 'application/pdf',
                    'Content-Disposition' => 'attachment; filename="' . $filename . '"',
                ]);
            } else {
                $zipContent = $exportService->exportToZip($patient->id);
                $filename = 'ficha_paciente_' . $patient->id . '_' . now()->format('Y-m-d_His') . '.zip';

                return response($zipContent, 200, [
                    'Content-Type' => 'application/zip',
                    'Content-Disposition' => 'attachment; filename="' . $filename . '"',
                ]);
            }
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error exporting patient file: ' . $e->getMessage(), [
                'patient_id' => $patient->id,
                'format' => $request->get('format'),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'message' => 'Error al exportar ficha del paciente',
                'error' => config('app.debug') ? $e->getMessage() : 'Error interno del servidor',
            ], 500);
        }
    }
}
