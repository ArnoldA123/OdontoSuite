<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * BF-021 (slice 11): removed 3 redundant `$this->app->singleton(...)`
     * bindings for ClinicalAttachmentService, AiImageAnalysisService and
     * BillingService. Laravel's container auto-resolves unbound services
     * per-request; explicit singletons here were redundant surface area.
     * Service bindings with custom factories or scoped state still belong
     * here.
     */
    public function register(): void
    {
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Registrar event listeners para auditoría
        \Illuminate\Support\Facades\Event::listen(
            \App\Events\AppointmentCreated::class,
            \App\Listeners\LogAppointmentActivity::class
        );

        \Illuminate\Support\Facades\Event::listen(
            \App\Events\AppointmentUpdated::class,
            \App\Listeners\LogAppointmentActivity::class
        );

        \Illuminate\Support\Facades\Event::listen(
            \App\Events\AppointmentDeleted::class,
            \App\Listeners\LogAppointmentActivity::class
        );

        \Illuminate\Support\Facades\Event::listen(
            \App\Events\PatientCreated::class,
            \App\Listeners\LogPatientActivity::class
        );

        \Illuminate\Support\Facades\Event::listen(
            \App\Events\PatientUpdated::class,
            \App\Listeners\LogPatientActivity::class
        );

        \Illuminate\Support\Facades\Event::listen(
            \App\Events\PatientDeleted::class,
            \App\Listeners\LogPatientActivity::class
        );

        // Sprint 3: auto-crear transaction pendiente cuando se cierra una cita con monto
        \Illuminate\Support\Facades\Event::listen(
            \App\Events\AppointmentCompleted::class,
            \App\Listeners\CreateTransactionOnAppointmentCompleted::class
        );

        // Sprint 0 fix (NF-5): notifica al usuario cuando un export de paciente termina.
        \Illuminate\Support\Facades\Event::listen(
            \App\Events\PatientFileExported::class,
            \App\Listeners\NotifyPatientFileExported::class
        );

        // Sprint 3 fix (IM-4): notifica cuando un procedimiento se desactiva.
        \Illuminate\Support\Facades\Event::listen(
            \App\Events\ProcedureCatalogDeactivated::class,
            \App\Listeners\NotifyProcedureDeactivation::class
        );

        // Sprint 3 fix (IM-7): persiste historial de versiones.
        \Illuminate\Support\Facades\Event::listen(
            \App\Events\ProcedureCatalogUpdated::class,
            \App\Listeners\TrackProcedureVersion::class
        );

        // Slice 03 (T-03.9): audit trail for ReminderSent deliveries.
        \Illuminate\Support\Facades\Event::listen(
            \App\Events\ReminderSent::class,
            \App\Listeners\TrackReminderDelivery::class
        );

        // Slice 10 (T-10.3): audit trail for PaymentReceived (MercadoPago + others).
        \Illuminate\Support\Facades\Event::listen(
            \App\Events\PaymentReceived::class,
            \App\Listeners\LogPaymentReceived::class
        );

        // Slice 10 (T-10.3 + T-10.4): audit trail for AppointmentCheckedIn
        // and the broadcast moved to a PrivateChannel.
        \Illuminate\Support\Facades\Event::listen(
            \App\Events\AppointmentCheckedIn::class,
            \App\Listeners\LogAppointmentCheckedIn::class
        );
    }
}
