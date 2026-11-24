<?php

namespace App\Http\Controllers\Api;

use App\Events\SpecialtyRecordCreated;
use App\Events\SpecialtyRecordUpdated;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateSpecialtyRecordRequest;
use App\Models\ClinicalEncounter;
use App\Models\TreatmentPlanItem;
use App\Services\SpecialtyRecordService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

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

            if (! $specialty || ! $patientId) {
                return response()->json([
                    'message' => 'Se requiere especialidad y ID del paciente',
                ], 400);
            }

            $records = $this->specialtyRecordService->getSpecialtyRecords($patientId, $specialty);

            return response()->json([
                'data' => $records,
                'meta' => [
                    'total' => $records->count(),
                    'specialty' => $specialty,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Error al obtener registros de especialidad', ['exception' => $e]);

            return response()->json([
                'message' => 'Error al obtener registros de especialidad',
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

            $validated = array_merge($validated, $this->validatedLinks(
                $request,
                (int) $validated['patient_id'],
                isset($validated['appointment_id']) ? (int) $validated['appointment_id'] : null,
                isset($validated['procedure_id']) ? (int) $validated['procedure_id'] : null,
            ));

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
                    'message' => 'Registro de especialidad creado exitosamente',
                ],
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error al crear registro de especialidad', ['exception' => $e]);

            return response()->json([
                'message' => 'Error al crear registro de especialidad',
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
                $relations = [
                    'patient',
                    'appointment',
                    'procedure',
                    'createdBy',
                    // Slice 1: the act and the plan line the record names.
                    'clinicalEncounter',
                    'treatmentPlanItem',
                ];

                // orthodontics_records has no dental_piece_id column, so only
                // the models that own the relation load it.
                if (method_exists($modelClass, 'dentalPiece')) {
                    $relations[] = 'dentalPiece';
                }

                $record = $modelClass::with($relations)->find($id);
                if ($record) {
                    break;
                }
            }

            if (! $record) {
                return response()->json([
                    'message' => 'Registro de especialidad no encontrado',
                ], 404);
            }

            return response()->json([
                'data' => $record,
            ]);
        } catch (\Exception $e) {
            Log::error('Error al obtener registro de especialidad', ['exception' => $e]);

            return response()->json([
                'message' => 'Error al obtener registro de especialidad',
            ], 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateSpecialtyRecordRequest $request, int $id): JsonResponse
    {
        try {
            $validated = $request->validated();

            $specialty = $validated['specialty'];
            unset($validated['specialty']);

            $existing = $this->findRecord($id);

            if (! $existing) {
                return response()->json([
                    'message' => 'Registro de especialidad no encontrado',
                ], 404);
            }

            // The update contract leaves `patient_id` out (a record stays with
            // its patient), so the links are checked against the stored one.
            $validated = array_merge($validated, $this->validatedLinks(
                $request,
                (int) $existing->patient_id,
                array_key_exists('appointment_id', $validated)
                    ? ($validated['appointment_id'] !== null ? (int) $validated['appointment_id'] : null)
                    : ($existing->appointment_id !== null ? (int) $existing->appointment_id : null),
                array_key_exists('procedure_id', $validated) && $validated['procedure_id'] !== null
                    ? (int) $validated['procedure_id']
                    : ($existing->procedure_id !== null ? (int) $existing->procedure_id : null),
            ));

            $record = $this->specialtyRecordService->updateRecord($specialty, $id, $validated);

            // Emitir evento de WebSocket
            event(new SpecialtyRecordUpdated($record, $specialty));

            return response()->json([
                'data' => $record,
                'meta' => [
                    'message' => 'Registro de especialidad actualizado exitosamente',
                ],
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $e->errors(),
            ], 422);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Registro de especialidad no encontrado',
            ], 404);
        } catch (\Exception $e) {
            Log::error('Error al actualizar registro de especialidad', ['exception' => $e]);

            return response()->json([
                'message' => 'Error al actualizar registro de especialidad',
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
                'specialty' => 'required|in:implantologia,ortodoncia,endodoncia,rehabilitacion,cirugia_oral',
            ]);

            $this->specialtyRecordService->deleteRecord($validated['specialty'], $id);

            return response()->json([
                'meta' => [
                    'message' => 'Registro de especialidad eliminado exitosamente',
                ],
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $e->errors(),
            ], 422);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Registro de especialidad no encontrado',
            ], 404);
        } catch (\Exception $e) {
            Log::error('Error al eliminar registro de especialidad', ['exception' => $e]);

            return response()->json([
                'message' => 'Error al eliminar registro de especialidad',
            ], 500);
        }
    }

    /**
     * Obtener registros por paciente y especialidad.
     */
    public function getByPatient(int $patientId, string $specialty): JsonResponse
    {
        try {
            $records = $this->specialtyRecordService->getSpecialtyRecords($patientId, $specialty);

            return response()->json([
                'data' => $records,
                'meta' => [
                    'total' => $records->count(),
                    'specialty' => $specialty,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Error al obtener registros del paciente', ['exception' => $e]);

            return response()->json([
                'message' => 'Error al obtener registros del paciente',
            ], 500);
        }
    }

    /**
     * Obtener todos los registros de especialidades de un paciente.
     */
    public function getAllByPatient(int $patientId): JsonResponse
    {
        try {
            $records = $this->specialtyRecordService->getAllPatientSpecialtyRecords($patientId);

            // Slice 1: every group publishes the two links so the patient
            // workspace can say which act and which plan line each record
            // belongs to without a second read per row.
            $records = array_map(
                static fn ($group) => method_exists($group, 'load')
                    ? $group->load(['clinicalEncounter', 'treatmentPlanItem'])
                    : $group,
                $records,
            );

            return response()->json([
                'data' => $records,
                'meta' => [
                    'total' => collect($records)->flatten()->count(),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Error al obtener todos los registros del paciente', ['exception' => $e]);

            return response()->json([
                'message' => 'Error al obtener todos los registros del paciente',
            ], 500);
        }
    }

    /**
     * Slice 1 of «Registros de especialidad integrados a la atención y la
     * historia» — the two links a record may declare.
     *
     * They are validated here and not in the FormRequests because the create
     * and update contracts are per specialty and know nothing about the act;
     * one rule serves both doors and all five tables instead of five copies.
     *
     * The relation is a consistency rule, not a suggestion: an act of another
     * patient, an act of another appointment or a plan line of another patient
     * refuses the write. A plan line without a catalog procedure accepts any
     * procedure, because it never promised one.
     *
     * @return array<string, int|null>
     */
    private function validatedLinks(
        Request $request,
        int $patientId,
        ?int $appointmentId,
        ?int $procedureId,
    ): array {
        $validated = Validator::make($request->all(), [
            'clinical_encounter_id' => 'sometimes|nullable|integer|exists:clinical_encounters,id',
            'treatment_plan_item_id' => 'sometimes|nullable|integer|exists:treatment_plan_items,id',
        ], [
            'clinical_encounter_id.exists' => 'La atención seleccionada no existe',
            'treatment_plan_item_id.exists' => 'El ítem del plan seleccionado no existe',
        ])->validate();

        $links = [];

        foreach (['clinical_encounter_id', 'treatment_plan_item_id'] as $key) {
            if (! array_key_exists($key, $validated)) {
                continue;
            }

            $links[$key] = $validated[$key] !== null && $validated[$key] !== ''
                ? (int) $validated[$key]
                : null;
        }

        $errors = [];

        if (($links['clinical_encounter_id'] ?? null) !== null) {
            $encounter = ClinicalEncounter::find($links['clinical_encounter_id']);

            if ((int) $encounter->patient_id !== $patientId) {
                $errors['clinical_encounter_id'] = 'La atención no pertenece al paciente del registro.';
            } elseif (
                $appointmentId !== null
                && $encounter->appointment_id !== null
                && (int) $encounter->appointment_id !== $appointmentId
            ) {
                $errors['clinical_encounter_id'] = 'La atención no pertenece a la cita del registro.';
            }
        }

        if (($links['treatment_plan_item_id'] ?? null) !== null) {
            // The plan is read apart from the line, so the eager load never
            // repeats the show loop's query shape.
            $item = TreatmentPlanItem::find($links['treatment_plan_item_id']);
            $item?->loadMissing('treatmentPlan');

            if ((int) $item?->treatmentPlan?->patient_id !== $patientId) {
                $errors['treatment_plan_item_id'] = 'El ítem del plan no pertenece al paciente del registro.';
            } elseif (
                $procedureId !== null
                && $item->procedure_catalog_id !== null
                && (int) $item->procedure_catalog_id !== $procedureId
            ) {
                $errors['procedure_id'] = 'El procedimiento del registro no coincide con el ítem del plan.';
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $links;
    }

    /**
     * Resolve a specialty record by id without knowing its table: the five
     * models share neither a table nor a parent, only the contract.
     */
    private function findRecord(int $id): ?\Illuminate\Database\Eloquent\Model
    {
        foreach (self::SPECIALTY_MODELS as $modelClass) {
            $record = $modelClass::find($id);

            if ($record) {
                return $record;
            }
        }

        return null;
    }

    /**
     * Obtener estadísticas por especialidad.
     */
    public function getStats(int $patientId, string $specialty): JsonResponse
    {
        try {
            $stats = $this->specialtyRecordService->getSpecialtyStats($patientId, $specialty);

            return response()->json([
                'data' => $stats,
            ]);
        } catch (\Exception $e) {
            Log::error('Error al obtener estadísticas', ['exception' => $e]);

            return response()->json([
                'message' => 'Error al obtener estadísticas',
            ], 500);
        }
    }
}
