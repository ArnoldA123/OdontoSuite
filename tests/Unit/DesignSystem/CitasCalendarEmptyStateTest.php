<?php

namespace Tests\Unit\DesignSystem;

/**
 * T7 — CitasCalendarEmptyStateTest.
 *
 * Structural contract for the D1/D9 empty-range states: when the loaded
 * range carries zero appointments the grid shell renders ONE quiet
 * empty block (dental motif + icon + per-view copy + permission-gated CTA)
 * INSTEAD of the Day/Week/Month grids, and it never shows while loading.
 *
 * Anchored on the `data-calendar-empty` block + the behaviour it marks:
 *
 *   1) placement — the block sits INSIDE `data-calendar-shell="grid"`
 *      (after the loading state, before the Day view) so the legend strip
 *      and the shell chrome stay put while only the body swaps;
 *   2) precedence — `v-if="loading"` first, then
 *      `v-else-if="appointments.length === 0"`, then the view bodies: the
 *      empty state can never render during a load (including the first
 *      paint, where `loading` now starts true);
 *   3) motif — the shared `<DentalMotifLayer variant="box">` (the same
 *      layer DashboardSectionEmpty renders) behind the copy, exposed for
 *      tests through `data-calendar-empty-motif`;
 *   4) copy — per-view (day/week/month) through the `emptyStateCopy`
 *      computed in the dashboard's D9 language: one short title + one
 *      precise sentence;
 *   5) CTA — optional "Nueva cita" gated by `can.createAppointment` (the
 *      same key as the hero CTA), quiet tokens only, no inline SVG.
 *
 * The behaviour itself is exercised end-to-end by the smoke suite
 * (tests/js/smoke/appointments.test.js — "T7 empty range smoke").
 *
 * Implementation note: regex delimiters are `#` (NOT `/`) because path
 * patterns contain forward slashes.
 */
class CitasCalendarEmptyStateTest extends ModuleAppShellTestCase
{
    /** Calendar page path constant — single source of truth for the absolute path. */
    private const CALENDAR_PATH = '/resources/js/modules/appointments/CalendarPage.vue';

    /** Template markers delimiting the empty-state region. */
    private const EMPTY_MARKER = '<!-- Empty State (D1/D9) -->';
    private const DAY_VIEW_MARKER = '<!-- Day View -->';

    /** @return array<int, string> */
    protected static function polishedFiles(): array
    {
        return [
            dirname(__DIR__, 3) . self::CALENDAR_PATH,
        ];
    }

    private static function calendarSource(): string
    {
        $path = dirname(__DIR__, 3) . self::CALENDAR_PATH;
        $src = is_file($path) ? file_get_contents($path) : false;
        self::assertNotFalse($src, sprintf('%s must be readable.', self::CALENDAR_PATH));

        return $src;
    }

    /**
     * Extract the empty-state template region (from its marker comment to
     * the Day view marker that follows it).
     */
    private static function emptySlice(string $src): string
    {
        $start = strpos($src, self::EMPTY_MARKER);
        $end = strpos($src, self::DAY_VIEW_MARKER);
        self::assertNotFalse($start, sprintf('%s MUST keep the `%s` template marker.', self::CALENDAR_PATH, self::EMPTY_MARKER));
        self::assertNotFalse($end, sprintf('%s MUST keep the `%s` template marker.', self::CALENDAR_PATH, self::DAY_VIEW_MARKER));
        self::assertGreaterThan($start, $end, 'Empty-state slice must precede the Day view.');

        return substr($src, $start, $end - $start);
    }

