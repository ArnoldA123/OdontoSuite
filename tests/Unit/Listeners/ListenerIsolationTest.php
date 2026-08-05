<?php

namespace Tests\Unit\Listeners;

use ReflectionClass;
use Tests\TestCase;

/**
 * Slice 10 (T-10.7): per AGENTS.md §7 every active listener MUST wrap its
 * handle() body in try/catch so a failing listener NEVER propagates a 500 to
 * the user. Slice 10 enforces this on the listeners that were missing it.
 */
class ListenerIsolationTest extends TestCase
{
    /**
     * @dataProvider listenerClassProvider
     * @test
     */
    public function listener_handle_is_wrapped_in_try_catch(string $listenerClass): void
    {
        if (!class_exists($listenerClass)) {
            $this->markTestSkipped("Listener class {$listenerClass} not present");
        }

        $reflection = new ReflectionClass($listenerClass);
        $source = file_get_contents($reflection->getFileName());

        // AGENTS.md §7: try { ... } catch must wrap the handle body so a
        // listener crash is swallowed + logged, never propagated as 500.
        $this->assertStringContainsString(
            'try {',
            $source,
            "{$listenerClass} must wrap handle() body in try { ... }"
        );
        $this->assertStringContainsString(
            'catch',
            $source,
            "{$listenerClass} must catch exceptions in handle()"
        );
    }

    public static function listenerClassProvider(): array
    {
        return [
            'LogAppointmentActivity'        => [\App\Listeners\LogAppointmentActivity::class],
            'LogPatientActivity'            => [\App\Listeners\LogPatientActivity::class],
            'CreateTransactionOnAppointmentCompleted' => [\App\Listeners\CreateTransactionOnAppointmentCompleted::class],
            'NotifyPatientFileExported'     => [\App\Listeners\NotifyPatientFileExported::class],
            'NotifyProcedureDeactivation'   => [\App\Listeners\NotifyProcedureDeactivation::class],
            'TrackProcedureVersion'         => [\App\Listeners\TrackProcedureVersion::class],
            'TrackReminderDelivery'         => [\App\Listeners\TrackReminderDelivery::class],
            'ClearDashboardCache'           => [\App\Listeners\ClearDashboardCache::class],
            'LogPaymentReceived'            => [\App\Listeners\LogPaymentReceived::class],
            'LogAppointmentCheckedIn'       => [\App\Listeners\LogAppointmentCheckedIn::class],
        ];
    }
}
