<?php

namespace Tests\Feature\Ui;

use App\Models\User;
use Tests\TestCase;

/**
 * HOTFIX-DASH-009 / WU3 — Per-section staggered springs (3 sections,
 * 60ms stagger).
 *
 * Dashboard MUST attach useSpring({ damping: 1.0, response: 0.35 }) to each
 * of: greeting block, KPI grid, the agenda empty state. Sections MUST enter
 * with stagger delays of 0ms, 60ms, 120ms (via setTimeout post-mount).
 * Pin the RULE: 3 distinct useSpring instances on Dashboard page sections.
 * (apple-design §4 springs for entrance — default to damping 1.0 critically
 * damped; apple-design §8 intermediate frames telegraph direction — stagger
 * tells the eye where the eye should land.) The quick-actions spring left
 * with its section (WU3 / D5).
 */
class HotfixDashboardStaggerTest extends TestCase
{

    private const DASHBOARD_PAGE_REL = '/resources/js/modules/dashboard/DashboardPage.vue';

    private static function dashboardPagePath(): string
    {
        return dirname(__DIR__, 3) . self::DASHBOARD_PAGE_REL;
    }

    /**
     * Extract every useSpring({ ... }) options literal from the file.
     *
     * @return string[]
     */
    private static function extractUseSpringOptions(string $source): array
    {
        // Strip `//` line comments first. A commented-out useSpring would
        // otherwise still match the pattern and be counted as a live spring,
        // so the guard could not tell a removed entrance from a live one.
        $source = (string) preg_replace('#//[^\n]*#', '', $source);

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

    public function test_dashboard_attaches_three_distinct_use_spring_instances(): void
    {
        $user = User::factory()->make();
        $response = $this->actingAs($user)->get('/dashboard');
        $response->assertStatus(200);

        $this->assertFileExists(
            self::dashboardPagePath(),
            'DashboardPage.vue must exist (HOTFIX-DASH-009 source boundary)'
        );

        $source = (string) file_get_contents(self::dashboardPagePath());
        $opts = self::extractUseSpringOptions($source);

        // Entrance springs are the useSpring calls carrying a cssVar; the
        // KPI count-up springs are runtime helpers without one.
        $entranceOpts = [];
        $cssVars = [];
        foreach ($opts as $body) {
            if (preg_match('/cssVar\s*:\s*[\'"]([^\'"]+)[\'"]/i', $body, $vm)) {
                $entranceOpts[] = $body;
                $cssVars[] = $vm[1];
            }
        }

        $this->assertCount(
            3,
            $entranceOpts,
            'DashboardPage.vue MUST attach exactly THREE section entrance useSpring instances (greeting, KPI grid, agenda empty state) — WU3 / D5 removed the quick-actions spring.'
        );

        // Each spring MUST declare a distinct cssVar so the entrance
        // animations cannot collide on the same CSS custom property.
        $uniqueCssVars = array_values(array_unique($cssVars));
        $this->assertCount(
            3,
            $uniqueCssVars,
            'DashboardPage.vue MUST declare exactly THREE distinct cssVar tokens across the entrance useSpring calls.'
        );
        $this->assertNotContains(
            '--spring-dash-quick-o',
            $uniqueCssVars,
            'The quick-actions entrance cssVar left with its section (WU3 / D5).'
        );
    }

    /**
     * T4 - every entrance cssVar MUST be consumed by a template rule.
     * Before T4 the vars were written to the sections but no style rule read
     * them, so the motion was vestigial. Pin the consumption so the entrance
     * cannot silently become dead code again.
     */
    public function test_every_entrance_css_var_is_consumed_by_a_template_rule(): void
    {
        $source = (string) file_get_contents(self::dashboardPagePath());

        $expectedVars = [
            '--spring-dash-greeting-o',
            '--spring-dash-kpi-o',
            '--spring-dash-empty-o',
        ];

        foreach ($expectedVars as $cssVar) {
            $this->assertMatchesRegularExpression(
                "/revealStyle\(\s*'" . preg_quote($cssVar, '/') . "'\s*\)/",
                $source,
                "DashboardPage.vue MUST consume `{$cssVar}` through revealStyle() in a template style rule (T4 - the entrance springs must not be vestigial)."
            );
        }

        $this->assertStringNotContainsString(
            '--spring-dash-quick-o',
            $source,
            'DashboardPage.vue must drop the retired quick-actions entrance cssVar (WU3 / D5).'
        );

        // The helper itself must build the var() read with the final-state
        // fallback (1), which is what renders under reduced motion.
        $this->assertMatchesRegularExpression(
            '/var\(\s*\$\{cssVar\}\s*,\s*1\s*\)/',
            $source,
            'DashboardPage.vue revealStyle() MUST read var(cssVar, 1) so the un-attached final state is the fallback (T4 reduced-motion contract).'
        );
    }

    /**
     * T4 - the entrance stagger is part of the contract. Pin each delay
     * literally so a future edit cannot flatten the cascade.
     */
    public function test_dashboard_entrance_stagger_keeps_the_0_60_120_delays(): void
    {
        $source = (string) file_get_contents(self::dashboardPagePath());

        $expectedDelays = [
            'greetingSpring' => 0,
            'kpiSpring' => 60,
            'emptyStateSpring' => 120,
        ];

        foreach ($expectedDelays as $spring => $delay) {
            $this->assertMatchesRegularExpression(
                '/' . preg_quote($spring, '/') . '\.set\(1\)\s*,\s*' . $delay . '\s*\)/',
                $source,
                "DashboardPage.vue MUST keep the {$delay}ms stagger delay for {$spring} (T4 - 0/60/120ms entrance cascade, WU3)."
            );
        }
    }
}
