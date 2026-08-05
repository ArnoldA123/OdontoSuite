<?php

namespace Tests\Unit\Composables;

use PHPUnit\Framework\TestCase;

/**
 * Bugfix-2026-08 slice 11 — deferred state-handling tasks from slice 08.
 *
 * Covers T-08.12 (useNotifications visibilitychange auto-refresh),
 * T-08.14 (useApi error normalization helper).
 *
 * T-08.13 (optimistic rollback) and T-08.15 (Spanish localization) are
 * verified as already-resolved: PaymentModal has no optimistic state to
 * roll back (it waits for the server), and the codebase's user-facing
 * strings are already in Spanish. Documented as no-ops.
 */
class DeferredStateHandlingTest extends TestCase
{
    private const PROJECT_ROOT = 'E:/UNIVERSIDAD PRIVADA DEL NORTE/UPN 10 CICLO/Capstone/Proyecto/OdontoSuiteV2/OdontoSuite';

    /** @test T-08.12 */
    public function useNotifications_exposes_a_visibility_auto_refresh_helper(): void
    {
        $source = file_get_contents(self::PROJECT_ROOT . '/resources/js/composables/useNotifications.js');
        $this->assertNotFalse($source);

        // The composable must export a function that listens to the
        // `visibilitychange` document event and triggers a refresh of
        // notifications when the tab becomes visible.
        $hasVisibilityHandler = (bool) preg_match(
            '/visibilitychange/',
            $source
        );
        $this->assertTrue(
            $hasVisibilityHandler,
            'T-08.12: useNotifications must handle the `visibilitychange` event to auto-refresh when the tab becomes visible.'
        );

        // Must check for `document.visibilityState === 'visible'`
        $checksVisibleState = (bool) preg_match(
            "/visibilityState\\s*===\\s*['\\\"]visible['\\\"]/",
            $source
        );
        $this->assertTrue(
            $checksVisibleState,
            'T-08.12: visibilitychange handler must check document.visibilityState === "visible"'
        );

        // Must register + clean up the listener (addEventListener / removeEventListener)
        $hasCleanup = str_contains($source, 'removeEventListener');
        $this->assertTrue(
            $hasCleanup,
            'T-08.12: useNotifications must clean up the visibilitychange listener (removeEventListener)'
        );
    }

    /** @test T-08.14 */
    public function useApi_exposes_a_normalize_error_helper(): void
    {
        $source = file_get_contents(self::PROJECT_ROOT . '/resources/js/composables/useApi.js');
        $this->assertNotFalse($source);

        // The helper must be exported from useApi() return object.
        $hasNormalizeError = str_contains($source, 'normalizeError');

        $this->assertTrue(
            $hasNormalizeError,
            'T-08.14: useApi must expose a normalizeError(err) helper that returns a Spanish-localized message.'
        );

        // The helper must handle the common shapes (err.response.data.message,
        // err.response.data.meta.message, err.message).
        $functionBody = $this->extractNormalizeErrorBody($source);
        if ($functionBody === null) {
            $this->fail('T-08.14: normalizeError helper is referenced but its implementation cannot be located.');
        }

        $this->assertStringContainsString(
            'response',
            $functionBody,
            'T-08.14: normalizeError must read err.response'
        );

        $this->assertStringContainsString(
            'message',
            $functionBody,
            'T-08.14: normalizeError must read err.message or err.response.data.message'
        );
    }

    private function extractNormalizeErrorBody(string $source): ?string
    {
        // Match `const normalizeError = (...) => { ... }` or `function normalizeError(...) { ... }`.
        if (!preg_match('/(?:const|function)\s+normalizeError\s*=?\s*(?:\\([^)]*\\)|[a-z_]+)\\s*=>?\\s*\\{/i', $source, $m, PREG_OFFSET_CAPTURE)) {
            return null;
        }

        $start = $m[0][1] + strlen($m[0][0]);
        // Walk braces to find the matching close.
        $depth = 0;
        $len = strlen($source);
        for ($i = $start; $i < $len; $i++) {
            $c = $source[$i];
            if ($c === '{') {
                $depth++;
            } elseif ($c === '}') {
                if ($depth === 0) {
                    return substr($source, $start, $i - $start);
                }
                $depth--;
            }
        }

        return null;
    }
}
