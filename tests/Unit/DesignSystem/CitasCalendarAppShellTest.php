<?php

namespace Tests\Unit\DesignSystem;

/**
 * PR-citas-02 — CitasCalendarAppShellTest.
 *
 * Asserts CITAS-CAL-001 (7-value status legend via <UiStatusBadge>) +
 * CITAS-CAL-002 (no hover-lift / no border-theme / no bg-primary-50 today) +
 * CITAS-RT-001 (Echo `appointments` channel subscription preserved) +
 * WS pill preservation for the single `CalendarPage.vue` file.
 *
 * The base class `ModuleAppShellTestCase` enforces the 5 inherited DLR-R
 * rules (canvas token, no `border-theme`, focus ring, no `<style scoped>`,
 * no legacy focus-ring aliases) via `polishedFileProvider()`. This subclass
 * adds the 6 calendar-specific rule assertions below.
 *
 * Per CITAS-CON-001, the `<script>` block of `CalendarPage.vue` is NEVER
 * edited in this PR; the rule is asserted by
 * `test_calendar_use_echo_appointments_channel_preserved` (the `useEcho`
 * `appointments` channel subscription `.listen(...)` + `echo.leave(...)`
 * stays byte-for-byte).
 *
 * Implementation note: regex delimiters are `#` (NOT `/`) because the path
 * patterns contain forward slashes; using `/` as delimiter would force
 * every `/` in the path to be escaped `\/`, which is brittle and error-prone.
 */
class CitasCalendarAppShellTest extends ModuleAppShellTestCase
{
    /** Calendar page path constant — used by the data provider + the single-file
     *  rules. Keeps a single source of truth for the absolute path. */
    private const CALENDAR_PATH = '/resources/js/modules/appointments/CalendarPage.vue';

    /** @return array<int, string> */
    protected static function polishedFiles(): array
    {
        return [
            dirname(__DIR__, 3) . self::CALENDAR_PATH,
        ];
    }

    /**
     * CITAS-CAL-001 — the legend template MUST reference all 7 status enum
     * values via tokenised primitives (`<UiStatusBadge variant="...">`).
     * Regex-based, order/spacing-tolerant; the rule pins the 7 enum values,
     * not the literal output of one example (per design §6.3).
     *
     * Each of the 7 enum values (`scheduled`, `confirmed`, `in_progress`,
     * `completed`, `cancelled`, `no_show`, `rescheduled`) MUST appear at
     * least once on a `<UiStatusBadge>` in the legend. The legend badge
     * uses the tokenised variant (`info | success | warning | neutral |
     * error`) AND the enum value as a `data-status` attribute (so the
     * enum-to-variant mapping is explicit in the template). The check
     * matches `<UiStatusBadge ... data-status="X">` for each X.
     */
    public function test_calendar_status_legend_renders_all_seven_enum_values(): void
    {
        $path = dirname(__DIR__, 3) . self::CALENDAR_PATH;
        $src = self::readSource($path);
        $this->assertNotNull($src, sprintf('%s must be readable.', $path));

        $requiredEnumValues = [
            'scheduled',
            'confirmed',
            'in_progress',
            'completed',
            'cancelled',
            'no_show',
            'rescheduled',
        ];

        foreach ($requiredEnumValues as $enum) {
            $this->assertTrue(
                (bool) preg_match(
                    '#<UiStatusBadge\b[^>]*data-status\s*=\s*["\']' . preg_quote($enum, '#') . '["\']#',
                    $src
                ),
                sprintf(
                    '%s MUST reference the status enum value `%s` on a `<UiStatusBadge>` '
                    . '(via `data-status="%s"`) in the legend template (CITAS-CAL-001). '
                    . 'The 7-value legend is the load-bearing bug fix per CITAS-CAL-001.',
                    $path,
                    $enum,
                    $enum
                )
            );
        }
    }

