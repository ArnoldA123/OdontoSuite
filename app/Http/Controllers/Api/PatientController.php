<?php

namespace App\Http\Controllers\Api;

use App\Events\PatientCreated;
use App\Events\PatientDeleted;
use App\Events\PatientUpdated;
use App\Http\Controllers\Controller;
use App\Http\Resources\PatientResource;
use App\Listeners\ClearDashboardCache;
use App\Models\Patient;
use App\Services\PatientExportService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class PatientController extends Controller
{
    use AuthorizesRequests;

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'sort' => ['sometimes', 'nullable', 'string', Rule::in(['last_name', 'first_name', 'created_at', 'email'])],
            'direction' => ['sometimes', 'nullable', 'string', Rule::in(['asc', 'desc'])],
            'branch_id' => ['sometimes', 'nullable', 'integer', Rule::exists('branches', 'id')],
            'search' => ['sometimes', 'nullable', 'string', 'max:255'],
        ]);

        $term = self::normalizeSearchTerm($validated['search'] ?? null);
        $branchId = $validated['branch_id'] ?? null;

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
            'updated_at',
        ]);

        // Search by name, email, phone or document_number (DNI)
        if ($term !== null) {
            $applySearch = static function ($query) use ($term): void {
                $like = self::likePattern($term);

                $query->where(function ($q) use ($like) {
                    $q->where('first_name', 'like', $like)
                        ->orWhere('last_name', 'like', $like)
                        ->orWhere('email', 'like', $like)
                        ->orWhere('phone', 'like', $like)
                        ->orWhere('document_number', 'like', $like);
                });
            };

            $applySearch($searchQuery);
            $applySearch($baseQuery);
        }

        // Multi-tenant: filtrar por branch_id si se envía
        if ($branchId !== null) {
            $searchQuery->where('branch_id', $branchId);
            $baseQuery->where('branch_id', $branchId);
        }

        // Calcular totales de activos e inactivos (antes de aplicar filtro de estado)
        $totalActive = (clone $baseQuery)->where('is_active', true)->count();
        $totalInactive = (clone $baseQuery)->where('is_active', false)->count();

        // Filter by active status - Solo aplicar filtro si se envía explícitamente.
        // `boolean()` acepta true/false/1/0 y los strings 'true'/'false' que manda
        // la lista; `filled()` ignora un `active=` vacío en lugar de leerlo como
        // «inactivos».
        if ($request->filled('active')) {
            $searchQuery->where('is_active', $request->boolean('active'));
        }

        // per_page nunca puede romper el listado: fuera de 1..100 cae al default.
        $perPage = filter_var($request->input('per_page', 15), FILTER_VALIDATE_INT);
        $perPage = ($perPage === false || $perPage < 1) ? 15 : min($perPage, 100);

        $direction = self::sortDirection($validated['direction'] ?? null);
        foreach (self::sortColumns($validated['sort'] ?? null) as $column) {
            $searchQuery->orderBy($column, $direction);
        }

        $patients = $searchQuery->paginate($perPage);

        // Bounded PR2 follow-up (verify-report #337 CRITICAL): wrap the
        // paginated items in PatientResource so the `age` key (and every
        // other derived field) reaches the JSON contract consumed by
        // PatientsPage.vue and PatientSelector.vue. The custom active_count /
        // inactive_count meta is preserved by merging after the collection
        // call so the SPA's filter counters keep working.
        return response()->json([
            'data' => PatientResource::collection($patients->getCollection())->resolve(),
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
     * Whitelisted sort targets for the patients list; anything outside the
     * whitelist keeps the legacy last_name,first_name ordering.
     */
    private static function sortColumns(?string $sort): array
    {
        return [
            'last_name' => ['last_name', 'first_name'],
            'first_name' => ['first_name', 'last_name'],
            'created_at' => ['created_at'],
            'email' => ['email'],
        ][$sort] ?? ['last_name', 'first_name'];
    }

    private static function sortDirection(?string $direction): string
    {
        return $direction === 'desc' ? 'desc' : 'asc';
    }

    /**
     * Trimmed search term, or null when the client sent nothing usable.
     */
    private static function normalizeSearchTerm(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $term = trim($value);

        return $term === '' ? null : $term;
    }

    /**
     * LIKE pattern with the wildcards escaped, so a literal % or _ matches
     * itself instead of the whole table.
     */
    private static function likePattern(string $term): string
    {
        return '%'.addcslashes($term, '\\%_').'%';
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
            'phone' => 'nullable|string|max:20|unique:patients,phone',
            'birth_date' => 'nullable|date|before:today',
            'gender' => 'nullable|in:male,female,other',
            'address' => 'nullable|string|max:500',
            'emergency_contact_name' => 'nullable|string|max:255',
            'emergency_contact_phone' => 'nullable|string|max:20',
            'medical_history' => 'nullable|string',
            'allergies' => 'nullable|string',
            'notes' => 'nullable|string',
            'is_active' => 'boolean',
            'branch_id' => 'nullable|integer|exists:branches,id',
        ]);

        // Asegurar que is_active sea true por defecto si no se proporciona
        $validated['is_active'] = $validated['is_active'] ?? true;

        $patient = Patient::create($validated);

        // Emitir evento de WebSocket (el listener se encargará de la auditoría)
        event(new PatientCreated($patient));

        // Limpiar cache del dashboard
        ClearDashboardCache::handle();

        // Bounded PR2 follow-up: wrap the created patient in PatientResource
        // so the 201 response includes the derived `age` key.
        return response()->json([
            'data' => PatientResource::make($patient)->resolve(),
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
                    'dentalChair:id,name,code',
                ])->orderBy('scheduled_at', 'desc')->limit(10);
            },
            'waitingLists' => function ($query) {
                $query->with([
                    'appointmentType:id,name',
                    'preferredUser:id,name',
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
            },
        ]);

        return response()->json([
            'data' => PatientResource::make($patient)->resolve(),
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
            'phone' => [
                'nullable',
                'string',
                'max:20',
                Rule::unique('patients', 'phone')->ignore($patient->id),
            ],
            'birth_date' => 'nullable|date|before:today',
            'gender' => 'nullable|in:male,female,other',
            'address' => 'nullable|string|max:500',
            'emergency_contact_name' => 'nullable|string|max:255',
            'emergency_contact_phone' => 'nullable|string|max:20',
            'medical_history' => 'nullable|string',
            'allergies' => 'nullable|string',
            'notes' => 'nullable|string',
            'is_active' => 'boolean',
            'branch_id' => ['nullable', 'integer', Rule::exists('branches', 'id')],
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
            'branch_id' => $patient->branch_id,
        ];

        $patient->update($validated);
        $patient->refresh();

        // Emitir evento de WebSocket (el listener se encargará de la auditoría)
        event(new PatientUpdated($patient, $oldValues));

        // Limpiar cache del dashboard
        ClearDashboardCache::handle();

        return response()->json([
            'data' => PatientResource::make($patient)->resolve(),
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
        // Un paciente con historial clínico o financiero no se elimina: cada
        // belongsTo(Patient) deja de resolverlo (el soft delete lo oculta) y
        // el expediente queda huérfano en los demás módulos.
        $blockers = array_filter([
            'cita(s)' => $patient->appointments()->count(),
            'historia(s) clínica(s)' => $patient->medicalRecords()->count(),
            'plan(es) de tratamiento' => $patient->treatmentPlans()->count(),
            'presupuesto(s)' => $patient->quotations()->count(),
            'movimiento(s) de caja' => $patient->transactions()->count(),
            'adjunto(s) clínico(s)' => $patient->clinicalAttachments()->count(),
        ]);

        if ($blockers !== []) {
            $detail = collect($blockers)
                ->map(fn (int $count, string $label) => "{$count} {$label}")
                ->implode(', ');
            $message = "No se puede eliminar un paciente con historial clínico o financiero ({$detail}). Desactívalo en lugar de eliminarlo.";

            return response()->json([
                'message' => $message,
                'errors' => [
                    'patient' => [$message],
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
        $term = self::normalizeSearchTerm($request->get('q') ?? $request->get('search'));

        $request->merge(['search' => $term]);

        $validated = $request->validate([
            'search' => 'required|string|min:2',
            'branch_id' => ['sometimes', 'nullable', 'integer', Rule::exists('branches', 'id')],
        ], [
            'search.required' => 'Debes escribir al menos 2 caracteres',
            'search.min' => 'Mínimo 2 caracteres para buscar',
        ]);

        $like = self::likePattern($validated['search']);
        $words = preg_split('/\s+/', $validated['search'], -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $patients = Patient::where('is_active', true)
            ->when(
                $validated['branch_id'] ?? null,
                fn ($query, $branchId) => $query->where('branch_id', $branchId)
            )
            ->where(function ($query) use ($like, $words) {
                $query->where('first_name', 'like', $like)
                    ->orWhere('last_name', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->orWhere('phone', 'like', $like)
                    ->orWhere('document_number', 'like', $like)
                    ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", [$like]);

                // Full name split in words: every word must match one of the
                // name columns, so both "Gonzalo Velasco" and "Velasco
                // Gonzalo" find the same patient.
                if (count($words) > 1) {
                    $query->orWhere(function ($nameQuery) use ($words) {
                        foreach ($words as $word) {
                            $wordLike = self::likePattern($word);

                            $nameQuery->where(function ($partQuery) use ($wordLike) {
                                $partQuery->where('first_name', 'like', $wordLike)
                                    ->orWhere('last_name', 'like', $wordLike);
                            });
                        }
                    });
                }
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
                'name' => trim($p->first_name.' '.$p->last_name),
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
        $this->authorize('export', $patient);

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
     * Synchronous export method (for backward compatibility).
     */
    private function exportSync(Request $request, Patient $patient): \Illuminate\Http\Response|JsonResponse
    {
        $this->authorize('export', $patient);

        $request->validate([
            'format' => 'required|in:pdf,zip',
        ]);

        try {
            $exportService = new PatientExportService;
            $format = $request->get('format');

            if ($format === 'pdf') {
                $pdfContent = $exportService->exportToPdf($patient->id);
                $filename = 'ficha_paciente_'.$patient->id.'_'.now()->format('Y-m-d_His').'.pdf';

                return response($pdfContent, 200, [
                    'Content-Type' => 'application/pdf',
                    'Content-Disposition' => 'attachment; filename="'.$filename.'"',
                ]);
            } else {
                $zipContent = $exportService->exportToZip($patient->id);
                $filename = 'ficha_paciente_'.$patient->id.'_'.now()->format('Y-m-d_His').'.zip';

                return response($zipContent, 200, [
                    'Content-Type' => 'application/zip',
                    'Content-Disposition' => 'attachment; filename="'.$filename.'"',
                ]);
            }
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error exporting patient file: '.$e->getMessage(), [
                'patient_id' => $patient->id,
                'format' => $request->get('format'),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Error al exportar ficha del paciente',
                'error' => config('app.debug') ? $e->getMessage() : 'Error interno del servidor',
            ], 500);
        }
    }
}
