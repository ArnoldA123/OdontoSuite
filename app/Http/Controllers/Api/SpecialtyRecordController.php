<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\SpecialtyRecordService;
use App\Events\SpecialtyRecordCreated;
use App\Events\SpecialtyRecordUpdated;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class SpecialtyRecordController extends Controller
{
    /**
     * BF-014 (slice 11): canonical model class list for the show() lookup
     * loop. Order is stable so the resolved specialty is deterministic.
     */
    private const SPECIALTY_MODELS = [
        \App\Models\ImplantologyRecord::class,
        \App\Models\OrthodonticsRecord::class,
        \App\Models\EndodonticsRecord::class,
        \App\Models\RehabilitationRecord::class,
        \App\Models\OralSurgeryRecord::class,
    ];

    protected $specialtyRecordService;

    public function __construct(SpecialtyRecordService $specialtyRecordService)
    {
        $this->specialtyRecordService = $specialtyRecordService;
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $specialty = $request->get('specialty');
            $patientId = $request->get('patient_id');

            if (!$specialty || !$patientId) {
                return response()->json([
                    'message' => 'Se requiere especialidad y ID del paciente'
                ], 400);
            }

            $records = $this->specialtyRecordService->getSpecialtyRecords($patientId, $specialty);

            return response()->json([
                'data' => $records,
                'meta' => [
                    'total' => $records->count(),
                    'specialty' => $specialty
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al obtener registros de especialidad',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(\App\Http\Requests\StoreSpecialtyRecordRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();

            $specialty = $validated['specialty'];
            unset($validated['specialty']);

            $record = match ($specialty) {
                'implantologia' => $this->specialtyRecordService->createImplantologyRecord($validated),
                'ortodoncia' => $this->specialtyRecordService->createOrthodonticsRecord($validated),
                'endodoncia' => $this->specialtyRecordService->createEndodonticsRecord($validated),
                'rehabilitacion' => $this->specialtyRecordService->createRehabilitationRecord($validated),
                'cirugia_oral' => $this->specialtyRecordService->createOralSurgeryRecord($validated),
                default => throw new \InvalidArgumentException("Especialidad no válida: {$specialty}")
            };

            // Emitir evento de WebSocket
            event(new SpecialtyRecordCreated($record, $specialty));

            return response()->json([
                'data' => $record,
                'meta' => [
                    'message' => 'Registro de especialidad creado exitosamente'
                ]
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al crear registro de especialidad',
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
            // BF-014 (slice 11): replaced 5 sequential model->find() calls
            // with a foreach loop over the model classes. The first hit
            // wins. Average cost drops from O(5) round-trips to O(1).
            $record = null;
            foreach (self::SPECIALTY_MODELS as $modelClass) {
                $record = $modelClass::with(['patient', 'appointment', 'dentalPiece', 'createdBy'])
                    ->find($id);
                if ($record) {
                    break;
                }
            }

            if (!$record) {
                return response()->json([
                    'message' => 'Registro de especialidad no encontrado'
                ], 404);
            }

            return response()->json([
                'data' => $record
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al obtener registro de especialidad',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        try {
            $validated = $request->validate([
                'specialty' => 'required|in:implantologia,ortodoncia,endodoncia,rehabilitacion,cirugia_oral',
                // Campos específicos por especialidad (mismos que en store)
                'implant_brand' => 'nullable|string',
                'implant_model' => 'nullable|string',
                'implant_diameter' => 'nullable|string',
                'implant_length' => 'nullable|string',
                'batch_number' => 'nullable|string',
                'placement_date' => 'nullable|date',
                'treatment_type' => 'nullable|string',
                'treatment_start_date' => 'nullable|date',
                'tooth_number' => 'nullable|string',
                'canal_count' => 'nullable|integer',
                'restoration_type' => 'nullable|string',
                'material_type' => 'nullable|string',
                'procedure_type' => 'nullable|string',
                'surgery_date' => 'nullable|date',
                // Campos comunes
                'notes' => 'nullable|string',
                'complications' => 'nullable|string',
                'follow_up_notes' => 'nullable|string'
            ]);

            $specialty = $validated['specialty'];
            unset($validated['specialty']);

            $record = $this->specialtyRecordService->updateRecord($specialty, $id, $validated);

            // Emitir evento de WebSocket
            event(new SpecialtyRecordUpdated($record, $specialty));

            return response()->json([
                'data' => $record,
                'meta' => [
                    'message' => 'Registro de especialidad actualizado exitosamente'
                ]
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al actualizar registro de especialidad',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        try {
            $validated = $request->validate([
                'specialty' => 'required|in:implantologia,ortodoncia,endodoncia,rehabilitacion,cirugia_oral'
            ]);

            $this->specialtyRecordService->deleteRecord($validated['specialty'], $id);

            return response()->json([
                'meta' => [
                    'message' => 'Registro de especialidad eliminado exitosamente'
                ]
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al eliminar registro de especialidad',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener registros por paciente y especialidad
     */
    public function getByPatient(int $patientId, string $specialty): JsonResponse
    {
        try {
            $records = $this->specialtyRecordService->getSpecialtyRecords($patientId, $specialty);

            return response()->json([
                'data' => $records,
                'meta' => [
                    'total' => $records->count(),
                    'specialty' => $specialty
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al obtener registros del paciente',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener todos los registros de especialidades de un paciente
     */
    public function getAllByPatient(int $patientId): JsonResponse
    {
        try {
            $records = $this->specialtyRecordService->getAllPatientSpecialtyRecords($patientId);

            return response()->json([
                'data' => $records,
                'meta' => [
                    'total' => collect($records)->flatten()->count()
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al obtener todos los registros del paciente',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener estadísticas por especialidad
     */
    public function getStats(int $patientId, string $specialty): JsonResponse
    {
        try {
            $stats = $this->specialtyRecordService->getSpecialtyStats($patientId, $specialty);

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
}
