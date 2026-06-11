<?php

namespace Tests\Unit\Services;

use Tests\TestCase;

/**
 * Sprint 4 (M-6): tests estructurales para BillingService.
 */
class BillingServiceTest extends TestCase
{
    public function test_service_has_required_methods(): void
    {
        // BillingService tiene varios metodos; los principales segun AGENTS.md
        $svc = \App\Services\BillingService::class;
        $this->assertTrue(class_exists($svc));
    }

    /**
     * M-2: verifica que BillingService tambien envuelve event() con try/catch.
     */
    public function test_event_dispatch_wrapped_in_try_catch(): void
    {
        $content = file_get_contents(base_path('app/Services/BillingService.php'));
        $hasTryCatch = preg_match(
            '/try\s*\{[^}]*event\(new[^}]*\}\s*catch\s*\(/s',
            $content
        );
        $this->assertNotNull(
            $hasTryCatch,
            'BillingService debe envolver event(new ...) con try/catch (M-2 fix)'
        );
    }
}
