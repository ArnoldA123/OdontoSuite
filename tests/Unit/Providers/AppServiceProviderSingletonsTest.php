<?php

namespace Tests\Unit\Providers;

use PHPUnit\Framework\TestCase;

/**
 * Bugfix-2026-08 slice 11 — BF-021 AppServiceProvider singleton cleanup
 * RED test.
 *
 * AppServiceProvider::register() currently binds 3 services as singletons
 * (ClinicalAttachmentService, AiImageAnalysisService, BillingService). These
 * bindings are redundant because Laravel's container auto-resolves them as
 * singletons when the constructor has no per-call state. The fix removes
 * the redundant bindings to reduce surface area.
 *
 * The test asserts that register() either contains no `$this->app->singleton`
 * calls OR contains only the bindings that are truly required (none in this
 * codebase).
 */
class AppServiceProviderSingletonsTest extends TestCase
{
    private const PROVIDER_FILE = 'E:/UNIVERSIDAD PRIVADA DEL NORTE/UPN 10 CICLO/Capstone/Proyecto/OdontoSuiteV2/OdontoSuite/app/Providers/AppServiceProvider.php';

    public function test_app_service_provider_register_does_not_contain_redundant_singletons(): void
    {
        $source = file_get_contents(self::PROVIDER_FILE);
        $this->assertNotNull($source);

        // Strip block + line comments so the docblock text mentioning the
        // old singleton calls does not trigger a false positive.
        $codeOnly = preg_replace('!/\*.*?\*/!s', '', $source);
        $codeOnly = preg_replace('![ \t]*//.*!', '', (string) $codeOnly);

        $singletonCount = preg_match_all(
            '/\$this->app->singleton\(/',
            (string) $codeOnly
        );

        $this->assertSame(
            0,
            $singletonCount,
            'BF-021: AppServiceProvider::register() must not declare redundant singleton bindings; Laravel auto-resolves services.'
        );
    }

    public function test_app_service_provider_event_listeners_remain_in_boot(): void
    {
        $source = file_get_contents(self::PROVIDER_FILE);

        // The fix must not affect boot(). Event::listen calls must remain
        // untouched (they are wiring, not DI bindings).
        $listenCount = preg_match_all('/Event::listen\\(/', $source);
        $this->assertGreaterThanOrEqual(
            11,
            $listenCount,
            'AppServiceProvider::boot() must retain at least 11 Event::listen calls (BF-021 cleanup does not touch boot()).'
        );
    }
}
