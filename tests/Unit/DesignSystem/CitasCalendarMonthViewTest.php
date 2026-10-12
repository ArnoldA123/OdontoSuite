<?php

namespace Tests\Unit\DesignSystem;

/**
 * T6 — CitasCalendarMonthViewTest.
 *
 * Structural contract for the redesigned Month view body + the D8 "N más"
 * drill-down to the Day view. Anchored on the `data-calendar-*` hooks and
 * the real behaviour they mark, never on dead class literals:
 *
 *   1) grid anatomy — one quiet hairline panel (`data-calendar-month-grid`),
 *      a weekday header row driven by the SAME `weekDayNames` source as the
 *      week view, and cells bound from the 42-cell `monthDays` grid;
 *   2) chip contract — month chips are the same registry-driven chip as
 *      Day/Week (`data-calendar-appointment` + `selectAppointment` +
 *      `getAppointmentClasses`) and stay click-only: no drag arming, no drop
 *      targets, no click-to-create slots (explicit non-goals of T6);
 *   3) overflow split — the chip cap + "N más" count come from the pure
 *      `splitMonthCellAppointments` helper (unit-tested in Vitest), never
 *      from an inline `slice(0, 3)` / count arithmetic in the component;
 *   4) drill-down — `data-calendar-more="<date>"` (count in
 *      `data-calendar-more-count`) and the day-number header
 *      (`data-calendar-day-link`) jump to the Day view through the shared
 *      `changeView('day')` wiring, parsing the date with LOCAL components
 *      (CITAS-TZ-001: no UTC `new Date('YYYY-MM-DD')`, no `.toISOString()`);
 *   5) quiet tokens — today / out-of-month emphasis is selected in script
 *      helpers from token utilities; the month region carries no raw colour
 *      literals, no inline SVG and no glass chrome.
 *
 * The base class `ModuleAppShellTestCase` enforces the 5 inherited DLR-R
 * rules on CalendarPage.vue via `polishedFileProvider()`; the month-specific
 * assertions live in the methods below.
 *
 * Implementation note: regex delimiters are `#` (NOT `/`) because some
 * patterns contain forward slashes (import paths, API URLs).
 */
class CitasCalendarMonthViewTest extends ModuleAppShellTestCase
{
    /** Calendar page path constant — single source of truth for the absolute path. */
    private const CALENDAR_PATH = '/resources/js/modules/appointments/CalendarPage.vue';

    /** Pure date-math module path constant (D3/T2 extraction, extended by T6). */
    private const DATE_MATH_PATH = '/resources/js/modules/appointments/calendarDateMath.js';

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
        self::assertNotFalse($src, sprintf('%s must be readable.', $path));

