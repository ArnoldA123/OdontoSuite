<?php

namespace Tests\Unit\DesignSystem;

/**
 * PR-citas-03 — NewAppointmentModalAppShellTest.
 *
 * Asserts CITAS-MOD-001 (`<UiModal>` migration + duplicate-key 422 mapping)
 * + CITAS-CONF-001 (no client-side "no conflict" claim; duplicate-key is
 * rendered as a friendly template-level badge) + CITAS-TZ-001 (no
 * `.toISOString()` on `datetime-local`) + CITAS-CON-001 (`useApi` ownership
 * of the 401 redirect path + emit contract preserved) for the single
 * `NewAppointmentModal.vue` file.
 *
 * The base class `ModuleAppShellTestCase` enforces the 5 inherited DLR-R
 * rules (canvas token, no `border-theme`, focus ring, no `<style scoped>`,
 * no legacy focus-ring aliases) via `polishedFileProvider()`. This subclass
 * adds the 7 modal-specific rule assertions below.
 *
 * Per CITAS-CON-001, the `<script>` block's reactivity (refs, lifecycle,
 * watchers, composable destructure, `useApi` ownership) MUST be preserved
 * byte-for-byte; the rule is asserted by `test_modal_emit_contract_preserved`
 * + `test_modal_echo_channels_preserved`. The script additions are strictly
 * limited to (a) the `UiModal` + `UiStatusBadge` imports, (b) one reactive
 * `error` ref + a `duplicateKeyError` computed, (c) error assignment in the
 * catch block (422 / duplicate_key mapping), (d) error clearing in
 * `resetForm`. The `useApi()` 401 redirect contract (UXF-021 sibling
 * preservation) is untouched.
 *
 * Implementation note: regex delimiters are `#` (NOT `/`) because the path
 * patterns contain forward slashes; using `/` as delimiter would force
 * every `/` in the path to be escaped `\/`, which is brittle and error-prone.
 */
class NewAppointmentModalAppShellTest extends ModuleAppShellTestCase
{
    /** Modal path constant — used by the data provider + the single-file
     *  rules. Keeps a single source of truth for the absolute path. */
    private const MODAL_PATH = '/resources/js/components/appointments/NewAppointmentModal.vue';

    /** @return array<int, string> */
    protected static function polishedFiles(): array
    {
        return [
            dirname(__DIR__, 3).self::MODAL_PATH,
        ];
    }

    /**
     * CITAS-MOD-001 — the modal chrome MUST consume `<UiModal>`. The
     * hand-built `<Teleport to="body">` + `bg-black bg-opacity-50` backdrop
     * (the pre-PR chrome) MUST be absent. Regex pins the rule, not the
     * literal output of one example.
     */
    public function test_modal_uses_ui_modal(): void
    {
        $path = dirname(__DIR__, 3).self::MODAL_PATH;
        $src = self::readSource($path);
        $this->assertNotNull($src, sprintf('%s must be readable.', $path));

        // 1) The `<UiModal>` primitive is consumed.
        $this->assertTrue(
            (bool) preg_match('#<UiModal\b#', $src),
            sprintf(
                '%s MUST consume `<UiModal>` for the modal chrome (CITAS-MOD-001). '
                .'The hand-built `<Teleport to="body">` + `bg-black bg-opacity-50` backdrop is deprecated.',
                $path
            )
        );

        // 2) The hand-built `<Teleport to="body">` must be absent.
        $this->assertSame(
            0,
            preg_match('#<Teleport\s+to\s*=\s*["\']body["\']#', $src),
            sprintf(
                '%s MUST NOT keep a hand-built `<Teleport to="body">` modal '
                .'(CITAS-MOD-001). The chrome MUST consume `<UiModal>`.',
                $path
            )
        );

        // 3) The legacy `bg-black bg-opacity-50` backdrop must be absent.
        $this->assertSame(
            0,
            preg_match('#(?<![\w-])bg-black\s+bg-opacity-50(?![\w-])#', $src),
            sprintf(
                '%s MUST NOT keep the legacy `bg-black bg-opacity-50` backdrop literal (CITAS-MOD-001). '
                .'`<UiModal>` owns the backdrop.',
                $path
            )
        );
    }