    /**
     * CITAS-CAL-001 — the legend MUST consume `<UiStatusBadge variant="...">`
     * for each status. Counted at least 7 references (one per enum value).
     */
    public function test_calendar_uses_ui_status_badge_for_legend(): void
    {
        $path = dirname(__DIR__, 3) . self::CALENDAR_PATH;
        $src = self::readSource($path);
        $this->assertNotNull($src, sprintf('%s must be readable.', $path));

        // At least 7 <UiStatusBadge variant="..."> references (one per enum value).
        $count = preg_match_all(
            '#<UiStatusBadge\b[^>]*variant\s*=\s*["\'][a-z_]+["\']#',
            $src
        );
        $this->assertGreaterThanOrEqual(
            7,
            $count,
            sprintf(
                '%s MUST consume `<UiStatusBadge variant="...">` at least 7 times (one per status enum value) '
                . 'in the legend template (CITAS-CAL-001). Found %d.',
                $path,
                $count
            )
        );
    }

    /**
     * CITAS-CAL-002 (calendar-specific) — the calendar MUST NOT carry the
     * legacy `border-theme` literal anywhere in the template. The base class
     * `ModuleAppShellTestCase::test_no_legacy_border_theme_literal` already
     * pins this rule via `polishedFileProvider()`; this method provides a
     * calendar-specific error message + doubles the assertion in case the
     * inherited rule is overridden in a future refactor.
     */
    public function test_calendar_no_border_theme(): void
    {
        $path = dirname(__DIR__, 3) . self::CALENDAR_PATH;
        $src = self::readSource($path);
        $this->assertNotNull($src, sprintf('%s must be readable.', $path));

        $this->assertSame(
            0,
            preg_match('#(?<![\w-])border-theme(?![\w-])#', $src),
            sprintf(
                '%s MUST NOT keep the legacy `border-theme` literal anywhere in the template (CITAS-CAL-002 / DLR-R-002). '
                . 'Use the `border-hairline` / `--color-hairline` token instead.',
                $path
            )
        );
    }

    /**
     * CITAS-CAL-002 (calendar-specific) — the calendar MUST NOT carry the
     * legacy `hover-lift` affordance on appointment blocks. The token-aligned
     * hover affordance is the `<UiCard clickable>` primitive (per design §2.7
     * + §3.2 CalendarPage surface).
     */
    public function test_calendar_no_hover_lift(): void
    {
        $path = dirname(__DIR__, 3) . self::CALENDAR_PATH;
        $src = self::readSource($path);
        $this->assertNotNull($src, sprintf('%s must be readable.', $path));

        $this->assertSame(
            0,
            preg_match('#(?<![\w-])hover-lift(?![\w-])#', $src),
            sprintf(
                '%s MUST NOT keep the legacy `hover-lift` affordance on appointment blocks (CITAS-CAL-002). '
                . 'The token-aligned hover affordance is `<UiCard clickable>` (per design §3.2).',
                $path
            )
        );
    }

    /**
     * CITAS-CAL-002 (calendar-specific) — the calendar MUST NOT carry legacy
     * status-pill colour classes (`bg-success-100`, `bg-warning-100`,
     * `bg-error-100`, `bg-primary-50`, `bg-primary-100`, `bg-primary-200`,
     * `text-success-700`, `text-warning-700`, `text-error-700`, `bg-accent`,
     * `text-accent`). The token-aligned form is `<UiStatusBadge variant="...">`
     * with tokenised systemGreen/systemYellow/systemRed/systemBlue ramps.
     */
    public function test_calendar_no_legacy_status_pills(): void
    {
        $path = dirname(__DIR__, 3) . self::CALENDAR_PATH;
        $src = self::readSource($path);
        $this->assertNotNull($src, sprintf('%s must be readable.', $path));

        $forbiddenAliases = [
            'bg-success-100',
            'bg-warning-100',
            'bg-error-100',
            'bg-primary-50',
            'bg-primary-100',
            'bg-primary-200',
            'text-success-700',
            'text-warning-700',
            'text-error-700',
            'bg-accent',
            'text-accent',
        ];

        foreach ($forbiddenAliases as $alias) {
            $this->assertSame(
                0,
                preg_match('#(?<![\w-])' . preg_quote($alias, '#') . '(?![\w-])#', $src),
                sprintf(
                    '%s MUST NOT keep the legacy status-pill colour class `%s` (CITAS-CAL-002 / DLR-R-009). '
                    . 'Replace it with `<UiStatusBadge variant="...">` or the tokenised system*-* ramp.',
                    $path,
                    $alias
                )
            );
        }
    }

