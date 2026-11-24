<?php

namespace App\Http\Controllers\Api;

use App\Authorization\BranchAccess;
use App\Exceptions\AccountingLockBlockedException;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Services\AccountingLockService;
use App\Services\BillingService;
use App\Services\TransactionService;
use App\Support\AppointmentBalance;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PendingPaymentsController extends Controller
{
    public function __construct(private readonly BillingService $billing) {}

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
            // #393 — Cobros and `appointments/ready-to-bill` are the same set:
            // the debt and the payments come from `AppointmentBalance`, so the
            // balance the desk lists is the balance the paying door collects.
            // The old query listed every completed appointment without a
            // transaction and priced it with the appointment type's catalog
            // price, which published 0.00 debts and amounts nobody owed.
            $appointments = $this->billing->readyToBill([
                'search' => $request->input('search'),
                'from' => $request->input('date_from'),
                'to' => $request->input('date_to'),
                'patient_id' => $request->input('patient_id'),
                'branch_id' => $request->input('branch_id'),
                'per_page' => $request->input('per_page', 15),
            ]);

            $pendingPayments = collect($appointments->items())->map(function (Appointment $appointment) {
                $balance = AppointmentBalance::summary($appointment);

                return [
                    'id' => $appointment->id,
                    'patient' => [
                        'id' => $appointment->patient_id,
                        'name' => $appointment->patient?->name ?? 'Paciente no encontrado',
                        'document_number' => $appointment->patient?->document_number ?? 'N/A',
                        'email' => $appointment->patient?->email ?? 'N/A',
                        'phone' => $appointment->patient?->phone ?? 'N/A',
                    ],
                    'appointment' => [
                        'id' => $appointment->id,
                        'date' => $appointment->scheduled_at,
                        'appointment_type' => [
                            'id' => $appointment->appointment_type_id,
                            'name' => $appointment->appointmentType?->name ?? 'Tipo no encontrado',
                        ],
                        'professional' => [
                            'id' => $appointment->user_id,
                            'name' => $appointment->user?->name ?? 'Profesional no encontrado',
                        ],
                    ],
                    'concept' => $appointment->appointmentType?->name ?? 'Consulta',
                    'amount' => $balance['balance'],
                    'total_amount' => $balance['total'],
                    'paid_amount' => $balance['paid'],
                    'balance' => $balance['balance'],
                    'status' => 'pending',
                    'created_at' => $appointment->created_at,
                ];
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

            // #466 — the guard runs before the transaction below, so a blocked
            // attempt keeps its `audit_logs` entry instead of rolling back with
            // the refusal. The drawer is resolved here, so the pre-check reads
            // the appointment plainly and the authoritative locked read stays
            // inside the transaction.
            //
            // The flag records whether the guard actually ran: if this plain
            // read does not see a closed attention but the locked read does, the
            // service guards inside the transaction instead of writing money no
            // guard ever saw (its evidence may roll back, but the money never
            // enters).
            $accountingLockGuarded = false;
            $guardedAppointment = Appointment::find($id);

            if ($guardedAppointment instanceof Appointment
                && $guardedAppointment->status === 'completed') {
                BranchAccess::assertBranch(
                    $request->user(),
                    $guardedAppointment->branch_id === null ? null : (int) $guardedAppointment->branch_id,
                );

                $cashSession = app(TransactionService::class)->activeCashSession();

                app(AccountingLockService::class)->guard(
                    AccountingLockService::CASH_TRANSACTIONS,
                    now(),
                    $cashSession?->branch_id ?? Auth::user()?->branch_id,
                    $guardedAppointment,
                    'create',
                    $validated['lock_override_reason'] ?? null,
                );

                $accountingLockGuarded = true;
            }

            return DB::transaction(function () use ($request, $validated, $id, $accountingLockGuarded) {
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

                $transaction = app(TransactionService::class)->createTransaction([
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
                ], accountingLockGuarded: $accountingLockGuarded);

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