    /**
     * CITAS-MOD-001 — the modal MUST NOT contain a raw `<select>` carrying
     * the legacy `border-theme` literal. Every select MUST be migrated to
     * `<UiSelect>`. The whole-token regex excludes modifier variants like
     * `border-theme-light`.
     */
    public function test_modal_no_raw_select(): void
    {
        $path = dirname(__DIR__, 3).self::MODAL_PATH;
        $src = self::readSource($path);
        $this->assertNotNull($src, sprintf('%s must be readable.', $path));

        // Zero raw `<select ... class="...border-theme...">` controls.
        $this->assertSame(
            0,
            preg_match(
                '#<select\b[^>]*\bborder-theme(?![\w-])#',
                $src
            ),
            sprintf(
                '%s MUST NOT keep a raw `<select>` carrying the legacy `border-theme` literal (CITAS-MOD-001 / DLR-R-002). '
                .'All selects MUST consume `<UiSelect>`.',
                $path
            )
        );
    }

    /**
     * DLR-R-004 (modal-specific) — the modal MUST NOT carry the legacy
     * focus-ring aliases (`focus:ring-primary-500` or `focus:border-accent`).
     * The token-aligned form is `var(--focus-ring-default)` consumed by the
     * Ui primitives, or `focus:ring-systemBlue-500` (Apple-language ramp).
     */
    public function test_modal_no_legacy_focus_ring(): void
    {
        $path = dirname(__DIR__, 3).self::MODAL_PATH;
        $src = self::readSource($path);
        $this->assertNotNull($src, sprintf('%s must be readable.', $path));

        $this->assertSame(
            0,
            preg_match(
                '#(?<![\w-])focus:ring-primary-500(?![\w-])#',
                $src
            ),
            sprintf(
                '%s MUST NOT contain the legacy `focus:ring-primary-500` focus-ring alias (DLR-R-004 / CITAS-MOD-001). '
                .'Use `focus:ring-systemBlue-500` or rely on the Ui primitives.',
                $path
            )
        );

        $this->assertSame(
            0,
            preg_match(
                '#(?<![\w-])focus:border-accent(?![\w-])#',
                $src
            ),
            sprintf(
                '%s MUST NOT contain the legacy `focus:border-accent` focus-ring alias (DLR-R-004). '
                .'The global `var(--focus-ring-default)` token owns the focus border colour.',
                $path
            )
        );
    }

    /**
     * CITAS-MOD-001 — the form fields MUST consume the Ui primitives.
     * All three (<UiInput>, <UiSelect>, <UiButton>) MUST be present in
     * either JSX-tag form or as a named import from `components/ui/<Name>.vue`.
     */
    public function test_modal_uses_ui_select_ui_input(): void
    {
        $path = dirname(__DIR__, 3).self::MODAL_PATH;
        $src = self::readSource($path);
        $this->assertNotNull($src, sprintf('%s must be readable.', $path));

        $this->assertTrue(
            (bool) preg_match('#<UiInput\b#', $src)
                || (bool) preg_match(
                    '#import\s+\w*[Ii]nput\w*\s+from\s+[\'"][^\'"]*components/ui/Input\.vue[\'"]#',
                    $src
                ),
            sprintf('%s MUST consume <UiInput> (CITAS-MOD-001).', $path)
        );

        $this->assertTrue(
            (bool) preg_match('#<UiSelect\b#', $src)
                || (bool) preg_match(
                    '#import\s+\w*[Ss]elect\w*\s+from\s+[\'"][^\'"]*components/ui/Select\.vue[\'"]#',
                    $src
                ),
            sprintf('%s MUST consume <UiSelect> (CITAS-MOD-001).', $path)
        );

        $this->assertTrue(
            (bool) preg_match('#<UiButton\b#', $src)
                || (bool) preg_match(
                    '#import\s+\w*[Bb]utton\w*\s+from\s+[\'"][^\'"]*components/ui/Button\.vue[\'"]#',
                    $src
                ),
            sprintf('%s MUST consume <UiButton> for the submit affordance (CITAS-MOD-001).', $path)
        );
    }

