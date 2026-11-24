<?php

namespace Tests\Unit\Events;

use App\Events\AppointmentCheckedIn;
use App\Models\Appointment;
use App\Models\Patient;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Support\Facades\Date;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * citas-privacidad (T2) — the arrival reaches the boards that read the agenda.
 *
 * The agenda and Mi día derive their state from the appointment and both
 * listen on the private `appointments` channel, but AppointmentCheckedIn only
 * published on `appointment.{id}`: the arrival moved neither board until a
 * manual reload. The event now publishes on both channels and keeps the
 * operational envelope, so no patient rides along.
 */
class AppointmentCheckedInChannelTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Date::setTestNow('2026-11-24 10:15:00');
    }

    protected function tearDown(): void
    {
        Date::setTestNow();

        parent::tearDown();
    }

    private function appointment(): Appointment
    {
        $appointment = new Appointment;
        $appointment->forceFill([
            'id' => 42,
            'patient_id' => 7,
            'user_id' => 8,
            'scheduled_at' => now(),
            'duration_minutes' => 30,
            'status' => 'arrived',
            'checked_in_at' => now(),
            'updated_at' => now(),
        ]);

        $patient = new Patient;
        $patient->forceFill([
            'id' => 7,
            'first_name' => 'Ana',
            'last_name' => 'Torres',
            'document_number' => '45678912',
            'phone' => '987654321',
        ]);
        $appointment->setRelation('patient', $patient);

        return $appointment;
    }

    /**
     * @return string[] the wire names, `private-` prefix included
     */
    private function channelNames(AppointmentCheckedIn $event): array
    {
        return array_map(static fn ($channel): string => $channel->name, $event->broadcastOn());
    }

    #[Test]
    public function it_publishes_on_the_appointments_channel_and_keeps_the_appointment_channel(): void
    {
        $names = $this->channelNames(new AppointmentCheckedIn($this->appointment()));

        $this->assertContains(
            'private-appointments',
            $names,
            'AppointmentCheckedIn MUST publish on `appointments`: the agenda and Mi día listen there (T2).'
        );
        $this->assertContains(
            'private-appointment.42',
            $names,
            'AppointmentCheckedIn MUST keep the appointment private channel.'
        );
        $this->assertCount(2, $names, 'AppointmentCheckedIn publishes on exactly those two private channels.');
    }

    #[Test]
    public function both_channels_are_private_and_carry_the_prefix_once(): void
    {
        foreach ((new AppointmentCheckedIn($this->appointment()))->broadcastOn() as $channel) {
            $this->assertInstanceOf(PrivateChannel::class, $channel);
            $this->assertStringNotContainsString('private-private-', $channel->name);
        }
    }

    #[Test]
    public function it_keeps_the_listener_name_the_boards_subscribe(): void
    {
        $this->assertSame(
            'appointment.checked_in',
            (new AppointmentCheckedIn($this->appointment()))->broadcastAs()
        );
    }

    #[Test]
    public function the_envelope_carries_no_patient(): void
    {
        $payload = (new AppointmentCheckedIn($this->appointment()))->broadcastWith();

        $this->assertSame(['appointment'], array_keys($payload));
        $this->assertSame(
            ['id', 'user_id', 'status', 'scheduled_at', 'updated_at'],
            array_keys($payload['appointment'])
        );
        $this->assertArrayNotHasKey('patient', $payload['appointment']);
        $this->assertArrayNotHasKey('patient_id', $payload['appointment']);

        foreach (['Ana', 'Torres', '45678912', '987654321'] as $privateValue) {
            $this->assertStringNotContainsString(
                $privateValue,
                json_encode($payload, JSON_THROW_ON_ERROR),
                "AppointmentCheckedIn leaked '{$privateValue}'."
            );
        }
    }
}
