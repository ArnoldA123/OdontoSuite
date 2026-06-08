<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(\App\Services\ClinicalAttachmentService::class);
        $this->app->singleton(\App\Services\AiImageAnalysisService::class);
        $this->app->singleton(\App\Services\BillingService::class);
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
    }
}