    /**
     * CITAS-CONF-001 — the modal MUST render a friendly "Otra mesa ya
     * reservó este horario" message when a 422 duplicate-key error is
     * returned from `AppointmentService::createAppointment`. The mapping
     * is template-level: a `<UiStatusBadge variant="error">` is rendered
     * when the reactive error state indicates a duplicate-key (either
     * `error.code === 'duplicate_key'` or `error.response.status === 422`
     * with the unique-constraint regex on the message).
     */
    public function test_modal_handles_duplicate_key_422(): void
    {
        $path = dirname(__DIR__, 3).self::MODAL_PATH;
        $src = self::readSource($path);
        $this->assertNotNull($src, sprintf('%s must be readable.', $path));

        // 1) The modal imports `UiStatusBadge` (it is NOT globally registered,
        //    so a local import is required). The path may be either relative
        //    (`../ui/StatusBadge.vue`) or alias-based (`@/components/ui/StatusBadge.vue`).
        $this->assertTrue(
            (bool) preg_match(
                '#import\s+\w*[Ss]tatus[Bb]adge\w*\s+from\s+[\'"](?:(?:\.\./ui/StatusBadge\.vue)|(?:[^\'"]*components/ui/StatusBadge\.vue))[\'"]#',
                $src
            ),
            sprintf(
                '%s MUST import `UiStatusBadge` (CITAS-CONF-001 / CITAS-MOD-001). '
                .'UiStatusBadge is not globally registered — local import required.',
                $path
            )
        );

        // 2) A `<UiStatusBadge variant="error" ...>` is rendered conditionally
        //    on the duplicate-key heuristic. The label must include the
        //    canonical "Otra mesa ya reserv" message (literal + closing).
        $this->assertTrue(
            (bool) preg_match(
                '#<UiStatusBadge\b[^>]*variant\s*=\s*["\']error["\'][^>]*Otra\s+mesa\s+ya\s+reserv#s',
                $src
            ),
            sprintf(
                '%s MUST render a `<UiStatusBadge variant="error">` with the label '
                .'"Otra mesa ya reservó este horario" for duplicate-key 422 (CITAS-CONF-001).',
                $path
            )
        );

        // 3) The catch block of `saveAppointment` maps the duplicate-key
        //    error to the reactive error state (the rule fires on either
        //    `error.code === 'duplicate_key'` OR `error.response.status === 422`).
        $this->assertTrue(
            (bool) preg_match(
                '#duplicate_key|status\s*===\s*422#',
                $src
            ),
            sprintf(
                '%s MUST detect duplicate-key errors via either `error.code === \'duplicate_key\'` '
                .'OR `error.response.status === 422` (CITAS-CONF-001). The DB unique constraints '
                .'`unique_user_time_slot` / `unique_chair_time_slot` bubble as 422 / duplicate_key.',
                $path
            )
        );
    }

    /**
     * CITAS-TZ-001 — the modal MUST NOT call `.toISOString()` on a
     * `datetime-local` value. The server interprets naive local time as
     * `app.timezone` per `AppointmentService::createAppointment`
     * (`Carbon::parse($data['scheduled_at'])->setTimezone(config('app.timezone'))`).
     * A JS-side `.toISOString()` would drop the local TZ offset and silently
     * corrupt the appointment time (the bug that triggered migration
     * `2026_06_02_173228_fix_appointments_timezone_offset`).
     */
    public function test_modal_no_to_iso_string_on_datetime_local(): void
    {
        $path = dirname(__DIR__, 3).self::MODAL_PATH;
        $src = self::readSource($path);
        $this->assertNotNull($src, sprintf('%s must be readable.', $path));

        $this->assertSame(
            0,
            preg_match('#\.toISOString\s*\(\s*\)#', $src),
            sprintf(
                '%s MUST NOT call `.toISOString()` on any value (CITAS-TZ-001). '
                .'The server interprets `datetime-local` input as naive local time; '
                .'a JS-side ISO conversion would drop the local TZ offset.',
                $path
            )
        );
    }