        return $src;
    }

    private static function dateMathSource(): string
    {
        $path = dirname(__DIR__, 3) . self::DATE_MATH_PATH;
        $src = is_file($path) ? file_get_contents($path) : false;
        self::assertNotFalse($src, sprintf('%s must be readable.', $path));

        return $src;
    }

    /**
     * Extract the Month view template region (from its marker comment to the
     * details-modal comment that follows it). Scoping keeps the month rules
     * month rules: a legacy literal in the untouched Day/Week/modal regions
     * must not mask a month regression, and vice versa.
     */
    private static function monthSlice(string $src): string
    {
        $start = strpos($src, '<!-- Month View -->');
        $end = strpos($src, '<!-- Appointment Details Modal -->');
        self::assertNotFalse($start, sprintf('%s MUST keep the `<!-- Month View -->` template marker.', self::CALENDAR_PATH));
        self::assertNotFalse($end, sprintf('%s MUST keep the `<!-- Appointment Details Modal -->` template marker.', self::CALENDAR_PATH));
        self::assertGreaterThan($start, $end, 'Month view slice must precede the details modal.');

        return substr($src, $start, $end - $start);
    }

    /**
     * T6 grid anatomy — the month body renders inside one quiet hairline
     * panel, the weekday header reuses the SAME `weekDayNames` source as the
     * week view (Spanish short names, one hook per header cell), and the 42
     * cells bind from the `monthDays` computed that maps the pure
     * `buildMonthDays` grid.
     */
    public function test_month_grid_anatomy_and_weekday_header(): void
    {
        $src = self::calendarSource();
        $slice = self::monthSlice($src);

        $this->assertSame(
            1,
            preg_match('#data-calendar-month-grid#', $slice),
            sprintf('%s month body MUST be one quiet surface behind the `data-calendar-month-grid` hook (T6).', self::CALENDAR_PATH)
        );
        $this->assertSame(
            1,
            preg_match('#style="border: 1px solid var\(--color-hairline\); border-radius: var\(--radius-panel\)"#', $slice),
            sprintf('%s month panel MUST use the canvas hairline + --radius-panel grammar (T6 quiet surface, same as Day/Week).', self::CALENDAR_PATH)
        );

        // Header row: same Spanish short names as the week view, one hook per cell.
        $this->assertSame(
            1,
            preg_match('#v-for="(\w+)\s+in\s+weekDayNames"#', $slice),
            sprintf('%s month weekday header MUST iterate `weekDayNames` — the SAME source as the week view header (T6).', self::CALENDAR_PATH)
        );
        $this->assertSame(
            1,
            preg_match('#data-calendar-month-weekday#', $slice),
            sprintf('%s month weekday header cells MUST expose `data-calendar-month-weekday` (T6 hook).', self::CALENDAR_PATH)
        );

        // Cells: one hook per day, bound from the 42-cell pure grid.
        $this->assertSame(
            1,
            preg_match('#v-for="day\s+in\s+monthDays"#', $slice),
            sprintf('%s month body MUST iterate `monthDays` (T6).', self::CALENDAR_PATH)
        );
        $this->assertSame(
            1,
            preg_match('#:data-calendar-month-day="day\.date"#', $slice),
            sprintf('%s month cells MUST expose `:data-calendar-month-day="day.date"` (T6 hook, one per cell).', self::CALENDAR_PATH)
        );
        $this->assertSame(
            1,
            preg_match_all('#buildMonthDays\(currentDate\.value, now\)#', $src),
            sprintf('%s `monthDays` computed MUST keep delegating the 42-cell grid to the pure `buildMonthDays` (D3 contract preserved under T6).', self::CALENDAR_PATH)
        );
    }

    /**
     * T6 chip contract — month chips are the SAME registry-driven chip as
     * the Day/Week grids: `data-calendar-appointment` hook, details modal via
     * `selectAppointment`, status accent via `getAppointmentClasses`, and a
     * compact time (tnum) + patient-name content. Click-only: the T5 drag
     * wiring and the T4 click-to-create slots stay Day/Week-only.
     */
    public function test_month_chips_registry_driven_and_click_only(): void
    {
        $src = self::calendarSource();
        $slice = self::monthSlice($src);

        $this->assertSame(
            1,
            preg_match('#:data-calendar-appointment="appointment\.id"#', $slice),
            sprintf('%s month chips MUST carry `:data-calendar-appointment="appointment.id"` (T6; same hook as Day/Week).', self::CALENDAR_PATH)
        );
        $this->assertSame(
            1,
            preg_match('#@click="selectAppointment\(appointment\)"#', $slice),
            sprintf('%s month chips MUST open the details modal via `selectAppointment(appointment)` (T6).', self::CALENDAR_PATH)
        );
        $this->assertSame(
            1,
            preg_match('#:class="getAppointmentClasses\(appointment\)"#', $slice),
            sprintf('%s month chips MUST derive status styling from the registry via `getAppointmentClasses` (T6; no template colour map).', self::CALENDAR_PATH)
        );

        // Compact chip content: time with tnum + patient name.
        $this->assertSame(
            1,
            preg_match('#formatTime\(appointment\.scheduled_at\)#', $slice),
            sprintf('%s month chips MUST show the appointment time via `formatTime` (T6 compact anatomy).', self::CALENDAR_PATH)
        );
        $this->assertSame(
            1,
            preg_match('#appointment\.patient\?\.first_name#', $slice),
            sprintf('%s month chips MUST show the patient name (T6 compact anatomy).', self::CALENDAR_PATH)
        );

        // Month view is explicitly drag-free and click-to-create-free (T5/T6
        // scope decisions): none of the Day/Week interaction wiring leaks in.
        foreach ([
            'data-calendar-draggable' => 'drag source hook',
            'data-calendar-drag-state' => 'drag active marker',
            'data-calendar-drop-target' => 'drop target hook',
            'data-calendar-drop-active' => 'drop hover marker',
            '@pointerdown' => 'drag arming',
            'data-calendar-slot' => 'click-to-create slot hook',
            'openSlotCreate' => 'click-to-create handler',
        ] as $needle => $label) {
            $this->assertSame(
                0,
                preg_match('#' . preg_quote($needle, '#') . '#', $slice),
                sprintf(
                    '%s month body MUST NOT leak the Day/Week `%s` (`%s`) — month chips are click-only, no drag, no click-to-create (T5/T6 scope).',
                    self::CALENDAR_PATH,
                    $label,
                    $needle
                )
            );
        }
    }

    /**
     * T6 overflow split — the chip cap and the "N más" count come from the
     * pure `splitMonthCellAppointments` helper (MONTH_CELL_CHIP_LIMIT lives
     * in the module and is unit-tested in Vitest); the component keeps no
     * inline `slice(0, 3)` / `count - 3` arithmetic.
     */
    public function test_month_overflow_split_delegated_to_pure_helper(): void
    {
        $src = self::calendarSource();
        $math = self::dateMathSource();

        $this->assertSame(
            1,
            preg_match("#import \\{[^}]*\\bsplitMonthCellAppointments\\b[^}]*\\} from '\\./calendarDateMath'#s", $src),
            sprintf('%s MUST import `splitMonthCellAppointments` from the pure `./calendarDateMath` module (T6: testable without a DOM).', self::CALENDAR_PATH)
        );
        $this->assertSame(
            1,
            preg_match('#splitMonthCellAppointments\(dayAppointments\)#', $src),
            sprintf('%s `monthDays` computed MUST delegate the chip-cap/overflow split to `splitMonthCellAppointments` (T6).', self::CALENDAR_PATH)
        );
        $this->assertSame(
            0,
            preg_match('#slice\(0,\s*3\)|appointmentCount\s*-\s*3#', $src),
            sprintf('%s MUST NOT keep inline chip-cap arithmetic (`slice(0, 3)` / `count - 3`) — the cap belongs to the pure helper (T6).', self::CALENDAR_PATH)
        );

        // The pure module owns the cap constant and stays presentation-free.
        $this->assertSame(
            1,
            preg_match('#export const MONTH_CELL_CHIP_LIMIT = 3#', $math),
            sprintf('%s MUST export `MONTH_CELL_CHIP_LIMIT = 3` as the single source of the month chip cap (T6 / D8).', self::DATE_MATH_PATH)
        );
        $this->assertSame(
            1,
            preg_match('#export function splitMonthCellAppointments\(#', $math),
            sprintf('%s MUST export `splitMonthCellAppointments` (T6 pure helper).', self::DATE_MATH_PATH)
        );
        $this->assertDoesNotMatchRegularExpression(
            '#bg-|text-|ring-|border-|\\#[0-9a-fA-F]{3,8}\\b#',
            $math,
            sprintf('%s MUST stay presentation-free (T6 helper carries the split, not the styling).', self::DATE_MATH_PATH)
        );
    }

    /**
     * T6 drill-down contract (D8) — "N más" (`data-calendar-more="<date>"`,
     * count in `data-calendar-more-count`) and the day-number header
     * (`data-calendar-day-link`) both jump to the Day view via
     * `drillDownToDate`, which parses the date with LOCAL components and
     * reuses the shared `changeView('day')` wiring (date set BEFORE the
     * switch so the reloaded range is the target day).
     */
    public function test_more_drill_down_switches_to_day_view(): void
    {
        $src = self::calendarSource();
        $slice = self::monthSlice($src);

        $this->assertSame(
            1,
            preg_match('#:data-calendar-more="day\.date"#', $slice),
            sprintf('%s "N más" control MUST expose `data-calendar-more="<date>"` (T6/D8 hook).', self::CALENDAR_PATH)
        );
        $this->assertSame(
            1,
            preg_match('#:data-calendar-more-count="day\.overflowCount"#', $slice),
            sprintf('%s "N más" control MUST expose the remaining count via `data-calendar-more-count` (T6/D8 hook).', self::CALENDAR_PATH)
        );
        $this->assertSame(
            1,
            preg_match('#:data-calendar-day-link="day\.date"#', $slice),
            sprintf('%s month day-number header MUST expose `data-calendar-day-link="<date>"` (T6 drill-down affordance).', self::CALENDAR_PATH)
        );
        $this->assertSame(
            2,
            preg_match_all('#@click="drillDownToDate\(day\.date\)"#', $slice),
            sprintf('%s BOTH the day-number header and the "N más" control MUST drill down via `drillDownToDate(day.date)` (T6/D8).', self::CALENDAR_PATH)
        );

        // The wiring: local-component parse → set current date → shared
        // changeView('day') (which reloads the appointments for the day range).
        $this->assertSame(
            1,
            preg_match(
                "#const drillDownToDate = dateStr => \\{\\s*const \\[year, month, day\\] = dateStr\\.split\\('-'\\)\\.map\\(Number\\)\\s*currentDate\\.value = new Date\\(year, month - 1, day\\)\\s*changeView\\('day'\\)\\s*\\}#",
                $src
            ),
            sprintf(
                '%s `drillDownToDate` MUST parse the date from LOCAL components (no UTC `new Date(\'YYYY-MM-DD\')`, CITAS-TZ-001) and '
                . 'reuse `changeView(\'day\')` AFTER setting `currentDate` (T6/D8 wiring).',
                self::CALENDAR_PATH
            )
        );
    }

    /**
     * T6 quiet-token contract — today/out-of-month emphasis comes from the
     * script helpers selecting token utilities (accent ramp for today,
     * theme surface/secondary for quiet states); the month region carries no
     * raw colour literals (hex/rgb), no inline SVG and no glass chrome. The
     * old hardcoded `bg-systemBlue-50 border-systemBlue-200` today map is
     * gone file-wide.
     */
    public function test_month_emphasis_token_driven_and_quiet(): void
    {
        $src = self::calendarSource();
        $slice = self::monthSlice($src);

        // Emphasis is selected in script helpers, not an inline class map.
        $this->assertSame(
            1,
            preg_match('#:class="monthCellClasses\(day\)"#', $slice),
            sprintf('%s month cells MUST bind their background via `monthCellClasses(day)` (T6: one token-driven helper, no inline colour map).', self::CALENDAR_PATH)
        );
        $this->assertSame(
            1,
            preg_match('#:class="monthDayNumberClasses\(day\)"#', $slice),
            sprintf('%s month day numbers MUST bind via `monthDayNumberClasses(day)` (T6).', self::CALENDAR_PATH)
        );
        $this->assertSame(
            1,
            preg_match("#if \(day\.isToday\) return 'bg-accent-50'#", $src),
            sprintf('%s `monthCellClasses` MUST highlight today with the accent token background (`bg-accent-50`) (T6, token set only).', self::CALENDAR_PATH)
        );
        $this->assertSame(
            1,
            preg_match("#if \(day\.isToday\) return 'bg-accent-500 text-white font-semibold'#", $src),
            sprintf('%s `monthDayNumberClasses` MUST highlight today with the accent token pill (`bg-accent-500`) (T6).', self::CALENDAR_PATH)
        );
        $this->assertSame(
            1,
            preg_match("#if \(!day\.isCurrentMonth\) return 'bg-theme-surface'#", $src),
            sprintf('%s out-of-month cells MUST be quiet via the theme surface token (T6).', self::CALENDAR_PATH)
        );

        // The legacy hardcoded today map is gone file-wide.
        $this->assertSame(
            0,
            preg_match('#bg-systemBlue-50 border-systemBlue-200#', $src),
            sprintf('%s MUST NOT keep the old hardcoded month today map `bg-systemBlue-50 border-systemBlue-200` (T6/D2: registry + tokens only).', self::CALENDAR_PATH)
        );

        // No raw colour literals, no inline SVG, no glass chrome in the month region.
        $this->assertSame(
            0,
            preg_match('#\\#[0-9a-fA-F]{3,8}\\b|rgb\\(|rgba\\(|hsl\\(#', $slice),
            sprintf('%s month region MUST use design-token utility classes only — no raw colour literals (T6).', self::CALENDAR_PATH)
        );
        $this->assertSame(
            0,
            preg_match('#<svg#', $slice),
            sprintf('%s month region MUST NOT carry inline SVGs (T6: heroicons only, no hand-drawn art).', self::CALENDAR_PATH)
        );
        $this->assertSame(
            0,
            preg_match('#variant\s*=\s*["\']glass["\']#', $slice),
            sprintf('%s month region MUST NOT carry `UiCard variant="glass"` chrome (T6 / D1).', self::CALENDAR_PATH)
        );
    }
}