    /**
     * T7 placement — the empty block lives INSIDE the grid shell: after the
     * `data-calendar-shell="grid"` opening and before the shell's closing
     * `</section>` (the shell owns the legend strip + surface chrome; only
     * the body swaps to the empty state).
     */
    public function test_empty_block_lives_inside_the_grid_shell(): void
    {
        $src = self::calendarSource();

        $shell = strpos($src, 'data-calendar-shell="grid"');
        $empty = strpos($src, self::EMPTY_MARKER);
        $sectionClose = strpos($src, '</section>');
        $details = strpos($src, '<!-- Appointment Details Modal -->');

        $this->assertNotFalse($shell, sprintf('%s MUST keep the `data-calendar-shell="grid"` hook (T4).', self::CALENDAR_PATH));
        $this->assertNotFalse($empty, sprintf('%s MUST keep the `%s` marker (T7).', self::CALENDAR_PATH, self::EMPTY_MARKER));
        $this->assertNotFalse($sectionClose, sprintf('%s MUST keep the grid-shell `</section>`.', self::CALENDAR_PATH));
        $this->assertNotFalse($details, sprintf('%s MUST keep the `<!-- Appointment Details Modal -->` marker.', self::CALENDAR_PATH));

        $this->assertTrue($shell < $empty, 'Grid shell must open before the empty state (T7: empty state is a shell body state).');
        $this->assertTrue($empty < $sectionClose, 'Empty state must render before the grid shell `</section>` (T7: it lives INSIDE the shell).');
        $this->assertTrue($sectionClose < $details, 'The grid shell must close before the details modal (template order preserved).');

        $slice = self::emptySlice($src);
        $this->assertSame(
            1,
            preg_match('#data-calendar-empty(?!-)#', $slice),
            sprintf('%s empty block MUST expose the `data-calendar-empty` hook (T7; consumed by the smoke suite).', self::CALENDAR_PATH)
        );
    }

    /**
     * T7 precedence — loading wins over the empty state, the empty state
     * wins over the view bodies, and `loading` starts true so the empty
     * block never flashes on first paint (D9: never show "no data" while
     * the range is still loading).
     */
    public function test_empty_block_precedence_over_loading_and_grids(): void
    {
        $src = self::calendarSource();

        $loading = strpos($src, '<div v-if="loading"');
        $empty = strpos($src, self::EMPTY_MARKER);
        $dayView = strpos($src, self::DAY_VIEW_MARKER);

        $this->assertNotFalse($loading, sprintf('%s MUST keep the `v-if="loading"` state (T7).', self::CALENDAR_PATH));
        $this->assertNotFalse($empty, sprintf('%s MUST keep the empty-state marker (T7).', self::CALENDAR_PATH));
        $this->assertNotFalse($dayView, sprintf('%s MUST keep the Day view marker (T7).', self::CALENDAR_PATH));

        $this->assertLessThan($empty, $loading, 'Loading state must precede the empty state (T7: empty never renders while loading).');
        $this->assertLessThan($dayView, $empty, 'Empty state must precede the view bodies (T7: grids swap out on an empty range).');

        $this->assertSame(
            1,
            preg_match('#v-else-if="appointments\.length === 0"#', $src),
            sprintf('%s empty block MUST gate on `v-else-if="appointments.length === 0"` (T7: exactly zero loaded appointments).', self::CALENDAR_PATH)
        );

        $this->assertSame(
            1,
            preg_match('#const loading = ref\(true\)#', $src),
            sprintf(
                '%s `loading` MUST start true — the initial load must show the loading state, '
                . 'never the D1/D9 empty block (T7).',
                self::CALENDAR_PATH
            )
        );
    }

