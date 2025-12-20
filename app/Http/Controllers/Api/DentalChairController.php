<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\DentalChair;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class DentalChairController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): JsonResponse
    {
        try {
            $chairs = DentalChair::where('is_active', true)->get();

            return response()->json([
                'data' => $chairs,
                'meta' => [
                    'total' => $chairs->count()
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al obtener ambientes',
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
                'equipment' => 'nullable|string',
                'status' => 'required|in:active,inactive,maintenance',
                'is_active' => 'boolean'
            ]);

            $validated['code'] = 'AMB-' . str_pad(DentalChair::count() + 1, 3, '0', STR_PAD_LEFT);
            $validated['is_active'] = $validated['is_active'] ?? true;

            $chair = DentalChair::create($validated);

            // Log audit
            if (Auth::check()) {
                AuditLog::log(
                    Auth::user(),
                    'dental_chair_created',
                    $chair,
                    [],
                    [
                        'name' => $chair->name,
                        'code' => $chair->code,
                        'description' => $chair->description,
                        'equipment' => $chair->equipment,
                        'status' => $chair->status,
                        'is_active' => $chair->is_active,
                    ]
                );
            }

            return response()->json([
                'data' => $chair,
                'meta' => [
                    'message' => 'Ambiente creado exitosamente'
                ]
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al crear ambiente',
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
            $chair = DentalChair::with([
                'auditLogs' => function ($query) {
                    $query->with('user:id,name,email')
                          ->orderBy('created_at', 'desc')
                          ->limit(50);
                }
            ])->findOrFail($id);

            return response()->json([
                'data' => $chair
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Ambiente no encontrado',
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
            $chair = DentalChair::findOrFail($id);

            $validated = $request->validate([
                'name' => 'sometimes|string|max:255',
                'description' => 'nullable|string',
                'equipment' => 'nullable|string',
                'status' => 'sometimes|in:active,inactive,maintenance',
                'is_active' => 'boolean'
            ]);

            // Capture old values for audit (only relevant fields)
            $oldValues = [
                'name' => $chair->name,
                'code' => $chair->code,
                'description' => $chair->description,
                'equipment' => $chair->equipment,
                'status' => $chair->status,
                'is_active' => $chair->is_active,
            ];

            $chair->update($validated);
            $chair->refresh();

            // Capture new values for audit (only relevant fields)
            $newValues = [
                'name' => $chair->name,
                'code' => $chair->code,
                'description' => $chair->description,
                'equipment' => $chair->equipment,
                'status' => $chair->status,
                'is_active' => $chair->is_active,
            ];

            // Log audit
            if (Auth::check()) {
                AuditLog::log(
                    Auth::user(),
                    'dental_chair_updated',
                    $chair,
                    $oldValues,
                    $newValues
                );
            }

            return response()->json([
                'data' => $chair,
                'meta' => [
                    'message' => 'Ambiente actualizado exitosamente'
                ]
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al actualizar ambiente',
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
            $chair = DentalChair::findOrFail($id);
            
            // Capture values for audit before deletion
            $chairData = [
                'name' => $chair->name,
                'code' => $chair->code,
                'description' => $chair->description,
                'equipment' => $chair->equipment,
                'status' => $chair->status,
                'is_active' => $chair->is_active,
            ];

            $chair->delete();

            // Log audit
            if (Auth::check()) {
                AuditLog::log(
                    Auth::user(),
                    'dental_chair_deleted',
                    $chair,
                    $chairData,
                    []
                );
            }

            return response()->json([
                'meta' => [
                    'message' => 'Ambiente eliminado exitosamente'
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al eliminar ambiente',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get active dental chairs.
     */
    public function active(): JsonResponse
    {
        try {
            $chairs = DentalChair::where('is_active', true)
                ->select('id', 'name', 'code', 'description', 'status')
                ->get();

            return response()->json([
                'data' => $chairs,
                'meta' => [
                    'total' => $chairs->count()
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error en DentalChairController@active: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error al obtener sillas dentales activas',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Search dental chairs.
     */
    public function search(Request $request): JsonResponse
    {
        try {
            $query = $request->get('q', '');

            $chairs = DentalChair::where(function ($q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                  ->orWhere('description', 'like', "%{$query}%")
                  ->orWhere('equipment', 'like', "%{$query}%");
            })->get();

            return response()->json([
                'data' => $chairs,
                'meta' => [
                    'total' => $chairs->count(),
                    'query' => $query
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al buscar ambientes',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
