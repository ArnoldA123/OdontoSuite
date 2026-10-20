<?php

namespace Tests\Unit\Events;

use App\Events\QuotationApproved;
use App\Events\QuotationCreated;
use App\Events\QuotationUpdated;
use App\Models\Quotation;
use Illuminate\Broadcasting\PrivateChannel;
use ReflectionClass;
use Tests\TestCase;

/**
 * Issue #200 — the three quotation events must travel only on the private
 * `quotations` channel.
 *
 * They used to publish the full quotation model (patient data, amounts) on the
 * public `quotations` channel and on the public `dashboard-updates` channel.
 * No consumer listens to quotation events on `dashboard-updates`, so dropping
 * that entry removes the leak without breaking a subscriber.
 */
class QuotationBroadcastChannelsTest extends TestCase
{
    /**
     * @return array<string, array{class-string}>
     */
    public static function quotationEvents(): array
    {
        return [
            'QuotationCreated' => [QuotationCreated::class],
            'QuotationUpdated' => [QuotationUpdated::class],
            'QuotationApproved' => [QuotationApproved::class],
        ];
    }

    /**
     * @test
     *
     * @dataProvider quotationEvents
     */
    public function quotation_event_broadcasts_on_the_private_quotations_channel(string $eventClass): void
    {
        $channels = (new $eventClass($this->quotation()))->broadcastOn();

        $this->assertCount(1, $channels, "{$eventClass} MUST broadcast on exactly one channel.");
        $this->assertInstanceOf(PrivateChannel::class, $channels[0]);
        $this->assertSame(
            'private-quotations',
            $channels[0]->name,
            "{$eventClass} MUST broadcast on the private `quotations` channel."
        );
    }

    /**
     * @test
     *
     * @dataProvider quotationEvents
     */
    public function quotation_event_no_longer_publishes_to_the_public_dashboard_channel(string $eventClass): void
    {
        $source = file_get_contents((new ReflectionClass($eventClass))->getFileName());

        $this->assertStringNotContainsString(
            'dashboard-updates',
            $source,
            "{$eventClass} MUST NOT publish the quotation payload to `dashboard-updates`: that channel is public."
        );
    }

    /**
     * @test
     *
     * @dataProvider quotationEvents
     */
    public function quotation_event_never_uses_the_public_channel_class(string $eventClass): void
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

    private function quotation(): Quotation
    {
        $quotation = new Quotation;
        $quotation->id = 1;

        return $quotation;
    }
}
