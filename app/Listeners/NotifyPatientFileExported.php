<?php

namespace App\Listeners;

use App\Events\PatientFileExported;
use App\Models\AuditLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

class NotifyPatientFileExported
{
    /**
     * Sprint 0 fix (NF-5): notifica al usuario que el export está listo.
     * - Loguea en AuditLog si hay usuario autenticado.
     * - Envía email con link de descarga firmado (válido 60 min) si hay email.
     * - Loguea a storage/logs/laravel.log siempre (trazabilidad mínima).
     */
    public function handle(PatientFileExported $event): void
    {
        $user = $event->user ?? Auth::user();
        $patient = $event->patient;

        $message = sprintf(
            'Patient file exported: patient=%d (%s) format=%s file=%s user=%s',
            $patient->id,
            $patient->full_name ?? "{$patient->first_name} {$patient->last_name}",
            $event->format,
            $event->filePath,
            $user?->id ?? 'guest',
        );

        Log::info($message);

        if ($user) {
            try {
                AuditLog::log(
                    $user,
                    'patient_file_exported',
                    $patient,
                    [],
                    [
                        'format' => $event->format,
                        'file_path' => $event->filePath,
                    ]
                );
            } catch (\Exception $e) {
                Log::warning('AuditLog failed for PatientFileExported: ' . $e->getMessage());
            }
        }

        if ($user && $user->email) {
            try {
                $downloadUrl = URL::temporarySignedRoute(
                    'patient-export.download',
                    now()->addMinutes(60),
                    ['patient' => $patient->id, 'format' => $event->format]
                );

                Mail::raw(
                    "El archivo del paciente {$patient->full_name} ({$event->format}) está listo para descargar.\n\nDescargá desde: {$downloadUrl}\n\nEl enlace expira en 60 minutos.",
                    function ($message) use ($user, $patient, $event) {
                        $message->to($user->email)
                            ->subject("Expediente de {$patient->full_name} listo - {$event->format}");
                    }
                );
            } catch (\Exception $e) {
                Log::warning('Email notification failed for PatientFileExported: ' . $e->getMessage());
            }
        }
    }
}
