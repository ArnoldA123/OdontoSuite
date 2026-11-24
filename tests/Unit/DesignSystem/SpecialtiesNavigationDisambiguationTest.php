<?php

namespace Tests\Unit\DesignSystem;

use PHPUnit\Framework\TestCase;

/**
 * Navigation guard for the specialties domain (issue #210, catalog hub).
 *
 * Two surfaces of the same domain used to live in the sidebar: the clinical
 * entry that captures records per patient (`/specialty-records`) and the admin
 * catalog that owns the taxonomy (`/specialties`). The catalog hub now groups
 * the three administrative masters behind one «Catálogos» entry, so the rule
 * changes shape without losing its point:
 *
 *   - the clinical entry keeps its own unambiguous label and roles;
 *   - the catalog masters no longer have top-level sibling entries;
 *   - the hub is the single door and stays administrator-only;
 *   - no entry reuses the bare "Especialidades" label.
 */
class SpecialtiesNavigationDisambiguationTest extends TestCase
{
    private const APP_LAYOUT_PATH = '/resources/js/components/layout/AppLayout.vue';

    private const CLINICAL_ROUTE = '/specialty-records';

    private const HUB_ROUTE = '/catalogs';

    private const CLINICAL_LABEL = 'Registros de especialidad';

    private const HUB_LABEL = 'Catálogos';

    private const AMBIGUOUS_LABEL = 'Especialidades';

    /** @var array<int, string> */
    private const CATALOG_ROUTES = ['/appointment-types', '/specialties', '/procedure-catalog'];

    private static function appLayoutSource(): string
    {
        $path = dirname(__DIR__, 3).self::APP_LAYOUT_PATH;
        $source = file_get_contents($path);
        self::assertNotFalse($source, self::APP_LAYOUT_PATH.' must be readable.');

        return $source;
    }

    /**
     * Parse the `allItems` literal inside the `navigation` computed into
     * `route => ['label' => string, 'roles' => string[]]`, so the assertions
     * describe navigation entries instead of a literal class or string order.
     *
     * @return array<string, array{label: string, roles: array<int, string>}>
     */
    private static function navigationItems(): array
    {
        $source = self::appLayoutSource();

        $start = strpos($source, 'const allItems = [');
        self::assertNotFalse($start, 'AppLayout MUST declare the navigation allItems literal.');

        $end = strpos($source, '// Filtrar navegación según el rol', $start);
        self::assertNotFalse($end, 'AppLayout MUST filter the navigation items right after the literal.');

        $body = substr($source, $start, $end - $start);

        $items = [];
        foreach (preg_split('/\n\s{4}\{/', $body) as $index => $chunk) {
            if ($index === 0) {
                continue; // literal opening
            }

            if (! preg_match("/name:\\s*'([^']+)'/", $chunk, $nameMatch)) {
                continue;
            }

            if (! preg_match("/to:\\s*'([^']+)'/", $chunk, $routeMatch)) {
                continue;
            }

            $roles = [];
            if (preg_match('/roles:\s*\[([^\]]*)\]/s', $chunk, $rolesMatch)) {
                preg_match_all("/'([^']+)'/", $rolesMatch[1], $roleMatches);
                $roles = $roleMatches[1];
            }

            $items[$routeMatch[1]] = [
                'label' => $nameMatch[1],
                'roles' => $roles,
            ];
        }

        return $items;
    }

    public function test_the_clinical_entry_is_named_after_the_records_it_captures(): void
    {
        $items = self::navigationItems();

        $this->assertArrayHasKey(
            self::CLINICAL_ROUTE,
            $items,
            'The sidebar MUST keep the clinical records route: the hub groups catalogs, not clinical records.'
        );
        $this->assertSame(
            self::CLINICAL_LABEL,
            $items[self::CLINICAL_ROUTE]['label'],
            'The clinical entry MUST be labeled "'.self::CLINICAL_LABEL.'" so it does not read as the catalog.'
        );
    }

    public function test_the_catalog_masters_have_no_top_level_navigation_entries(): void
    {
        $items = self::navigationItems();

        foreach (self::CATALOG_ROUTES as $route) {
            $this->assertArrayNotHasKey(
                $route,
                $items,
                sprintf(
                    'The catalog master `%s` MUST be reached through the hub; the sidebar keeps one entry.',
                    $route
                )
            );
        }
    }

    public function test_the_hub_is_the_single_catalog_door(): void
    {
        $items = self::navigationItems();

        $this->assertArrayHasKey(self::HUB_ROUTE, $items, 'The sidebar MUST offer the catalog hub.');
        $this->assertSame(self::HUB_LABEL, $items[self::HUB_ROUTE]['label']);
        $this->assertSame(
            ['administrador'],
            $items[self::HUB_ROUTE]['roles'],
            'The catalog hub MUST stay administrator-only: its three destinations are administration desks.'
        );

        foreach ($items as $route => $item) {
            if ($route === self::HUB_ROUTE) {
                continue;
            }

            $this->assertNotSame(
                'Catálogo de especialidades',
                $item['label'],
                'The retired specialties entry MUST NOT survive as a second catalog door.'
            );
            $this->assertNotSame(
                'Tipos de cita',
                $item['label'],
                'The retired appointment-types entry MUST NOT survive as a second catalog door.'
            );
        }
    }

    public function test_no_navigation_entry_uses_the_ambiguous_bare_label(): void
    {
        $items = self::navigationItems();

        $ambiguous = array_keys(array_filter(
            $items,
            static fn (array $item): bool => $item['label'] === self::AMBIGUOUS_LABEL
        ));

        $this->assertSame(
            [],
            $ambiguous,
            'No navigation entry may be labeled "'.self::AMBIGUOUS_LABEL.'": the domain has two surfaces '
            .'and the bare label does not say which one it opens.'
        );
    }

    public function test_the_clinical_entry_keeps_its_role_contract(): void
    {
        $items = self::navigationItems();

        $this->assertSame(
            ['administrador', 'odontologo', 'implantologo'],
            $items[self::CLINICAL_ROUTE]['roles'],
            'The clinical records entry MUST list the desks that hold `manage.specialty_record`: '
            .'issue #210 froze the label and the path, and issue #313 closed the laboratory desk '
            .'out of the module because it publishes the surgical narrative.'
        );
    }
}
