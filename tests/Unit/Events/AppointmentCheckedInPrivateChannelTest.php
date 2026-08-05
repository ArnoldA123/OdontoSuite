<?php

namespace Tests\Unit\Events;

use App\Events\AppointmentCheckedIn;
use Illuminate\Broadcasting\PrivateChannel;
use ReflectionClass;
use Tests\TestCase;

/**
 * Slice 10 (T-10.4): AppointmentCheckedIn must broadcast on a private channel
 * to avoid leaking patient appointment data on a public channel.
 *
 * BF-018: AppointmentCheckedIn was broadcasting on public 'appointments' and
 * 'dashboard-updates' channels with patient PII but no JS consumer was registered
 * for '.appointment.checked_in', so the broadcast leaked data to anyone
 * authenticated to listen to those channels.
 */
class AppointmentCheckedInPrivateChannelTest extends TestCase
{
    /** @test */
    public function appointment_checked_in_broadcasts_on_private_channel(): void
    {
        $reflection = new ReflectionClass(AppointmentCheckedIn::class);
        $source = file_get_contents($reflection->getFileName());

        // Must use PrivateChannel, not the public Channel.
        $this->assertStringContainsString(
            'PrivateChannel',
            $source,
            'AppointmentCheckedIn must broadcast on a PrivateChannel (BF-018)'
        );
    }

    /** @test */
    public function appointment_checked_in_does_not_use_public_channel_class(): void
    {
        $reflection = new ReflectionClass(AppointmentCheckedIn::class);
        $source = file_get_contents($reflection->getFileName());

        // It must not import or instantiate the public Channel class.
        $this->assertStringNotContainsString(
            'use Illuminate\\Broadcasting\\Channel;',
            $source,
            'AppointmentCheckedIn must NOT use the public Channel import'
        );
        $this->assertStringNotContainsString(
            'new Channel(',
            $source,
            'AppointmentCheckedIn must NOT instantiate new Channel()'
        );
    }

    /** @test */
    public function appointment_checked_in_no_longer_marked_nf2_orphan(): void
    {
        // The NF-2 marker was stale: the event is now wired (private channel
        // + channel authorization in routes/channels.php). After T-10.4 the
        // marker must be removed because the event is properly secured.
        $reflection = new ReflectionClass(AppointmentCheckedIn::class);
        $source = file_get_contents($reflection->getFileName());

        $this->assertStringNotContainsString(
            'Sprint 0 fix (NF-2)',
            $source,
            'AppointmentCheckedIn no longer needs the NF-2 marker (wired + secured)'
        );
    }
}
