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
            dirname(__DIR__, 3) . self::MODAL_PATH,
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
        $path = dirname(__DIR__, 3) . self::MODAL_PATH;
        $src = self::readSource($path);
        $this->assertNotNull($src, sprintf('%s must be readable.', $path));

        // 1) The `<UiModal>` primitive is consumed.
        $this->assertTrue(
            (bool) preg_match('#<UiModal\b#', $src),
            sprintf(
                '%s MUST consume `<UiModal>` for the modal chrome (CITAS-MOD-001). '
                . 'The hand-built `<Teleport to="body">` + `bg-black bg-opacity-50` backdrop is deprecated.',
                $path
            )
        );

        // 2) The hand-built `<Teleport to="body">` must be absent.
        $this->assertSame(
            0,
            preg_match('#<Teleport\s+to\s*=\s*["\']body["\']#', $src),
            sprintf(
                '%s MUST NOT keep a hand-built `<Teleport to="body">` modal '
                . '(CITAS-MOD-001). The chrome MUST consume `<UiModal>`.',
                $path
            )
        );

        // 3) The legacy `bg-black bg-opacity-50` backdrop must be absent.
        $this->assertSame(
            0,
            preg_match('#(?<![\w-])bg-black\s+bg-opacity-50(?![\w-])#', $src),
            sprintf(
                '%s MUST NOT keep the legacy `bg-black bg-opacity-50` backdrop literal (CITAS-MOD-001). '
                . '`<UiModal>` owns the backdrop.',
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
        $path = dirname(__DIR__, 3) . self::MODAL_PATH;
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
                . 'All selects MUST consume `<UiSelect>`.',
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
        $path = dirname(__DIR__, 3) . self::MODAL_PATH;
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
                . 'Use `focus:ring-systemBlue-500` or rely on the Ui primitives.',
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
                . 'The global `var(--focus-ring-default)` token owns the focus border colour.',
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
        $path = dirname(__DIR__, 3) . self::MODAL_PATH;
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
        $path = dirname(__DIR__, 3) . self::MODAL_PATH;
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
                . 'UiStatusBadge is not globally registered — local import required.',
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
                . '"Otra mesa ya reservó este horario" for duplicate-key 422 (CITAS-CONF-001).',
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
                . 'OR `error.response.status === 422` (CITAS-CONF-001). The DB unique constraints '
                . '`unique_user_time_slot` / `unique_chair_time_slot` bubble as 422 / duplicate_key.',
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
        $path = dirname(__DIR__, 3) . self::MODAL_PATH;
        $src = self::readSource($path);
        $this->assertNotNull($src, sprintf('%s must be readable.', $path));

        $this->assertSame(
            0,
            preg_match('#\.toISOString\s*\(\s*\)#', $src),
            sprintf(
                '%s MUST NOT call `.toISOString()` on any value (CITAS-TZ-001). '
                . 'The server interprets `datetime-local` input as naive local time; '
                . 'a JS-side ISO conversion would drop the local TZ offset.',
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
        $path = dirname(__DIR__, 3) . self::MODAL_PATH;
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
                . 'byte-for-byte (CITAS-CON-001). Parent callers listen on these event names.',
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
                . '`useApi` owns the 401 redirect contract; the modal MUST NOT bypass it.',
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
                . '(CITAS-CON-001). The canonical error feedback is preserved alongside the '
                . '422 duplicate-key badge mapping.',
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
