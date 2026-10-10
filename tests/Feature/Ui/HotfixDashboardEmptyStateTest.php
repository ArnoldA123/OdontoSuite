<?php

namespace Tests\Feature\Ui;

use App\Models\User;
use Tests\TestCase;

/**
 * HOTFIX-DASH-007 + WU3 / R4 — Empty state uses line-art SVG and carries
 * NO CTA.
 *
 * The today-appointments empty state MUST contain:
 *   - An inline <svg> line-art calendar icon at stroke-width="1.5", 24x24
 *   - ONE title + one precise sentence of informational copy.
 *
 * MUST NOT (WU3 / R4, single CTA per destination): the empty state MUST
 * NOT render a "Crear nueva cita" button. The agenda header owns the only
 * appointment CTA and it opens the modal, so the copy MUST NOT point at
 * "la sección de calendario" either. (apple-design §16 icon stroke 1.5;
 * design-taste §9.F "NO div-based fake product UI" — line-art SVGs are OK.)
 */
class HotfixDashboardEmptyStateTest extends TestCase
{

    private const DASHBOARD_PAGE_REL = '/resources/js/modules/dashboard/DashboardPage.vue';

    private static function dashboardPagePath(): string
    {
        return dirname(__DIR__, 3) . self::DASHBOARD_PAGE_REL;
    }

    public function test_empty_state_has_inline_svg_with_stroke_width_1_5(): void
    {
        $user = User::factory()->make();
        $response = $this->actingAs($user)->get('/dashboard');
        $response->assertStatus(200);

        $this->assertFileExists(
            self::dashboardPagePath(),
            'DashboardPage.vue must exist (HOTFIX-DASH-007 source boundary)'
        );

        $source = (string) file_get_contents(self::dashboardPagePath());

        // Pin the RULE: empty state container (data-state="empty-appointments")
        // MUST contain an inline <svg> with stroke-width="1.5". The current
        // implementation delegates the icon to a child <EmptyState>
        // component which renders a calendar icon, but the spec requires
        // the inline <svg> to live directly inside the data-state block so
        // the rule is auditable in source.
        $hasSvg = preg_match(
            '/data-state\s*=\s*[\'"]empty-appointments[\'"][\s\S]*?<svg\b[^>]*stroke-width\s*=\s*["\']1\.5["\']/is',
            $source
        );

        $this->assertGreaterThan(
            0,
            (int) $hasSvg,
            'DashboardPage.vue empty state (data-state="empty-appointments") MUST contain inline <svg stroke-width="1.5"> — HOTFIX-DASH-007, apple-design §16 (icon stroke 1.5 baseline for body icons), design-taste §9.F (no div-based fake UI).'
        );
    }

    public function test_empty_state_carries_no_cta_and_informational_copy(): void
    {
        $user = User::factory()->make();
        $response = $this->actingAs($user)->get('/dashboard');
        $response->assertStatus(200);

        $source = (string) file_get_contents(self::dashboardPagePath());

        // Pin the RULE (WU3 / R4): the empty state renders NO button. The
        // header owns the only "Nueva cita" CTA for the same destination.
        // Scope: from the hook to the closing tag of the shared component.
        $matched = preg_match(
            '/data-state\s*=\s*[\'"]empty-appointments[\'"][\s\S]*?<\/DashboardSectionEmpty>/is',
            $source,
            $blockMatches
        );
        $this->assertGreaterThan(0, (int) $matched, 'empty-appointments block must exist');
        $block = $blockMatches[0] ?? '';

        $this->assertDoesNotMatchRegularExpression(
            '/<(?:button|UiButton)\b/i',
            $block,
            'DashboardPage.vue empty state (data-state="empty-appointments") MUST NOT render a CTA button — WU3 / R4 (single CTA per destination: the agenda header owns "Nueva cita").'
        );

        $this->assertStringNotContainsString(
            'Crear nueva cita',
            $source,
            'DashboardPage.vue must drop the retired "Crear nueva cita" empty-state CTA copy (WU3 / R4).'
        );
        $this->assertStringNotContainsString(
            'sección de calendario',
            $source,
            'DashboardPage.vue empty-state copy must not point users at the calendar section (WU3 / D9: the CTA behavior elsewhere opens the modal).'
        );
    }
}
