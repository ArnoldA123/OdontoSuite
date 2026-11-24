<?php

namespace Tests\Unit\DesignSystem;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Header homogeneity across the nine top-level surfaces.
 *
 * Two header systems used to coexist: `PageHeader.vue` (30px/700, `border-b`,
 * no radius) on Environments, and a hand-built "hero" band on the other eight
 * (36px/600, canvas + hairline + panel radius + elevation-2). Patients and
 * Professionals also painted the brand lockup inside the header, six pages
 * offered a "Volver" button that navigated to `/dashboard`, and the CTAs
 * alternated "Nuevo paciente" with "Nuevo Profesional".
 *
 * The canonical contract, guarded here:
 *
 *   (a) every surface imports and renders the shared `PageHeader`, con una
 *       excepción declarada (T5a): `/calendar` posee una banda compacta de
 *       125 px (`data-calendar-frame` + `<header data-calendar-header>`)
 *       porque la tarjeta medía 175 px antes de la rejilla;
 *   (b) no surface keeps header markup of its own (`<header>` element,
 *       `data-*-hero` hook, `text-3xl font-bold`, `text-4xl` outside the
 *       component, `elevation-1`) — salvo el único `<header>` compacto del
 *       calendario;
 *   (c) no surface offers a back action inside its header ("Volver" is not a
 *       page action: none of the nine is a child of another);
 *   (d) no surface paints the brand glyph or the dental motif inside its
 *       header;
 *   (e) every CTA uses sentence case ("Nuevo profesional", not "Nuevo
 *       Profesional").
 *
 * The nine routes are the source of the table: the guard can never pass by
 * checking a surface that no longer exists.
 */
class SurfaceHeaderHomogeneityTest extends TestCase
{
    /**
     * route => [page, kicker, title, primary CTA|null].
     *
     * `title` is the literal copy when the page owns it; the greeting titles
     * (dashboard and Mi día) are declared by their expression instead.
     *
     * @var array<string, array{page: string, kicker: string, title: string|null, cta: string|null}>
     */
    private const SURFACES = [
        '/dashboard' => [
            'page' => '/resources/js/modules/dashboard/DashboardPage.vue',
            'kicker' => 'HOY',
            'title' => null,
            'cta' => null,
        ],
        '/my-day' => [
            'page' => '/resources/js/modules/my-day/MyDayPage.vue',
            'kicker' => 'Mi día',
            'title' => null,
            'cta' => null,
        ],
        '/calendar' => [
            'page' => '/resources/js/modules/appointments/CalendarPage.vue',
            // El rediseño del calendario (T4) titula la cejilla con la
            // superficie y la sede que la agenda muestra: se declara como
            // expresión, igual que el título de las dos superficies con saludo.
            'kicker' => null,
            'title' => null,
            'cta' => 'Nueva cita',
        ],
        '/continuity' => [
            'page' => '/resources/js/modules/continuity/ContinuityPage.vue',
            'kicker' => 'Pendientes',
            'title' => 'Pendientes',
            'cta' => null,
        ],
        '/patients' => [
            'page' => '/resources/js/modules/patients/PatientsPage.vue',
            'kicker' => 'Pacientes',
            'title' => 'Pacientes',
            'cta' => 'Nuevo paciente',
        ],
        '/professionals' => [
            'page' => '/resources/js/modules/professionals/ProfessionalsPage.vue',
            'kicker' => 'Profesionales',
            'title' => 'Profesionales',
            'cta' => 'Nuevo profesional',
        ],
        '/environments' => [
            'page' => '/resources/js/modules/environments/EnvironmentsPage.vue',
            'kicker' => 'Ambientes',
            'title' => 'Ambientes',
            'cta' => 'Nuevo ambiente',
        ],
        '/appointment-types' => [
            'page' => '/resources/js/modules/appointment-types/AppointmentTypesPage.vue',
            'kicker' => 'Catálogo',
            'title' => 'Tipos de cita',
            'cta' => 'Nuevo tipo de cita',
        ],
        '/specialties' => [
            'page' => '/resources/js/modules/specialties/SpecialtiesPage.vue',
            'kicker' => 'Catálogo',
            'title' => 'Especialidades',
            'cta' => 'Nueva especialidad',
            // Desde el hub de catálogos la anatomía vive en `CatalogPageShell`,
            // que compone `PageHeader`; la página declara el kicker, el título
            // y sus acciones en el contenedor compartido.
        ],
    ];

    private const PAGE_HEADER = '/resources/js/components/layout/PageHeader.vue';

    private static function projectRoot(): string
    {
        return dirname(__DIR__, 3);
    }

    private static function read(string $relativePath): string
    {
        $abs = self::projectRoot().$relativePath;
        self::assertFileExists($abs, "Source file must exist: {$relativePath}");

        $source = file_get_contents($abs);
        self::assertNotFalse($source, "Source file must be readable: {$relativePath}");

        return (string) $source;
    }

    /**
     * The markup the page hands to the shared component. Every header-scoped
     * rule reads this slice, so a surface cannot pass by moving a forbidden
     * control elsewhere in the page.
     */
    private static function headerBlock(string $source, string $label): string
    {
        // T5a: el calendario abre con su propia banda compacta de 125 px
        // declarados (title 24/32 + contexto + fila de herramientas), no con
        // la tarjeta PageHeader: la banda no es una tarjeta ni lleva sombra.
        if ($label === '/calendar') {
            $matched = preg_match(
                '#<header\b[^>]*data-calendar-header[^>]*>[\s\S]*?</header>#',
                $source,
                $matches
            );
            self::assertSame(
                1,
                $matched,
                "{$label} must render one compact <header data-calendar-header>...</header> block."
            );

            return $matches[0];
        }

        if ($label === '/specialties') {
            // El hub movió la cabecera a `CatalogPageShell`: el bloque es el
            // contenedor con sus props y el slot de acciones que alimenta a
            // PageHeader.
            $matched = preg_match('#<CatalogPageShell\b[^>]*>#', $source, $opening);
            self::assertSame(
                1,
                $matched,
                "{$label} must render one <CatalogPageShell> container."
            );

            $actions = self::actionsSlotBlock($source);

            return $opening[0].$actions;
        }

        $matched = preg_match('#<PageHeader\b[\s\S]*?</PageHeader>#', $source, $matches);
        self::assertSame(1, $matched, "{$label} must render one <PageHeader>...</PageHeader> block.");

        return $matches[0];
    }

    /**
     * El slot de acciones puede contener `<template #icon-left>` anidados: se
     * extrae contando aperturas y cierres en vez de cortar en el primer
     * `</template>`.
     */
    private static function actionsSlotBlock(string $source): string
    {
        $start = strpos($source, '<template #actions>');
        if ($start === false) {
            return '';
        }

        $depth = 0;
        $offset = $start;

        while (preg_match('/<template\b|<\/template>/', $source, $match, PREG_OFFSET_CAPTURE, $offset)) {
            $token = $match[0][0];
            $position = $match[0][1];

            if (str_starts_with($token, '</')) {
                $depth--;
                if ($depth === 0) {
                    return substr($source, $start, $position + strlen($token) - $start);
                }
            } else {
                $depth++;
            }

            $offset = $position + strlen($token);
        }

        return '';
    }

    public static function surfaces(): array
    {
        $cases = [];
        foreach (self::SURFACES as $route => $surface) {
            $cases[$route] = [$route, $surface];
        }

        return $cases;
    }

    #[DataProvider('surfaces')]
    public function test_surface_mounts_the_shared_page_header(string $route, array $surface): void
    {
        $source = self::read($surface['page']);

        if ($route === '/calendar') {
            // Excepción declarada (T5a): el calendario posee una banda única
            // más densa que la anatomía compartida, y no monta la tarjeta.
            self::assertStringContainsString(
                'data-calendar-frame',
                $source,
                "{$route} must render its compact page band (data-calendar-frame)."
            );
            self::assertStringNotContainsString(
                'PageHeader',
                $source,
                "{$route} must not mount PageHeader: its band is the compact header (T5a)."
            );

            return;
        }

        if ($route === '/specialties') {
            // El hub de catálogos: la página compone el contenedor compartido y
            // el contenedor compone PageHeader. La anatomía sigue viviendo en
            // un solo componente.
            self::assertMatchesRegularExpression(
                "/import\s+CatalogPageShell\s+from\s+['\"][^'\"]*components\/catalogs\/CatalogPageShell\\.vue['\"]/",
                $source,
                "{$route} must import the shared CatalogPageShell component."
            );
            self::assertStringContainsString(
                '<CatalogPageShell',
                $source,
                "{$route} must render <CatalogPageShell>; its header is the shared container, not local markup."
            );

            $shell = self::read('/resources/js/components/catalogs/CatalogPageShell.vue');
            self::assertMatchesRegularExpression(
                "/import\s+PageHeader\s+from\s+['\"][^'\"]*PageHeader\\.vue['\"]/",
                $shell,
                'CatalogPageShell.vue must import the shared PageHeader component.'
            );
            self::assertStringContainsString(
                '<PageHeader',
                $shell,
                'CatalogPageShell.vue must render <PageHeader>; the header anatomy is not reimplemented.'
            );

            return;
        }

        self::assertMatchesRegularExpression(
            "/import\s+PageHeader\s+from\s+['\"][^'\"]*components\\/layout\\/PageHeader\\.vue['\"]/",
            $source,
            "{$route} must import the shared PageHeader component (one component, no exceptions)."
        );
        self::assertStringContainsString(
            '<PageHeader',
            $source,
            "{$route} must render <PageHeader>; its header is the component, not local markup."
        );
    }

    #[DataProvider('surfaces')]
    public function test_surface_keeps_no_header_markup_of_its_own(string $route, array $surface): void
    {
        $source = self::read($surface['page']);

        if ($route === '/calendar') {
            // Una sola banda propia, y es la declarada: ningún otro <header>.
            self::assertSame(
                1,
                preg_match_all('#<header\b#', $source),
                "{$route} must open exactly one <header> element (the compact page band)."
            );
            self::assertMatchesRegularExpression(
                '#<header\b[^>]*data-calendar-header#',
                $source,
                "{$route} header must be the compact band (data-calendar-header)."
            );
        } else {
            self::assertDoesNotMatchRegularExpression(
                '#<header\b#',
                $source,
                "{$route} must not open a <header> element: the page header is PageHeader."
            );
        }
        self::assertDoesNotMatchRegularExpression(
            '/data-[a-z0-9-]+-hero\b/',
            $source,
            "{$route} must not expose a data-*-hero hook: header markup lives only in PageHeader."
        );
        self::assertStringNotContainsString(
            'text-3xl font-bold',
            $source,
            "{$route} must not paint a page title with text-3xl font-bold (the shared title is 36px/600)."
        );
        self::assertStringNotContainsString(
            'text-4xl',
            $source,
            "{$route} must not declare text-4xl anywhere: the only 36px title is PageHeader's."
        );
        self::assertStringNotContainsString(
            'elevation-1',
            $source,
            "{$route} must not use the elevation-1 rung in its header (the canonical rung is elevation-2)."
        );
    }

    #[DataProvider('surfaces')]
    public function test_surface_offers_no_back_action_in_its_header(string $route, array $surface): void
    {
        $source = self::read($surface['page']);
        $header = self::headerBlock($source, $route);

        self::assertDoesNotMatchRegularExpression(
            '/\bVolver\b/u',
            $header,
            "{$route} must not offer a back action in its header: none of the nine is a child of another."
        );
        self::assertStringNotContainsString(
            'data-back-button',
            $source,
            "{$route} must not keep the retired data-back-button hook."
        );
        self::assertStringNotContainsString(
            'data-calendar-action="back"',
            $source,
            "{$route} must not keep the retired calendar back action."
        );
    }

    #[DataProvider('surfaces')]
    public function test_surface_paints_no_brand_or_motif_inside_its_header(string $route, array $surface): void
    {
        $source = self::read($surface['page']);
        $header = self::headerBlock($source, $route);

        self::assertStringNotContainsString(
            '<svg',
            $header,
            "{$route} must not paint an icon or the brand glyph inside its header."
        );
        self::assertStringNotContainsString(
            'DentalMotifLayer',
            $header,
            "{$route} must not mount DentalMotifLayer inside its header (empty states may keep the motif)."
        );
        self::assertStringNotContainsString(
            'M8 3.5C5.5 3.5 4 5.5 4 8',
            $source,
            "{$route} must not inline the tooth glyph: the brand lives outside the page headers."
        );
        self::assertStringNotContainsString(
            'data-hero-motif',
            $source,
            "{$route} must not keep a hero motif hook."
        );
    }

    #[DataProvider('surfaces')]
    public function test_surface_cta_uses_sentence_case(string $route, array $surface): void
    {
        $source = self::read($surface['page']);
        $header = self::headerBlock($source, $route);

        self::assertDoesNotMatchRegularExpression(
            '/\b(?:Nuevo|Nueva)\s+[A-ZÁÉÍÓÚÑ]/u',
            $header,
            "{$route} must write its CTA as a noun phrase in sentence case (\"Nuevo profesional\", not \"Nuevo Profesional\")."
        );

        if ($surface['cta'] === null) {
            return;
        }

        self::assertStringContainsString(
            $surface['cta'],
            $header,
            "{$route} must offer \"{$surface['cta']}\" as the page's primary CTA."
        );
    }

    #[DataProvider('surfaces')]
    public function test_surface_declares_its_kicker_and_title(string $route, array $surface): void
    {
        $source = self::read($surface['page']);
        $header = self::headerBlock($source, $route);

        if ($route === '/calendar') {
            // T5a: la banda propia declara la cejilla y el título como texto
            // del header (mismos datos que ataba la tarjeta: `headerKicker` y
            // `headerTitle`), no como props del componente retirado.
            self::assertMatchesRegularExpression(
                '#data-calendar-kicker[\s\S]*?\{\{\s*headerKicker\s*\}\}#',
                $header,
                "{$route} must paint the dynamic kicker from `headerKicker`."
            );
            self::assertMatchesRegularExpression(
                '#<h1\b[^>]*data-calendar-title[\s\S]*?\{\{\s*headerTitle\s*\}\}#',
                $header,
                "{$route} must paint its period title as the page <h1>."
            );
            self::assertStringContainsString(
                'const headerKicker = computed',
                $source,
                "{$route} must declare the `headerKicker` computed it paints."
            );
            self::assertStringContainsString(
                'const headerTitle = computed',
                $source,
                "{$route} must declare the `headerTitle` computed it paints."
            );

            return;
        }

        if ($surface['kicker'] === null) {
            // A dynamic kicker is always bound from a computed named
            // `headerKicker`: the same contract as an expression title.
            self::assertStringContainsString(
                ':kicker="headerKicker"',
                $header,
                "{$route} must bind its dynamic kicker from `headerKicker`."
            );
            self::assertStringContainsString(
                'const headerKicker = computed',
                $source,
                "{$route} must declare the `headerKicker` computed it binds."
            );

            return;
        }

        self::assertStringContainsString(
            'kicker="'.$surface['kicker'].'"',
            $header,
            "{$route} must declare the kicker \"{$surface['kicker']}\" (uppercase text, no icons)."
        );

        if ($surface['title'] !== null) {
            self::assertMatchesRegularExpression(
                '/\btitle="'.preg_quote($surface['title'], '/').'"/u',
                $header,
                "{$route} must use the title \"{$surface['title']}\"."
            );
        }
    }

    #[DataProvider('surfaces')]
    public function test_surface_offers_the_shared_update_action(string $route): void
    {
        $source = self::read(self::SURFACES[$route]['page']);
        $header = self::headerBlock($source, $route);

        if ($route === '/calendar') {
            // T5a: la banda compacta no gasta 110 px en el rótulo; el botón se
            // anuncia por `aria-label` y conserva su hook de acción.
            self::assertMatchesRegularExpression(
                '#<UiButton\b(?=[^>]*variant="secondary")(?=[^>]*data-calendar-action="refresh")(?=[^>]*aria-label="Actualizar[^"]*")[^>]*>#s',
                $header,
                "{$route} must offer «Actualizar» as a secondary icon action in its compact band."
            );

            return;
        }

        self::assertDataContainsRefreshAction($header, $route);
    }

    private static function assertDataContainsRefreshAction(string $header, string $route): void
    {
        self::assertMatchesRegularExpression(
            '/<UiButton\b(?=[^>]*variant="secondary")[^>]*>[\s\S]*?Actualizar\s*<\/UiButton>/',
            $header,
            "{$route} must offer «Actualizar» as a secondary action in its header."
        );
    }

    /**
     * Five tables, one row-action vocabulary and one order: "Ver ficha" →
     * "Editar" → the destructive action ("Eliminar", or "Archivar"/
     * "Desactivar" where the domain forbids deletion).
     *
     * @var array<int, string>
     */
    private const LIST_SURFACES = [
        '/patients' => '/resources/js/modules/patients/PatientsPage.vue',
        '/professionals' => '/resources/js/modules/professionals/ProfessionalsPage.vue',
        '/environments' => '/resources/js/modules/environments/EnvironmentsPage.vue',
        '/appointment-types' => '/resources/js/modules/appointment-types/AppointmentTypesPage.vue',
        '/specialties' => '/resources/js/modules/specialties/SpecialtiesPage.vue',
    ];

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function listSurfaces(): array
    {
        $cases = [];
        foreach (self::LIST_SURFACES as $route => $page) {
            $cases[$route] = [$route, $page];
        }

        return $cases;
    }

    /**
     * The `#actions` slot of the list's own table: the row control contract,
     * isolated from the modals that also carry an "Editar" button.
     */
    private static function rowActionsBlock(string $source, string $label): string
    {
        $start = strpos($source, '#actions="{ row }"');
        self::assertNotFalse($start, "{$label} must render one #actions slot on its list table.");

        $end = strpos($source, '</UiDataTable>', $start);
        self::assertNotFalse($end, "{$label} must close its #actions slot inside the table.");

        return substr($source, $start, $end - $start);
    }

    #[DataProvider('listSurfaces')]
    public function test_list_actions_share_one_vocabulary_and_order(string $route, string $page): void
    {
        $actions = self::rowActionsBlock(self::read($page), $route);

        self::assertStringContainsString(
            'Ver ficha',
            $actions,
            "{$route} must offer «Ver ficha» as the first row action."
        );

        $order = [
            'Ver ficha' => strpos($actions, 'Ver ficha'),
            'Editar' => strpos($actions, 'Editar'),
        ];

        $destructive = null;
        foreach (['Eliminar', 'Archivar', 'Desactivar'] as $label) {
            $position = strpos($actions, $label);
            if ($position !== false) {
                $destructive = [$label => $position];

                break;
            }
        }
        self::assertNotNull(
            $destructive,
            "{$route} must close its row actions with the destructive action (Eliminar/Archivar/Desactivar)."
        );
        $order += $destructive;

        $expected = array_keys($order);
        $sorted = $order;
        asort($sorted);
        self::assertSame(
            $expected,
            array_keys($sorted),
            "{$route} must order its row actions as Ver ficha -> Editar -> destructive."
        );
    }

    #[DataProvider('listSurfaces')]
    public function test_list_empty_rides_the_shared_empty_state(string $route, string $page): void
    {
        $source = self::read($page);

        self::assertMatchesRegularExpression(
            "/from\s*['\"][^'\"]*components\/ui\/EmptyState\.vue['\"]/",
            $source,
            "{$route} must import UiEmptyState for the table's empty state."
        );
        self::assertStringContainsString(
            '<UiEmptyState',
            $source,
            "{$route} must render UiEmptyState for the table's empty state."
        );
        self::assertStringNotContainsString(
            '<UiSectionEmpty',
            $source,
            "{$route} must not paint the table empty with UiSectionEmpty (reserved for sections inside a page)."
        );
    }

    public function test_page_header_component_owns_the_canonical_anatomy(): void
    {
        $source = self::read(self::PAGE_HEADER);

        self::assertStringContainsString(
            'data-page-header',
            $source,
            'PageHeader.vue must expose the data-page-header hook.'
        );
        self::assertMatchesRegularExpression(
            '/<h1\b[^>]*data-page-header-title[^>]*>|<h1\b[^>]*class="page-title[^"]*"/',
            $source,
            'PageHeader.vue must render the page title as an <h1>.'
        );
        self::assertStringContainsString(
            'text-4xl font-semibold text-label',
            $source,
            'PageHeader.vue must paint the title 36px/600 on the label token.'
        );
        self::assertStringContainsString(
            'text-xs font-medium uppercase tracking-wide',
            $source,
            'PageHeader.vue must paint the kicker at 12px, uppercase and tracking-wide.'
        );
        self::assertStringContainsString(
            'p-6 md:p-8',
            $source,
            'PageHeader.vue must use 24px padding (32px from md up).'
        );

        foreach ([
            'var(--color-canvas)',
            '1px solid var(--color-hairline)',
            'var(--radius-panel)',
            'var(--elevation-2)',
        ] as $token) {
            self::assertStringContainsString(
                $token,
                $source,
                "PageHeader.vue must consume the {$token} surface token."
            );
        }

        self::assertStringNotContainsString(
            'border-b',
            $source,
            'PageHeader.vue must not keep the bottom border: the header is a panel, not a separator.'
        );
        self::assertMatchesRegularExpression(
            '/kicker:\s*\{/',
            $source,
            'PageHeader.vue must accept the optional kicker prop.'
        );
        self::assertStringContainsString(
            '<slot name="actions" />',
            $source,
            'PageHeader.vue must expose the actions slot.'
        );
    }
}
