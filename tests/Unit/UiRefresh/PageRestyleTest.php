<?php

namespace Tests\Unit\UiRefresh;

use PHPUnit\Framework\TestCase;

/**
 * PR2 — page-template restyle tests for ui-refresh-apple-clinical-2026-08.
 *
 * Covers the three vertical exemplars (Login + Dashboard + 404) which
 * inherit the iOS clinical aesthetic from the new primitive layer shipped
 * in PR1. Each test is a static-grep against the .vue file (or a CSS-file
 * parse) that asserts the iOS-clinical class binding resolves, the
 * serif headline is gone, and the dashboard revalued stat numbers /
 * status chips are present.
 *
 * Failure indicates a page template regressed the iOS clinical read —
 * a wrong token class slipped back in, the serif font-family came back,
 * a stat number was repainted with a colored ramp, or a gradient
 * snuck into the dashboard.
 */
class PageRestyleTest extends TestCase
{
    /** Project root absolute path. */
    private static function projectRootPath(): string
    {
        return dirname(__DIR__, 3);
    }

    /** Absolute path of a file under resources/. */
    private static function path(string $rel): string
    {
        return self::projectRootPath() . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
    }

    /** Read a UTF-8 file's contents; returns empty string on failure. */
    private static function read(string $rel): string
    {
        $path = self::path($rel);
        return is_file($path) ? (string) file_get_contents($path) : '';
    }

    /**
     * Count case-insensitive literal occurrences in the source.
     * Uses a non-overlapping, fast str_ireplace count.
     */
    private static function countCi(string $haystack, string $needle): int
    {
        if ($needle === '') {
            return 0;
        }
        return substr_count(strtolower($haystack), strtolower($needle));
    }

    /**
     * Task 2.1.1 — LoginPage drops `var(--font-serif)` everywhere. The
     * headline, hero caption, and prefers-contrast block all used the
     * serif family in the previous design; the system font replaces it.
     *
     * @test
     */
    public function login_page_drops_var_font_serif(): void
    {
        $src = self::read('resources/js/modules/auth/LoginPage.vue');
        $this->assertNotSame('', $src, 'LoginPage.vue must exist');

        $this->assertSame(
            0,
            self::countCi($src, 'var(--font-serif)'),
            'LoginPage.vue must not reference var(--font-serif) anywhere'
        );
    }

    /**
     * Task 2.1.2 — Dashboard's cash-status badge resolves to the iOS
     * filled pattern: `bg-systemGreen-100 text-systemGreen-600` for
     * "Abierta", `bg-systemRed-100 text-systemRed-600` for "Cerrada",
     * `bg-systemGray-100 text-systemGray-600` for "Sin sesión".
     *
     * The badge text is rendered through a Vue computed binding; the
     * source file must contain all three color triples so the binding
     * can resolve at runtime.
     *
     * @test
     */
    public function dashboard_cash_badge_color_matches_state(): void
    {
        $src = self::read('resources/js/modules/dashboard/DashboardPage.vue');
        $this->assertNotSame('', $src, 'DashboardPage.vue must exist');

        $mustContain = [
            'bg-systemGreen-100 text-systemGreen-600' => 'Abierta badge (green)',
            'bg-systemRed-100 text-systemRed-600' => 'Cerrada badge (red)',
            'bg-systemGray-100 text-systemGray-600' => 'Sin sesión badge (gray)',
        ];
        foreach ($mustContain as $needle => $label) {
            $this->assertGreaterThan(
                0,
                self::countCi($src, $needle),
                "DashboardPage.vue must contain `{$needle}` for the {$label}"
            );
        }
    }

    /**
     * Task 2.1.3 — the "Citas Hoy" stat number must be rendered in
     * `text-label` (pure black) — NOT in a colored ramp. iOS clinical
     * stat cards paint big numbers in label color so the stat reads as
     * "information", not "branded metric".
     *
     * @test
     */
    public function dashboard_stat_number_uses_text_label(): void
    {
        $src = self::read('resources/js/modules/dashboard/DashboardPage.vue');
        $this->assertNotSame('', $src, 'DashboardPage.vue must exist');

        $this->assertSame(
            0,
            self::countCi($src, 'text-terracotta-600'),
            'DashboardPage.vue must not use text-terracotta-600 on stat numbers'
        );
        $this->assertSame(
            0,
            self::countCi($src, 'text-terracotta-500'),
            'DashboardPage.vue must not use text-terracotta-500 on stat numbers'
        );
        // The stat number must be in text-label.
        $this->assertGreaterThan(
            0,
            self::countCi($src, 'text-label'),
            'DashboardPage.vue must use text-label on stat numbers'
        );
    }

    /**
     * Task 2.1.4 — anti-requirement guard. No gradient backgrounds may
     * appear on the dashboard. iOS clinical uses flat surface fills,
     * not the decorative gradient of the previous design.
     *
     * @test
     */
    public function dashboard_no_linear_gradient(): void
    {
        $src = self::read('resources/js/modules/dashboard/DashboardPage.vue');
        $this->assertNotSame('', $src, 'DashboardPage.vue must exist');

        $this->assertSame(
            0,
            self::countCi($src, 'linear-gradient'),
            'DashboardPage.vue must not contain any linear-gradient'
        );
        $this->assertSame(
            0,
            self::countCi($src, 'bg-gradient'),
            'DashboardPage.vue must not contain any bg-gradient utility'
        );
    }

    /**
     * Task 2.1.5 — 404 page drops `var(--font-serif)` on the headline.
     * The page must render the headline in the system font family.
     *
     * @test
     */
    public function not_found_page_drops_var_font_serif(): void
    {
        $src = self::read('resources/js/modules/errors/NotFoundPage.vue');
        $this->assertNotSame('', $src, 'NotFoundPage.vue must exist');

        $this->assertSame(
            0,
            self::countCi($src, 'var(--font-serif)'),
            'NotFoundPage.vue must not reference var(--font-serif)'
        );
    }
}
