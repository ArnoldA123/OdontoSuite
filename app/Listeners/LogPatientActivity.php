<?php

namespace App\Listeners;

use App\Events\PatientCreated;
use App\Events\PatientUpdated;
use App\Events\PatientDeleted;
use App\Models\AuditLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class LogPatientActivity
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

            if ($event instanceof PatientCreated) {
                AuditLog::log(
                    $user,
                    'patient_created',
                    $event->patient,
                    [],
                    $event->patient->toArray()
                );
            } elseif ($event instanceof PatientUpdated) {
                AuditLog::log(
                    $user,
                    'patient_updated',
                    $event->patient,
                    $event->oldValues ?? [],
                    $event->patient->toArray()
                );
            } elseif ($event instanceof PatientDeleted) {
                // Para deleted, necesitamos crear una instancia temporal
                $tempPatient = new \App\Models\Patient();
                $tempPatient->id = $event->patientId;
                
                AuditLog::log(
                    $user,
                    'patient_deleted',
                    $tempPatient,
                    $event->oldValues ?? [],
                    []
                );
            }
        } catch (\Exception $e) {
            Log::channel('audit')->error('Error logging patient activity', [
                'event' => get_class($event),
                'error' => $e->getMessage(),
            ]);
        }
    }
}

