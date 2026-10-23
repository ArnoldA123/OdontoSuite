<?php

namespace Tests\Unit\DesignSystem;

use PHPUnit\Framework\TestCase;

/**
 * Navigation guard for the specialties domain (issue #210).
 *
 * Two surfaces of the same domain live in the sidebar: the clinical entry that
 * captures records per patient (`/specialty-records`) and the admin catalog
 * that owns the taxonomy (`/specialties`). A bare "Especialidades" label in
 * either entry makes an administrator guess which one is which, so the rule is
 * pinned here: one unambiguous label per route, and neither entry reuses the
 * other's vocabulary.
 *
 * Routes and roles are pinned too because the fix is cosmetic by contract: the
 * issue accepts a label rename only when paths and permissions stay untouched.
 */
class SpecialtiesNavigationDisambiguationTest extends TestCase
{
    private const APP_LAYOUT_PATH = '/resources/js/components/layout/AppLayout.vue';

    private const CLINICAL_ROUTE = '/specialty-records';

    private const CATALOG_ROUTE = '/specialties';

    private const CLINICAL_LABEL = 'Registros de Especialidad';

    private const CATALOG_LABEL = 'Catálogo de Especialidades';

    private const AMBIGUOUS_LABEL = 'Especialidades';

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
            'The sidebar MUST keep the clinical records route: the fix renames a label, never a path.'
        );
        $this->assertSame(
            self::CLINICAL_LABEL,
            $items[self::CLINICAL_ROUTE]['label'],
            'The clinical entry MUST be labeled "'.self::CLINICAL_LABEL.'" so it does not read as the catalog.'
        );
    }

    public function test_the_catalog_entry_keeps_its_own_master_label(): void
    {
        $items = self::navigationItems();

        $this->assertArrayHasKey(
            self::CATALOG_ROUTE,
            $items,
            'The sidebar MUST keep the admin catalog route.'
        );
        $this->assertSame(
            self::CATALOG_LABEL,
            $items[self::CATALOG_ROUTE]['label'],
            'The catalog entry MUST be labeled "'.self::CATALOG_LABEL.'".'
        );
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

    public function test_both_entries_keep_their_routes_and_role_permissions(): void
    {
        $items = self::navigationItems();

        $this->assertSame(
            ['administrador', 'odontologo', 'implantologo'],
            $items[self::CLINICAL_ROUTE]['roles'],
            'The clinical records entry MUST list the desks that hold `manage.specialty_record`: '
            .'issue #210 froze the label and the path, and issue #313 closed the laboratory desk '
            .'out of the module because it publishes the surgical narrative.'
        );
        $this->assertSame(
            ['administrador'],
            $items[self::CATALOG_ROUTE]['roles'],
            'The admin catalog entry MUST stay administrator-only.'
        );
    }
}
