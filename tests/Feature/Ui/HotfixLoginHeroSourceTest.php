<?php

namespace Tests\Feature\Ui;

use Tests\TestCase;

/**
 * HOTFIX-LOGIN-006 — Hero column is NOT the stock dental photo.
 *
 * The hero column MUST NOT contain any <img src=...login-hero.jpg> raster.
 * The hero MUST be an editorial composition (typography-led wordmark +
 * abstract line-art SVG(s)) on the canvas background.
 * Pin the RULE — no raster login-hero.jpg reference (design-taste §4.8).
 */
class HotfixLoginHeroSourceTest extends TestCase
{
    private const LOGIN_PAGE_REL = '/resources/js/modules/auth/LoginPage.vue';

    private static function loginPagePath(): string
    {
        return dirname(__DIR__, 3) . self::LOGIN_PAGE_REL;
    }

    public function test_hero_column_does_not_reference_login_hero_jpg(): void
    {
        $response = $this->get('/login');
        $response->assertStatus(200);

        $source = (string) file_get_contents(self::loginPagePath());

        // Pin the RULE: NO <img> element whose src ends with login-hero.jpg.
        $this->assertSame(
            0,
            substr_count($source, 'login-hero.jpg'),
            'LoginPage.vue MUST NOT reference login-hero.jpg (HOTFIX-LOGIN-006 — no stock dental photo per design-taste §4.8, §9.F)'
        );
    }

    public function test_hero_column_has_no_img_with_jpg_or_png_src(): void
    {
        $response = $this->get('/login');
        $response->assertStatus(200);

        $source = (string) file_get_contents(self::loginPagePath());

        // Belt-and-suspenders: no <img src="...jpg|png"> inside the
        // .login-hero-column region. The hero MUST be SVG-led.
        $heroBlockMatch = preg_match(
            '/<aside\s+class\s*=\s*"login-hero-column"[^>]*>(.*?)<\/aside>/is',
            $source,
            $heroBlock
        );
        $this->assertSame(
            1,
            (int) $heroBlockMatch,
            'LoginPage.vue must contain exactly one <aside class="login-hero-column"> (HOTFIX-LOGIN-006)'
        );

        $heroSource = $heroBlock[1];
        $rasterImgCount = preg_match_all(
            '/<img\b[^>]*src\s*=\s*"[^"]*\.(jpg|jpeg|png|webp)/i',
            $heroSource
        );
        $this->assertSame(
            0,
            (int) $rasterImgCount,
            'LoginPage.vue hero column MUST NOT contain any raster <img src="...jpg|png|webp"> (HOTFIX-LOGIN-006 — hero is editorial/SVG-led)'
        );
    }
}
