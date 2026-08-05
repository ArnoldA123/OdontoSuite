<?php

namespace App\Listeners;

use App\Events\AppointmentCompleted;
use Illuminate\Support\Facades\Log;

/**
 * Hook de billing post-consulta (Sprint 3).
 *
 * El modelo `transactions` requiere `payment_method_id` (FK NOT NULL) que
 * solo existe DESPUÉS de que el cajero registra un pago real. Por eso
 * este listener ya no crea una Transaction pendiente automáticamente:
 *
 * - El listado "ready to bill" se obtiene directo del Appointment
 *   (status=completed, final_amount>0, sin pago) desde
 *   `GET /api/appointments/ready-to-bill`.
 * - La Transaction real se crea cuando el cajero aplica un método
 *   de pago en el módulo de caja.
 *
 * Queda como no-op + log para mantener el listener registrado
 * (lo consume el módulo de caja vía evento `appointment.completed`
 * para refrescar contadores) y para futura lógica derivada
 * (ej. auditoría, notificaciones).
 */
class CreateTransactionOnAppointmentCompleted
{
    public function handle(AppointmentCompleted $event): void
    {
        try {
            $appointment = $event->appointment;
            $finalAmount = (float) ($appointment->final_amount ?? 0);

            Log::info('Appointment completed event received', [
                'appointment_id' => $appointment->id,
                'final_amount' => $finalAmount,
                'consultation_mode' => $appointment->consultation_mode,
            ]);
        } catch (\Throwable $e) {
            // AGENTS.md §7: listener MUST swallow + log + report. Failure
            // here MUST NOT crash the consultation flow.
            Log::error('CreateTransactionOnAppointmentCompleted failed: ' . $e->getMessage(), [
                'appointment_id' => $event->appointment->id ?? null,
                'exception' => $e,
            ]);
            report($e);
        }
    }
}
