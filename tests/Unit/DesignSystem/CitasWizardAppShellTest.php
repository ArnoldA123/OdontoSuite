<?php

namespace Tests\Unit\DesignSystem;

/**
 * PR-citas-01 — CitasWizardAppShellTest.
 *
 * Asserts CITAS-WIZ-001 (Ui primitives in wizard) + CITAS-TZ-001 (no JS-side
 * toISOString on datetime-local) + CITAS-CON-001 (useConsultation contract
 * preserved) for the single `ConsultationWizard.vue` file.
 *
 * The base class `ModuleAppShellTestCase` enforces the 5 inherited DLR-R
 * rules (canvas token, no `border-theme`, focus ring, no `<style scoped>`,
 * no legacy focus-ring aliases). This subclass adds the 4 PR-citas-01-only
 * rules below.
 *
 * Per CITAS-CON-001, the `<script>` block of `ConsultationWizard.vue` is
 * NEVER edited in this PR; the rule is asserted by
 * `test_wizard_use_consultation_contract_preserved` (the `useConsultation`
 * composable destructure + `loadContext` + `submit` calls + `defineEmits`
 * payload stay byte-for-byte).
 *
 * Implementation note: regex delimiters are `#` (NOT `/`) because the path
 * patterns contain forward slashes; using `/` as delimiter would force
 * every `/` in the path to be escaped `\/`, which is brittle and error-prone.
 */
class CitasWizardAppShellTest extends ModuleAppShellTestCase
{
    /** Wizard path constant — used by the data provider + the single-file
     *  rules. Keeps a single source of truth for the absolute path. */
    private const WIZARD_PATH = '/resources/js/modules/appointments/ConsultationWizard.vue';

    /** @return array<int, string> */
    protected static function polishedFiles(): array
    {
        return [
            dirname(__DIR__, 3) . self::WIZARD_PATH,
        ];
    }

    /**
     * CITAS-WIZ-001 — the wizard's step strip MUST consume `<UiTabs>` AND
     * the legacy inline `@click="currentStep = step.id"` step handler MUST
     * be absent. Single-file rule (the wizard is the only step strip in
     * the module).
     */
    public function test_wizard_uses_ui_tabs_for_step_strip(): void
    {
        $path = dirname(__DIR__, 3) . self::WIZARD_PATH;
        $src = self::readSource($path);
        $this->assertNotNull($src, sprintf('%s must be readable.', $path));

        // UiTabs is consumed (either as a JSX-style tag or as an import).
        $this->assertTrue(
            (bool) preg_match('#<UiTabs\b#', $src)
                || (bool) preg_match(
                    '#import\s+\w*[Tt]abs\w*\s+from\s+[\'"][^\'"]*components/ui/Tabs\.vue[\'"]#',
                    $src
                ),
            sprintf(
                '%s MUST consume <UiTabs> for the 5-step navigation strip (CITAS-WIZ-001).',
                $path
            )
        );

        // The inline `@click="currentStep = step.id"` step handler is the
        // anti-rule; its presence means the step strip was NOT migrated to
        // <UiTabs v-model="currentStep">.
        $this->assertSame(
            0,
            preg_match('#@click\s*=\s*["\']currentStep\s*=\s*step\.id["\']#', $src),
            sprintf(
                '%s MUST NOT keep the inline `@click="currentStep = step.id"` step handler (CITAS-WIZ-001). '
                . 'Step navigation flows through `<UiTabs v-model="currentStep">`.',
                $path
            )
        );
    }

