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
 * canvas + hairline + --radius-card-lg + --elevation-2 surface
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
 *
 * Contract-change note (T5a): la superficie abre con una sola banda compacta
 * (`data-calendar-frame` + `<header data-calendar-header>`) en lugar de la
 * tarjeta `PageHeader`, la leyenda pasa a una línea de puntos de 7 px y
 * etiquetas de 10 px, y la barra de tres selects desaparece (pastillas de
 * profesional + selects compactos de ambiente/sede). El hueco agendable del
 * tablero se limita a 30–120 min (`gapIsBookable`).
 *
 * Contract-change note (U3): the calendar no longer opens the clinical
 * consultation. Starting or continuing an attention belongs to `/my-day`,
 * the professional's only clinical door; the calendar keeps reception's
 * custody doors (check-in, announce) and publishes a bridge to My Day for
 * sessions that hold `view.my_day`. The three guards below fail if the
 * wizard entry comes back or if the check-in door disappears.
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
     * CITAS-CAL-001 — la leyenda cubre los cinco estados que se leen en el
     * bloque del calendario: Programada, Confirmada, En espera, En consulta y
     * Completada.
     *
     * T5b: la leyenda de ocho estados ocupaba una línea densa que competía con
     * las pastillas y el «En vivo». Cancelada, No se presentó y Reprogramada se
     * explican solas en el bloque y salen de la leyenda. La garantía se
     * sostiene en dos puntos:
     *
     *   1) el registro `appointmentStatus.js` define cada clave (fuente única),
     *      y
     *   2) la leyenda itera `legendStatuses` = claves del registro filtradas por
     *      los cinco estados canónicos (`LEGEND_STATUS_KEYS`), atando
     *      `:data-status` desde la clave del bucle.
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
            'arrived',
            'in_progress',
            'completed',
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
                '%s MUST keep the `data-calendar-legend` hook on the legend line (CITAS-CAL-001).',
                $path
            )
        );

        // 3) the legend list derives from the registry keys filtered by the
        //    five canonical states — not from an inline literal list.
        $this->assertSame(
            1,
            preg_match(
                "#LEGEND_STATUS_KEYS\s*=\s*\['scheduled',\s*'confirmed',\s*'arrived',\s*'in_progress',\s*'completed'\]#",
                $src
            ),
            sprintf(
                '%s MUST declare the five canonical legend states in `LEGEND_STATUS_KEYS` (T5b).',
                $path
            )
        );
        $this->assertSame(
            1,
            preg_match(
                "#legendStatuses\s*=\s*APPOINTMENT_STATUS_KEYS\.filter\(\s*key\s*=>\s*LEGEND_STATUS_KEYS\.includes\(\s*key\s*\)\s*\)#",
                $src
            ),
            sprintf(
                '%s MUST derive `legendStatuses` from `APPOINTMENT_STATUS_KEYS` filtered by '
                .'`LEGEND_STATUS_KEYS` (CITAS-CAL-001 / T5b: five states, no dense band).',
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
     * CITAS-CAL-001 — la leyenda del registro es una sola línea compacta de
     * puntos y etiquetas (T5a), no una banda de píldoras. La regla que importa
     * se conserva: el color del punto y la etiqueta salen del registro
     * `appointmentStatus.js`, nunca de un literal del template.
     *
     * Contract updated for T5a (was: pills via
     * `<UiBadge :variant="statusBadgeVariant(...)">`): la banda de ocho
     * píldoras medía una banda entera de chrome, así que la leyenda pasó a
     * puntos de 7 px y etiquetas de 10 px al final de la fila de herramientas.
     */
    public function test_calendar_legend_is_a_registry_driven_single_line(): void
    {
        $path = dirname(__DIR__, 3).self::CALENDAR_PATH;
        $src = self::readSource($path);
        $this->assertNotNull($src, sprintf('%s must be readable.', $path));

        // El punto consume la rampa del registro para cada estado.
        $this->assertSame(
            1,
            preg_match('#:class\s*=\s*"statusRailClasses\(\w+\)\.dot"#', $src),
            sprintf(
                '%s legend MUST paint each dot from `statusRailClasses(status).dot` (CITAS-CAL-001). '
                .'The colour comes from the appointmentStatus registry, not the template.',
                $path
            )
        );

        // La etiqueta sale del registro.
        $this->assertSame(
            1,
            preg_match('#\{\{\s*statusLabel\(\w+\)\s*\}\}#', $src),
            sprintf(
                '%s legend MUST render labels via `{{ statusLabel(...) }}` (CITAS-CAL-001). '
                .'Labels come from the appointmentStatus registry, not the template.',
                $path
            )
        );

        // Puntos de 7 px y etiquetas de 10 px: una sola línea.
        $this->assertSame(
            1,
            preg_match('#class="h-\[7px\] w-\[7px\] rounded-full"#', $src),
            sprintf(
                '%s legend dots MUST be 7 px (T5a single-line legend).',
                $path
            )
        );
        $this->assertSame(
            1,
            preg_match('#text-\[10px\] leading-\[14px\]#', $src),
            sprintf(
                '%s legend labels MUST be 10 px on a 14 px line (T5a single-line legend).',
                $path
            )
        );

        // La leyenda no es una banda: vive dentro de la fila de herramientas y
        // no pinta ninguna píldora con variante.
        $tools = strpos($src, 'data-calendar-tools');
        $legendStart = strpos($src, 'data-calendar-legend', (int) $tools);
        $this->assertNotFalse($tools, sprintf('%s MUST keep the tools row.', $path));
        $this->assertNotFalse($legendStart, sprintf('%s MUST keep the legend hook inside the tools row.', $path));
        $legendEnd = strpos($src, '</div>', $legendStart);
        $this->assertNotFalse($legendEnd, sprintf('%s legend line MUST close.', $path));

        $this->assertSame(
            0,
            preg_match(
                '#<UiBadge\b#',
                substr($src, $legendStart, $legendEnd - $legendStart)
            ),
            sprintf(
                '%s MUST NOT paint badge pills in the compact legend (T5a): dots + labels only.',
                $path
            )
        );
    }

    /**
     * T5a shell contract — la superficie abre con una sola banda compacta
     * propia (`data-calendar-frame` + `<header data-calendar-header>`), no con
     * la tarjeta `PageHeader`: los tres controles de chrome que medía la
     * auditoría se reducen a una banda de 125 px declarados. La leyenda vive
     * en la fila de herramientas, no dentro del contenido, y ninguna barra de
     * tres selects queda en el cuerpo.
     */
    public function test_calendar_shell_opens_with_one_compact_band(): void
    {
        $path = dirname(__DIR__, 3).self::CALENDAR_PATH;
        $src = self::readSource($path);
        $this->assertNotNull($src, sprintf('%s must be readable.', $path));

        $this->assertSame(
            1,
            preg_match('#data-calendar-frame#', $src),
            sprintf('%s MUST open with the single compact band (`data-calendar-frame`, T5a).', $path)
        );
        $this->assertSame(
            1,
            preg_match('#<header\b[^>]*data-calendar-header#', $src),
            sprintf('%s MUST keep its compact `<header data-calendar-header>` (T5a).', $path)
        );
        $this->assertSame(
            0,
            preg_match('#<PageHeader\b#', $src),
            sprintf(
                '%s MUST NOT mount the card `PageHeader` any more (T5a: the band is 24/32 with a hairline, no shadow).',
                $path
            )
        );
        $this->assertSame(
            0,
            preg_match('#<UiFilterBar\b#', $src),
            sprintf(
                '%s MUST NOT render the three-select filter bar (T5a: professional pills + compact chair/sede selects).',
                $path
            )
        );
        $this->assertSame(
            1,
            preg_match('#data-calendar-toolbar[^>]*data-calendar-tools#', $src),
            sprintf('%s MUST keep one tools row inside the band (T5a).', $path)
        );
        $this->assertSame(
            0,
            preg_match('#<(header|section|main)\b[^>]*data-calendar-shell\s*=\s*"hero"#', $src),
            sprintf('%s MUST NOT keep a hand-built hero landmark.', $path)
        );
        $this->assertSame(
            0,
            preg_match('#<DentalMotifLayer\b#', $src),
            sprintf(
                '%s MUST NOT paint the dental motif: empty states match the shared primitive.',
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
     * T4 grid contract — the Week body keeps ONE slot-lookup helper and the Day
     * board groups its blocks by resource.
     *
     * Contract change (T5b week blocks): la semana ya no apila píldoras dentro
     * de celdas de una hora; pinta un bloque posicionado por minuto en su
     * columna de día, así que el único lookup es `weekDayAppointments(date)`.
     * El par duplicado `getAppointmentsForHour` / `getAppointmentsForDayAndHour`
     * y el antiguo `appointmentsForSlot` quedan prohibidos: una sola lectura
     * por día, sin dos implementaciones de la misma búsqueda.
     */
    public function test_week_grid_keeps_the_slot_lookup_and_day_board_groups_by_resource(): void
    {
        $path = dirname(__DIR__, 3).self::CALENDAR_PATH;
        $src = self::readSource($path);
        $this->assertNotNull($src, sprintf('%s must be readable.', $path));

        $this->assertSame(
            0,
            preg_match('#getAppointmentsForHour|getAppointmentsForDayAndHour#', $src),
            sprintf(
                '%s MUST NOT keep the duplicated `getAppointmentsForHour` / `getAppointmentsForDayAndHour` pair (T4).',
                $path
            )
        );

        $this->assertSame(
            0,
            preg_match('#appointmentsForSlot#', $src),
            sprintf(
                '%s MUST NOT keep the hour-cell `appointmentsForSlot` lookup (T5b: the week '
                .'positions blocks per day).',
                $path
            )
        );
        $this->assertSame(
            1,
            preg_match('#const weekDayAppointments = #', $src),
            sprintf('%s MUST define exactly one `weekDayAppointments` helper (T5b week body).', $path)
        );

        $this->assertGreaterThanOrEqual(
            1,
            preg_match_all('#weekDayAppointments\(day\.date\)#', $src),
            sprintf('%s MUST render the Week blocks from `weekDayAppointments(day.date)` (T5b).', $path)
        );

        // El tablero del día agrupa por recurso con el módulo puro.
        $this->assertSame(
            1,
            preg_match('#boardColumns = computed\(#', $src),
            sprintf(
                '%s Day board MUST derive its columns from a `boardColumns` computed (T4 day board).',
                $path
            )
        );
        $this->assertSame(
            1,
            preg_match("#from './calendarBoardMath'#", $src),
            sprintf(
                '%s MUST import the board geometry from the pure `./calendarBoardMath` module (T4: '
                .'window, gaps and grouping test without a DOM).',
                $path
            )
        );
        $this->assertSame(
            1,
            preg_match('#groupByChair\(#', $src),
            sprintf('%s Day board MUST group by ambiente through `groupByChair` (T4).', $path)
        );
        $this->assertSame(
            1,
            preg_match('#groupByProfessional\(#', $src),
            sprintf(
                '%s Day board MUST offer the professional grouping through `groupByProfessional` (T4).',
                $path
            )
        );
    }

    /**
     * T4 click-to-create contract — every slot hit area is a real `<button>`
     * (keyboard: Enter/Space) gated by the same `can.createAppointment`
     * permission as the "Nueva cita" CTA, carrying a `data-calendar-slot` hook
     * and wiring `openSlotCreate`. The prefill reaches `NewAppointmentModal`
     * through the existing `initialDate` prop
     * (`:initial-date="newAppointmentPrefill"`).
     *
     * Contract change (T4 day board): the Day hit areas are the bookable holes
     * of each resource column, so their key names the day and the gap's start
     * hour (`${dayDateString}-${slot.hour}`) and they also publish the exact
     * start minute (`data-calendar-free-slot-start`) — a 45-minute hole that
     * opens at 13:15 is not booked at 13:00. The Week grid keeps the
     * `${day.date}-${hour}` cell key.
     */
    public function test_click_to_create_slot_contract(): void
    {
        $path = dirname(__DIR__, 3).self::CALENDAR_PATH;
        $src = self::readSource($path);
        $this->assertNotNull($src, sprintf('%s must be readable.', $path));

        // The gate sits ON the button tag that carries the slot hook: when
        // the user cannot create appointments the hit area does not render.
        // T5a adds `gapIsBookable(slot)` to the Day gate: a hole longer than
        // 120 minutes is declared, never offered as a three-hour button.
        $this->assertSame(
            1,
            preg_match_all(
                '#<button\b[^>]*v-if\s*=\s*"can\.createAppointment\?\.value"[^>]*:data-calendar-slot\s*=#s',
                $src
            ),
            sprintf(
                '%s Week hit area MUST be a real `<button>` gated by `can.createAppointment?.value` (T4).',
                $path
            )
        );
        $this->assertSame(
            1,
            preg_match_all(
                '#<button\b[^>]*v-if\s*=\s*"can\.createAppointment\?\.value && gapIsBookable\(slot\)"[^>]*:data-calendar-slot\s*=#s',
                $src
            ),
            sprintf(
                '%s Day hit area MUST gate on `can.createAppointment?.value && gapIsBookable(slot)` (T5a).',
                $path
            )
        );

        // Hook format contract: data-calendar-slot="<date>-<hour>". The Week
        // grid keys its cell by the hour row; the Day board keys the hole by
        // its start hour and publishes the exact minute next to it.
        $this->assertSame(
            1,
            preg_match_all('#:data-calendar-slot\s*=\s*"`\$\{day\.date\}-\$\{hour\}`"#', $src),
            sprintf(
                '%s Week grid MUST bind `:data-calendar-slot="`${day.date}-${hour}`"` (T4).',
                $path
            )
        );
        $this->assertSame(
            1,
            preg_match_all(
                '#:data-calendar-slot\s*=\s*"`\$\{dayDateString\}-\$\{slot\.hour\}`"#',
                $src
            ),
            sprintf(
                '%s Day board MUST bind `:data-calendar-slot="`${dayDateString}-${slot.hour}`"` on its '
                .'bookable holes (T4).',
                $path
            )
        );
        $this->assertSame(
            1,
            preg_match('#:data-calendar-free-slot-start\s*=\s*"timeAtMinutes\(slot\.startMinutes\)"#', $src),
            sprintf(
                '%s Day board holes MUST publish their exact start minute through `data-calendar-free-slot-start` (T4).',
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

    /**
     * S4 — the calendar MAY start the attention through the existing
     * `POST /api/appointments/{id}/start-attention` door, gated by
     * `start.consultation`; it MUST NOT open the clinical consultation: no
     * wizard mount, no closure door. The closure and its document live in
     * `/my-day`, and a scheduling surface that mounts the wizard can walk an
     * act it does not own.
     */
    public function test_calendar_starts_the_attention_without_opening_the_consultation(): void
    {
        $path = dirname(__DIR__, 3).self::CALENDAR_PATH;
        $src = self::readSource($path);
        $this->assertNotNull($src, sprintf('%s must be readable.', $path));

        // The wizard and the closure stay forbidden file-wide.
        $forbidden = [
            'openForAppointment',
            'openConsultationWizard',
            'ConsultationWizard',
            'wizardAppointment',
            'complete-consultation',
        ];

        foreach ($forbidden as $token) {
            $this->assertSame(
                0,
                preg_match('#'.preg_quote($token, '#').'#', $src),
                sprintf(
                    '%s MUST NOT reference `%s`: the calendar may start the attention through its own '
                    .'endpoint, but the clinical consultation and its closure belong to `/my-day` (S4).',
                    $path,
                    $token
                )
            );
        }

        // …and the one door it may use is the existing endpoint, gated by the
        // same permission that gates the act.
        $this->assertSame(
            1,
            preg_match(
                '#<UiButton\b(?=[^>]*data-calendar-action="start-attention")(?=[^>]*can\.startConsultation\?\.value)(?=[^>]*@click="startAttention\(selectedAppointment\)")([^>]*)>#s',
                $src
            ),
            sprintf(
                '%s MUST start the attention through exactly one `<UiButton data-calendar-action="start-attention">` '
                .'gated by `can.startConsultation?.value` and wired to `startAttention(selectedAppointment)` (S4/S5).',
                $path
            )
        );

        $this->assertStringContainsString(
            'post(`/api/appointments/${appointment.id}/start-attention`, {})',
            $src,
            sprintf(
                '%s MUST start the attention through the existing `POST /api/appointments/{id}/start-attention` '
                .'endpoint (S4: no new routes, no wizard).',
                $path
            )
        );
    }

    /**
     * U3 bridge — the details surface offers `Ir a mi jornada` as a real
     * router link to `/my-day`, visible only to sessions holding
     * `view.my_day` (the permission `/api/my-day` demands). Reception holds
     * check-in but not My Day, so the link must not render for it and must
     * leave no empty slot.
     */
    public function test_calendar_details_offer_the_my_day_bridge(): void
    {
        $path = dirname(__DIR__, 3).self::CALENDAR_PATH;
        $src = self::readSource($path);
        $this->assertNotNull($src, sprintf('%s must be readable.', $path));

        $start = strpos($src, '<!-- Appointment Details Modal -->');
        $end = strpos($src, '<!-- New Appointment Modal -->');
        $this->assertNotFalse($start, sprintf('%s MUST keep the details modal marker.', $path));
        $this->assertNotFalse($end, sprintf('%s MUST keep the new appointment modal marker.', $path));
        $this->assertGreaterThan($start, $end, 'Details slice must precede the new appointment modal.');

        $slice = substr($src, $start, $end - $start);
        $pattern = '#<RouterLink\b(?=[^>]*data-calendar-action="my-day")([^>]*)>\s*Ir a mi jornada\s*</RouterLink>#s';
        $this->assertSame(
            1,
            preg_match($pattern, $slice, $matches),
            sprintf(
                '%s details MUST publish one `<RouterLink data-calendar-action="my-day">Ir a mi jornada</RouterLink>` (U3).',
                $path
            )
        );

        $tag = $matches[1];
        $this->assertStringContainsString('v-if="can.viewMyDay?.value"', $tag, sprintf('%s My Day bridge MUST gate on `can.viewMyDay?.value` (U3: the same permission `/api/my-day` demands).', $path));
        $this->assertStringContainsString('to="/my-day"', $tag, sprintf('%s My Day bridge MUST navigate to `/my-day` (U3).', $path));
    }

    /**
     * U3 + #281 — reception has no My Day, so the calendar MUST keep the
     * check-in door it owns: one `check-in` action gated by
     * `can.checkInAppointment` and wired to `markArrival`.
     */
    public function test_calendar_details_keep_the_check_in_door(): void
    {
        $path = dirname(__DIR__, 3).self::CALENDAR_PATH;
        $src = self::readSource($path);
        $this->assertNotNull($src, sprintf('%s must be readable.', $path));

        $pattern = '#<UiButton\b(?=[^>]*data-calendar-action="check-in")([^>]*)>#s';
        $this->assertSame(
            1,
            preg_match($pattern, $src, $matches),
            sprintf('%s MUST keep exactly one `<UiButton data-calendar-action="check-in">` (U3/#281: reception has no My Day).', $path)
        );

        $tag = $matches[1];
        $this->assertStringContainsString('can.checkInAppointment?.value', $tag, sprintf('%s check-in MUST stay gated on `can.checkInAppointment?.value` (U3/#281).', $path));
        $this->assertStringContainsString('@click="markArrival(selectedAppointment)"', $tag, sprintf('%s check-in MUST stay wired to `markArrival(selectedAppointment)` (U3/#281).', $path));
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
