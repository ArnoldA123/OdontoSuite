<?php

namespace Tests\Feature\Ui;

use Tests\TestCase;

/**
 * HOTFIX-LOGIN-007 — Mirror spring entrance (form + hero).
 *
 * The login page MUST attach TWO distinct useSpring instances (one for the
 * form wrap, one for the hero column) — the mirror easing from apple-design
 * §7. The hero spring MUST have a response in [0.40, 0.50] (slower mirror
 * vs. the form spring). Under prefers-reduced-motion, BOTH springs collapse
 * to instant. Pin the RULE, not example options.
 */
class HotfixLoginMirrorSpringTest extends TestCase
{
    private const LOGIN_PAGE_REL = '/resources/js/modules/auth/LoginPage.vue';

    private static function loginPagePath(): string
    {
        return dirname(__DIR__, 3) . self::LOGIN_PAGE_REL;
    }

    /**
     * Extract every useSpring({ ... }) options literal from the file.
     * Returns the inner option text for each call (ordered).
     *
     * @return string[]
     */
    private static function extractUseSpringOptions(string $source): array
    {
        $opts = [];
        $pattern = '/useSpring\s*\(\s*\{([^{}]*(?:\{[^{}]*\}[^{}]*)*)\}\s*\)/s';
        if (!preg_match_all($pattern, $source, $matches)) {
            return $opts;
        }
        foreach ($matches[1] as $body) {
            $opts[] = $body;
        }
        return $opts;
    }

    public function test_login_page_uses_at_least_two_distinct_springs(): void
    {
        $response = $this->get('/login');
        $response->assertStatus(200);

        $source = (string) file_get_contents(self::loginPagePath());
        $opts = self::extractUseSpringOptions($source);

        $this->assertGreaterThanOrEqual(
            2,
            count($opts),
            'LoginPage.vue must attach at least TWO distinct useSpring instances (form + hero mirror) — HOTFIX-LOGIN-007, apple-design §7'
        );

        // Each spring MUST declare a distinct cssVar so the two entrance
        // animations cannot collide on the same CSS custom property.
        $cssVars = [];
        foreach ($opts as $body) {
            if (preg_match('/cssVar\s*:\s*[\'"]([^\'"]+)[\'"]/i', $body, $vm)) {
                $cssVars[] = $vm[1];
            }
        }
        $uniqueCssVars = array_values(array_unique($cssVars));
        $this->assertGreaterThanOrEqual(
            2,
            count($uniqueCssVars),
            'LoginPage.vue must declare at least TWO distinct cssVar tokens across useSpring calls (form + hero) — HOTFIX-LOGIN-007'
        );
    }

    public function test_hero_spring_response_is_between_0_40_and_0_50(): void
    {
        $response = $this->get('/login');
        $response->assertStatus(200);

        $source = (string) file_get_contents(self::loginPagePath());
        $opts = self::extractUseSpringOptions($source);

        $this->assertGreaterThanOrEqual(
            2,
            count($opts),
            'LoginPage.vue must attach at least TWO useSpring instances — HOTFIX-LOGIN-007'
        );

        // Identify the hero spring by its cssVar suffix `--spring-hero` or
        // by `cssVar: '--spring-hero-...'`. If no hero spring is named that
        // way, fall back to the spring whose response differs from the form
        // spring's response.
        $heroResponse = null;
        foreach ($opts as $body) {
            if (preg_match('/cssVar\s*:\s*[\'"]--spring-hero/i', $body)
                && preg_match('/response\s*:\s*([0-9]*\.?[0-9]+)/i', $body, $rm)
            ) {
                $heroResponse = (float) $rm[1];
                break;
            }
        }

        if ($heroResponse === null) {
            // No hero-named spring found. Fallback: pick the spring with the
            // largest response value (the hero is the slower mirror).
            $responses = [];
            foreach ($opts as $body) {
                if (preg_match('/response\s*:\s*([0-9]*\.?[0-9]+)/i', $body, $rm)) {
                    $responses[] = (float) $rm[1];
                }
            }
            $this->assertNotEmpty(
                $responses,
                'LoginPage.vue useSpring calls must declare a response value — HOTFIX-LOGIN-007'
            );
            $heroResponse = max($responses);
        }

        $this->assertGreaterThanOrEqual(
            0.40,
            $heroResponse,
            sprintf(
                'Hero spring response must be ≥ 0.40 (slower mirror). Got: %.2f. (HOTFIX-LOGIN-007, apple-design §7)',
                $heroResponse
            )
        );
        $this->assertLessThanOrEqual(
            0.50,
            $heroResponse,
            sprintf(
                'Hero spring response must be ≤ 0.50. Got: %.2f. (HOTFIX-LOGIN-007, apple-design §7)',
                $heroResponse
            )
        );
    }

    public function test_reduced_motion_collapses_both_form_and_hero_springs(): void
    {
        $response = $this->get('/login');
        $response->assertStatus(200);

        $source = (string) file_get_contents(self::loginPagePath());

        // The prefers-reduced-motion media query MUST neutralize BOTH the
        // form-wrap AND the hero column. The current source only covers the
        // form-wrap, so this assertion fails until the hero spring is added
        // and reduced-motion is honored for it as well.
        $this->assertMatchesRegularExpression(
            '/@media\s*\(\s*prefers-reduced-motion\s*:\s*reduce\s*\)\s*\{[^}]*\.login-form-wrap[\s\S]*?(?:transform\s*:\s*none|transition\s*:\s*none)/is',
            $source,
            'prefers-reduced-motion must neutralize .login-form-wrap (HOTFIX-LOGIN-007)'
        );
        $this->assertMatchesRegularExpression(
            '/@media\s*\(\s*prefers-reduced-motion\s*:\s*reduce\s*\)\s*\{[^}]*\.login-hero-column[\s\S]*?(?:transform\s*:\s*none|transition\s*:\s*none|opacity\s*:\s*1)/is',
            $source,
            'prefers-reduced-motion must ALSO neutralize .login-hero-column — both springs must collapse to instant (HOTFIX-LOGIN-007, apple-design §14)'
        );
    }
}
