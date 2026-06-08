<?php

namespace App\Listeners;

use App\Events\AppointmentCompleted;
use App\Models\Transaction;
use Illuminate\Support\Facades\Log;

/**
 * Crea una Transaction pendiente (status=pending, type=pending_payment) cuando
 * se cierra una cita con final_amount > 0, para que el módulo de caja la liste
 * como "lista para cobrar" sin necesidad de un endpoint extra.
 *
 * NO resta inventario ni descuenta stock — eso es responsabilidad del flujo de pago.
 */
class CreateTransactionOnAppointmentCompleted
{
    public function handle(AppointmentCompleted $event): void
    {
        try {
            $appointment = $event->appointment;
            $finalAmount = (float) ($appointment->final_amount ?? 0);

            if ($finalAmount <= 0) {
                return;
            }

            $alreadyPending = Transaction::where('appointment_id', $appointment->id)
                ->where('type', 'pending_payment')
                ->exists();

            if ($alreadyPending) {
                return;
            }

            Transaction::create([
                'patient_id' => $appointment->patient_id,
                'appointment_id' => $appointment->id,
                'treatment_plan_id' => $appointment->treatment_plan_id,
                'created_by' => $event->appointment->updated_by,
                'transaction_number' => $this->generateTransactionNumber(),
                'type' => 'pending_payment',
                'amount' => $finalAmount,
                'subtotal' => $finalAmount,
                'description' => 'Pendiente de cobro generado al cerrar la cita.',
                'status' => 'pending',
                'metadata' => [
                    'source' => 'consultation_completed',
                    'consultation_mode' => $appointment->consultation_mode,
                ],
            ]);

            Log::info('Pending transaction created from consultation', [
                'appointment_id' => $appointment->id,
                'final_amount' => $finalAmount,
            ]);
        } catch (\Throwable $e) {
            Log::warning('CreateTransactionOnAppointmentCompleted failed: ' . $e->getMessage(), [
                'appointment_id' => $event->appointment->id ?? null,
            ]);
        }
    }

    private function generateTransactionNumber(): string
    {
        return 'TXN-' . date('Ymd-His') . '-' . strtoupper(substr(md5(uniqid('', true)), 0, 6));
    }
}
