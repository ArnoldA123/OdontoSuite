<?php

namespace App\Http\Controllers\Api;

use App\Events\ClinicalAttachmentCreated;
use App\Events\ClinicalEvolutionCreated;
use App\Events\ClinicalEvolutionDeleted;
use App\Events\ClinicalEvolutionUpdated;
use App\Events\MedicalRecordCreated;
use App\Events\MedicalRecordUpdated;
use App\Http\Controllers\Controller;
use App\Models\ClinicalAttachment;
use App\Models\ClinicalEvolution;
use App\Models\MedicalRecord;
use App\Services\MedicalRecordService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class MedicalRecordController extends Controller
{
    protected $medicalRecordService;

    public function __construct(MedicalRecordService $medicalRecordService)
    {
        $this->medicalRecordService = $medicalRecordService;
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
                'evolutions' => function ($query) {
                    $query->orderBy('evolution_date', 'desc');
                },
                'attachments' => function ($query) {
                    $query->where('is_active', true);
                },
            ])->findOrFail($id);

            return response()->json([
                'data' => $record,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Historia clínica no encontrada',
                'error' => $e->getMessage(),
            ], 404);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        try {
            $validated = $request->validate([
                'first_visit_date' => 'nullable|date',
                'chief_complaint' => 'nullable|string',
                'medical_history' => 'nullable|string',
                'dental_history' => 'nullable|string',
                'allergies' => 'nullable|string',
                'medications' => 'nullable|string',
                'systemic_conditions' => 'nullable|string',
                'family_history' => 'nullable|string',
                'social_history' => 'nullable|string',
                'vital_signs' => 'nullable|array',
                'clinical_examination' => 'nullable|string',
                'diagnosis' => 'nullable|string',
                'treatment_plan' => 'nullable|string',
                'notes' => 'nullable|string',
            ]);

            $record = MedicalRecord::findOrFail($id);
            $record->update($validated);
            $record->refresh();

            // Emitir evento de WebSocket
            event(new MedicalRecordUpdated($record));

            return response()->json([
                'data' => $record->load(['patient', 'createdBy']),
                'meta' => [
                    'message' => 'Historia clínica actualizada exitosamente',
                ],
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al actualizar historia clínica',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $record = MedicalRecord::findOrFail($id);

            // Marcar como inactivo en lugar de eliminar
            $record->update(['is_active' => false]);

            return response()->json([
                'meta' => [
                    'message' => 'Historia clínica desactivada exitosamente',
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al eliminar historia clínica',
                'error' => $e->getMessage(),
            ], 500);
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
            $evolutions = ClinicalEvolution::with([
                'patient',
                'appointment',
                'createdBy',
                'attachments',
            ])
                ->where('medical_record_id', $id)
                ->orderBy('evolution_date', 'desc')
                ->get();

            return response()->json([
                'data' => $evolutions,
                'meta' => [
                    'total' => $evolutions->count(),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al obtener evoluciones',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Actualizar una evolución clínica.
     */
    public function updateEvolution(\App\Http\Requests\StoreEvolutionRequest $request, int $id): JsonResponse
    {
        try {
            $evolution = $this->medicalRecordService->updateEvolution($id, $request->validated());

            // Emitir evento de WebSocket: otra pestaña o profesional refresca
            // su timeline sin esperar el pull manual (issue #223).
            event(new ClinicalEvolutionUpdated($evolution));

            return response()->json([
                'data' => $evolution,
                'meta' => [
                    'message' => 'Evolución actualizada exitosamente',
                ],
            ]);
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
            return response()->json([
                'message' => 'Error al actualizar evolución',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Eliminar (soft) una evolución clínica.
     */
    public function deleteEvolution(int $id): JsonResponse
    {
        try {
            // El modelo se conserva en memoria para el broadcast: despues del
            // soft delete la fila ya no es legible para el cliente.
            $evolution = ClinicalEvolution::findOrFail($id);

            $this->medicalRecordService->deleteEvolution($evolution->id);

            // Emitir evento de WebSocket con el medical_record_id necesario
            // para que el cliente filtre el borrado (issue #223).
            event(new ClinicalEvolutionDeleted($evolution));

            return response()->json([
                'meta' => [
                    'message' => 'Evolución eliminada exitosamente',
                ],
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Evolución no encontrada',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al eliminar evolución',
                'error' => $e->getMessage(),
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
     * Eliminar (soft) un archivo adjunto (slice 01, T-01.3 / API-001).
     * Returns 204 on success, 403 if caller is not clinical staff (handled
     * at middleware level), 404 if attachment does not exist.
     */
    public function deleteAttachment(int $attachmentId): \Illuminate\Http\Response|JsonResponse
    {
        try {
            $attachment = ClinicalAttachment::findOrFail($attachmentId);
            $attachment->update(['is_active' => false]);

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
