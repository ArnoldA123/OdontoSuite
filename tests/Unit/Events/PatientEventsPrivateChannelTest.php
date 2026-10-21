<?php

namespace Tests\Unit\Events;

use App\Events\PatientCreated;
use App\Events\PatientDeleted;
use App\Events\PatientUpdated;
use Illuminate\Broadcasting\PrivateChannel;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use Tests\TestCase;

/**
 * patients-module-audit / round 2 — the three patient events must travel on a
 * private channel.
 *
 * They used to publish `new Channel('patients')`, so anyone holding the Reverb
 * app key could subscribe without authenticating and receive the full patient
 * model, `medical_history` and `allergies` included. The same payload also went
 * to the public `dashboard-updates` channel.
 *
 * Mirrors AppointmentCheckedInPrivateChannelTest (BF-018) at the source level so
 * the guard runs without a database, and pins the two properties that make the
 * leak impossible: the event instantiates PrivateChannel, and it never names the
 * public Channel class.
 */
class PatientEventsPrivateChannelTest extends TestCase
{
    /**
     * @return array<string, array{class-string}>
     */
    public static function patientEvents(): array
    {
        return [
            'PatientCreated' => [PatientCreated::class],
            'PatientUpdated' => [PatientUpdated::class],
            'PatientDeleted' => [PatientDeleted::class],
        ];
    }

    #[Test]
    #[DataProvider('patientEvents')]
    public function patient_event_broadcasts_on_the_private_patients_channel(string $eventClass): void
    {
        $source = file_get_contents((new ReflectionClass($eventClass))->getFileName());

        $this->assertStringContainsString(
            'PrivateChannel',
            $source,
            "{$eventClass} MUST broadcast on a PrivateChannel: its payload carries clinical data."
        );
        $this->assertStringContainsString(
            "new PrivateChannel('patients')",
            $source,
            "{$eventClass} MUST broadcast on the private `patients` channel."
        );
    }

    #[Test]
    #[DataProvider('patientEvents')]
    public function patient_event_never_uses_the_public_channel_class(string $eventClass): void
    {
        $source = file_get_contents((new ReflectionClass($eventClass))->getFileName());

        $this->assertStringNotContainsString(
            'use Illuminate\\Broadcasting\\Channel;',
            $source,
            "{$eventClass} MUST NOT import the public Channel class."
        );
        $this->assertStringNotContainsString(
            'new Channel(',
            $source,
            "{$eventClass} MUST NOT instantiate new Channel()."
        );
    }

    #[Test]
    #[DataProvider('patientEvents')]
    public function patient_event_no_longer_publishes_to_the_public_dashboard_channel(string $eventClass): void
    {
        $source = file_get_contents((new ReflectionClass($eventClass))->getFileName());

        $this->assertStringNotContainsString(
            'dashboard-updates',
            $source,
            "{$eventClass} MUST NOT publish the patient payload to `dashboard-updates`: that channel is public."
        );
    }

    #[Test]
    #[DataProvider('patientEvents')]
    public function patient_event_channel_resolves_to_the_private_wire_name(string $eventClass): void
    {
        $patient = new \App\Models\Patient;
        $patient->id = 7;

        $event = match ($eventClass) {
            PatientCreated::class => new PatientCreated($patient),
            PatientUpdated::class => new PatientUpdated($patient, []),
            PatientDeleted::class => new PatientDeleted(7, []),
        };

        $channels = $event->broadcastOn();

        $this->assertCount(1, $channels, "{$eventClass} MUST broadcast on exactly one channel.");
        $this->assertInstanceOf(PrivateChannel::class, $channels[0]);
        $this->assertSame('private-patients', $channels[0]->name);
    }
}
