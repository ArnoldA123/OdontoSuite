<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Interconsultation;
use App\Events\InterconsultationCreated;
use App\Events\InterconsultationResponded;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class InterconsultationController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = Interconsultation::with(['patient', 'fromSpecialist', 'toSpecialist', 'appointment']);

            // Filtros
            if ($request->has('patient_id')) {
                $query->where('patient_id', $request->get('patient_id'));
            }

            if ($request->has('to_specialist')) {
                $query->where('to_specialist_id', $request->get('to_specialist'));
            }

            if ($request->has('from_specialist')) {
                $query->where('from_specialist_id', $request->get('from_specialist'));
            }

            if ($request->has('status')) {
                $query->where('status', $request->get('status'));
            }

            if ($request->has('priority')) {
                $query->where('priority', $request->get('priority'));
            }

            if ($request->has('specialty_from')) {
                $query->where('specialty_from', $request->get('specialty_from'));
            }

            if ($request->has('specialty_to')) {
                $query->where('specialty_to', $request->get('specialty_to'));
            }

            $interconsultations = $query->orderBy('requested_date', 'desc')->paginate(15);

            return response()->json([
                'data' => $interconsultations->items(),
                'meta' => [
                    'current_page' => $interconsultations->currentPage(),
                    'last_page' => $interconsultations->lastPage(),
                    'per_page' => $interconsultations->perPage(),
                    'total' => $interconsultations->total(),
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al obtener interconsultas',
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
                'patient_id' => 'required|exists:patients,id',
                'to_specialist_id' => 'required|exists:users,id',
                'appointment_id' => 'nullable|exists:appointments,id',
                'specialty_from' => 'required|string',
                'specialty_to' => 'required|string',
                'reason' => 'nullable|string',
                'clinical_question' => 'nullable|string',
                'clinical_data' => 'nullable|string',
                'requested_studies' => 'nullable|string',
                'priority' => 'required|in:low,medium,high,urgent',
                'requested_date' => 'nullable|date'
            ]);

            $validated['from_specialist_id'] = Auth::id();
            $validated['requested_date'] = $validated['requested_date'] ?? now()->toDateString();

            $interconsultation = Interconsultation::create($validated);
            $interconsultation->load(['patient', 'fromSpecialist', 'toSpecialist', 'appointment']);

            // Emitir evento de WebSocket
            event(new InterconsultationCreated($interconsultation));

            return response()->json([
                'data' => $interconsultation,
                'meta' => [
                    'message' => 'Interconsulta creada exitosamente'
                ]
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al crear interconsulta',
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
            $interconsultation = Interconsultation::with([
                'patient',
                'fromSpecialist',
                'toSpecialist',
                'appointment'
            ])->findOrFail($id);

            return response()->json([
                'data' => $interconsultation
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Interconsulta no encontrada',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        try {
            $validated = $request->validate([
                'reason' => 'nullable|string',
                'clinical_question' => 'nullable|string',
                'clinical_data' => 'nullable|string',
                'requested_studies' => 'nullable|string',
                'priority' => 'nullable|in:low,medium,high,urgent'
            ]);

            $interconsultation = Interconsultation::findOrFail($id);
            $interconsultation->update($validated);

            return response()->json([
                'data' => $interconsultation->load(['patient', 'fromSpecialist', 'toSpecialist', 'appointment']),
                'meta' => [
                    'message' => 'Interconsulta actualizada exitosamente'
                ]
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al actualizar interconsulta',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $interconsultation = Interconsultation::findOrFail($id);

            // Solo permitir eliminar si está pendiente
            if ($interconsultation->status !== 'pending') {
                return response()->json([
                    'message' => 'No se puede eliminar una interconsulta que no esté pendiente'
                ], 422);
            }

            $interconsultation->delete();

            return response()->json([
                'meta' => [
                    'message' => 'Interconsulta eliminada exitosamente'
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al eliminar interconsulta',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Responder interconsulta
     */
    public function respond(Request $request, int $id): JsonResponse
    {
        try {
            $validated = $request->validate([
                'response' => 'required|string',
                'recommendations' => 'nullable|string',
                'follow_up_notes' => 'nullable|string',
                'follow_up_date' => 'nullable|date|after:today'
            ]);

            $interconsultation = Interconsultation::findOrFail($id);

            // Verificar que el usuario actual es el especialista destinatario
            if ($interconsultation->to_specialist_id !== Auth::id()) {
                return response()->json([
                    'message' => 'No tienes permisos para responder esta interconsulta'
                ], 403);
            }

            $validated['response_date'] = now()->toDateString();
            $validated['status'] = 'completed';

            $interconsultation->update($validated);
            $interconsultation->load(['patient', 'fromSpecialist', 'toSpecialist', 'appointment']);

            // Emitir evento de WebSocket
            event(new InterconsultationResponded($interconsultation));

            return response()->json([
                'data' => $interconsultation,
                'meta' => [
                    'message' => 'Interconsulta respondida exitosamente'
                ]
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al responder interconsulta',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Completar interconsulta
     */
    public function complete(Request $request, int $id): JsonResponse
    {
        try {
            $validated = $request->validate([
                'follow_up_notes' => 'nullable|string',
                'follow_up_date' => 'nullable|date|after:today'
            ]);

            $interconsultation = Interconsultation::findOrFail($id);

            // Verificar que el usuario actual es el especialista que envió la interconsulta
            if ($interconsultation->from_specialist_id !== Auth::id()) {
                return response()->json([
                    'message' => 'No tienes permisos para completar esta interconsulta'
                ], 403);
            }

            $validated['status'] = 'completed';
            $interconsultation->update($validated);

            return response()->json([
                'data' => $interconsultation->load(['patient', 'fromSpecialist', 'toSpecialist', 'appointment']),
                'meta' => [
                    'message' => 'Interconsulta completada exitosamente'
                ]
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al completar interconsulta',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener interconsultas del usuario actual
     */
    public function myInterconsultations(Request $request): JsonResponse
    {
        try {
            $userId = Auth::id();
            $type = $request->get('type', 'all'); // all, sent, received

            $query = Interconsultation::with(['patient', 'fromSpecialist', 'toSpecialist', 'appointment']);

            if ($type === 'sent') {
                $query->where('from_specialist_id', $userId);
            } elseif ($type === 'received') {
                $query->where('to_specialist_id', $userId);
            } else {
                $query->where(function ($q) use ($userId) {
                    $q->where('from_specialist_id', $userId)
                      ->orWhere('to_specialist_id', $userId);
                });
            }

            // Filtros adicionales
            if ($request->has('status')) {
                $query->where('status', $request->get('status'));
            }

            if ($request->has('priority')) {
                $query->where('priority', $request->get('priority'));
            }

            $interconsultations = $query->orderBy('requested_date', 'desc')->paginate(15);

            return response()->json([
                'data' => $interconsultations->items(),
                'meta' => [
                    'current_page' => $interconsultations->currentPage(),
                    'last_page' => $interconsultations->lastPage(),
                    'per_page' => $interconsultations->perPage(),
                    'total' => $interconsultations->total(),
                    'type' => $type
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al obtener interconsultas',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
