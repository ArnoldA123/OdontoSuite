<?php

namespace Tests\Unit\Authorization;

use App\Authorization\Permissions;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * #280 — the SPA maps its `can.*` flags to catalog permissions and never
 * repeats the role matrix. This test crosses both artifacts.
 */
class FrontendPermissionContractTest extends TestCase
{
    private static function source(string $relative): string
    {
        $path = dirname(__DIR__, 3).'/'.$relative;
        $source = file_get_contents($path);

        self::assertNotFalse($source, "{$relative} must exist");

        return $source;
    }

    /**
     * @return array<string, string> key => permission
     */
    private function frontendMap(): array
    {
        preg_match_all(
            "/^\\s*'?([\\w.-]+)'?:\\s*'([a-z_]+(?:\\.[a-z_]+)+)'/m",
            self::source('resources/js/composables/usePermissions.js'),
            $matches,
            PREG_SET_ORDER,
        );

        $map = [];

        foreach ($matches as $match) {
            $map[$match[1]] = $match[2];
        }

        return $map;
    }

    #[Test]
    public function every_frontend_flag_maps_to_a_catalog_permission(): void
    {
        $map = $this->frontendMap();

        $this->assertGreaterThan(50, count($map), 'usePermissions.js should expose the full flag set.');

        foreach ($map as $key => $permission) {
            $this->assertTrue(
                Permissions::exists($permission),
                "usePermissions.js maps [{$key}] to unknown permission [{$permission}].",
            );
        }
    }

    #[Test]
    public function the_authorization_map_does_not_declare_role_lists(): void
    {
        $source = self::source('resources/js/composables/usePermissions.js');
        $map = substr(
            $source,
            (int) strpos($source, 'const PERMISSION_BY_KEY'),
            (int) strpos($source, 'export function usePermissions'),
        );

        $this->assertNotSame('', $map, 'usePermissions.js must declare PERMISSION_BY_KEY.');

        $this->assertDoesNotMatchRegularExpression(
            "/\\[[^\\]]*'(administrador|recepcionista|odontologo|implantologo|tecnico_dental|asistente|finanzas)'/",
            $map,
            'The authorization map must not enumerate roles; derive the flags from the permission payload.',
        );
    }

    #[Test]
    public function frontend_only_permissions_are_consumed_by_the_spa(): void
    {
        $map = $this->frontendMap();

        foreach (Permissions::FRONTEND_ONLY as $permission) {
            $this->assertContains(
                $permission,
                $map,
                "Frontend-only permission [{$permission}] is not mapped by usePermissions.js.",
            );
        }
    }

    #[Test]
    public function the_smoke_fixture_matches_the_catalog(): void
    {
        $path = dirname(__DIR__, 3).'/tests/js/support/role-permissions.json';
        $fixture = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);

        $expected = [];

        foreach (Permissions::ROLES as $role) {
            $expected[$role] = Permissions::forRole($role);
        }

        $this->assertSame(
            $expected,
            $fixture,
            'The smoke fixture drifted from the catalog. Regenerate it with `php scripts/export-permissions-fixture.php`.',
        );
    }

    #[Test]
    public function the_consultation_close_is_derived_from_its_permission(): void
    {
        $map = $this->frontendMap();

        $this->assertSame(Permissions::COMPLETE_APPOINTMENT, $map['completeConsultation'] ?? null);
        $this->assertSame(Permissions::CHECK_IN_APPOINTMENT, $map['checkInAppointment'] ?? null);
    }
}
