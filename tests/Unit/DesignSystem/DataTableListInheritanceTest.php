<?php

namespace Tests\Unit\DesignSystem;

use PHPUnit\Framework\TestCase;

/**
 * #453 (U11, axis `list`) — the four lists that still hand-rolled their own
 * table inherit `UiDataTable` (`components/ui/DataTable.vue`).
 *
 * The axis asks one question: does the page's list ride the shared table
 * primitive, or does it draw its own `<table>`? A hand-rolled table loses the
 * internal scroll viewport, the "Desliza para ver más columnas" affordance and
 * the shared row/cell rhythm, so on a narrow screen the columns fall out of the
 * page instead of scrolling inside it.
 *
 * These are source-inspection pins, paired with the DOM-level smoke contracts
 * (`tests/js/smoke/patients-list-redesign.test.js`,
 * `professionals.test.js`, `appointment-types.test.js`,
 * `procedure-catalog.test.js`), which mount the pages and assert the rendered
 * table, the row hooks, the row actions and the scroll affordance:
 *
 *   - `test_pages_render_the_shared_table_primitive`
 *   - `test_pages_render_no_hand_rolled_table`
 *   - `test_pagination_follows_the_endpoint`
 */
class DataTableListInheritanceTest extends TestCase
{
    /**
     * The four lists of the finding, with the pagination decision each one
     * carries. `/patients` and `/procedure-catalog` paginate on the server, so
     * the primitive must not paginate a second time; `/professionals` and
     * `/appointment-types` receive the whole collection from their endpoint, so
     * the primitive renders every row.
     *
     * @var array<string, array{path: string, serverPagination: bool}>
     */
    private const LISTS = [
        'patients' => [
            'path' => '/resources/js/modules/patients/PatientsPage.vue',
            'serverPagination' => true,
        ],
        'professionals' => [
            'path' => '/resources/js/modules/professionals/ProfessionalsPage.vue',
            'serverPagination' => false,
        ],
        'appointment-types' => [
            'path' => '/resources/js/modules/appointment-types/AppointmentTypesPage.vue',
            'serverPagination' => false,
        ],
        'procedure-catalog' => [
            'path' => '/resources/js/modules/procedure-catalog/ProcedureCatalogPage.vue',
            'serverPagination' => true,
        ],
    ];

    private static function projectRootPath(): string
    {
        return dirname(__DIR__, 3);
    }

    private static function readSource(string $relativePath): string
    {
        $path = self::projectRootPath().$relativePath;
        self::assertFileExists($path, "Source file must exist: {$path}");
        $src = file_get_contents($path);
        self::assertNotFalse($src, "Source file must be readable: {$path}");

        return (string) $src;
    }

    /**
     * Every list imports the primitive from `components/ui/DataTable.vue` and
     * renders it; a named import is what makes the axis' import graph resolve.
     */
    public function test_pages_render_the_shared_table_primitive(): void
    {
        foreach (self::LISTS as $name => $list) {
            $src = self::readSource($list['path']);

            self::assertMatchesRegularExpression(
                "#import\s+UiDataTable\s+from\s+['\"]\.\./\.\./components/ui/DataTable\.vue['\"]|import\s+UiDataTable\s+from\s+['\"]@/components/ui/DataTable\.vue['\"]#",
                $src,
                "{$name}: the list must import UiDataTable from components/ui/DataTable.vue (#453)."
            );

            self::assertMatchesRegularExpression(
                '/<UiDataTable\b/',
                $src,
                "{$name}: the list must render <UiDataTable> (#453: the primitive owns the scroll affordance)."
            );
        }
    }

    /**
     * And no list keeps its own table markup: the rows, the header cells and
     * the body belong to the primitive now.
     */
    public function test_pages_render_no_hand_rolled_table(): void
    {
        foreach (self::LISTS as $name => $list) {
            $src = self::readSource($list['path']);

            foreach (['<table', '<thead', '<tbody', '<th ', '<td '] as $markup) {
                self::assertStringNotContainsString(
                    $markup,
                    $src,
                    "{$name}: the list must not hand-roll `{$markup}` markup (#453: UiDataTable renders the table)."
                );
            }
        }
    }

    /**
     * The pagination decision is the endpoint's, not the client's. A list with
     * a server page keeps `Pagination` outside the primitive and disables the
     * primitive's own pager; a list fed by a non-paginating endpoint renders
     * every row (`:pagination="false"`) instead of inventing a client page.
     */
    public function test_pagination_follows_the_endpoint(): void
    {
        foreach (self::LISTS as $name => $list) {
            $src = self::readSource($list['path']);

            self::assertMatchesRegularExpression(
                '/:pagination="false"/',
                $src,
                "{$name}: the primitive must not paginate the rows it is given; the list owns its page (#453)."
            );

            if ($list['serverPagination']) {
                self::assertStringContainsString(
                    '<Pagination',
                    $src,
                    "{$name}: a server-paginated list keeps the Pagination control outside the table (#453)."
                );

                continue;
            }

            self::assertStringNotContainsString(
                '<Pagination',
                $src,
                "{$name}: the endpoint returns the whole collection, so the list must not paginate it (#453)."
            );
        }
    }
}
