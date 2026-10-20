<?php

namespace Tests\Unit\Composables;

use PHPUnit\Framework\TestCase;

/**
 * Bugfix-2026-08 slice 09 — RED test for FF-001 (usePermissions.createMovement).
 *
 * Findings covered:
 *  - FF-001: usePermissions did NOT expose createMovement; the CashRegisterPage
 *    button was rendered for every role (RBAC bypass).
 *  - The fix adds `can.createMovement` to usePermissions.js, restricted to
 *    `administrador` and `finanzas` (matches backend middleware at
 *    routes/api.php:345 / 360).
 *
 * Strict TDD: source-level assertions, no jsdom / vitest (per
 * openspec/config.yaml -> js_unit_runner: none). The test fails before the
 * slice's code edit and passes after.
 */
class PermissionsCreateMovementTest extends TestCase
{
    /** Project root. */
    private static function projectRootPath(): string
    {
        return dirname(__DIR__, 3);
    }

    private function usePermissionsSource(): string
    {
        $source = file_get_contents(self::projectRootPath().'/resources/js/composables/usePermissions.js');
        $this->assertNotFalse($source, 'usePermissions.js must exist');

        return $source;
    }

    /** @test FF-001 */
    public function use_permissions_exposes_create_movement_in_can_object(): void
    {
        $source = $this->usePermissionsSource();

        // The `can.createMovement` permission must be declared inside the
        // `can` object (same shape as createTransaction, openCashRegister, etc.).
        // Match the trailing-comma + identifier-after pattern to avoid false
        // positives on identifiers that happen to share a name in a comment.
        $this->assertMatchesRegularExpression(
            '/createMovement\s*:\s*computed\s*\(\s*\(\s*\)\s*=>\s*\[\s*[\'"]administrador[\'"]\s*,\s*[\'"]finanzas[\'"]\s*\][^)]*\)/',
            $source,
            'usePermissions.js must expose can.createMovement restricted to administrador + finanzas (FF-001 RBAC)'
        );
    }

    /** @test FF-001 */
    public function use_permissions_create_movement_does_not_include_clinical_roles(): void
    {
        $source = $this->usePermissionsSource();

        // The role list for createMovement must NOT include clinical roles —
        // the finding was specifically that ANY role could see the button.
        // Asserting on the literal role strings inside the createMovement
        // declaration only (not the whole file) by looking at the snippet
        // around `createMovement`.
        $this->assertDoesNotMatchRegularExpression(
            '/createMovement\s*:[^,}]*[\'"]odontologo[\'"][^,}]*,/',
            $source,
            'createMovement must not whitelist odontologo (FF-001)'
        );
        $this->assertDoesNotMatchRegularExpression(
            '/createMovement\s*:[^,}]*[\'"]recepcionista[\'"][^,}]*,/',
            $source,
            'createMovement must not whitelist recepcionista (FF-001)'
        );
        $this->assertDoesNotMatchRegularExpression(
            '/createMovement\s*:[^,}]*[\'"]implantologo[\'"][^,}]*,/',
            $source,
            'createMovement must not whitelist implantologo (FF-001)'
        );
    }

    /** @test FF-001 */
    public function cash_register_page_consumes_create_movement_from_use_permissions(): void
    {
        $page = file_get_contents(self::projectRootPath()
            .'/resources/js/modules/cash-register/CashRegisterPage.vue');
        $this->assertNotFalse($page, 'CashRegisterPage.vue must exist');

        // The "Nuevo Movimiento" button must be gated by `v-if="canCreateMovement"`.
        $this->assertMatchesRegularExpression(
            '/v-if\s*=\s*["\']canCreateMovement["\']/',
            $page,
            'CashRegisterPage "Nuevo Movimiento" button must be gated by canCreateMovement (FF-001)'
        );

        // And `canCreateMovement` must read the permission from the `can`
        // object. The previous contract destructured `createMovement` straight
        // off `usePermissions()`, which returns `{ can, ... }`: the value was
        // always `undefined` and the button never rendered for anyone, so this
        // guard was pinning the bug instead of the rule.
        $this->assertMatchesRegularExpression(
            '/const\s*\{[^}]*\bcan\b[^}]*\}\s*=\s*usePermissions\s*\(\s*\)/',
            $page,
            'CashRegisterPage must read the `can` object from usePermissions() (FF-001)'
        );
        $this->assertMatchesRegularExpression(
            '/canCreateMovement\s*=\s*computed\(\s*\(\s*\)\s*=>\s*can\.createMovement\.value\s*\)/',
            $page,
            'canCreateMovement must resolve can.createMovement.value (FF-001)'
        );
    }
}
