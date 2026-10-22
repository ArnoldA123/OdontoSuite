<?php

namespace App\Http\Controllers\Api;

use App\Events\ClinicalAddendumCreated;
use App\Events\ClinicalAttachmentCreated;
use App\Events\ClinicalEvolutionCreated;
use App\Events\MedicalRecordCreated;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAddendumRequest;
use App\Models\ClinicalAttachment;
use App\Models\ClinicalEvolution;
use App\Models\MedicalRecord;
use App\Services\ClinicalRecordAuditService;
use App\Services\MedicalRecordService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class MedicalRecordController extends Controller
{
    protected $medicalRecordService;

    protected $clinicalRecordAudit;

    public function __construct(
        MedicalRecordService $medicalRecordService,
        ClinicalRecordAuditService $clinicalRecordAudit
    ) {
        $this->medicalRecordService = $medicalRecordService;
        $this->clinicalRecordAudit = $clinicalRecordAudit;
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            // Attachments belong to the patient, not to an evolution: a file
            // uploaded from the Adjuntos tab has no clinical_evolution_id and
            // would never reach the record through the hasManyThrough.
            $query = MedicalRecord::with(['patient', 'createdBy'])->withCount('evolutions');

            if ($request->filled('patient_id')) {
                $query->where('patient_id', $request->input('patient_id'));
            }

            // Multi-tenant: filtrar por branch_id del paciente asociado
            if ($request->filled('branch_id')) {
                $query->whereHas('patient', fn ($q) => $q->where('branch_id', $request->input('branch_id')));
            }

            // Deactivated records stay in the database for audit; the index
            // only offers them when the caller asks for them explicitly.
            $status = (string) $request->input('status', 'active');
            if ($status !== 'all') {
                $query->where('is_active', $status !== 'inactive');
            }

            if ($request->filled('search')) {
                $term = '%'.trim((string) $request->input('search')).'%';
                $query->where(function ($search) use ($term) {
                    $search->where('record_number', 'like', $term)
                        ->orWhereHas('patient', function ($patient) use ($term) {
                            $patient->where('first_name', 'like', $term)
                                ->orWhere('last_name', 'like', $term)
                                ->orWhere('document_number', 'like', $term);
                        });
                });
            }

            $perPage = max(1, min((int) $request->input('per_page', 10), 50));
            $records = $query->orderBy('first_visit_date', 'desc')->paginate($perPage);

            $attachmentCounts = ClinicalAttachment::whereIn(
                'patient_id',
                collect($records->items())->pluck('patient_id')->unique()->all()
            )
                ->where('is_active', true)
                ->selectRaw('patient_id, count(*) as total')
                ->groupBy('patient_id')
                ->pluck('total', 'patient_id');

            $items = collect($records->items())->each(function ($record) use ($attachmentCounts) {
                $record->attachments_count = (int) ($attachmentCounts[$record->patient_id] ?? 0);
            });

            // F3 (#292): one read entry per record the page returns.
            $this->clinicalRecordAudit->recordsViewed($items, ClinicalRecordAuditService::SURFACE_INDEX);

            return response()->json([
                'data' => $items->all(),
                'meta' => [
                    'total' => $records->total(),
                    'current_page' => $records->currentPage(),
                    'per_page' => $records->perPage(),
                    'last_page' => $records->lastPage(),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al obtener historias clínicas',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(\App\Http\Requests\StoreMedicalRecordRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();

            $record = $this->medicalRecordService->createRecord($validated['patient_id'], $validated);

            // Emitir evento de WebSocket
            event(new MedicalRecordCreated($record));

            return response()->json([
                'data' => $record,
                'meta' => [
                    'message' => 'Historia clínica creada exitosamente',
                ],
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al crear historia clínica',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(int $id): JsonResponse
    {
        try {
            $record = MedicalRecord::with([
                'patient',
                'createdBy',
                // F3 (#292): the corrections travel with the original so the
                // client can render them next to the row they amend.
                'addenda.author',
                'evolutions' => function ($query) {
                    $query->with('addenda.author')->orderBy('evolution_date', 'desc');
                },
                'attachments' => function ($query) {
                    $query->where('is_active', true);
                },
            ])->findOrFail($id);

            // F3 (#292): opening a history is a clinical read; the audit is not
            // swallowed, so a history that cannot be recorded is not served.
            $this->clinicalRecordAudit->recordViewed($record, ClinicalRecordAuditService::SURFACE_RECORD);

            return response()->json([
                'data' => $record,
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Historia clínica no encontrada',
            ], 404);
        }
    }

    /**
     * Agregar evolución a la historia clínica.
     */
    public function addEvolution(\App\Http\Requests\StoreEvolutionRequest $request, int $id): JsonResponse
    {
        try {
            $validated = $request->validated();

            $evolution = $this->medicalRecordService->addEvolution($id, $validated);
            $evolution->load('medicalRecord.patient', 'createdBy');

            // Emitir evento de WebSocket
            event(new ClinicalEvolutionCreated($evolution));

            return response()->json([
                'data' => $evolution,
                'meta' => [
                    'message' => 'Evolución agregada exitosamente',
                ],
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al agregar evolución',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Obtener evoluciones de la historia clínica.
     */
    public function getEvolutions(int $id): JsonResponse
    {
        try {
            // The history is the audited entity: resolve it before the
            // timeline so an unknown id answers 404 instead of an empty list
            // with no read trail.
            $record = MedicalRecord::findOrFail($id);

            $evolutions = ClinicalEvolution::with([
                'patient',
                'appointment',
                'createdBy',
                'attachments',
                // F3 (#292): the corrections of each visit travel with it.
                'addenda.author',
            ])
                ->where('medical_record_id', $id)
                ->orderBy('evolution_date', 'desc')
                ->get();

            $this->clinicalRecordAudit->recordViewed($record, ClinicalRecordAuditService::SURFACE_EVOLUTIONS);

            return response()->json([
                'data' => $evolutions,
                'meta' => [
                    'total' => $evolutions->count(),
                ],
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Historia clínica no encontrada',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al obtener evoluciones',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Agregar un addendum a la historia clínica (F3 #292).
     *
     * La única escritura posterior al alta: el autor sale de la sesión y el
     * original no se modifica.
     */
    public function addRecordAddendum(StoreAddendumRequest $request, int $id): JsonResponse
    {
        try {
            $addendum = $this->medicalRecordService->addRecordAddendum($id, $request->validated());

            event(new ClinicalAddendumCreated($addendum));

            return response()->json([
                'data' => $addendum,
                'meta' => [
                    'message' => 'Addendum registrado exitosamente',
                ],
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $e->errors(),
            ], 422);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Historia clínica no encontrada',
            ], 404);
        } catch (\Exception $e) {
            // The 500 keeps the diagnosis in the log: the envelope never echoes
            // the exception, the same rule the other clinical modules follow.
            Log::error('Error al registrar un addendum clínico', [
                'exception' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Error al registrar el addendum',
            ], 500);
        }
    }

    /**
     * Agregar un addendum a una evolución concreta de la historia (F3 #292).
     */
    public function addEvolutionAddendum(StoreAddendumRequest $request, int $evolution): JsonResponse
    {
        try {
            $addendum = $this->medicalRecordService->addEvolutionAddendum($evolution, $request->validated());

            event(new ClinicalAddendumCreated($addendum));

            return response()->json([
                'data' => $addendum,
                'meta' => [
                    'message' => 'Addendum registrado exitosamente',
                ],
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $e->errors(),
            ], 422);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Evolución no encontrada',
            ], 404);
        } catch (\Exception $e) {
            // The 500 keeps the diagnosis in the log: the envelope never echoes
            // the exception, the same rule the other clinical modules follow.
            Log::error('Error al registrar un addendum clínico', [
                'exception' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Error al registrar el addendum',
            ], 500);
        }
    }

    /**
     * Subir archivo adjunto.
     */
    public function uploadAttachment(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'patient_id' => 'required|exists:patients,id',
                'appointment_id' => 'nullable|exists:appointments,id',
                'clinical_evolution_id' => 'nullable|exists:clinical_evolutions,id',
                'file' => 'required|file|max:10240', // 10MB max
                'category' => 'nullable|string|in:radiografia,foto_clinica,documento,otro,general',
                'description' => 'nullable|string',
                'is_private' => 'boolean',
            ]);

            $attachment = $this->medicalRecordService->attachFile($validated);
            $attachment->load('patient', 'clinicalEvolution.medicalRecord.patient');

            // Emitir evento de WebSocket
            event(new ClinicalAttachmentCreated($attachment));

            return response()->json([
                'data' => $attachment,
                'meta' => [
                    'message' => 'Archivo subido exitosamente',
                ],
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al subir archivo',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Eliminar un archivo adjunto.
     *
     * Contract (#244): the stored binary is removed from the `public` disk and
     * the row stays behind as the archive marker (`is_active = false`), so the
     * clinical audit trail keeps who uploaded what and when without keeping a
     * file the app can no longer reach. Repeating the call is a no-op.
     *
     * Returns 204 on success, 403 if caller is not clinical staff (handled
     * at middleware level), 404 if attachment does not exist.
     */
    public function deleteAttachment(int $attachmentId): \Illuminate\Http\Response|JsonResponse
    {
        try {
            $this->medicalRecordService->deleteAttachment($attachmentId);

            return response()->noContent();
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Adjunto no encontrado',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al eliminar adjunto',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Obtener estadísticas de la historia clínica.
     */
    public function getStats(int $patientId): JsonResponse
    {
        try {
            $stats = $this->medicalRecordService->getRecordStats($patientId);

            // F3 (#292): the patient's statistics expose clinical content, so
            // every active history of that patient is recorded as read.
            $this->clinicalRecordAudit->patientRecordsViewed($patientId, ClinicalRecordAuditService::SURFACE_STATS);

            return response()->json([
                'data' => $stats,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al obtener estadísticas',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Obtener archivos por categoría.
     */
    public function getAttachmentsByCategory(Request $request, int $patientId): JsonResponse
    {
        try {
            $validated = $request->validate([
                'category' => 'nullable|string|in:radiografia,foto_clinica,documento,otro,general',
            ]);

            $attachments = $this->medicalRecordService->getAttachmentsByCategory(
                $patientId,
                $validated['category'] ?? null
            );

            // F3 (#292): the attachment list is patient-scoped; record the read
            // against each active history it belongs to.
            $this->clinicalRecordAudit->patientRecordsViewed($patientId, ClinicalRecordAuditService::SURFACE_ATTACHMENTS);

            return response()->json([
                'data' => $attachments,
                'meta' => [
                    'total' => $attachments->count(),
                ],
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al obtener archivos',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