    /**
     * CITAS-CON-001 — the modal's emit contract MUST be preserved
     * byte-for-byte. The actual emits are `update:modelValue`, `created`,
     * `updated` (the v-model wrapper + the two success-side events). The
     * parent callers (`DashboardPage.vue` via `?openAppointmentModal=true`
     * redirect, `CalendarPage.vue`, `MedicalRecordsPage.vue`) listen on
     * these names; renaming them silently breaks the caller-side wiring.
     */
    public function test_modal_emit_contract_preserved(): void
    {
        $path = dirname(__DIR__, 3).self::MODAL_PATH;
        $src = self::readSource($path);
        $this->assertNotNull($src, sprintf('%s must be readable.', $path));

        // The `defineEmits` call lists the three event names verbatim.
        $this->assertTrue(
            (bool) preg_match(
                "#defineEmits\\s*\\(\\s*\\[\\s*['\"]update:modelValue['\"]\\s*,\\s*['\"]created['\"]\\s*,\\s*['\"]updated['\"]\\s*\\]\\s*\\)#",
                $src
            ),
            sprintf(
                '%s MUST keep `defineEmits([\'update:modelValue\', \'created\', \'updated\'])` '
                .'byte-for-byte (CITAS-CON-001). Parent callers listen on these event names.',
                $path
            )
        );

        // The modal also imports `useApi` (the 401 redirect owner — UXF-021
        // sibling preservation).
        $this->assertTrue(
            (bool) preg_match(
                '#import\s*\{[^}]*?useApi[^}]*?\}\s*from\s*[\'"]\.\./\.\./composables/useApi[\'"]#',
                $src
            )
            || (bool) preg_match(
                '#import\s*\{[^}]*?useApi[^}]*?\}\s*from\s*[\'"]@/composables/useApi[\'"]#',
                $src
            ),
            sprintf(
                '%s MUST keep the `useApi` import (CITAS-CON-001). '
                .'`useApi` owns the 401 redirect contract; the modal MUST NOT bypass it.',
                $path
            )
        );

        // The catch block of `saveAppointment` still routes errors through
        // `toast.error(...)` (the canonical error feedback) — the 500
        // toast fallback MUST stay in place. The 422 branch maps to the
        // reactive `error` ref AND still surfaces a toast; both branches
        // are preserved.
        $this->assertTrue(
            (bool) preg_match(
                '#catch\s*\([^)]*\)\s*\{[^}]*toast\.error#s',
                $src
            ),
            sprintf(
                '%s MUST keep a `catch (...) { ... toast.error(...) }` block in `saveAppointment` '
                .'(CITAS-CON-001). The canonical error feedback is preserved alongside the '
                .'422 duplicate-key badge mapping.',
                $path
            )
        );
    }

