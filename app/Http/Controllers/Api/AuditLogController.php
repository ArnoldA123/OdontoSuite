<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AppointmentType;
use App\Models\AuditLog;
use App\Models\DentalChair;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class AuditLogController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $query = AuditLog::with('user:id,name,email');

        // Filter by patient
        if ($request->has('patient_id')) {
            $query->where('auditable_type', Patient::class)
                  ->where('auditable_id', $request->get('patient_id'));
    }

        // Filter by user
        if ($request->has('user_id')) {
            $query->where('user_id', $request->get('user_id'));
        }

        // Filter by action
        if ($request->has('action')) {
            $query->where('action', $request->get('action'));
        }

        // Filter by date range
        if ($request->has('date_from')) {
            $query->whereDate('created_at', '>=', $request->get('date_from'));
        }

        if ($request->has('date_to')) {
            $query->whereDate('created_at', '<=', $request->get('date_to'));
        }

        // Filter by model type
        if ($request->has('model_type')) {
            $query->where('auditable_type', $request->get('model_type'));
        }

        $perPage = $request->get('per_page', 50);
        $perPage = min($perPage, 100); // Limit max per page to 100

        $logs = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json([
            'data' => $logs->items(),
            'meta' => [
                'message' => 'Historial de auditoría cargado exitosamente',
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
                'per_page' => $logs->perPage(),
                'total' => $logs->total(),
            ],
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(int $id): JsonResponse
    {
        $log = AuditLog::with('user:id,name,email')
            ->findOrFail($id);

        // Load the auditable model if it still exists
        if ($log->auditable_type && $log->auditable_id) {
            try {
                $auditable = $log->auditable;
                $log->setAttribute('auditable_model', $auditable);
            } catch (\Exception $e) {
                // Model was deleted, that's okay
                $log->setAttribute('auditable_model', null);
            }
        }

        return response()->json([
            'data' => $log,
        ]);
    }

    /**
     * Get audit logs for a specific patient.
     */
    public function byPatient(int $patientId): JsonResponse
    {
        try {
            $patient = Patient::findOrFail($patientId);

            $logs = AuditLog::with('user:id,name,email')
                ->where('auditable_type', Patient::class)
                ->where('auditable_id', $patientId)
                ->orderBy('created_at', 'desc')
                ->get();

            // Safely get patient name
            $patientName = $patient->first_name && $patient->last_name 
                ? trim($patient->first_name . ' ' . $patient->last_name)
                : ($patient->first_name ?? 'Paciente #' . $patientId);

            return response()->json([
                'data' => $logs,
                'meta' => [
                    'patient_id' => $patientId,
                    'patient_name' => $patientName,
                    'total' => $logs->count(),
                ],
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Paciente no encontrado',
                'data' => [],
                'meta' => [
                    'patient_id' => $patientId,
                    'total' => 0,
                ],
            ], 404);
        } catch (\Exception $e) {
            Log::error('Error loading audit logs for patient: ' . $e->getMessage(), [
                'patient_id' => $patientId,
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'message' => 'Error al cargar historial de auditoría',
                'data' => [],
                'meta' => [
                    'patient_id' => $patientId,
                    'total' => 0,
                ],
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Get audit logs for a specific user.
     */
    public function byUser(int $userId): JsonResponse
    {
        try {
            $user = User::findOrFail($userId);

            $logs = AuditLog::with('user:id,name,email')
                ->where('auditable_type', User::class)
                ->where('auditable_id', $userId)
                ->orderBy('created_at', 'desc')
                ->get();

            // Safely get user name
            $userName = $user->name ?? 'Usuario #' . $userId;

            return response()->json([
                'data' => $logs,
                'meta' => [
                    'user_id' => $userId,
                    'user_name' => $userName,
                    'total' => $logs->count(),
                ],
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Usuario no encontrado',
                'data' => [],
                'meta' => [
                    'user_id' => $userId,
                    'total' => 0,
                ],
            ], 404);
        } catch (\Exception $e) {
            Log::error('Error loading audit logs for user: ' . $e->getMessage(), [
                'user_id' => $userId,
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'message' => 'Error al cargar historial de auditoría',
                'data' => [],
                'meta' => [
                    'user_id' => $userId,
                    'total' => 0,
                ],
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Get audit logs for a specific dental chair.
     */
    public function byDentalChair(int $chairId): JsonResponse
    {
        try {
            $chair = DentalChair::findOrFail($chairId);

            $logs = AuditLog::with('user:id,name,email')
                ->where('auditable_type', DentalChair::class)
                ->where('auditable_id', $chairId)
                ->orderBy('created_at', 'desc')
                ->get();

            // Safely get chair name
            $chairName = $chair->name ?? 'Ambiente #' . $chairId;

            return response()->json([
                'data' => $logs,
                'meta' => [
                    'chair_id' => $chairId,
                    'chair_name' => $chairName,
                    'total' => $logs->count(),
                ],
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Ambiente no encontrado',
                'data' => [],
                'meta' => [
                    'chair_id' => $chairId,
                    'total' => 0,
                ],
            ], 404);
        } catch (\Exception $e) {
            Log::error('Error loading audit logs for dental chair: ' . $e->getMessage(), [
                'chair_id' => $chairId,
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'message' => 'Error al cargar historial de auditoría',
                'data' => [],
                'meta' => [
                    'chair_id' => $chairId,
                    'total' => 0,
                ],
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Get audit logs for a specific appointment type.
     */
    public function byAppointmentType(int $typeId): JsonResponse
    {
        try {
            $type = AppointmentType::findOrFail($typeId);

            $logs = AuditLog::with('user:id,name,email')
                ->where('auditable_type', AppointmentType::class)
                ->where('auditable_id', $typeId)
                ->orderBy('created_at', 'desc')
                ->get();

            // Safely get type name
            $typeName = $type->name ?? 'Tipo de Cita #' . $typeId;

            return response()->json([
                'data' => $logs,
                'meta' => [
                    'type_id' => $typeId,
                    'type_name' => $typeName,
                    'total' => $logs->count(),
                ],
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Tipo de cita no encontrado',
                'data' => [],
                'meta' => [
                    'type_id' => $typeId,
                    'total' => 0,
                ],
            ], 404);
        } catch (\Exception $e) {
            Log::error('Error loading audit logs for appointment type: ' . $e->getMessage(), [
                'type_id' => $typeId,
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'message' => 'Error al cargar historial de auditoría',
                'data' => [],
                'meta' => [
                    'type_id' => $typeId,
                    'total' => 0,
                ],
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }
}