    /**
     * T7 motif + copy — the block reuses the shared
     * `<DentalMotifLayer variant="box">` behind the copy (the same layer
     * DashboardSectionEmpty renders), and the copy is per-view through the
     * `emptyStateCopy` computed in the dashboard's D9 language: one short
     * title + one precise sentence for day, week and month.
     */
    public function test_empty_block_motif_and_per_view_copy(): void
    {
        $src = self::calendarSource();
        $slice = self::emptySlice($src);

        $this->assertSame(
            1,
            preg_match('#<DentalMotifLayer\b[^>]*variant="box"#', $slice),
            sprintf('%s empty block MUST carry the shared `<DentalMotifLayer variant="box">` (T7/D1: same motif as the dashboard empty states).', self::CALENDAR_PATH)
        );
        $this->assertSame(
            1,
            preg_match('#data-calendar-empty-motif#', $slice),
            sprintf('%s empty motif MUST expose `data-calendar-empty-motif` (T7 hook).', self::CALENDAR_PATH)
        );

        $this->assertSame(
            1,
            preg_match('#const emptyStateCopy = computed\(\(\) => \{#', $src),
            sprintf('%s MUST derive the empty copy from an `emptyStateCopy` computed (T7: per-view, not inline literals).', self::CALENDAR_PATH)
        );
        $this->assertSame(
            1,
            preg_match('#return copy\[currentView\.value\]#', $src),
            sprintf('%s `emptyStateCopy` MUST key the copy by `currentView` (T7: day/week/month aware).', self::CALENDAR_PATH)
        );

        foreach (['Sin citas este día', 'Sin citas esta semana', 'Sin citas este mes'] as $title) {
            $this->assertStringContainsString(
                $title,
                $src,
                sprintf('%s MUST define the per-view empty title "%s" (T7/D9 copy).', self::CALENDAR_PATH, $title)
            );
        }
        $this->assertStringContainsString(
            '{{ emptyStateCopy.title }}',
            $slice,
            sprintf('%s empty block MUST render the title from `emptyStateCopy` (T7).', self::CALENDAR_PATH)
        );
        $this->assertStringContainsString(
            '{{ emptyStateCopy.description }}',
            $slice,
            sprintf('%s empty block MUST render the description from `emptyStateCopy` (T7: one title + one sentence).', self::CALENDAR_PATH)
        );
    }

    /**
     * T7 quiet CTA — the optional "Nueva cita" action rides the block,
     * gated by the SAME `can.createAppointment` permission as the hero CTA,
     * wired to the same `openNewAppointmentModal` handler. Quiet tokens
     * only: no inline SVG (the icon is a heroicon component), no raw
     * colour literals.
     */
    public function test_empty_block_cta_gated_and_quiet(): void
    {
        $src = self::calendarSource();
        $slice = self::emptySlice($src);

        // CTA tag: gate + handler on the same UiButton as the hook.
        $pattern = '#<UiButton\b(?=[^>]*data-calendar-action="empty-new-appointment")([^>]*)>#s';
        $this->assertSame(
            1,
            preg_match($pattern, $src, $matches),
            sprintf('%s empty CTA MUST be one `<UiButton data-calendar-action="empty-new-appointment">` (T7 hook).', self::CALENDAR_PATH)
        );
        $tag = $matches[1];
        $this->assertStringContainsString('v-if="can.createAppointment?.value"', $tag, sprintf('%s empty CTA MUST gate on `can.createAppointment?.value` (T7: same key as the hero CTA).', self::CALENDAR_PATH));
        $this->assertStringContainsString('@click="openNewAppointmentModal"', $tag, sprintf('%s empty CTA MUST reuse `openNewAppointmentModal` (T7).', self::CALENDAR_PATH));

        // Quiet tokens: icon via heroicon component, canvas/hairline grammar.
        $this->assertSame(
            0,
            preg_match('#<svg#', $slice),
            sprintf('%s empty slice MUST NOT carry inline `<svg>` (T7: heroicon components only, same rule as the month grid).', self::CALENDAR_PATH)
        );
        $this->assertSame(
            0,
            preg_match('#\\#[0-9a-fA-F]{3,8}\\b|rgb\\(|rgba\\(|hsl\\(#', $slice),
            sprintf('%s empty slice MUST use design-token utilities only — no raw colour literals (T7 quiet language).', self::CALENDAR_PATH)
        );
        $this->assertStringContainsString('var(--color-hairline)', $slice, sprintf('%s empty block MUST use the hairline token (quiet shell grammar).', self::CALENDAR_PATH));
        $this->assertStringContainsString('var(--radius-panel)', $slice, sprintf('%s empty block MUST use the --radius-panel token (quiet shell grammar).', self::CALENDAR_PATH));
    }
}
