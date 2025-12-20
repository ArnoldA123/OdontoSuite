<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AppointmentType;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class AppointmentTypeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): JsonResponse
    {
        try {
            $types = AppointmentType::where('is_active', true)->get();

            return response()->json([
                'data' => $types,
                'meta' => [
                    'total' => $types->count()
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al obtener tipos de cita',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'description' => 'nullable|string',
                'duration_minutes' => 'required|integer|min:15|max:480',
                'price' => 'nullable|numeric|min:0',
                'color' => 'required|string|max:7',
                'is_active' => 'boolean'
            ]);

            $validated['is_active'] = $validated['is_active'] ?? true;
            
            // Map duration_minutes to default_duration_minutes if needed
            if (isset($validated['duration_minutes'])) {
                $validated['default_duration_minutes'] = $validated['duration_minutes'];
                unset($validated['duration_minutes']);
            }

            $type = AppointmentType::create($validated);

            // Log audit
            if (Auth::check()) {
                AuditLog::log(
                    Auth::user(),
                    'appointment_type_created',
                    $type,
                    [],
                    [
                        'name' => $type->name,
                        'description' => $type->description,
                        'default_duration_minutes' => $type->default_duration_minutes,
                        'price' => $type->price,
                        'color' => $type->color,
                        'is_active' => $type->is_active,
                    ]
                );
            }

            return response()->json([
                'data' => $type,
                'meta' => [
                    'message' => 'Tipo de cita creado exitosamente'
                ]
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al crear tipo de cita',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id): JsonResponse
    {
        try {
            $type = AppointmentType::with([
                'auditLogs' => function ($query) {
                    $query->with('user:id,name,email')
                          ->orderBy('created_at', 'desc')
                          ->limit(50);
                }
            ])->findOrFail($id);

            return response()->json([
                'data' => $type
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Tipo de cita no encontrado',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        try {
            $type = AppointmentType::findOrFail($id);

            $validated = $request->validate([
                'name' => 'sometimes|string|max:255',
                'description' => 'nullable|string',
                'duration_minutes' => 'sometimes|integer|min:15|max:480',
                'price' => 'nullable|numeric|min:0',
                'color' => 'sometimes|string|max:7',
                'is_active' => 'boolean'
            ]);

            // Map duration_minutes to default_duration_minutes if needed
            if (isset($validated['duration_minutes'])) {
                $validated['default_duration_minutes'] = $validated['duration_minutes'];
                unset($validated['duration_minutes']);
            }

            // Capture old values for audit (only relevant fields)
            $oldValues = [
                'name' => $type->name,
                'description' => $type->description,
                'default_duration_minutes' => $type->default_duration_minutes,
                'price' => $type->price,
                'color' => $type->color,
                'is_active' => $type->is_active,
            ];

            $type->update($validated);
            $type->refresh();

            // Capture new values for audit (only relevant fields)
            $newValues = [
                'name' => $type->name,
                'description' => $type->description,
                'default_duration_minutes' => $type->default_duration_minutes,
                'price' => $type->price,
                'color' => $type->color,
                'is_active' => $type->is_active,
            ];

            // Log audit
            if (Auth::check()) {
                AuditLog::log(
                    Auth::user(),
                    'appointment_type_updated',
                    $type,
                    $oldValues,
                    $newValues
                );
            }

            return response()->json([
                'data' => $type,
                'meta' => [
                    'message' => 'Tipo de cita actualizado exitosamente'
                ]
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al actualizar tipo de cita',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            $type = AppointmentType::findOrFail($id);
            
            // Capture values for audit before deletion
            $typeData = [
                'name' => $type->name,
                'description' => $type->description,
                'default_duration_minutes' => $type->default_duration_minutes,
                'price' => $type->price,
                'color' => $type->color,
                'is_active' => $type->is_active,
            ];

            $type->delete();

            // Log audit
            if (Auth::check()) {
                AuditLog::log(
                    Auth::user(),
                    'appointment_type_deleted',
                    $type,
                    $typeData,
                    []
                );
            }

            return response()->json([
                'meta' => [
                    'message' => 'Tipo de cita eliminado exitosamente'
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al eliminar tipo de cita',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get active appointment types.
     */
    public function active(): JsonResponse
    {
        try {
            $types = AppointmentType::where('is_active', true)
                ->select('id', 'name', 'default_duration_minutes', 'price', 'color')
                ->get();

            return response()->json([
                'data' => $types,
                'meta' => [
                    'total' => $types->count()
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error en AppointmentTypeController@active: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error al obtener tipos de cita activos',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Search appointment types.
     */
    public function search(Request $request): JsonResponse
    {
        try {
            $query = $request->get('q', '');

            $types = AppointmentType::where(function ($q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                  ->orWhere('description', 'like', "%{$query}%");
            })->get();

            return response()->json([
                'data' => $types,
                'meta' => [
                    'total' => $types->count(),
                    'query' => $query
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al buscar tipos de cita',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
