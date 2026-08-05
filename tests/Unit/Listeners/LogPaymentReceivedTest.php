<?php

namespace Tests\Unit\Listeners;

use App\Events\PaymentReceived;
use App\Listeners\LogPaymentReceived;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * Slice 10 (T-10.3): PaymentReceived must have a useful listener wired
 * (LogPaymentReceived) so the event is not orphan-broadcast. The listener
 * records an audit log entry for every external payment received.
 */
class LogPaymentReceivedTest extends TestCase
{
    /** @test */
    public function listener_class_exists(): void
    {
        $this->assertTrue(
            class_exists(LogPaymentReceived::class),
            'LogPaymentReceived listener must exist (T-10.3 wires PaymentReceived)'
        );
    }

    /** @test */
    public function listener_is_registered_for_payment_received_in_app_service_provider(): void
    {
        $providerPath = app_path('Providers/AppServiceProvider.php');
        $source = file_get_contents($providerPath);

        $this->assertStringContainsString(
            'PaymentReceived::class',
            $source,
            'AppServiceProvider must register PaymentReceived listener'
        );
        $this->assertStringContainsString(
            'LogPaymentReceived::class',
            $source,
            'AppServiceProvider must register LogPaymentReceived listener'
        );
    }

    /** @test */
    public function listener_handle_accepts_payment_received_event(): void
    {
        $reflection = new \ReflectionClass(LogPaymentReceived::class);
        $method = $reflection->getMethod('handle');

        $this->assertTrue($method->isPublic(), 'handle() must be public');

        $params = $method->getParameters();
        $this->assertGreaterThanOrEqual(1, count($params));

        $type = $params[0]->getType();
        $this->assertNotNull($type, 'handle() must declare a parameter type');
        $typeName = $type instanceof \ReflectionNamedType ? $type->getName() : (string) $type;
        $this->assertSame(
            PaymentReceived::class,
            $typeName,
            'handle() must type-hint PaymentReceived'
        );
    }

    /** @test */
    public function listener_wraps_body_in_try_catch_per_agents_md_section_7(): void
    {
        $reflection = new \ReflectionClass(LogPaymentReceived::class);
        $source = file_get_contents($reflection->getFileName());

        // AGENTS.md §7: listener MUST catch its own exceptions.
        $this->assertStringContainsString(
            'try {',
            $source,
            'LogPaymentReceived::handle must be wrapped in try { ... }'
        );
        $this->assertStringContainsString(
            'catch',
            $source,
            'LogPaymentReceived::handle must catch exceptions'
        );
    }
}
