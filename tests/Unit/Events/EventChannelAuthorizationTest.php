<?php

namespace Tests\Unit\Events;

use App\Events\AppointmentCheckedIn;
use App\Events\PaymentReceived;
use ReflectionClass;
use Tests\TestCase;

/**
 * Slice 10 (T-10.4): the private channels that AppointmentCheckedIn and
 * PaymentReceived broadcast on MUST have an authorization entry in
 * routes/channels.php. Without it the broadcast will throw at runtime.
 *
 * The guard asserts the REGISTERED base names, not the wire names. Laravel
 * adds the `private-` prefix itself, so routes/channels.php must register
 * `appointment.{appointmentId}` / `cash-register.{branchId}` and the events
 * must emit PrivateChannel with those same base names. The previous version
 * asserted the wire literal (`private-appointment.`), which only existed in a
 * mirror comment: deleting or renaming the real registration still left the
 * test green.
 */
class EventChannelAuthorizationTest extends TestCase
{
    /**
     * Channel names actually passed to Broadcast::channel(...) in
     * routes/channels.php. Comment-only mentions are ignored on purpose.
     *
     * @return string[]
     */
    private static function registeredChannelNames(string $source): array
    {
        preg_match_all('/Broadcast::channel\(\s*[\'"]([^\'"]+)[\'"]/', $source, $matches);

        return $matches[1];
    }

    /**
     * Literal channel names a class emits via `new PrivateChannel(...)`.
     *
     * @return string[]
     */
    private static function emittedPrivateChannelNames(string $eventClass): array
    {
        $reflection = new ReflectionClass($eventClass);
        $source = (string) file_get_contents($reflection->getFileName());

        preg_match_all('/new\s+PrivateChannel\(\s*([\'"])(.*?)\1/s', $source, $matches);

        return $matches[2];
    }

    /** @param string[] $names */
    private function anyStartsWith(array $names, string $prefix): bool
    {
        foreach ($names as $name) {
            if (str_starts_with($name, $prefix)) {
                return true;
            }
        }

        return false;
    }

    /** @test */
    public function routes_channels_php_registers_the_appointment_base_channel(): void
    {
        $source = (string) file_get_contents(base_path('routes/channels.php'));
        $names = self::registeredChannelNames($source);

        // Laravel derives the wire name (private-appointment.{id}) from the
        // base name it registers here.
        $this->assertContains(
            'appointment.{appointmentId}',
            $names,
            'routes/channels.php must register the appointment base channel; Laravel derives private-appointment.{id} from it.'
        );

        foreach ($names as $name) {
            $this->assertStringStartsNotWith(
                'private-',
                $name,
                "routes/channels.php must register base channel names; '{$name}' duplicates the private- prefix Laravel already adds."
            );
        }
    }

    /** @test */
    public function routes_channels_php_registers_the_cash_register_base_channel(): void
    {
        $source = (string) file_get_contents(base_path('routes/channels.php'));
        $names = self::registeredChannelNames($source);

        // Laravel derives the wire name (private-cash-register.{branchId})
        // from the base name it registers here.
        $this->assertContains(
            'cash-register.{branchId}',
            $names,
            'routes/channels.php must register the cash-register base channel; Laravel derives private-cash-register.{branchId} from it.'
        );

        foreach ($names as $name) {
            $this->assertStringStartsNotWith(
                'private-',
                $name,
                "routes/channels.php must register base channel names; '{$name}' duplicates the private- prefix Laravel already adds."
            );
        }
    }

    /** @test */
    public function appointment_checked_in_emits_private_channel_with_base_name(): void
    {
        $names = self::emittedPrivateChannelNames(AppointmentCheckedIn::class);

        $this->assertNotEmpty(
            $names,
            'AppointmentCheckedIn must broadcast via new PrivateChannel(...)'
        );

        $this->assertTrue(
            $this->anyStartsWith($names, 'appointment.'),
            'AppointmentCheckedIn must emit PrivateChannel("appointment.{id}") to match the registered base channel'
        );

        foreach ($names as $name) {
            $this->assertStringStartsNotWith(
                'private-',
                $name,
                "AppointmentCheckedIn must not embed the private- prefix; Laravel adds it. Emitted: '{$name}'."
            );
        }
    }

    /** @test */
    public function payment_received_emits_private_channel_with_base_name(): void
    {
        $names = self::emittedPrivateChannelNames(PaymentReceived::class);

        $this->assertNotEmpty(
            $names,
            'PaymentReceived must broadcast via new PrivateChannel(...)'
        );

        $this->assertTrue(
            $this->anyStartsWith($names, 'cash-register.'),
            'PaymentReceived must emit PrivateChannel("cash-register.{branchId}") to match the registered base channel'
        );

        foreach ($names as $name) {
            $this->assertStringStartsNotWith(
                'private-',
                $name,
                "PaymentReceived must not embed the private- prefix; Laravel adds it. Emitted: '{$name}'."
            );
        }
    }
}
