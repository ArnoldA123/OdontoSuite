<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Odontogram;
use App\Models\OdontogramRecord;
use App\Models\Patient;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class OdontogramController extends Controller
{
    /**
     * List odontograms for a patient.
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'patient_id' => 'required|exists:patients,id',
        ]);

        $odontograms = Odontogram::with(['createdBy:id,name', 'records.dentalPiece', 'records.toothSurface'])
            ->where('patient_id', $request->get('patient_id'))
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'data' => $odontograms,
        ]);
    }

    /**
     * Store a newly created odontogram.
     */
    public function store(\App\Http\Requests\StoreOdontogramRequest $request): JsonResponse
    {
        $validated = $request->validate([
            'patient_id' => 'required|exists:patients,id',
            'version' => 'nullable|string|max:10',
            'notes' => 'nullable|string',
            'configuration' => 'nullable|array',
        ]);

        $validated['created_by'] = Auth::id();
        $validated['version'] = $validated['version'] ?? '1.0';
        $validated['is_active'] = true;

        $odontogram = Odontogram::create($validated);
        $odontogram->load(['patient', 'createdBy']);

        return response()->json([
            'data' => $odontogram,
            'meta' => [
                'message' => 'Odontograma creado exitosamente',
            ],
        ], 201);
    }

    /**
     * Display the specified odontogram.
     */
    public function show(Odontogram $odontogram): JsonResponse
    {
        $odontogram->load([
            'patient',
            'createdBy:id,name,email',
            'records' => function ($query) {
                $query->with([
                    'dentalPiece:id,fdi_number,name,type',
                    'toothSurface:id,surface_code,surface_name',
                    'appointment:id,scheduled_at',
                    'createdBy:id,name'
                ]);
            }
        ]);

        return response()->json([
            'data' => $odontogram,
        ]);
    }

    /**
     * Update the specified odontogram.
     */
    public function update(Request $request, Odontogram $odontogram): JsonResponse
    {
        $validated = $request->validate([
            'version' => 'nullable|string|max:10',
            'notes' => 'nullable|string',
            'configuration' => 'nullable|array',
            'is_active' => 'nullable|boolean',
        ]);

        $odontogram->update($validated);
        $odontogram->load(['patient', 'createdBy']);

        return response()->json([
            'data' => $odontogram,
            'meta' => [
                'message' => 'Odontograma actualizado exitosamente',
            ],
        ]);
    }

    /**
     * Remove the specified odontogram.
     */
    public function destroy(Odontogram $odontogram): JsonResponse
    {
        $odontogram->delete();

        return response()->json([
            'data' => null,
            'meta' => [
                'message' => 'Odontograma eliminado exitosamente',
            ],
        ]);
    }

    /**
     * Add a record to an odontogram.
     */
    public function addRecord(Request $request, Odontogram $odontogram): JsonResponse
    {
        $validated = $request->validate([
            'dental_piece_id' => 'required|exists:dental_pieces,id',
            'tooth_surface_id' => 'nullable|exists:tooth_surfaces,id',
            'condition_code' => 'required|string|max:10',
            'condition_name' => 'required|string|max:50',
            'diagnosis' => 'nullable|string',
            'treatment_notes' => 'nullable|string',
            'color' => 'nullable|string|max:7',
            'appointment_id' => 'nullable|exists:appointments,id',
        ]);

        $validated['odontogram_id'] = $odontogram->id;
        $validated['created_by'] = Auth::id();
        $validated['color'] = $validated['color'] ?? '#000000';

        $record = OdontogramRecord::create($validated);
        $record->load(['dentalPiece', 'toothSurface', 'appointment', 'createdBy']);

        return response()->json([
            'data' => $record,
            'meta' => [
                'message' => 'Registro agregado exitosamente',
            ],
        ], 201);
    }

    /**
     * Update an odontogram record.
     */
    public function updateRecord(Request $request, OdontogramRecord $record): JsonResponse
    {
        $validated = $request->validate([
            'dental_piece_id' => 'sometimes|exists:dental_pieces,id',
            'tooth_surface_id' => 'nullable|exists:tooth_surfaces,id',
            'condition_code' => 'sometimes|string|max:10',
            'condition_name' => 'sometimes|string|max:50',
            'diagnosis' => 'nullable|string',
            'treatment_notes' => 'nullable|string',
            'color' => 'nullable|string|max:7',
            'appointment_id' => 'nullable|exists:appointments,id',
        ]);

        $record->update($validated);
        $record->load(['dentalPiece', 'toothSurface', 'appointment', 'createdBy']);

        return response()->json([
            'data' => $record,
            'meta' => [
                'message' => 'Registro actualizado exitosamente',
            ],
        ]);
    }

    /**
     * Delete an odontogram record.
     */
    public function deleteRecord(OdontogramRecord $record): JsonResponse
    {
        $record->delete();

        return response()->json([
            'data' => null,
            'meta' => [
                'message' => 'Registro eliminado exitosamente',
            ],
        ]);
    }

    /**
     * Get active odontogram for a patient.
     */
    public function getActive(Request $request): JsonResponse
    {
        $request->validate([
            'patient_id' => 'required|exists:patients,id',
        ]);

        $odontogram = Odontogram::with([
            'records' => function ($query) {
                $query->with([
                    'dentalPiece:id,fdi_number,name,type',
                    'toothSurface:id,surface_code,surface_name',
                    'appointment:id,scheduled_at',
                    'createdBy:id,name'
                ]);
            }
        ])
        ->where('patient_id', $request->get('patient_id'))
        ->where('is_active', true)
        ->first();

        if (!$odontogram) {
            return response()->json([
                'data' => null,
                'meta' => [
                    'message' => 'No se encontró un odontograma activo para este paciente',
                ],
            ], 404);
        }

        return response()->json([
            'data' => $odontogram,
        ]);
    }
}

