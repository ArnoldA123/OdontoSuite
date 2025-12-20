<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Patient;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class PendingPaymentsController extends Controller
{
    /**
     * Obtener pagos pendientes
     */
    public function index(Request $request): JsonResponse
    {
        // Verificar autenticación
        if (!Auth::check()) {
            return response()->json([
                'message' => 'No autorizado'
            ], 401);
        }

        try {
            // Query para obtener citas completadas que NO tienen transacciones asociadas
            $query = Appointment::where('status', 'completed')
                ->whereDate('scheduled_at', '<=', Carbon::today())
                ->whereDoesntHave('transactions', function ($q) {
                    $q->where('status', '!=', 'voided');
                });

            // Filtros
            if ($request->filled('search')) {
                $search = $request->get('search');
                $query->whereHas('patient', function ($q) use ($search) {
                    $q->whereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ["%{$search}%"])
                      ->orWhere('document_number', 'like', "%{$search}%");
                });
            }

            if ($request->filled('date_from')) {
                $query->whereDate('scheduled_at', '>=', $request->get('date_from'));
            }

            if ($request->filled('date_to')) {
                $query->whereDate('scheduled_at', '<=', $request->get('date_to'));
            }

            $appointments = $query->orderBy('scheduled_at', 'desc')->get();

            // Transformar datos con manejo de errores
            $pendingPayments = $appointments->map(function ($appointment) {
                try {
                    return [
                        'id' => $appointment->id,
                        'patient' => [
                            'id' => $appointment->patient_id,
                            'name' => $appointment->patient ? $appointment->patient->name : 'Paciente no encontrado',
                            'document_number' => $appointment->patient ? $appointment->patient->document_number : 'N/A',
                            'email' => $appointment->patient ? $appointment->patient->email : 'N/A',
                            'phone' => $appointment->patient ? $appointment->patient->phone : 'N/A',
                        ],
                        'appointment' => [
                            'id' => $appointment->id,
                            'date' => $appointment->scheduled_at,
                            'appointment_type' => [
                                'id' => $appointment->appointment_type_id,
                                'name' => $appointment->appointmentType ? $appointment->appointmentType->name : 'Tipo no encontrado',
                            ],
                            'professional' => [
                                'id' => $appointment->user_id,
                                'name' => $appointment->user ? $appointment->user->name : 'Profesional no encontrado',
                            ],
                        ],
                        'concept' => $appointment->appointmentType ? $appointment->appointmentType->name : 'Consulta',
                        'amount' => $appointment->appointmentType ? ($appointment->appointmentType->price ?? 0) : 0,
                        'status' => 'pending',
                        'created_at' => $appointment->created_at,
                    ];
                } catch (\Exception $e) {
                    return [
                        'id' => $appointment->id,
                        'patient' => [
                            'id' => $appointment->patient_id,
                            'name' => 'Error al cargar paciente',
                            'document_number' => 'N/A',
                            'email' => 'N/A',
                            'phone' => 'N/A',
                        ],
                        'appointment' => [
                            'id' => $appointment->id,
                            'date' => $appointment->scheduled_at,
                            'appointment_type' => [
                                'id' => $appointment->appointment_type_id,
                                'name' => 'Error al cargar tipo',
                            ],
                            'professional' => [
                                'id' => $appointment->user_id,
                                'name' => 'Error al cargar profesional',
                            ],
                        ],
                        'concept' => 'Error',
                        'amount' => 0,
                        'status' => 'pending',
                        'created_at' => $appointment->created_at,
                    ];
                }
            });

            return response()->json([
                'data' => $pendingPayments,
                'meta' => [
                    'total' => $pendingPayments->count(),
                    'filters' => $request->only(['search', 'date_from', 'date_to'])
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al obtener los pagos pendientes',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    }

    /**
     * Obtener detalles de un pago pendiente específico
     */
    public function show($id): JsonResponse
    {
        try {
            $appointment = Appointment::with([
                'patient',
                'appointmentType',
                'transactions'
            ])
            ->where('id', $id)
            ->where('status', 'completed')
            ->first();

            if (!$appointment) {
                return response()->json([
                    'message' => 'Cita no encontrada o no completada'
                ], 404);
            }

            $payment = [
                'id' => $appointment->id,
                'patient' => [
                    'id' => $appointment->patient->id,
                    'name' => $appointment->patient->name,
                    'document_number' => $appointment->patient->document_number,
                    'email' => $appointment->patient->email,
                    'phone' => $appointment->patient->phone,
                ],
                'appointment' => [
                    'id' => $appointment->id,
                    'date' => $appointment->scheduled_at,
                    'appointment_type' => [
                        'id' => $appointment->appointmentType->id,
                        'name' => $appointment->appointmentType->name,
                    ],
                ],
                'treatment_plan' => null,
                'concept' => $appointment->appointmentType->name,
                'amount' => $appointment->appointmentType->price ?? 0,
                'status' => 'pending',
                'created_at' => $appointment->created_at,
            ];

            return response()->json([
                'data' => $payment
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al obtener el pago pendiente',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