    /**
     * Rediseño del alta (captura rápida + disponibilidad) — the patient is a
     * server-side search, not the whole table paged into the dialog.
     *
     * The contract this pins is the one `PatientController@search` actually
     * reads: `q` (or `search`), minimum 2 characters, at most 20 rows. The old
     * `loadData()` walked `/api/patients?per_page=100` page by page, which put
     * every patient of every branch inside the modal before the user typed.
     */
    public function test_patient_is_searched_on_the_server(): void
    {
        $path = dirname(__DIR__, 3).self::MODAL_PATH;
        $src = self::readSource($path);
        $this->assertNotNull($src, sprintf('%s must be readable.', $path));

        $this->assertTrue(
            (bool) preg_match('#/api/patients/search#', $src),
            sprintf('%s MUST search the patient through `GET /api/patients/search`.', $path)
        );

        // The term travels as `q` — the parameter `PatientController@search`
        // reads (`q ?? search`). `search` alone would work too, but pinning `q`
        // keeps the call site honest about the contract it was verified against.
        $this->assertTrue(
            (bool) preg_match("#get\\(\\s*'/api/patients/search'\\s*,\\s*\\{\\s*params\\s*:\\s*\\{\\s*q\\s*:#", $src),
            sprintf('%s MUST send the term as `params: { q: … }` to `/api/patients/search`.', $path)
        );

        $this->assertSame(
            0,
            preg_match('#per_page=100#', $src),
            sprintf('%s MUST NOT page the whole patient table into the dialog (`per_page=100`).', $path)
        );
        $this->assertSame(
            0,
            preg_match('#\\bhasMorePages\\b#', $src),
            sprintf('%s MUST NOT keep the paged patient walk (`hasMorePages`).', $path)
        );
    }

    /**
     * The search results are a real listbox: `role="listbox"` + `role="option"`
     * + `aria-selected`, and the chosen patient publishes
     * `data-appointment-patient` (the hook the guards read).
     */
    public function test_patient_search_publishes_listbox_contract(): void
    {
        $path = dirname(__DIR__, 3).self::MODAL_PATH;
        $src = self::readSource($path);
        $this->assertNotNull($src, sprintf('%s must be readable.', $path));

        $this->assertTrue(
            (bool) preg_match('#role="listbox"#', $src),
            sprintf('%s MUST publish the suggestions as `role="listbox"`.', $path)
        );
        $this->assertTrue(
            (bool) preg_match('#role="option"#', $src),
            sprintf('%s MUST publish every suggestion as `role="option"`.', $path)
        );
        $this->assertTrue(
            (bool) preg_match('#:aria-selected\s*=#', $src),
            sprintf('%s MUST expose `aria-selected` on the active suggestion.', $path)
        );
        $this->assertTrue(
            (bool) preg_match('#data-appointment-patient\b#', $src),
            sprintf('%s MUST publish `data-appointment-patient` for the chosen patient.', $path)
        );
    }

    /**
     * "Cuándo" reads as an hour: native date, native-free hour grid and the
     * duration ladder. The hour is NOT a `<input type="time">` any more: the
     * native wheel changes the value while the user is scrolling the dialog,
     * which is exactly the defect this round removes. It is a 15-minute grid.
     */
    public function test_when_is_date_and_a_quarter_hour_grid(): void
    {
        $path = dirname(__DIR__, 3).self::MODAL_PATH;
        $src = self::readSource($path);
        $this->assertNotNull($src, sprintf('%s must be readable.', $path));

        $this->assertSame(
            1,
            preg_match('#<UiInput\\b(?=[^>]*type="date")#', $src),
            sprintf('%s MUST capture the date with a `<UiInput type="date">` (the native date field has no wheel defect).', $path)
        );
        $this->assertSame(
            0,
            preg_match('#<UiInput\\b(?=[^>]*type="time")#', $src),
            sprintf('%s MUST NOT capture the hour with a native `<input type="time">`: its wheel changes the value while the dialog scrolls.', $path)
        );
        $this->assertSame(
            0,
            preg_match('#<input\b[^>]*type="time"#', $src),
            sprintf('%s MUST NOT keep any raw `<input type="time">` control in the create dialog.', $path)
        );
        $this->assertSame(
            0,
            preg_match('#type="datetime-local"#', $src),
            sprintf('%s MUST NOT keep the single `datetime-local` field (the design reads date and hour apart).', $path)
        );
        $this->assertStringContainsString(
            'DURATION_VALUES = [15, 20, 30, 45, 60, 90, 120]',
            $src,
            sprintf('%s MUST offer the 15/20/30/45/60/90/120 duration ladder.', $path)
        );
        $this->assertTrue(
            (bool) preg_match('#data-appointment-end#', $src),
            sprintf('%s MUST publish the computed end ("Termina HH:MM").', $path)
        );
    }

