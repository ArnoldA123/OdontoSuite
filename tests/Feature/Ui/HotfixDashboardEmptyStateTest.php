<?php

namespace Tests\Feature\Ui;

use App\Models\User;
use Tests\TestCase;

/**
 * HOTFIX-DASH-007 — Empty state uses line-art SVG + primary CTA.
 *
 * The "Citas de Hoy" empty state MUST contain:
 *   - An inline <svg> line-art calendar icon at stroke-width="1.5", 24x24
 *   - A primary CTA <UiButton variant="primary"> with text matching
 *     /crear.*cita/i.
 *
 * MUST NOT: empty state MUST NOT be a div-based fake screenshot.
 * Pin the RULE: empty state contains SVG with stroke-width="1.5" AND a
 * primary button with text matching /crear.*cita/i. (apple-design §12
 * translucent chrome for depth — radial gradient as subtle depth;
 * apple-design §16 icon stroke 1.5; design-taste §9.F "NO div-based
 * fake product UI" — line-art SVGs are OK.)
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

    public function test_empty_state_has_primary_button_with_crear_cita_text(): void
    {
        $user = User::factory()->make();
        $response = $this->actingAs($user)->get('/dashboard');
        $response->assertStatus(200);

        $source = (string) file_get_contents(self::dashboardPagePath());

        // Pin the RULE: the empty state MUST contain a primary CTA whose
        // visible text matches /crear.*cita/i. The current EmptyState
        // component uses action-text="Agendar nueva cita" — the verb
        // "Agendar" (to schedule) does not match "Crear" (to create)
        // per the rule. The Spanish wording "Crear nueva cita" is the
        // canonical primary CTA for the appointment-creation intent.
        $hasPrimaryCrearCita = preg_match(
            '/variant\s*=\s*["\']primary["\'][^>]*>\s*(?:<\/?[^>]+>\s*)*\b[Cc]rear\b[^<]*\bcita\b/is',
            $source
        );

        $this->assertGreaterThan(
            0,
            (int) $hasPrimaryCrearCita,
            'DashboardPage.vue empty state MUST contain a primary CTA with text matching /crear.*cita/i — HOTFIX-DASH-007, apple-design §12 (translucent chrome for depth, primary CTA anchored to the action).'
        );
    }
}