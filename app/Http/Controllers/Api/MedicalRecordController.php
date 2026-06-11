<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MedicalRecord;
use App\Services\MedicalRecordService;
use App\Events\MedicalRecordCreated;
use App\Events\MedicalRecordUpdated;
use App\Events\ClinicalEvolutionCreated;
use App\Events\ClinicalAttachmentCreated;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

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
            $query = MedicalRecord::with(['patient', 'createdBy']);

            if ($request->has('patient_id')) {
                $query->where('patient_id', $request->patient_id);
            }

            // Multi-tenant: filtrar por branch_id del paciente asociado
            if ($request->has('branch_id')) {
                $query->whereHas('patient', fn($q) => $q->where('branch_id', $request->input('branch_id')));
            }

            $records = $query->orderBy('first_visit_date', 'desc')->paginate(10);

            return response()->json([
                'data' => $records->items(),
                'meta' => [
                    'total' => $records->total(),
                    'current_page' => $records->currentPage(),
                    'per_page' => $records->perPage(),
                    'last_page' => $records->lastPage()
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al obtener historias clínicas',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(\App\Http\Requests\StoreMedicalRecordRequest $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'patient_id' => 'required|exists:patients,id',
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
                'notes' => 'nullable|string'
            ]);

            $record = $this->medicalRecordService->createRecord($validated['patient_id'], $validated);

            // Emitir evento de WebSocket
            event(new MedicalRecordCreated($record));

            return response()->json([
                'data' => $record,
                'meta' => [
                    'message' => 'Historia clínica creada exitosamente'
                ]
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al crear historia clínica',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(int $id): JsonResponse
    {
        try {
            $record = \App\Models\MedicalRecord::with([
                'patient',
                'createdBy',
                'evolutions' => function ($query) {
                    $query->orderBy('evolution_date', 'desc');
                },
                'attachments' => function ($query) {
                    $query->where('is_active', true);
                }
            ])->findOrFail($id);

            return response()->json([
                'data' => $record
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Historia clínica no encontrada',
                'error' => $e->getMessage()
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
                'notes' => 'nullable|string'
            ]);

            $record = \App\Models\MedicalRecord::findOrFail($id);
            $record->update($validated);
            $record->refresh();

            // Emitir evento de WebSocket
            event(new MedicalRecordUpdated($record));

            return response()->json([
                'data' => $record->load(['patient', 'createdBy']),
                'meta' => [
                    'message' => 'Historia clínica actualizada exitosamente'
                ]
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al actualizar historia clínica',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $record = \App\Models\MedicalRecord::findOrFail($id);

            // Marcar como inactivo en lugar de eliminar
            $record->update(['is_active' => false]);

            return response()->json([
                'meta' => [
                    'message' => 'Historia clínica desactivada exitosamente'
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al eliminar historia clínica',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Agregar evolución a la historia clínica
     */
    public function addEvolution(\App\Http\Requests\StoreEvolutionRequest $request, int $id): JsonResponse
    {
        try {
            $validated = $request->validate([
                'appointment_id' => 'nullable|exists:appointments,id',
                'evolution_date' => 'nullable|date',
                'specialty' => 'nullable|string',
                'subjective' => 'nullable|string',
                'objective' => 'nullable|string',
                'assessment' => 'nullable|string',
                'plan' => 'nullable|string',
                'procedures_performed' => 'nullable|string',
                'materials_used' => 'nullable|string',
                'prescriptions' => 'nullable|string',
                'recommendations' => 'nullable|string',
                'next_appointment_notes' => 'nullable|string',
                'vital_signs' => 'nullable|array',
                'clinical_measurements' => 'nullable|array',
                'requires_follow_up' => 'boolean',
                'follow_up_date' => 'nullable|date|after:evolution_date'
            ]);

            $evolution = $this->medicalRecordService->addEvolution($id, $validated);
            $evolution->load('medicalRecord.patient', 'createdBy');

            // Emitir evento de WebSocket
            event(new ClinicalEvolutionCreated($evolution));

            return response()->json([
                'data' => $evolution,
                'meta' => [
                    'message' => 'Evolución agregada exitosamente'
                ]
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al agregar evolución',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener evoluciones de la historia clínica
     */
    public function getEvolutions(int $id): JsonResponse
    {
        try {
            $evolutions = \App\Models\ClinicalEvolution::with([
                'patient',
                'appointment',
                'createdBy',
                'attachments'
            ])
            ->where('medical_record_id', $id)
            ->orderBy('evolution_date', 'desc')
            ->get();

            return response()->json([
                'data' => $evolutions,
                'meta' => [
                    'total' => $evolutions->count()
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al obtener evoluciones',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Subir archivo adjunto
     */
    public function uploadAttachment(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'patient_id' => 'required|exists:patients,id',
                'appointment_id' => 'nullable|exists:appointments,id',
                'clinical_evolution_id' => 'nullable|exists:clinical_evolutions,id',
                'file' => 'required|file|max:10240', // 10MB max
                'category' => 'nullable|string|in:radiografia,foto_clinica,documento,otro',
                'description' => 'nullable|string',
                'is_private' => 'boolean'
            ]);

            $attachment = $this->medicalRecordService->attachFile($validated);
            $attachment->load('patient', 'clinicalEvolution.medicalRecord.patient');

            // Emitir evento de WebSocket
            event(new ClinicalAttachmentCreated($attachment));

            return response()->json([
                'data' => $attachment,
                'meta' => [
                    'message' => 'Archivo subido exitosamente'
                ]
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al subir archivo',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener estadísticas de la historia clínica
     */
    public function getStats(int $patientId): JsonResponse
    {
        try {
            $stats = $this->medicalRecordService->getRecordStats($patientId);

            return response()->json([
                'data' => $stats
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al obtener estadísticas',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener archivos por categoría
     */
    public function getAttachmentsByCategory(Request $request, int $patientId): JsonResponse
    {
        try {
            $category = $request->get('category', 'general');
            $attachments = $this->medicalRecordService->getAttachmentsByCategory($patientId, $category);

            return response()->json([
                'data' => $attachments,
                'meta' => [
                    'total' => $attachments->count()
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al obtener archivos',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
