<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Concerns\HasCrudRules;
use App\Models\PaymentMethod;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PaymentMethodController extends Controller
{
    use HasCrudRules;

    /**
     * Rules shared by the store and update actions.
     */
    protected function storeRules(): array
    {
        return [
            'code' => 'required|string|max:20|unique:payment_methods,code',
            'name' => 'required|string|max:50',
            'type' => ['sometimes', 'string', Rule::in(PaymentMethod::TYPES)],
            'description' => 'sometimes|nullable|string|max:500',
            'gateway_type' => 'sometimes|nullable|string|in:mercadopago,manual',
            'gateway_config' => 'sometimes|nullable|array',
            'requires_authorization' => 'sometimes|boolean',
            'requires_reference' => 'sometimes|boolean',
            'allows_change' => 'sometimes|boolean',
            'commission_percentage' => 'sometimes|numeric|min:0|max:100',
            'is_active' => 'sometimes|boolean',
        ];
    }

    /**
     * Display a listing of payment methods.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = PaymentMethod::query()->withCount('transactions');

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

            return $this->methodsResponse($query->orderBy('name')->get(), 'Metodos de pago cargados exitosamente');
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al cargar metodos de pago',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the active payment methods for cash-register dropdowns.
     */
    public function active(): JsonResponse
    {
        try {
            $methods = PaymentMethod::query()
                ->active()
                ->withCount('transactions')
                ->orderBy('name')
                ->get();

            return $this->methodsResponse($methods, 'Metodos de pago activos cargados exitosamente');
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al cargar metodos de pago activos',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @param Collection<int, PaymentMethod> $methods
     */
    private function methodsResponse(Collection $methods, string $message): JsonResponse
    {
        return response()->json([
            'data' => $methods->map(fn (PaymentMethod $method) => $this->serializeMethod($method))->all(),
            'meta' => [
                'message' => $message,
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeMethod(PaymentMethod $method): array
    {
        $data = $method->makeHidden('gateway_config')->toArray();
        $data['has_gateway_config'] = ! is_null($method->gateway_config);
        $data['transactions_count'] = (int) ($method->transactions_count ?? 0);

        return $data;
    }

    /**
     * Store a newly created payment method.
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate($this->storeRules());

            $validated['type'] = $validated['type'] ?? PaymentMethod::TYPE_CASH;
            $validated['is_active'] = $validated['is_active'] ?? true;
            $validated['is_system'] = false;
            $validated['commission_percentage'] = $validated['commission_percentage'] ?? 0;
            $validated['allows_change'] = $validated['allows_change'] ?? true;
            $validated['requires_authorization'] = $validated['requires_authorization'] ?? false;
            $validated['requires_reference'] = $validated['requires_reference'] ?? false;
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
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al crear metodo de pago',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified payment method.
     */
    public function show(PaymentMethod $paymentMethod): JsonResponse
    {
        // El admin puede ver si tiene credenciales (sin ver el valor)
        $data = $paymentMethod->toArray();
        $data['has_gateway_config'] = ! is_null($paymentMethod->gateway_config);
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
     * Update the specified payment method.
     */
    public function update(Request $request, PaymentMethod $paymentMethod): JsonResponse
    {
        try {
            $validated = $request->validate($this->updateRules($this->storeRules(), ['code', 'is_system'], [
                // name sigue siendo obligatorio al actualizar
                'name' => 'required|string|max:50',
            ]));

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
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar metodo de pago',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified payment method.
     */
    public function destroy(PaymentMethod $paymentMethod): JsonResponse
    {
        try {
            // Sprint 2: no permitir borrar metodos del sistema.
            // Solo se pueden desactivar (is_active=false).
            if ($paymentMethod->is_system) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se puede eliminar un metodo del sistema. Desactivalo en su lugar usando is_active=false.',
                ], 403);
            }

            // Verificar que no tenga transacciones asociadas
            $txCount = $paymentMethod->transactions()->count();
            if ($txCount > 0) {
                return response()->json([
                    'success' => false,
                    'message' => "No se puede eliminar: el metodo tiene {$txCount} transaccion(es) registrada(s). Desactivalo en su lugar.",
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
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
