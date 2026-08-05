<?php

namespace Tests\Unit\Events;

use Tests\TestCase;

/**
 * Slice 10 (T-10.4): the private channels that AppointmentCheckedIn and
 * PaymentReceived broadcast on MUST have an authorization entry in
 * routes/channels.php. Without it the broadcast will throw at runtime.
 */
class EventChannelAuthorizationTest extends TestCase
{
    /** @test */
    public function routes_channels_php_authorizes_private_appointment_channel(): void
    {
        $source = file_get_contents(base_path('routes/channels.php'));

        // AppointmentCheckedIn broadcasts on 'private-appointment.{id}'.
        $this->assertStringContainsString(
            'private-appointment.',
            $source,
            'routes/channels.php must authorize private-appointment.{id}'
        );
    }

    /** @test */
    public function routes_channels_php_authorizes_private_cash_register_branch_channel(): void
    {
        $source = file_get_contents(base_path('routes/channels.php'));

        // PaymentReceived broadcasts on 'private-cash-register.{branchId}'.
        $this->assertStringContainsString(
            'private-cash-register.',
            $source,
            'routes/channels.php must authorize private-cash-register.{branchId}'
        );
    }
}
