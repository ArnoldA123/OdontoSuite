<?php

namespace Tests\Unit\DesignSystem;

/**
 * PR-citas-02 — CitasCalendarAppShellTest.
 *
 * Asserts CITAS-CAL-001 (7-value status legend, registry-driven since the
 * T3 calendar-shell redesign) + CITAS-CAL-002 (no hover-lift / no
 * border-theme / no bg-primary-50 today) + CITAS-RT-001 (Echo
 * `appointments` channel subscription preserved) + WS pill preservation
 * for the single `CalendarPage.vue` file, plus the T3 shell contract
 * (`data-calendar-*` hooks + hero surface grammar).
 *
 * The base class `ModuleAppShellTestCase` enforces the 5 inherited DLR-R
 * rules (canvas token, no `border-theme`, focus ring, no `<style scoped>`,
 * no legacy focus-ring aliases) via `polishedFileProvider()`. This subclass
 * adds the calendar-specific rule assertions below.
 *
 * Per CITAS-CON-001, the reactivity contract of the `<script>` block is
 * preserved across the redesign: the `useEcho` `appointments` channel
 * subscription `.listen(...)` + `echo.leave(...)` stays byte-for-byte and
 * the pinned signatures survive (cross-checked by
 * `CitasNegativeSpaceRulesTest::test_script_block_reactivity_signature_preserved`).
 * The redesign's script delta is limited to the hero/legend glue.
 *
 * Contract-change note (T3): the legend row is now driven by the
 * `appointmentStatus.js` registry (T2) — labels/variants come from the
 * module and the template hardcodes neither. Assertions that pinned the
 * old literal `<UiStatusBadge variant="..." label="..." data-status="...">`
 * row were repointed at the new registry contract + `data-calendar-*`
 * hooks (see the per-test docblocks); no rule was dropped.
 *
 * Contract-change note (T4): the Day/Week view bodies were rebuilt in the
 * quiet shell grammar. New pinned contracts below: one shared slot-lookup
 * helper (`appointmentsForSlot`), click-to-create slots as gated
 * `<button>` hit areas with `data-calendar-slot` hooks + modal prefill,
 * and `data-calendar-appointment` chips driven only by the registry. The
 * shared content chrome dropped `UiCard variant="glass"` for the quiet
 * canvas + hairline + --radius-panel + --elevation-2 surface
 * (`data-calendar-shell="grid"`).
 *
 * Contract-change note (T6): the Month view body was rebuilt in the quiet
 * shell grammar (month-specific contract pinned by the new
 * `CitasCalendarMonthViewTest`). The chip-hook counts above went 2 → 3
 * because the Month grid now renders the same registry-driven chip as
 * Day/Week; no rule was dropped and the drag wiring stays Day/Week-only
 * (the T5 contract is untouched).
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

    /** Registry path constant — the T2 single source of truth for status
     *  presentation (labels + badge variants + the enum key list). */
    private const REGISTRY_PATH = '/resources/js/modules/appointments/appointmentStatus.js';

    /** @return array<int, string> */
    protected static function polishedFiles(): array
    {
        return [
            dirname(__DIR__, 3).self::CALENDAR_PATH,
        ];
    }

    /**
     * CITAS-CAL-001 — the legend MUST cover all 7 status enum values.
     *
     * Contract updated for the T3 calendar-shell redesign: the legend row
     * (`data-calendar-legend`) is DRIVEN by the `appointmentStatus.js`
     * registry (T2), so the enum values can no longer be pinned as literal
     * `data-status="X"` attributes on hardcoded `<UiStatusBadge>` tags. The
     * 7-value guarantee is repointed at its two new load-bearing points:
     *
     *   1) the registry MUST define every enum key (source of truth), and
     *   2) the legend MUST iterate the registry key list — `legendStatuses`
     *      = `APPOINTMENT_STATUS_KEYS` minus `in_consultation`, the twin of
     *      the legacy `in_progress` DB value in the registry — binding
     *      `:data-status` from the loop key, so the rendered pills still
     *      carry exactly the 7 pinned values.
     */
    public function test_calendar_status_legend_renders_all_seven_enum_values(): void
    {
        $path = dirname(__DIR__, 3).self::CALENDAR_PATH;
        $registryPath = dirname(__DIR__, 3).self::REGISTRY_PATH;
        $src = self::readSource($path);
        $this->assertNotNull($src, sprintf('%s must be readable.', $path));
        $registrySrc = self::readSource($registryPath);
        $this->assertNotNull($registrySrc, sprintf('%s must be readable.', $registryPath));

        $requiredEnumValues = [
            'scheduled',
            'confirmed',
            'in_progress',
            'completed',
            'cancelled',
            'no_show',
            'rescheduled',
        ];

        // 1) the registry (T2 source of truth) defines every pinned key.
        foreach ($requiredEnumValues as $enum) {
            $this->assertTrue(
                (bool) preg_match(
                    '#\b'.preg_quote($enum, '#').'\s*:\s*\{#',
                    $registrySrc
                ),
                sprintf(
                    '%s MUST define the status enum key `%s` in APPOINTMENT_STATUSES (CITAS-CAL-001). '
                    .'The 7-value legend is the load-bearing bug fix; the registry is its source of truth.',
                    $registryPath,
                    $enum
                )
            );
        }

        // 2) the legend row exists as the stable hook on the content card.
        $this->assertSame(
            1,
            preg_match('#data-calendar-legend#', $src),
            sprintf(
                '%s MUST keep the `data-calendar-legend` hook on the legend row (CITAS-CAL-001 / T3 shell contract).',
                $path
            )
        );

        // 3) the legend list derives from the registry keys minus the
        //    documented alias twin — not from an inline literal list.
        $this->assertSame(
            1,
            preg_match(
                "#legendStatuses\s*=\s*APPOINTMENT_STATUS_KEYS\.filter\(\s*key\s*=>\s*key\s*!==\s*['\"]in_consultation['\"]\s*\)#",
                $src
            ),
            sprintf(
                '%s MUST derive `legendStatuses` from `APPOINTMENT_STATUS_KEYS` excluding only the '
                .'`in_consultation` alias of `in_progress` (CITAS-CAL-001). The legend shows one pill '
                .'per pinned enum value; a different filter silently changes the 7-value legend.',
                $path
            )
        );

        // 4) the pills bind `:data-status` from the loop key.
        $this->assertSame(
            1,
            preg_match('#v-for\s*=\s*"\w+\s+in\s+legendStatuses"#', $src),
            sprintf(
                '%s MUST iterate `legendStatuses` in the legend row (CITAS-CAL-001).',
                $path
            )
        );
        $this->assertSame(
            1,
            preg_match('#:data-status\s*=\s*"\w+"#', $src),
            sprintf(
                '%s MUST bind `:data-status` from the legend loop key (CITAS-CAL-001).',
                $path
            )
        );
    }

    /**
     * CITAS-CAL-001 — the legend pills MUST consume the shared pill
     * primitive with registry-driven presentation (the dashboard's single
     * pill system: `<UiBadge :variant="..." size="sm">` + label slot).
     *
     * Contract updated for the T3 redesign (was:
     * `test_calendar_uses_ui_status_badge_for_legend`, which counted ≥ 7
     * literal `<UiStatusBadge variant="...">` tags): the old hardcoded
     * legend row is gone by design, so the rule repointed is the one that
     * mattered — no hardcoded variant/label literal on a legend pill. The
     * variant MUST be bound from `statusBadgeVariant` and the label MUST
     * come from `statusLabel` (both from the T2 registry).
     */
    public function test_calendar_legend_pills_are_registry_driven(): void
    {
        $path = dirname(__DIR__, 3).self::CALENDAR_PATH;
        $src = self::readSource($path);
        $this->assertNotNull($src, sprintf('%s must be readable.', $path));

        // Pill primitive + registry-bound variant.
        $this->assertSame(
            1,
            preg_match('#<UiBadge\b[^>]*:variant\s*=\s*"statusBadgeVariant\(\w+\)"#', $src),
            sprintf(
                '%s legend MUST render pills via `<UiBadge :variant="statusBadgeVariant(...)">` '
                .'(CITAS-CAL-001). Variants come from the appointmentStatus registry, not the template.',
                $path
            )
        );

        // Label from the registry.
        $this->assertSame(
            1,
            preg_match('#\{\{\s*statusLabel\(\w+\)\s*\}\}#', $src),
            sprintf(
                '%s legend MUST render pill labels via `{{ statusLabel(...) }}` (CITAS-CAL-001). '
                .'Labels come from the appointmentStatus registry, not the template.',
                $path
            )
        );

        // No hardcoded variant literal on any pill in the legend row.
        $this->assertSame(
            0,
            preg_match('#<UiBadge\b[^>]*(?<!:)variant\s*=\s*["\']#', $src),
            sprintf(
                '%s MUST NOT hardcode a variant literal on a legend pill (CITAS-CAL-001). '
                .'Bind `:variant` from the appointmentStatus registry instead.',
                $path
            )
        );
    }

    /**
     * T3 shell contract — the hero band carries the stable `data-calendar-*`
     * hooks on a real landmark and uses the dashboard hero surface grammar
     * (canvas + hairline + --radius-panel + --elevation-2 + decorative
     * DentalMotifLayer hero variant behind the content).
     */
    public function test_calendar_shell_hero_landmarks(): void
    {
        $path = dirname(__DIR__, 3).self::CALENDAR_PATH;
        $src = self::readSource($path);
        $this->assertNotNull($src, sprintf('%s must be readable.', $path));

        $this->assertSame(
            1,
            preg_match('#<(header|section|main)\b[^>]*data-calendar-shell\s*=\s*"hero"#', $src),
            sprintf(
                '%s MUST expose `data-calendar-shell="hero"` on a real landmark element (T3 shell contract).',
                $path
            )
        );

        foreach (['--color-canvas', '--color-hairline', '--radius-panel', '--elevation-2'] as $token) {
            $this->assertSame(
                1,
                preg_match('#var\('.preg_quote($token, '#').'\)#', $src),
                sprintf(
                    '%s hero MUST consume the %s token (dashboard hero grammar).',
                    $path,
                    $token
                )
            );
        }

        $this->assertSame(
            1,
            preg_match('#<DentalMotifLayer\b[^>]*variant\s*=\s*"hero"#', $src),
            sprintf(
                '%s hero MUST carry the decorative `<DentalMotifLayer variant="hero">` layer (T3 shell contract).',
                $path
            )
        );
    }

    /**
     * T3 shell contract — toolbar wiring preserved: the segmented switcher
     * exposes one `data-calendar-view` hook per view, the navigation exposes
     * `prev|today|next`, and the primary CTA keeps its permission gate +
     * `openNewAppointmentModal` handler (the behaviour the view bodies,
     * modals and wizard depend on).
     */
    public function test_calendar_shell_toolbar_wiring_preserved(): void
    {
        $path = dirname(__DIR__, 3).self::CALENDAR_PATH;
        $src = self::readSource($path);
        $this->assertNotNull($src, sprintf('%s must be readable.', $path));

        foreach (['day', 'week', 'month'] as $view) {
            $this->assertSame(
                1,
                preg_match('#data-calendar-view\s*=\s*"'.$view.'"#', $src),
                sprintf(
                    '%s MUST expose `data-calendar-view="%s"` on the %s segment (T3 shell contract).',
                    $path,
                    $view,
                    $view
                )
            );
            $this->assertSame(
                1,
                preg_match("#@click\s*=\s*\"changeView\\('".$view."'\\)\"#", $src),
                sprintf(
                    '%s segment `%s` MUST stay wired to `changeView` (T3 shell contract).',
                    $path,
                    $view
                )
            );
        }

        foreach (['prev', 'today', 'next'] as $nav) {
            $this->assertSame(
                1,
                preg_match('#data-calendar-nav\s*=\s*"'.$nav.'"#', $src),
                sprintf(
                    '%s MUST expose `data-calendar-nav="%s"` (T3 shell contract).',
                    $path,
                    $nav
                )
            );
        }

        foreach (['previousPeriod', 'goToToday', 'nextPeriod'] as $handler) {
            $this->assertSame(
                1,
                preg_match('#@click\s*=\s*"'.preg_quote($handler, '#').'"#', $src),
                sprintf(
                    '%s navigation MUST stay wired to `%s` (T3 shell contract).',
                    $path,
                    $handler
                )
            );
        }

        $this->assertSame(
            1,
            preg_match('#data-calendar-action\s*=\s*"new-appointment"#', $src),
            sprintf(
                '%s MUST expose `data-calendar-action="new-appointment"` on the primary CTA (T3 shell contract).',
                $path
            )
        );
        $this->assertSame(
            1,
            preg_match('#v-if\s*=\s*"can\.createAppointment\?\.value"#', $src),
            sprintf(
                '%s primary CTA MUST keep the `can.createAppointment` permission gate verbatim (T3 shell contract).',
                $path
            )
        );
        $this->assertSame(
            1,
            preg_match('#@click\s*=\s*"openNewAppointmentModal"#', $src),
            sprintf(
                '%s primary CTA MUST stay wired to `openNewAppointmentModal` (T3 shell contract).',
                $path
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
        $path = dirname(__DIR__, 3).self::CALENDAR_PATH;
        $src = self::readSource($path);
        $this->assertNotNull($src, sprintf('%s must be readable.', $path));

        $this->assertSame(
            0,
            preg_match('#(?<![\w-])border-theme(?![\w-])#', $src),
            sprintf(
                '%s MUST NOT keep the legacy `border-theme` literal anywhere in the template (CITAS-CAL-002 / DLR-R-002). '
                .'Use the `border-hairline` / `--color-hairline` token instead.',
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
        $path = dirname(__DIR__, 3).self::CALENDAR_PATH;
        $src = self::readSource($path);
        $this->assertNotNull($src, sprintf('%s must be readable.', $path));

        $this->assertSame(
            0,
            preg_match('#(?<![\w-])hover-lift(?![\w-])#', $src),
            sprintf(
                '%s MUST NOT keep the legacy `hover-lift` affordance on appointment blocks (CITAS-CAL-002). '
                .'The token-aligned hover affordance is `<UiCard clickable>` (per design §3.2).',
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
        $path = dirname(__DIR__, 3).self::CALENDAR_PATH;
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
                preg_match('#(?<![\w-])'.preg_quote($alias, '#').'(?![\w-])#', $src),
                sprintf(
                    '%s MUST NOT keep the legacy status-pill colour class `%s` (CITAS-CAL-002 / DLR-R-009). '
                    .'Replace it with `<UiStatusBadge variant="...">` or the tokenised system*-* ramp.',
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
        $path = dirname(__DIR__, 3).self::CALENDAR_PATH;
        $src = self::readSource($path);
        $this->assertNotNull($src, sprintf('%s must be readable.', $path));

        $this->assertSame(
            1,
            preg_match('#>\s*En vivo\s*<#', $src),
            sprintf(
                '%s MUST keep the "En vivo" WebSocket pill text verbatim (CITAS-RT-001). '
                .'The realtime subscription depends on the pill being visible to the receptionist.',
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
        $path = dirname(__DIR__, 3).self::CALENDAR_PATH;
        $src = self::readSource($path);
        $this->assertNotNull($src, sprintf('%s must be readable.', $path));

        // 1) The `appointments` channel is fetched via the
        // `privateChannel('appointments')` helper (#249: the server rejects
        // unauthenticated subscribers).
        $this->assertTrue(
            (bool) preg_match("#privateChannel\s*\(\s*['\"]appointments['\"]\s*\)#", $src),
            sprintf(
                '%s MUST keep `privateChannel(\'appointments\')` subscription (CITAS-RT-001). '
                .'The Echo channel name is the canonical realtime pipe for appointment events.',
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
                    '#\.listen\s*\(\s*[\'"]'.preg_quote($event, '#').'[\'"]#',
                    $src
                ),
                sprintf(
                    '%s MUST keep the `.listen("%s")` event listener (CITAS-RT-001). '
                    .'Realtime sync depends on the three canonical appointment events.',
                    $path,
                    $event
                )
            );
        }

        // 3) The `onUnmounted` hook calls `echo.leave('private-appointments')`:
        // Echo's leave() does not add the private prefix (#249).
        $this->assertTrue(
            (bool) preg_match(
                "#echo\.leave\s*\(\s*['\"]private-appointments['\"]\s*\)#",
                $src
            ),
            sprintf(
                '%s MUST keep `echo.leave(\'private-appointments\')` in the `onUnmounted` hook (CITAS-RT-001). '
                .'Without the leave call, switching routes leaks an Echo subscription.',
                $path
            )
        );
    }

    /**
     * T4 grid contract — the Day and Week bodies MUST share ONE slot-lookup
     * helper. The duplicated `getAppointmentsForHour` /
     * `getAppointmentsForDayAndHour` pair (identical except for the date
     * input) is gone by design: both grids call
     * `appointmentsForSlot(dateStr, hour)`.
     */
    public function test_day_week_grids_share_one_slot_lookup_helper(): void
    {
        $path = dirname(__DIR__, 3).self::CALENDAR_PATH;
        $src = self::readSource($path);
        $this->assertNotNull($src, sprintf('%s must be readable.', $path));

        $this->assertSame(
            0,
            preg_match('#getAppointmentsForHour|getAppointmentsForDayAndHour#', $src),
            sprintf(
                '%s MUST NOT keep the duplicated `getAppointmentsForHour` / `getAppointmentsForDayAndHour` pair (T4). '
                .'Both grids share the single `appointmentsForSlot(dateStr, hour)` helper.',
                $path
            )
        );

        $this->assertSame(
            1,
            preg_match_all('#const appointmentsForSlot = #', $src),
            sprintf('%s MUST define exactly one `appointmentsForSlot` helper (T4).', $path)
        );

        $this->assertGreaterThanOrEqual(
            2,
            preg_match_all('#appointmentsForSlot\(#', $src),
            sprintf(
                '%s MUST call `appointmentsForSlot(...)` from both the Day and the Week grid (T4).',
                $path
            )
        );
    }

    /**
     * T4 click-to-create contract — every slot hit area is a real `<button>`
     * (keyboard: Enter/Space) gated by the same `can.createAppointment`
     * permission as the "Nueva cita" CTA, carrying the
     * `data-calendar-slot="<date>-<hour>"` hook (plus the
     * `data-calendar-slot-day` column hook in the Week grid) and wiring
     * `openSlotCreate`. The prefill reaches `NewAppointmentModal` through the
     * existing `initialDate` prop (`:initial-date="newAppointmentPrefill"`).
     */
    public function test_click_to_create_slot_contract(): void
    {
        $path = dirname(__DIR__, 3).self::CALENDAR_PATH;
        $src = self::readSource($path);
        $this->assertNotNull($src, sprintf('%s must be readable.', $path));

        // The gate sits ON the button tag that carries the slot hook: when
        // the user cannot create appointments the hit area does not render.
        $this->assertSame(
            2,
            preg_match_all(
                '#<button\b[^>]*v-if\s*=\s*"can\.createAppointment\?\.value"[^>]*:data-calendar-slot\s*=#s',
                $src
            ),
            sprintf(
                '%s Day and Week slot hit areas MUST be real `<button>` elements gated by `can.createAppointment?.value` '
                .'(T4 click-to-create; Enter/Space come from native button semantics).',
                $path
            )
        );

        // Hook format contract: data-calendar-slot="<date>-<hour>".
        $this->assertSame(
            2,
            preg_match_all('#:data-calendar-slot\s*=\s*"`\$\{[\w.]+\}-\$\{hour\}`"#', $src),
            sprintf(
                '%s MUST bind `:data-calendar-slot="`${date}-${hour}`"` in both the Day and the Week grid (T4).',
                $path
            )
        );

        $this->assertSame(
            1,
            preg_match_all('#:data-calendar-slot-day\s*=\s*"day\.date"#', $src),
            sprintf(
                '%s Week grid MUST expose the `data-calendar-slot-day` day-column hook (T4).',
                $path
            )
        );

        $this->assertSame(
            2,
            preg_match_all('#@click\s*=\s*"openSlotCreate\(#', $src),
            sprintf(
                '%s slot hit areas MUST open the create flow via `openSlotCreate` in both grids (T4).',
                $path
            )
        );

        $this->assertSame(
            1,
            preg_match('#:initial-date\s*=\s*"newAppointmentPrefill"#', $src),
            sprintf(
                '%s MUST pass the slot prefill to `NewAppointmentModal` via `:initial-date` (T4; '
                .'the modal already owns the `initialDate` prop).',
                $path
            )
        );
    }

    /**
     * T4 chip contract — appointment chips carry `data-calendar-appointment`,
     * open the existing details modal via `selectAppointment`, and derive
     * their status styling ONLY from the appointmentStatus registry
     * (`getAppointmentClasses` → `APPOINTMENT_CHIP_BASE_CLASSES` +
     * `statusChipClasses`). No hardcoded system-color class map on the chips.
     *
     * Contract update (T6): counts 2 → 3 — the redesigned Month view renders
     * the same registry-driven chip as the Day and Week grids (compact,
     * `selectAppointment` → details modal, `getAppointmentClasses` for the
     * status accent). The T4 intent is unchanged and now covers all three
     * grids: one chip hook per grid, no hardcoded colour map anywhere.
     */
    public function test_appointment_chips_hooked_and_registry_driven(): void
    {
        $path = dirname(__DIR__, 3).self::CALENDAR_PATH;
        $src = self::readSource($path);
        $this->assertNotNull($src, sprintf('%s must be readable.', $path));

        $this->assertSame(
            3,
            preg_match_all('#:data-calendar-appointment\s*=\s*"appointment\.id"#', $src),
            sprintf(
                '%s Day, Week and Month chips MUST carry `:data-calendar-appointment="appointment.id"` (T4 + T6: one chip hook per grid).',
                $path
            )
        );

        $this->assertSame(
            3,
            preg_match_all('#@click\s*=\s*"selectAppointment\(appointment\)"#', $src),
            sprintf(
                '%s chips MUST keep the `selectAppointment(appointment)` wiring to the details modal in all three grids (T4 + T6).',
                $path
            )
        );

        $this->assertSame(
            3,
            preg_match_all('#:class\s*=\s*"getAppointmentClasses\(appointment\)"#', $src),
            sprintf(
                '%s chips MUST bind `:class="getAppointmentClasses(appointment)"` in all three grids — status styling comes '
                .'from the appointmentStatus registry, never from a template class map (T4 + T6).',
                $path
            )
        );

        $this->assertSame(
            1,
            preg_match('#statusChipClasses\(appointment\.status\)#', $src),
            sprintf(
                '%s `getAppointmentClasses` MUST delegate to `statusChipClasses(appointment.status)` (T4).',
                $path
            )
        );
    }

    /**
     * T4 quiet-chrome contract — the shared content surface drops the legacy
     * `UiCard variant="glass"` chrome (D1: old language) and keeps the quiet
     * shell grammar pinned by the `data-calendar-shell="grid"` hook (the
     * canvas / hairline / --radius-panel / --elevation-2 tokens are asserted
     * file-wide by `test_calendar_shell_hero_landmarks`).
     */
    public function test_calendar_content_surface_drops_glass_chrome(): void
    {
        $path = dirname(__DIR__, 3).self::CALENDAR_PATH;
        $src = self::readSource($path);
        $this->assertNotNull($src, sprintf('%s must be readable.', $path));

        $this->assertSame(
            0,
            preg_match('#variant\s*=\s*["\']glass["\']#', $src),
            sprintf(
                '%s MUST NOT keep `UiCard variant="glass"` chrome over the calendar grids (T4 / D1). '
                .'The content surface uses the quiet canvas + hairline + --radius-panel grammar.',
                $path
            )
        );

        $this->assertSame(
            1,
            preg_match('#data-calendar-shell\s*=\s*"grid"#', $src),
            sprintf(
                '%s MUST expose `data-calendar-shell="grid"` on the quiet content surface (T4).',
                $path
            )
        );
    }

    private static function readSource(string $path): ?string
    {
        if (! is_file($path)) {
            return null;
        }
        $src = file_get_contents($path);

        return $src === false ? null : $src;
    }
}
