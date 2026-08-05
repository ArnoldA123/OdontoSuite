<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class BranchController extends Controller
{
    /**
     * Display a listing of branches
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = Branch::query();

            // Filtro por estado (is_active)
            if ($request->has('is_active') && $request->is_active !== '') {
                $query->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN));
            }

            // Busqueda por texto (q)
            if ($request->has('q') && $request->q !== '') {
                $q = $request->q;
                $query->where(function ($w) use ($q) {
                    $w->where('name', 'like', "%$q%")
                      ->orWhere('code', 'like', "%$q%")
                      ->orWhere('city', 'like', "%$q%")
                      ->orWhere('address', 'like', "%$q%");
                });
            }

            $branches = $query->orderBy('name')->get();

            return response()->json([
                'data' => $branches,
                'meta' => [
                    'message' => 'Sucursales cargadas exitosamente',
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al cargar sucursales',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created branch
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'code' => 'required|string|max:10|unique:branches,code',
                'name' => 'required|string|max:255',
                'address' => 'sometimes|nullable|string|max:500',
                'city' => 'required|string|max:100',
                'state' => 'sometimes|nullable|string|max:100',
                'country' => 'sometimes|nullable|string|max:100',
                'postal_code' => 'sometimes|nullable|string|max:10',
                'phone' => 'sometimes|nullable|string|max:20',
                'email' => 'sometimes|nullable|email|max:255',
                'timezone' => 'sometimes|nullable|string|max:50',
                'latitude' => 'sometimes|nullable|numeric',
                'longitude' => 'sometimes|nullable|numeric',
                'description' => 'sometimes|nullable|string|max:1000',
                'is_active' => 'sometimes|boolean'
            ]);

            // Default country/timezone si no se enviaron
            $validated['country'] = $validated['country'] ?? 'Peru';
            $validated['timezone'] = $validated['timezone'] ?? 'America/Lima';
            $validated['is_active'] = $validated['is_active'] ?? true;
            // address y state son NOT NULL en la migration original; permitimos
            // string vacio si no se envian para no romper la API admin.
            $validated['address'] = $validated['address'] ?? '';
            $validated['state'] = $validated['state'] ?? '';

            $branch = Branch::create($validated);

            return response()->json([
                'data' => $branch,
                'meta' => [
                    'message' => 'Sucursal creada exitosamente',
                ],
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Los datos proporcionados no son validos.',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al crear sucursal',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified branch
     */
    public function show(Branch $branch): JsonResponse
    {
        return response()->json([
            'data' => $branch,
            'meta' => [
                'message' => 'Sucursal obtenida exitosamente',
            ],
        ]);
    }

    /**
     * Update the specified branch
     */
    public function update(Request $request, Branch $branch): JsonResponse
    {
        try {
            $validated = $request->validate([
                // code es inmutable (es la PK logica de la sede)
                'name' => 'required|string|max:255',
                'address' => 'sometimes|nullable|string|max:500',
                'city' => 'required|string|max:100',
                'state' => 'sometimes|nullable|string|max:100',
                'country' => 'sometimes|nullable|string|max:100',
                'postal_code' => 'sometimes|nullable|string|max:10',
                'phone' => 'sometimes|nullable|string|max:20',
                'email' => 'sometimes|nullable|email|max:255',
                'timezone' => 'sometimes|nullable|string|max:50',
                'latitude' => 'sometimes|nullable|numeric',
                'longitude' => 'sometimes|nullable|numeric',
                'description' => 'sometimes|nullable|string|max:1000',
                'is_active' => 'sometimes|boolean'
            ]);

            $branch->update($validated);

            return response()->json([
                'data' => $branch,
                'meta' => [
                    'message' => 'Sucursal actualizada exitosamente',
                ],
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Los datos proporcionados no son validos.',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar sucursal',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified branch
     */
    public function destroy(Branch $branch): JsonResponse
    {
        try {
            $branch->delete();

            return response()->json([
                'data' => null,
                'meta' => [
                    'message' => 'Sucursal eliminada exitosamente',
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'No se puede eliminar la sucursal. Verifica que no tenga usuarios, pacientes o sesiones de caja asociadas.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
