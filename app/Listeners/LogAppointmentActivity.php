<?php

namespace App\Listeners;

use App\Events\AppointmentCreated;
use App\Events\AppointmentUpdated;
use App\Events\AppointmentDeleted;
use App\Models\AuditLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class LogAppointmentActivity
{
    /**
     * Handle the event.
     */
    public function handle($event): void
    {
        try {
            if (!Auth::check()) {
                return;
            }

            $user = Auth::user();

            if ($event instanceof AppointmentCreated) {
                AuditLog::log(
                    $user,
                    'appointment_created',
                    $event->appointment,
                    [],
                    $event->appointment->toArray()
                );
            } elseif ($event instanceof AppointmentUpdated) {
                // Para updated, necesitamos los valores antiguos
                // Estos deberían pasarse en el evento o recuperarse antes de la actualización
                AuditLog::log(
                    $user,
                    'appointment_updated',
                    $event->appointment,
                    $event->oldValues ?? [],
                    $event->appointment->toArray()
                );
            } elseif ($event instanceof AppointmentDeleted) {
                // Para deleted, usar appointment si está disponible, sino crear instancia temporal
                $appointment = $event->appointment;
                if (!$appointment) {
                    $appointment = new \App\Models\Appointment();
                    $appointment->id = $event->appointmentId;
                }
                
                AuditLog::log(
                    $user,
                    'appointment_deleted',
                    $appointment,
                    $event->oldValues ?? [],
                    []
                );
            }
        } catch (\Exception $e) {
            Log::channel('audit')->error('Error logging appointment activity', [
                'event' => get_class($event),
                'error' => $e->getMessage(),
            ]);
        }
    }
}

