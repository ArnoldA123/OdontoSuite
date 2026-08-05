<?php

namespace Tests\Unit\Events;

use App\Events\PaymentReceived;
use ReflectionClass;
use Tests\TestCase;

/**
 * Slice 10 (T-10.3 + T-10.4): PaymentReceived broadcasts patient transaction
 * data (patient_name, amount, status) on a public Channel. It MUST be a
 * PrivateChannel and MUST have a listener (LogPaymentReceived) wired in
 * AppServiceProvider.
 */
class PaymentReceivedChannelTest extends TestCase
{
    /** @test */
    public function payment_received_broadcasts_on_private_channel(): void
    {
        $reflection = new ReflectionClass(PaymentReceived::class);
        $source = file_get_contents($reflection->getFileName());

        $this->assertStringContainsString(
            'PrivateChannel',
            $source,
            'PaymentReceived must broadcast on a PrivateChannel (PII protection)'
        );
    }

    /** @test */
    public function payment_received_no_longer_marked_nf2_orphan(): void
    {
        // After T-10.3 wires LogPaymentReceived, the NF-2 marker is stale.
        $reflection = new ReflectionClass(PaymentReceived::class);
        $source = file_get_contents($reflection->getFileName());

        $this->assertStringNotContainsString(
            'Sprint 0 fix (NF-2)',
            $source,
            'PaymentReceived no longer needs the NF-2 marker (listener wired)'
        );
    }
}
