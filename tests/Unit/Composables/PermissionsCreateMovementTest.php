<?php

namespace Tests\Unit\Composables;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Bugfix-2026-08 slice 09 — FF-001 (usePermissions.createMovement).
 *
 * #280 moved the rule to App\Authorization\Permissions: `createMovement` is
 * now a membership check on the `manage.cash_movement` permission instead of a
 * role list in the SPA. The CashRegisterPage contract is unchanged.
 */
class PermissionsCreateMovementTest extends TestCase
{
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

    /** FF-001 */
    #[Test]
    public function use_permissions_maps_create_movement_to_the_catalog_permission(): void
    {
        $this->assertMatchesRegularExpression(
            "/createMovement\\s*:\\s*'manage\\.cash_movement'/",
            $this->usePermissionsSource(),
            'usePermissions.js must map can.createMovement to manage.cash_movement (FF-001 / #280)'
        );
    }

    /** FF-001 */
    #[Test]
    public function use_permissions_does_not_whitelist_roles_for_create_movement(): void
    {
        $this->assertDoesNotMatchRegularExpression(
            "/createMovement\\s*:[^,}]*'(administrador|finanzas|odontologo|recepcionista)'/",
            $this->usePermissionsSource(),
            'createMovement must not carry its own role list (FF-001 / #280)'
        );
    }

    /** FF-001 */
    #[Test]
    public function cash_register_page_consumes_create_movement_from_use_permissions(): void
    {
        $page = file_get_contents(self::projectRootPath()
            .'/resources/js/modules/cash-register/CashRegisterPage.vue');
        $this->assertNotFalse($page, 'CashRegisterPage.vue must exist');

        $this->assertMatchesRegularExpression(
            '/v-if\s*=\s*["\']canCreateMovement["\']/',
            $page,
            'CashRegisterPage "Nuevo Movimiento" button must be gated by canCreateMovement (FF-001)'
        );

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
