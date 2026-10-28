<?php

namespace App\Http\Controllers\Api;

use App\Authorization\BranchAccess;
use App\Exceptions\AccountingLockBlockedException;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Support\AppointmentBalance;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PendingPaymentsController extends Controller
{
    /**
     * Obtener pagos pendientes.
     */
    public function index(Request $request): JsonResponse
    {
        // Verificar autenticación
        if (! Auth::check()) {
            return response()->json([
                'message' => 'No autorizado',
            ], 401);
        }

        try {
            // Query para obtener citas completadas que NO tienen transacciones asociadas
            $query = Appointment::where('status', 'completed')
                ->whereDate('scheduled_at', '<=', Carbon::today())
                ->whereDoesntHave('transactions', function ($q) {
                    $q->where('status', '!=', 'voided');
                });

            // #283 S3: los cobros pendientes son agenda, no ficha compartida.
            $branchFilter = BranchAccess::effectiveFilter(
                Auth::user(),
                $request->filled('branch_id') ? (int) $request->input('branch_id') : null,
            );

            if ($branchFilter !== null) {
                $query->where('branch_id', $branchFilter);
            }

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

            // Issue #198: the collection used to arrive whole, so the Cobros
            // tab had to page it on the client. `per_page` is clamped to the
            // documented range and the meta the UI reads is the paginator's.
            $perPage = max(1, min((int) $request->input('per_page', 15), 100));

            $appointments = $query->with(['patient', 'appointmentType', 'user'])
                ->orderBy('scheduled_at', 'desc')
                ->paginate($perPage);

            // Transformar datos con manejo de errores
            $pendingPayments = collect($appointments->items())->map(function ($appointment) {
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
                    'current_page' => $appointments->currentPage(),
                    'last_page' => $appointments->lastPage(),
                    'per_page' => $appointments->perPage(),
                    'total' => $appointments->total(),
                    'filters' => $request->only(['search', 'date_from', 'date_to']),
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('No se pudieron obtener los pagos pendientes', [
                'exception' => $e,
            ]);

            return response()->json([
                'message' => 'Error al obtener los pagos pendientes',
            ], 500);
        }
    }

    /**
     * Marcar un pago pendiente como pagado.
     *
     * Sprint 0 fix (NF-3): la ruta POST /api/pending-payments/{id}/pay devolvía
     * 501 con un TODO. Implementación real que delega en TransactionService.
     *
     * #393 — this is the paying door of the balance `ready-to-bill` publishes:
     * the amount and the payments come from `AppointmentBalance`, and the
     * appointment row is locked while the pair is read and written, so two
     * charges of the same attention cannot both pass the same balance.
     */
    public function pay(Request $request, $id): JsonResponse
    {
        try {
            $validated = $request->validate([
                'payment_method_id' => 'required|integer|exists:payment_methods,id',
                'amount' => 'required|numeric|min:0.01',
                'description' => 'nullable|string|max:255',
                'notes' => 'nullable|string|max:1000',
                'reference_number' => 'nullable|string|max:100',
                'discount_amount' => 'nullable|numeric|min:0',
                'discount_authorized_by' => 'nullable|integer|exists:users,id',
                'lock_override_reason' => 'nullable|string|max:1000',
            ]);

            return DB::transaction(function () use ($request, $validated, $id) {
                $appointment = Appointment::with(['patient', 'appointmentType'])
                    ->lockForUpdate()
                    ->find($id);

                if (! $appointment || $appointment->status !== 'completed') {
                    return response()->json([
                        'message' => 'Cita no encontrada o no completada',
                    ], 404);
                }

                // #283 S6: cobrar la cita de otra sede es 403 y ocurre antes de
                // crear la transacción, de modo que un rechazo no deja dinero
                // escrito ni un saldo sin actualizar.
                BranchAccess::assertBranch(
                    $request->user(),
                    $appointment->branch_id === null ? null : (int) $appointment->branch_id,
                );

                $summary = AppointmentBalance::summary($appointment);
                $total = $summary['total'];
                $paid = $summary['paid'];
                $pendingBalance = $summary['balance'];

                if ($total <= 0) {
                    return response()->json([
                        'message' => 'La cita no tiene monto por cobrar.',
                    ], 409);
                }

                // #393 (S3) — a charge that already happened has its own reason;
                // only an attention nobody paid is "sin saldo".
                if ($pendingBalance <= 0 && $paid > 0) {
                    return response()->json([
                        'message' => 'La cita ya tiene un pago registrado y no le queda saldo pendiente.',
                    ], 409);
                }

                if ($pendingBalance <= 0) {
                    return response()->json([
                        'message' => 'La cita no tiene saldo pendiente.',
                    ], 409);
                }

                if ((float) $validated['amount'] > $pendingBalance) {
                    return response()->json([
                        'message' => "El monto ({$validated['amount']}) excede el saldo pendiente ({$pendingBalance}).",
                    ], 422);
                }

                $transaction = app(\App\Services\TransactionService::class)->createTransaction([
                    'patient_id' => $appointment->patient_id,
                    'appointment_id' => $appointment->id,
                    'payment_method_id' => $validated['payment_method_id'],
                    'amount' => $validated['amount'],
                    'type' => 'payment',
                    'description' => $validated['description']
                        ?? "Pago de cita #{$appointment->id} - {$appointment->patient->full_name}",
                    'notes' => $validated['notes'] ?? null,
                    'reference_number' => $validated['reference_number'] ?? null,
                    'discount_amount' => $validated['discount_amount'] ?? 0,
                    'discount_authorized_by' => $validated['discount_authorized_by'] ?? null,
                    'lock_override_reason' => $validated['lock_override_reason'] ?? null,
                ]);

                $newPaidAmount = round($paid + (float) $validated['amount'], 2);
                $newBalance = round(max(0, $total - $newPaidAmount), 2);
                $appointment->update([
                    'total_cost' => $total,
                    'paid_amount' => $newPaidAmount,
                    'balance' => $newBalance,
                ]);

                return response()->json([
                    'data' => $transaction->load(['patient', 'paymentMethod', 'createdBy']),
                    'meta' => [
                        'message' => 'Pago registrado correctamente.',
                        'appointment_balance' => $newBalance,
                    ],
                ], 200);
            });
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (AccountingLockBlockedException $e) {
            // #410 (S9): the accounting freeze answers its own 403 with its
            // machine code, not the generic 500 of this door.
            throw $e;
        } catch (AuthorizationException $e) {
            // #283 S6: cobrar la cita de otra sede se responde 403, no 500, y
            // ocurre antes de crear la transacción.
            throw $e;
        } catch (\Exception $e) {
            Log::error('No se pudo procesar el pago pendiente', [
                'exception' => $e,
                'appointment_id' => $id,
            ]);

            return response()->json([
                'message' => 'Error al procesar el pago pendiente',
            ], 500);
        }
    }
}
