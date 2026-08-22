<?php

namespace Tests\Feature\Ui;

use Tests\TestCase;

/**
 * HOTFIX-LOGIN-003 — Subtitle uses readable size.
 *
 * The subtitle `.welcome-subtitle` MUST compute font-size ≥ 1rem (16px).
 * The SHOULD preference is 1.0625rem (17px, system body-large). Pin the
 * RULE — never less than 16px (apple-design §15).
 */
class HotfixLoginSubtitleTest extends TestCase
{
    private const LOGIN_PAGE_REL = '/resources/js/modules/auth/LoginPage.vue';

    private static function loginPagePath(): string
    {
        return dirname(__DIR__, 3) . self::LOGIN_PAGE_REL;
    }

    /**
     * Resolve the computed font-size of .welcome-subtitle in px.
     * Order of precedence:
     *   1. Explicit `font-size:` declaration inside the .welcome-subtitle rule.
     *   2. Tailwind utility class mapping (text-sm, text-base, text-lg, etc.)
     */
    private static function subtitleFontSizePx(string $source): ?float
    {
        if (!preg_match('/\.welcome-subtitle\s*\{([^}]+)\}/s', $source, $m)) {
            return null;
        }
        $block = $m[1];

        // 1. Explicit font-size declaration.
        if (preg_match('/font-size\s*:\s*([0-9]*\.?[0-9]+)\s*(px|rem|em)/i', $block, $fm)) {
            $value = (float) $fm[1];
            $unit = strtolower($fm[2]);
            if ($unit === 'rem' || $unit === 'em') {
                return $value * 16.0;
            }
            return $value;
        }

        // 2. Tailwind text-* utility (largest wins when multiple apply).
        $tailwindSizes = [
            'text-xs' => 12.0,
            'text-sm' => 14.0,
            'text-base' => 16.0,
            'text-lg' => 18.0,
            'text-xl' => 20.0,
            'text-2xl' => 24.0,
            'text-3xl' => 30.0,
        ];
        $resolved = null;
        foreach ($tailwindSizes as $util => $px) {
            if (preg_match('/(?:^|\s)' . preg_quote($util, '/') . '(?:\s|$)/', $block)) {
                $resolved = $px;
            }
        }
        return $resolved;
    }

    public function test_subtitle_font_size_is_at_least_16px(): void
    {
        $response = $this->get('/login');
        $response->assertStatus(200);

        $source = (string) file_get_contents(self::loginPagePath());

        $fontSizePx = self::subtitleFontSizePx($source);

        $this->assertNotNull(
            $fontSizePx,
            'LoginPage.vue must declare a resolvable font-size on .welcome-subtitle (HOTFIX-LOGIN-003)'
        );

        $this->assertGreaterThanOrEqual(
            16.0,
            $fontSizePx,
            sprintf(
                '.welcome-subtitle computed font-size must be ≥ 16px (1rem). Got: %.2fpx. (HOTFIX-LOGIN-003, apple-design §15)',
                $fontSizePx
            )
        );
    }
}
