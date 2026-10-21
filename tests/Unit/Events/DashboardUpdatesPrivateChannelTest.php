<?php

namespace Tests\Unit\Events;

use App\Events\AppointmentCompleted;
use App\Events\AppointmentCreated;
use App\Events\AppointmentDeleted;
use App\Events\AppointmentUpdated;
use App\Events\CashSessionClosed;
use App\Events\CashSessionOpened;
use App\Events\MedicalRecordCreated;
use App\Events\MedicalRecordUpdated;
use App\Events\PatientFileExported;
use App\Events\PaymentRegistered;
use App\Events\ProcedureCatalogDeactivated;
use App\Events\SpecialtyRecordCreated;
use App\Events\SpecialtyRecordUpdated;
use App\Events\TransactionCreated;
use App\Events\TransactionUpdated;
use App\Events\TreatmentPlanCreated;
use App\Events\TreatmentPlanDeleted;
use App\Events\TreatmentPlanUpdated;
use App\Models\Appointment;
use Illuminate\Broadcasting\PrivateChannel;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use Tests\TestCase;

/**
 * Issue #183 — `dashboard-updates` must not be a public channel.
 *
 * It is the widest channel in the project: 18 events publish appointments,
 * patients, transactions, cash sessions, quotations and record changes, and a
 * public channel verifies nothing, so anyone holding the Reverb app key could
 * subscribe and receive live domain payloads. The guard is source-level (no
 * database, no broadcaster) and pins the two properties that make the leak
 * impossible: every publisher instantiates PrivateChannel, and no publisher
 * names the public Channel class for this channel.
 */
class DashboardUpdatesPrivateChannelTest extends TestCase
{
    /**
     * Every event that publishes to `dashboard-updates`.
     *
     * @return array<string, array{class-string}>
     */
    public static function dashboardEvents(): array
    {
        $events = [
            AppointmentCompleted::class,
            AppointmentCreated::class,
            AppointmentDeleted::class,
            AppointmentUpdated::class,
            CashSessionClosed::class,
            CashSessionOpened::class,
            MedicalRecordCreated::class,
            MedicalRecordUpdated::class,
            PatientFileExported::class,
            PaymentRegistered::class,
            ProcedureCatalogDeactivated::class,
            SpecialtyRecordCreated::class,
            SpecialtyRecordUpdated::class,
            TransactionCreated::class,
            TransactionUpdated::class,
            TreatmentPlanCreated::class,
            TreatmentPlanDeleted::class,
            TreatmentPlanUpdated::class,
        ];

        $data = [];

        foreach ($events as $event) {
            $data[substr($event, strrpos($event, '\\') + 1)] = [$event];
        }

        return $data;
    }

    #[Test]
    #[DataProvider('dashboardEvents')]
    public function dashboard_event_publishes_on_the_private_dashboard_channel(string $eventClass): void
    {
        $source = file_get_contents((new ReflectionClass($eventClass))->getFileName());

        $this->assertStringContainsString(
            "new PrivateChannel('dashboard-updates')",
            $source,
            "{$eventClass} MUST publish dashboard-updates as a PrivateChannel (issue #183)."
        );
        $this->assertStringNotContainsString(
            "new Channel('dashboard-updates')",
            $source,
            "{$eventClass} MUST NOT keep the public Channel for dashboard-updates (issue #183)."
        );
    }

    #[Test]
    public function the_private_dashboard_channel_carries_the_private_prefix_on_the_wire(): void
    {
        $appointment = new Appointment;
        $appointment->id = 1;

        $channels = (new AppointmentCreated($appointment))->broadcastOn();

        $dashboard = collect($channels)->first(
            fn ($channel) => str_ends_with($channel->name, 'dashboard-updates')
        );

        $this->assertNotNull($dashboard, 'AppointmentCreated MUST broadcast on dashboard-updates.');
        $this->assertInstanceOf(PrivateChannel::class, $dashboard);
        $this->assertSame('private-dashboard-updates', $dashboard->name);
    }

    #[Test]
    public function the_event_set_still_covers_the_project_domain(): void
    {
        $this->assertCount(
            18,
            self::dashboardEvents(),
            'The dashboard fan-out MUST keep all 18 publishers; add new ones to this provider so they inherit the private guard.'
        );
    }
}