    /**
     * CITAS-RT-001 (calendar-specific) — the "En vivo" WebSocket pill MUST
     * stay present in the template (the realtime subscription depends on
     * the pill being visible to the receptionist). The check pins the
     * literal Spanish label "En vivo"; if the rollout accidentally drops it,
     * the realtime affordance is invisible to the user.
     */
    public function test_calendar_ws_pill_preserved(): void
    {
        $path = dirname(__DIR__, 3) . self::CALENDAR_PATH;
        $src = self::readSource($path);
        $this->assertNotNull($src, sprintf('%s must be readable.', $path));

        $this->assertSame(
            1,
            preg_match('#>\s*En vivo\s*<#', $src),
            sprintf(
                '%s MUST keep the "En vivo" WebSocket pill text verbatim (CITAS-RT-001). '
                . 'The realtime subscription depends on the pill being visible to the receptionist.',
                $path
            )
        );
    }

    /**
     * CITAS-RT-001 — the `<script>` block MUST keep the `useEcho` `appointments`
     * channel subscription byte-for-byte: `.listen(".appointment.created"...)`,
     * `.listen(".appointment.updated"...)`, `.listen(".appointment.deleted"...)`,
     * and `echo.leave("appointments")` in `onUnmounted`. The rule pins the
     * existing realtime contract; if any PR accidentally removes a listener,
     * the cross-tab realtime sync silently breaks.
     */
    public function test_calendar_use_echo_appointments_channel_preserved(): void
    {
        $path = dirname(__DIR__, 3) . self::CALENDAR_PATH;
        $src = self::readSource($path);
        $this->assertNotNull($src, sprintf('%s must be readable.', $path));

        // 1) The `appointments` channel is fetched via the `channel('appointments')` helper.
        $this->assertTrue(
            (bool) preg_match("#channel\s*\(\s*['\"]appointments['\"]\s*\)#", $src),
            sprintf(
                '%s MUST keep `channel(\'appointments\')` subscription (CITAS-RT-001). '
                . 'The Echo channel name is the canonical realtime pipe for appointment events.',
                $path
            )
        );

        // 2) The three event listeners are present.
        $requiredEvents = [
            '.appointment.created',
            '.appointment.updated',
            '.appointment.deleted',
        ];
        foreach ($requiredEvents as $event) {
            $this->assertTrue(
                (bool) preg_match(
                    '#\.listen\s*\(\s*[\'"]' . preg_quote($event, '#') . '[\'"]#',
                    $src
                ),
                sprintf(
                    '%s MUST keep the `.listen("%s")` event listener (CITAS-RT-001). '
                    . 'Realtime sync depends on the three canonical appointment events.',
                    $path,
                    $event
                )
            );
        }

        // 3) The `onUnmounted` hook calls `echo.leave('appointments')`.
        $this->assertTrue(
            (bool) preg_match(
                "#echo\.leave\s*\(\s*['\"]appointments['\"]\s*\)#",
                $src
            ),
            sprintf(
                '%s MUST keep `echo.leave(\'appointments\')` in the `onUnmounted` hook (CITAS-RT-001). '
                . 'Without the leave call, switching routes leaks an Echo subscription.',
                $path
            )
        );
    }

    private static function readSource(string $path): ?string
    {
        if (!is_file($path)) {
            return null;
        }
        $src = file_get_contents($path);
        return $src === false ? null : $src;
    }
}