    /**
     * The hour grid is built from the pure composable, paints one cell per
     * quarter of an hour, and publishes the occupancy of every cell with its
     * accessible reason. The cells are 44 px tall (the shared touch target) and
     * carry `aria-pressed`, not a toggle primitive of their own.
     */
    public function test_hour_grid_is_quarter_hour_cells_with_reasons(): void
    {
        $path = dirname(__DIR__, 3).self::MODAL_PATH;
        $src = self::readSource($path);
        $this->assertNotNull($src, sprintf('%s must be readable.', $path));

        $this->assertStringContainsString(
            'buildHourGrid',
            $src,
            sprintf('%s MUST build the hour grid through the pure `buildHourGrid` rule.', $path)
        );
        $this->assertTrue(
            (bool) preg_match('#data-appointment-hour-grid\b#', $src),
            sprintf('%s MUST publish `data-appointment-hour-grid`.', $path)
        );
        $this->assertTrue(
            (bool) preg_match('#:data-appointment-slot="slot\.value"#', $src),
            sprintf('%s MUST publish one `data-appointment-slot` hook per cell.', $path)
        );
        $this->assertTrue(
            (bool) preg_match('#:aria-pressed="pressed\(form\.time === slot\.value\)"#', $src),
            sprintf('%s MUST bind `aria-pressed` to the chosen cell.', $path)
        );
        $this->assertTrue(
            (bool) preg_match('#:aria-label="slot\.ariaLabel"#', $src),
            sprintf('%s MUST name every cell accessibly, including its occupancy reason.', $path)
        );
        $this->assertTrue(
            (bool) preg_match('#:disabled="slot\.disabled"#', $src),
            sprintf('%s MUST disable the occupied cells instead of letting them be chosen.', $path)
        );
        $this->assertTrue(
            (bool) preg_match('#min-h-\[44px\][^"\n]*rounded-ios border#', $src),
            sprintf('%s MUST keep every hour cell at the 44 px touch target.', $path)
        );
        $this->assertStringContainsString(
            'onHourGridKeydown',
            $src,
            sprintf('%s MUST move across the grid with the arrow keys.', $path)
        );
        $this->assertTrue(
            (bool) preg_match('#data-appointment-hour-hint#', $src),
            sprintf('%s MUST say what is missing before the grid can be painted.', $path)
        );
    }

    /**
     * The type chips follow the chosen professional: his specialty first, the
     * rest behind "Otros tipos", and the complete select grouped by specialty
     * (with the types that declare none in their own group).
     */
    public function test_appointment_type_chips_are_specialty_aware(): void
    {
        $path = dirname(__DIR__, 3).self::MODAL_PATH;
        $src = self::readSource($path);
        $this->assertNotNull($src, sprintf('%s must be readable.', $path));

        $this->assertStringContainsString(
            'groupAppointmentTypes',
            $src,
            sprintf('%s MUST group the type catalog by specialty through the pure rule.', $path)
        );
        $this->assertTrue(
            (bool) preg_match('#:aria-pressed="pressed\(#', $src),
            sprintf('%s MUST publish `aria-pressed` on the type chips.', $path)
        );
        $this->assertTrue(
            (bool) preg_match('#data-appointment-type-more#', $src),
            sprintf('%s MUST keep the "Otros tipos" door to the complete catalog.', $path)
        );
        $this->assertTrue(
            (bool) preg_match('#data-appointment-type-specialty#', $src),
            sprintf('%s MUST declare the specialty of the chip set once, not once per chip.', $path)
        );
        // El catálogo completo se expande DENTRO del flujo: sin menú flotante
        // no hay overlay que recortar ni sección partida por el borde.
        $this->assertTrue(
            (bool) preg_match('#data-appointment-type-catalog\b#', $src),
            sprintf('%s MUST publish the complete catalog as an inline panel.', $path)
        );
        $this->assertTrue(
            (bool) preg_match('#data-appointment-type-group="group\.label"#', $src),
            sprintf('%s MUST group the inline catalog by specialty.', $path)
        );
        $this->assertTrue(
            (bool) preg_match('#data-appointment-type-option="type\.id"#', $src),
            sprintf('%s MUST publish one option per type inside its group.', $path)
        );
        $this->assertSame(
            0,
            preg_match('#data-appointment-type-catalog[^>]*absolute#', $src),
            sprintf('%s MUST NOT render the catalog as an absolutely positioned overlay.', $path)
        );
        $this->assertTrue(
            (bool) preg_match('#professionalSpecialtySummary#', $src),
            sprintf('%s MUST declare the specialty of the frequent types next to the block label.', $path)
        );
        $this->assertStringContainsString(
            "'tipo' : 'tipos'",
            $src,
            sprintf('%s MUST publish the specialty with its type count.', $path)
        );
        $this->assertStringContainsString(
            'Sin especialidad',
            $src,
            sprintf('%s MUST keep the types without a specialty in their own named group.', $path)
        );
    }

