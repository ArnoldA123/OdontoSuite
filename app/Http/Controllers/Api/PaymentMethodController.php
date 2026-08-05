<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PaymentMethod;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class PaymentMethodController extends Controller
{
    /**
     * Display a listing of payment methods
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = PaymentMethod::query();

            // Filtro por estado
            if ($request->has('is_active') && $request->is_active !== '') {
                $query->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN));
            }

            // Busqueda
            if ($request->has('q') && $request->q !== '') {
                $q = $request->q;
                $query->where(function ($w) use ($q) {
                    $w->where('name', 'like', "%$q%")
                      ->orWhere('code', 'like', "%$q%")
                      ->orWhere('description', 'like', "%$q%");
                });
            }

            $methods = $query->orderBy('name')->get();

            // Nunca devolver gateway_config en el listado (seguridad)
            $methods->makeHidden('gateway_config');

            return response()->json([
                'data' => $methods,
                'meta' => [
                    'message' => 'Metodos de pago cargados exitosamente',
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al cargar metodos de pago',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created payment method
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'code' => 'required|string|max:20|unique:payment_methods,code',
                'name' => 'required|string|max:50',
                'description' => 'sometimes|nullable|string|max:500',
                'gateway_type' => 'sometimes|nullable|string|in:mercadopago,manual',
                'gateway_config' => 'sometimes|nullable|array',
                'requires_authorization' => 'sometimes|boolean',
                'allows_change' => 'sometimes|boolean',
                'commission_percentage' => 'sometimes|numeric|min:0|max:100',
                'is_active' => 'sometimes|boolean',
                // Los metodos custom (no-seed) no son del sistema
                'is_system' => 'sometimes|boolean'
            ]);

            $validated['is_active'] = $validated['is_active'] ?? true;
            $validated['is_system'] = $validated['is_system'] ?? false;
            $validated['commission_percentage'] = $validated['commission_percentage'] ?? 0;
            $validated['allows_change'] = $validated['allows_change'] ?? true;
            $validated['requires_authorization'] = $validated['requires_authorization'] ?? false;
            $validated['gateway_type'] = $validated['gateway_type'] ?? 'manual';
            $validated['gateway_config'] = $validated['gateway_config'] ?? null;

            $method = PaymentMethod::create($validated);

            // No devolver el gateway_config encriptado al cliente
            $method->makeHidden('gateway_config');

            return response()->json([
                'data' => $method,
                'meta' => [
                    'message' => 'Metodo de pago creado exitosamente',
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
                'message' => 'Error al crear metodo de pago',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified payment method
     */
    public function show(PaymentMethod $paymentMethod): JsonResponse
    {
        // El admin puede ver si tiene credenciales (sin ver el valor)
        $data = $paymentMethod->toArray();
        $data['has_gateway_config'] = !is_null($paymentMethod->gateway_config);
        // Nunca devolver el secret encriptado
        $data['gateway_config'] = null;

        return response()->json([
            'data' => $data,
            'meta' => [
                'message' => 'Metodo de pago obtenido exitosamente',
            ],
        ]);
    }

    /**
     * Update the specified payment method
     */
    public function update(Request $request, PaymentMethod $paymentMethod): JsonResponse
    {
        try {
            $validated = $request->validate([
                // code inmutable
                'name' => 'required|string|max:50',
                'description' => 'sometimes|nullable|string|max:500',
                'gateway_type' => 'sometimes|nullable|string|in:mercadopago,manual',
                'gateway_config' => 'sometimes|nullable|array',
                'requires_authorization' => 'sometimes|boolean',
                'allows_change' => 'sometimes|boolean',
                'commission_percentage' => 'sometimes|numeric|min:0|max:100',
                'is_active' => 'sometimes|boolean'
            ]);

            $paymentMethod->update($validated);

            $paymentMethod->makeHidden('gateway_config');

            return response()->json([
                'data' => $paymentMethod,
                'meta' => [
                    'message' => 'Metodo de pago actualizado exitosamente',
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
                'message' => 'Error al actualizar metodo de pago',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified payment method
     */
    public function destroy(PaymentMethod $paymentMethod): JsonResponse
    {
        try {
            // Sprint 2: no permitir borrar metodos del sistema.
            // Solo se pueden desactivar (is_active=false).
            if ($paymentMethod->is_system) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se puede eliminar un metodo del sistema. Desactivalo en su lugar usando is_active=false.'
                ], 403);
            }

            // Verificar que no tenga transacciones asociadas
            $txCount = $paymentMethod->transactions()->count();
            if ($txCount > 0) {
                return response()->json([
                    'success' => false,
                    'message' => "No se puede eliminar: el metodo tiene {$txCount} transaccion(es) registrada(s). Desactivalo en su lugar."
                ], 409);
            }

            $paymentMethod->delete();

            return response()->json([
                'data' => null,
                'meta' => [
                    'message' => 'Metodo de pago eliminado exitosamente',
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar metodo de pago',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
