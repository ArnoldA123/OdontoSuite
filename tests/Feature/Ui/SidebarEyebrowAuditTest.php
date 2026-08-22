<?php

namespace Tests\Feature\Ui;

use App\Models\User;
use Tests\TestCase;

/**
 * HOTFIX-DASH-001 — Sidebar section labels removed (no uppercase tracking).
 *
 * The Sidebar MUST NOT contain any element with `text-xs uppercase
 * tracking-[0.18em]` (or similar uppercase-tracking class) for section
 * labels. Sections MUST be separated by a 1px var(--color-hairline)
 * horizontal divider instead of text labels.
 *
 * Pins the RULE: regex scan AppLayout.vue template for `tracking-[0.18em]`
 * or `uppercase tracking-` for section labels. Assert zero matches.
 * (design-taste §9.F "Section-Numbering Eyebrows" + §4.7 EYEBROW COUNT
 * mechanical — every AI-built site puts an eyebrow above EVERY section
 * header, producing the same templated rhythm.)
 */
class SidebarEyebrowAuditTest extends TestCase
{

    private const APP_LAYOUT_REL = '/resources/js/components/layout/AppLayout.vue';

    private static function appLayoutPath(): string
    {
        return dirname(__DIR__, 3) . self::APP_LAYOUT_REL;
    }

    public function test_sidebar_has_no_uppercase_tracking_section_labels(): void
    {
        $user = User::factory()->make();
        $response = $this->actingAs($user)->get('/dashboard');
        $response->assertStatus(200);

        $this->assertFileExists(
            self::appLayoutPath(),
            'AppLayout.vue must exist (HOTFIX-DASH-001 source boundary)'
        );

        $source = (string) file_get_contents(self::appLayoutPath());

        // Pin the RULE: zero uppercase-tracking class on section labels.
        // The pattern targets any class list mixing `uppercase` with
        // `tracking-*` (the design-taste eyebrow signature).
        $uppercaseTrackingCount = preg_match_all(
            '/\buppercase\b[^\'"\n]*\btracking-(?:\[[^\]]+\]|wide|widest)/i',
            $source
        );

        $this->assertSame(
            0,
            (int) $uppercaseTrackingCount,
            'AppLayout.vue MUST NOT contain any element with `uppercase tracking-*` class for section labels — HOTFIX-DASH-001, design-taste §9.F (Section-Numbering Eyebrows) + §4.7 (EYEBROW COUNT mechanical: count of uppercase-tracking section labels must be 0).'
        );
    }

    public function test_sidebar_has_no_tracking_0_18em_class(): void
    {
        $user = User::factory()->make();
        $response = $this->actingAs($user)->get('/dashboard');
        $response->assertStatus(200);

        $source = (string) file_get_contents(self::appLayoutPath());

        // Specifically target `tracking-[0.18em]` — the design-taste
        // eyebrow signature. Sections must use hairline dividers, not
        // uppercase-tracked text labels.
        $trackingWideCount = preg_match_all('/tracking-\[0\.18em\]/i', $source);

        $this->assertSame(
            0,
            (int) $trackingWideCount,
            'AppLayout.vue MUST NOT contain `tracking-[0.18em]` for section labels — HOTFIX-DASH-001, design-taste §9.F (Section-Numbering Eyebrows binary ban).'
        );
    }
}