    /**
     * The right column reads who → what → extras: the type chips are ordered by
     * the chosen professional's specialty, so the block that explains the order
     * cannot sit above the block that depends on it.
     */
    public function test_team_comes_before_the_type_block(): void
    {
        $path = dirname(__DIR__, 3).self::MODAL_PATH;
        $src = self::readSource($path);
        $this->assertNotNull($src, sprintf('%s must be readable.', $path));

        $team = strpos($src, 'data-appointment-section="team"');
        $type = strpos($src, 'data-appointment-section="type"');
        $details = strpos($src, 'data-appointment-more');

        $this->assertNotFalse($team, sprintf('%s MUST render the team section.', $path));
        $this->assertNotFalse($type, sprintf('%s MUST render the type section.', $path));
        $this->assertNotFalse($details, sprintf('%s MUST render the details block.', $path));
        $this->assertLessThan(
            $type,
            $team,
            sprintf('%s MUST render the team block before the type block (the chips depend on the professional).', $path)
        );
        $this->assertLessThan(
            $details,
            $type,
            sprintf('%s MUST render the type block before «Más detalles».', $path)
        );
    }

    /**
     * Professional and chair are two independent lists, not a proposed pair:
     * each row declares its occupancy with its reason, the combination door is
     * always visible, and the chosen combination is stated in one line.
     */
    public function test_team_is_two_independent_lists_with_a_combination_line(): void
    {
        $path = dirname(__DIR__, 3).self::MODAL_PATH;
        $src = self::readSource($path);
        $this->assertNotNull($src, sprintf('%s must be readable.', $path));

        $this->assertStringContainsString(
            'buildAppointmentAvailability',
            $src,
            sprintf('%s MUST consume the pure `buildAppointmentAvailability` rule.', $path)
        );
        $this->assertStringContainsString(
            "from '../../composables/useAppointmentAvailability'",
            $src,
            sprintf('%s MUST import the rule from `composables/useAppointmentAvailability`.', $path)
        );
        $this->assertTrue(
            (bool) preg_match('#data-appointment-free-professionals\b#', $src),
            sprintf('%s MUST publish the free professional count.', $path)
        );
        $this->assertTrue(
            (bool) preg_match('#data-appointment-free-chairs\b#', $src),
            sprintf('%s MUST publish the free chair count.', $path)
        );
        $this->assertTrue(
            (bool) preg_match('#data-appointment-professional-list\b#', $src),
            sprintf('%s MUST render the professionals as their own list.', $path)
        );
        $this->assertTrue(
            (bool) preg_match('#data-appointment-chair-list\b#', $src),
            sprintf('%s MUST render the chairs as their own list.', $path)
        );
        $this->assertTrue(
            (bool) preg_match('#:data-appointment-professional="person\.id"#', $src),
            sprintf('%s MUST publish one hook per professional row.', $path)
        );
        $this->assertTrue(
            (bool) preg_match('#:data-appointment-chair="chair\.id"#', $src),
            sprintf('%s MUST publish one hook per chair row.', $path)
        );
        $this->assertTrue(
            (bool) preg_match('#data-appointment-suggestion\b#', $src),
            sprintf('%s MUST offer the pairs the day already used as one-click rows.', $path)
        );
        $this->assertTrue(
            (bool) preg_match('#data-appointment-combination\b#', $src),
            sprintf('%s MUST state the chosen combination in one line.', $path)
        );
        // The plain selects are the honest fallback: without the day's agenda
        // read there is no «Libre» to claim.
        $this->assertTrue(
            (bool) preg_match('#data-appointment-manual-pick\b#', $src),
            sprintf('%s MUST keep the manual professional/chair selects as the fallback.', $path)
        );
        $this->assertTrue(
            (bool) preg_match('#v-if="pairsVisible"#', $src),
            sprintf('%s MUST gate the team lists behind the read state.', $path)
        );
        $this->assertTrue(
            (bool) preg_match("#\\{\\{ (person|chair)\\.free \\? 'Libre' :#", $src),
            sprintf('%s MUST bind the «Libre» label to the computed resource state.', $path)
        );
        $this->assertTrue(
            (bool) preg_match('#Ocupado hasta#', $src),
            sprintf('%s MUST declare the occupied resources with their reason.', $path)
        );
        // La lista no desplaza: cuatro filas por página y paginador explícito.
        // Un scroller dentro del diálogo corta una fila a la mitad.
        $this->assertStringContainsString(
            'const TEAM_PAGE_SIZE = 4',
            $src,
            sprintf('%s MUST page the team lists (no inner scroll: a bounded list cuts a row in half).', $path)
        );
        $this->assertTrue(
            (bool) preg_match('#data-appointment-professionals-pager#', $src),
            sprintf('%s MUST publish the professionals pager when the list does not fit.', $path)
        );
        $this->assertTrue(
            (bool) preg_match('#data-appointment-chairs-pager#', $src),
            sprintf('%s MUST publish the chairs pager when the list does not fit.', $path)
        );
        // Las filas envuelven el nombre; nada de puntos suspensivos.
        $this->assertTrue(
            (bool) preg_match('#class="block min-w-0 break-words text-xs font-medium leading-tight text-theme-primary"#', $src),
            sprintf('%s MUST wrap the team row name instead of truncating it with an ellipsis.', $path)
        );
    }