    /**
     * CITAS-WIZ-001 — every raw `<input class="border-theme">`,
     * `<select class="border-theme">`, and `<textarea class="border-theme">`
     * control MUST be replaced by the canonical primitive (<UiInput>,
     * <UiSelect>, <UiTextarea>). We pin the absence of the legacy
     * `border-theme` literal across the wizard (DLR-R-002) — the inherited
     * rule already covers this, but we re-assert it as a single-file rule
     * so a regression names the wizard explicitly.
     */
    public function test_wizard_uses_ui_form_primitives(): void
    {
        $path = dirname(__DIR__, 3) . self::WIZARD_PATH;
        $src = self::readSource($path);
        $this->assertNotNull($src, sprintf('%s must be readable.', $path));

        // <UiInput> / <UiSelect> are both consumed.
        $this->assertTrue(
            (bool) preg_match('#<UiInput\b#', $src)
                || (bool) preg_match(
                    '#import\s+\w*[Ii]nput\w*\s+from\s+[\'"][^\'"]*components/ui/Input\.vue[\'"]#',
                    $src
                ),
            sprintf('%s MUST consume <UiInput> (CITAS-WIZ-001).', $path)
        );

        $this->assertTrue(
            (bool) preg_match('#<UiSelect\b#', $src)
                || (bool) preg_match(
                    '#import\s+\w*[Ss]elect\w*\s+from\s+[\'"][^\'"]*components/ui/Select\.vue[\'"]#',
                    $src
                ),
            sprintf('%s MUST consume <UiSelect> (CITAS-WIZ-001).', $path)
        );

        // `border-theme` literal is absent (DLR-R-002; whole-token regex
        // excludes `border-theme-light` / `border-theme-dark`).
        $this->assertSame(
            0,
            preg_match('#(?<![\w-])border-theme(?![\w-])#', $src),
            sprintf(
                '%s MUST NOT contain the legacy `border-theme` literal (CITAS-WIZ-001 / DLR-R-002). '
                . 'Use `border-hairline` / `--color-hairline`.',
                $path
            )
        );
    }

    /**
     * CITAS-WIZ-001 — the wizard's mode-selection buttons (consultation /
     * execution / plan_session) consume `<UiStatusBadge>` for the variant
     * mapping. Also asserts the hardcoded `text-red-500` required-asterisk
     * literal is absent (the `<UiInput required>` indicator owns the
     * required marker now).
     */
    public function test_wizard_mode_uses_status_badge_no_red_asterisk_literal(): void
    {
        $path = dirname(__DIR__, 3) . self::WIZARD_PATH;
        $src = self::readSource($path);
        $this->assertNotNull($src, sprintf('%s must be readable.', $path));

        $this->assertTrue(
            (bool) preg_match('#<UiStatusBadge\b#', $src)
                || (bool) preg_match(
                    '#import\s+\w*[Bb]adge\w*\s+from\s+[\'"][^\'"]*components/ui/StatusBadge\.vue[\'"]#',
                    $src
                ),
            sprintf('%s MUST consume <UiStatusBadge> for the mode chips (CITAS-WIZ-001).', $path)
        );

        // `text-red-500` literal is the legacy required-asterisk colour.
        // `<UiInput required>` (or any Ui* primitive) owns the indicator.
        $this->assertSame(
            0,
            preg_match('#(?<![\w-])text-red-500(?![\w-])#', $src),
            sprintf(
                '%s MUST NOT keep the hardcoded `text-red-500` required-asterisk literal (CITAS-WIZ-001). '
                . 'Required indicators are owned by `<UiInput required>`.',
                $path
            )
        );
    }

    /**
     * CITAS-TZ-001 — zero JS-side `.toISOString()` calls on any
     * `datetime-local` input value. The server interprets naive local
     * time as `app.timezone` per `AppointmentService::createAppointment`
     * (`Carbon::parse($data['scheduled_at'])->setTimezone(config('app.timezone'))`).
     * The migration `2026_06_02_173228_fix_appointments_timezone_offset`
     * exists precisely because this was once wrong.
     */
    public function test_wizard_no_js_side_to_iso_string_on_datetime_local(): void
    {
        $path = dirname(__DIR__, 3) . self::WIZARD_PATH;
        $src = self::readSource($path);
        $this->assertNotNull($src, sprintf('%s must be readable.', $path));

        $this->assertSame(
            0,
            preg_match('#\.toISOString\s*\(\s*\)#', $src),
            sprintf(
                '%s MUST NOT contain a JS-side `.toISOString()` call (CITAS-TZ-001). '
                . 'The server normalises naive local datetime-local values via `app.timezone`.',
                $path
            )
        );
    }

