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
    public function index(): JsonResponse
    {
        try {
            $paymentMethods = PaymentMethod::where('is_active', true)
                ->orderBy('name')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $paymentMethods
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al cargar métodos de pago',
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
            $request->validate([
                'name' => 'required|string|max:255',
                'description' => 'nullable|string|max:500',
                'is_active' => 'boolean'
            ]);

            $paymentMethod = PaymentMethod::create([
                'name' => $request->name,
                'description' => $request->description,
                'is_active' => $request->is_active ?? true
            ]);

            return response()->json([
                'success' => true,
                'data' => $paymentMethod,
                'message' => 'Método de pago creado exitosamente'
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al crear método de pago',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified payment method
     */
    public function show(PaymentMethod $paymentMethod): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $paymentMethod
        ]);
    }

    /**
     * Update the specified payment method
     */
    public function update(Request $request, PaymentMethod $paymentMethod): JsonResponse
    {
        try {
            $request->validate([
                'name' => 'required|string|max:255',
                'description' => 'nullable|string|max:500',
                'is_active' => 'boolean'
            ]);

            $paymentMethod->update([
                'name' => $request->name,
                'description' => $request->description,
                'is_active' => $request->is_active ?? $paymentMethod->is_active
            ]);

            return response()->json([
                'success' => true,
                'data' => $paymentMethod,
                'message' => 'Método de pago actualizado exitosamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar método de pago',
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
            $paymentMethod->delete();

            return response()->json([
                'success' => true,
                'message' => 'Método de pago eliminado exitosamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar método de pago',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
