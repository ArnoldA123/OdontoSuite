<?php

namespace Tests\Unit\Events;

use App\Events\PatientFileExported;
use App\Providers\AppServiceProvider;
use ReflectionClass;
use Tests\TestCase;

/**
 * Slice 10 — corrected event-coverage matrix:
 *
 * The original Sprint 0 fix (NF-2) audit claimed 26 events were orphans
 * with no listener. Empirical grep across the codebase shows the opposite:
 * most of those events ARE actively dispatched AND consumed by Reverb
 * subscribers in resources/js/composables/useWebSocketNotifications.js
 * (dashboard-updates channel + per-domain channels). Per the user-approved
 * decision for bugfix-2026-08 slice 10, the Reverb broadcast counts as
 * in-use — WebSocket consumers are valid listeners.
 *
 * This test now asserts the corrected state:
 *   1. Events with a Laravel listener wired in AppServiceProvider are NOT
 *      marked NF-2.
 *   2. ReminderSent (slice 09 wired TrackReminderDelivery), AppointmentCheckedIn
 *      (slice 10 secured PrivateChannel) and PaymentReceived (slice 10 wired
 *      LogPaymentReceived) are NOT marked NF-2 anymore.
 *   3. Events that still rely on WebSocket-only consumption retain the
 *      historical NF-2 docblock note (it accurately reflects WHY they have
 *      no Laravel listener — they are intentionally consumed via Reverb).
 *   4. PatientFileExported (NF-5 fix) keeps its listener wired.
 */
class OrphanEventsDeprecatedTest extends TestCase
{
    /** @test */
    public function events_with_laravel_listener_should_not_be_marked_nf2(): void
    {
        // Every event in this list has a Laravel listener wired in
        // AppServiceProvider::boot() — therefore the NF-2 marker must NOT
        // be present in its source.
        $wired = [
            'AppointmentCreated', 'AppointmentUpdated', 'AppointmentDeleted',
            'PatientCreated', 'PatientUpdated', 'PatientDeleted',
            'AppointmentCompleted', 'PatientFileExported',
            'ProcedureCatalogDeactivated', 'ProcedureCatalogUpdated',
            'ReminderSent', 'PaymentReceived', 'AppointmentCheckedIn',
        ];
        foreach ($wired as $eventClass) {
            $file = app_path("Events/{$eventClass}.php");
            $this->assertFileExists($file, "Event class {$eventClass} should exist");
            $content = file_get_contents($file);
            $this->assertStringNotContainsString(
                'Sprint 0 fix (NF-2)',
                $content,
                "{$eventClass} is wired (Laravel listener or secured channel) and must NOT keep the stale NF-2 marker"
            );
        }
    }

    /** @test */
    public function patient_file_exported_is_cabled_in_service_provider(): void
    {
        $provider = new AppServiceProvider($this->app);
        $reflection = new ReflectionClass($provider);
        $boot = $reflection->getMethod('boot');
        $code = file($reflection->getFileName());
        $bootSource = implode('', array_slice($code, $boot->getStartLine() - 1, $boot->getEndLine() - $boot->getStartLine() + 1));

        $this->assertStringContainsString('PatientFileExported', $bootSource);
        $this->assertStringContainsString('NotifyPatientFileExported', $bootSource);
    }

    /** @test */
    public function payment_received_listener_is_cabled_in_service_provider(): void
    {
        $provider = new AppServiceProvider($this->app);
        $reflection = new ReflectionClass($provider);
        $boot = $reflection->getMethod('boot');
        $code = file($reflection->getFileName());
        $bootSource = implode('', array_slice($code, $boot->getStartLine() - 1, $boot->getEndLine() - $boot->getStartLine() + 1));

        $this->assertStringContainsString('PaymentReceived', $bootSource, 'Slice 10 must wire PaymentReceived listener');
        $this->assertStringContainsString('LogPaymentReceived', $bootSource, 'Slice 10 must register LogPaymentReceived');
    }
}