    /**
     * Creating an appointment confirms once. The modal closes, hands the
     * payload to its parent through the pinned `created` emit and lets the page
     * paint the only confirmation (with its "Ver en la agenda" action). A
     * `toast.success('Cita creada…')` here is the duplicate this round removes.
     */
    public function test_create_does_not_confirm_twice(): void
    {
        $path = dirname(__DIR__, 3).self::MODAL_PATH;
        $src = self::readSource($path);
        $this->assertNotNull($src, sprintf('%s must be readable.', $path));

        $this->assertSame(
            0,
            preg_match('#toast\.success\(\s*[\'"]Cita creada#', $src),
            sprintf(
                '%s MUST NOT toast the creation itself: the page owns the single confirmation '
                .'(toast + broadcast echo + the page line is exactly the stacking this round removes).',
                $path
            )
        );

        $this->assertTrue(
            (bool) preg_match('#emit\(\s*[\'"]created[\'"]\s*,\s*createdPayload\(#', $src),
            sprintf('%s MUST emit `created` with the created payload (id, day and summary).', $path)
        );
        $this->assertStringContainsString(
            'const createdPayload = (response, appointmentData) => {',
            $src,
            sprintf('%s MUST build the created payload it hands to the parent.', $path)
        );
        $this->assertTrue(
            (bool) preg_match('#size="3xl"#', $src),
            sprintf(
                '%s MUST use the 768 px dialog size (`3xl`): the 512 px one squeezed the team rows and the '
                .'672 px one cut the date field and the fourth hour column.',
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
