<?php

namespace Tests\Feature\Ui;

use Tests\TestCase;

/**
 * HOTFIX-LOGIN-001 — Brand mark uses wordmark + SF-style glyph, NOT generic favicon PNG.
 *
 * Pins the RULE (not the example): the brand mark area MUST render (a) the
 * literal "OdontoSuite" text + (b) an inline <svg> with stroke-width="1.75".
 * It MUST NOT render an <img> referencing the legacy favicon raster.
 *
 * Source-inspection of LoginPage.vue is the appropriate boundary because the
 * /login route returns the Vue SPA shell — the Vue template IS the rendered
 * contract.
 */
class HotfixLoginBrandMarkTest extends TestCase
{
    private const LOGIN_PAGE_REL = '/resources/js/modules/auth/LoginPage.vue';

    private static function loginPagePath(): string
    {
        return dirname(__DIR__, 3) . self::LOGIN_PAGE_REL;
    }

    public function test_login_header_has_odontosuite_wordmark(): void
    {
        $response = $this->get('/login');
        $response->assertStatus(200);

        $this->assertFileExists(
            self::loginPagePath(),
            'LoginPage.vue must exist (HOTFIX-LOGIN-001 source boundary)'
        );

        $source = (string) file_get_contents(self::loginPagePath());

        // The brand mark area MUST contain the literal "OdontoSuite" wordmark.
        $this->assertStringContainsString(
            'OdontoSuite',
            $source,
            'LoginPage.vue must render the literal "OdontoSuite" wordmark in the brand area (HOTFIX-LOGIN-001, design-taste §0.D anti-default discipline, apple-design §15 system font wordmark)'
        );
    }

    public function test_login_header_has_inline_svg_glyph_with_stroke_width_1_75(): void
    {
        $response = $this->get('/login');
        $response->assertStatus(200);

        $source = (string) file_get_contents(self::loginPagePath());

        // Pin the RULE: there must be at least one inline <svg> glyph in the
        // template whose stroke-width is exactly "1.75" (apple-design §16
        // icon stroke 1.5 baseline is for body icons; brand glyphs use 1.75).
        $count = preg_match('/<svg\b[^>]*>.*?stroke-width\s*=\s*"1\.75"/is', $source);
        $this->assertGreaterThanOrEqual(
            1,
            (int) $count,
            'LoginPage.vue must contain an inline <svg> glyph with stroke-width="1.75" for the brand mark (HOTFIX-LOGIN-001, apple-design §16)'
        );
    }

    public function test_login_header_does_not_reference_easy_dent_png(): void
    {
        $response = $this->get('/login');
        $response->assertStatus(200);

        $source = (string) file_get_contents(self::loginPagePath());

        // The brand mark MUST NOT reference the legacy easy_dent.png raster
        // favicon. Pin the RULE — no <img> src may resolve to that asset.
        $this->assertSame(
            0,
            substr_count($source, 'easy_dent.png'),
            'LoginPage.vue MUST NOT contain any reference to easy_dent.png (HOTFIX-LOGIN-001, design-taste §0.D — no raster favicon in brand mark)'
        );

        // Belt-and-suspenders: no <img> tag in the brand-mark area may carry
        // an src attribute pointing at any .png file.
        $imgPngCount = preg_match_all('/<img\b[^>]*src\s*=\s*"[^"]*\.png/i', $source);
        $this->assertSame(
            0,
            (int) $imgPngCount,
            'LoginPage.vue MUST NOT render any <img src="...png"> in the brand mark area (HOTFIX-LOGIN-001)'
        );
    }
}