    /**
     * CITAS-CON-001 — the `useConsultation` composable destructure + the
     * `<script setup>` contract are preserved byte-for-byte. The wizard's
     * reactivity (`currentStep`, `payload`, `executedItemIds`,
     * `catalogResults`, `productResults`, `selectMode`, `addItem`,
     * `removeItem`, `onProcedureNameInput`, `selectProcedure`,
     * `closeCatalogResults`, `addMaterial`, `removeMaterial`,
     * `onProductSearchInput`, `selectProduct`, `closeProductResults`,
     * `addAttachment`, `removeAttachment`, `onFileSelected`, `nextStep`,
     * `prevStep`, `handleClose`, `handleSubmit`, `formatDateTime`,
     * `canSubmit`, the `watch` on `props.appointment`, and the
     * `watch(selectedPlan, ...)` deep watcher) are owned by the composable
     * contract and MUST NOT be touched.
     *
     * This rule pins the SURVIVAL of the key script-block identifiers;
     * it does NOT assert byte-for-byte text equality (that would be
     * brittle against whitespace + comment changes). It pins:
     *
     *   - the `useConsultation` import is present
     *   - the `defineEmits(['completed', 'close'])` payload is preserved
     *   - the `defineProps` with `appointment` prop is preserved
     *   - the `loadContext(newAppt.id)` call inside the appointment watcher
     *     is preserved
     */
    public function test_wizard_use_consultation_contract_preserved(): void
    {
        $path = dirname(__DIR__, 3) . self::WIZARD_PATH;
        $src = self::readSource($path);
        $this->assertNotNull($src, sprintf('%s must be readable.', $path));

        // Three alternative import paths (relative `../../`, alias `@/`, and
        // the destructured arrow body anchor) — accept any.
        $this->assertTrue(
            (bool) preg_match(
                '#import\s*\{[^}]*?useConsultation[^}]*?\}\s*from\s*[\'"]\.\./\.\./composables/useConsultation[\'"]#',
                $src
            )
            || (bool) preg_match(
                '#import\s*\{[^}]*?useConsultation[^}]*?\}\s*from\s*[\'"]@/composables/useConsultation[\'"]#',
                $src
            )
            || (bool) preg_match(
                '#useConsultation\s*\(\s*\)\s*=>\s*\{[^}]*loadContext#s',
                $src
            ),
            sprintf(
                '%s MUST keep `useConsultation` import + destructure (CITAS-CON-001).',
                $path
            )
        );

        $this->assertTrue(
            (bool) preg_match("#defineEmits\\s*\\(\\s*\\[\\s*['\"]completed['\"]\\s*,\\s*['\"]close['\"]\\s*\\]\\s*\\)#", $src),
            sprintf(
                '%s MUST keep `defineEmits([\'completed\',\'close\'])` byte-for-byte (CITAS-CON-001).',
                $path
            )
        );

        $this->assertTrue(
            (bool) preg_match(
                '#defineProps\s*\(\s*\{[^}]*?appointment\s*:\s*\{\s*type\s*:\s*Object[^}]*?\}\s*,?\s*\}\s*\)#s',
                $src
            ),
            sprintf(
                '%s MUST keep `defineProps({ appointment: { type: Object, default: null } })` (CITAS-CON-001).',
                $path
            )
        );

        $this->assertTrue(
            (bool) preg_match(
                '#loadContext\s*\(\s*newAppt\.id\s*\)#',
                $src
            ),
            sprintf(
                '%s MUST keep the `loadContext(newAppt.id)` call inside the appointment watcher (CITAS-CON-001).',
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
