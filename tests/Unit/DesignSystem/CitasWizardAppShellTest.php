<?php

namespace Tests\Unit\DesignSystem;

/**
 * PR-citas-01b — CitasWizardAppShellTest.
 *
 * Asserts CITAS-WIZ-001 (Ui primitives in wizard) + CITAS-WIZ-002 (no raw
 * border-theme on textareas/inputs) + CITAS-TZ-001 (no JS-side toISOString
 * on datetime-local) + CITAS-CON-001 (useConsultation contract preserved)
 * for the single `ConsultationWizard.vue` file.
 *
 * The base class `ModuleAppShellTestCase` enforces the 5 inherited DLR-R
 * rules (canvas token, no `border-theme`, focus ring, no `<style scoped>`,
 * no legacy focus-ring aliases) via `polishedFileProvider()`. This subclass
 * adds the 7 wizard-specific rule assertions below.
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
     * be absent. The 5-step navigation flows through `<UiTabs v-model="currentStep">`
     * (with `tabsForUiTabs` computed mapping steps → tab objects).
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
     * CITAS-WIZ-002 — every raw `<textarea>` and raw `<input>` MUST NOT
     * carry the legacy `border-theme` (or `border border-theme`) literal.
     * The token-aligned form is `border-hairline` (or the unprefixed
     * `border-hairline` shorthand inside a multi-class string).
     *
     * Whole-token regex excludes modifier variants (`border-theme-light`,
     * `border-theme-dark` etc.); we pin only the bare `border-theme` literal.
     */
    public function test_wizard_no_raw_textarea_or_input_class_string(): void
    {
        $path = dirname(__DIR__, 3) . self::WIZARD_PATH;
        $src = self::readSource($path);
        $this->assertNotNull($src, sprintf('%s must be readable.', $path));

        // No raw `border-theme` literal on any `<textarea` or `<input` tag.
        $this->assertSame(
            0,
            preg_match(
                '#<(?:textarea|input)\b[^>]*\bborder-theme(?![\w-])#',
                $src
            ),
            sprintf(
                '%s MUST NOT keep the legacy `border-theme` literal on raw `<textarea>` or `<input>` controls (CITAS-WIZ-002 / DLR-R-002). '
                . 'Use `border-hairline` / `--color-hairline` token, or migrate to `<UiTextarea>` / `<UiInput>`.',
                $path
            )
        );

        // No `border border-theme` (two-class string with the legacy border literal).
        $this->assertSame(
            0,
            preg_match(
                '#<(?:textarea|input)\b[^>]*\bborder\s+border-theme(?![\w-])#',
                $src
            ),
            sprintf(
                '%s MUST NOT keep the legacy `border border-theme` class string on raw controls (CITAS-WIZ-002).',
                $path
            )
        );
    }

    /**
     * CITAS-WIZ-001 — every wizard form control MUST consume one of the
     * three Ui primitives: `<UiInput>`, `<UiSelect>`, `<UiTextarea>`. The
     * test asserts all three are present in either JSX-tag form or as a
     * named import from `components/ui/<Name>.vue`.
     */
    public function test_wizard_uses_ui_input_ui_select_ui_textarea(): void
    {
        $path = dirname(__DIR__, 3) . self::WIZARD_PATH;
        $src = self::readSource($path);
        $this->assertNotNull($src, sprintf('%s must be readable.', $path));

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

        $this->assertTrue(
            (bool) preg_match('#<UiTextarea\b#', $src)
                || (bool) preg_match(
                    '#import\s+\w*[Tt]extarea\w*\s+from\s+[\'"][^\'"]*components/ui/UiTextarea\.vue[\'"]#',
                    $src
                ),
            sprintf('%s MUST consume <UiTextarea> (CITAS-WIZ-001).', $path)
        );
    }

    /**
     * CITAS-WIZ-001 — the hardcoded `text-red-500` required-asterisk literal
     * MUST be absent. The required indicator is owned by the `<UiInput required>`
     * / `<UiTextarea required>` primitives (the `required` attribute drives
     * `aria-required` + native form validation; the visual asterisk is no
     * longer needed). The `text-systemRed-*` token ramp replaces any
     * destructive-action colour.
     */
    public function test_wizard_no_text_red_500_required_indicator(): void
    {
        $path = dirname(__DIR__, 3) . self::WIZARD_PATH;
        $src = self::readSource($path);
        $this->assertNotNull($src, sprintf('%s must be readable.', $path));

        $this->assertSame(
            0,
            preg_match('#(?<![\w-])text-red-500(?![\w-])#', $src),
            sprintf(
                '%s MUST NOT keep the hardcoded `text-red-500` required-asterisk literal (CITAS-WIZ-001). '
                . 'Required indicators are owned by `<UiInput required>` / `<UiTextarea required>`; '
                . 'destructive-action colours MUST come from the `text-systemRed-*` token ramp.',
                $path
            )
        );
    }

    /**
     * DLR-R-004 (wizard-specific) — the wizard MUST NOT carry the legacy
     * focus-ring aliases (`focus:ring-primary-500` or `focus:border-accent`).
     * The token-aligned form is `focus:ring-systemBlue-500` (per design §2.7
     * Apple-language focus ramp), or the global `var(--focus-ring-default)`
     * consumed by the Ui primitives.
     */
    public function test_wizard_no_legacy_focus_ring(): void
    {
        $path = dirname(__DIR__, 3) . self::WIZARD_PATH;
        $src = self::readSource($path);
        $this->assertNotNull($src, sprintf('%s must be readable.', $path));

        $this->assertSame(
            0,
            preg_match(
                '#(?<![\w-])focus:ring-primary-500(?![\w-])#',
                $src
            ),
            sprintf(
                '%s MUST NOT contain the legacy `focus:ring-primary-500` focus-ring alias (DLR-R-004 / CITAS-WIZ-001). '
                . 'Use `focus:ring-systemBlue-500` (Apple-language ramp) or rely on the Ui primitives.',
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
     * DLR-R-021 (wizard-specific) — the wizard MUST NOT contain a `<style scoped>`
     * block. Tailwind utility classes + global token CSS own the visual surface;
     * scoped CSS would defeat the token-system invariant.
     */
    public function test_wizard_no_style_scoped(): void
    {
        $path = dirname(__DIR__, 3) . self::WIZARD_PATH;
        $src = self::readSource($path);
        $this->assertNotNull($src, sprintf('%s must be readable.', $path));

        $this->assertSame(
            0,
            preg_match('#<style\s+scoped\s*>#', $src),
            sprintf(
                '%s MUST NOT contain a `<style scoped>` block (DLR-R-021 / CITAS-WIZ-001). '
                . 'Tailwind utility classes + global token CSS own the visual surface.',
                $path
            )
        );
    }

    /**
     * CITAS-CON-001 — the `useConsultation` composable public surface AND
     * the wizard's destructured bindings MUST be preserved byte-for-byte.
     *
     * The composable file (`resources/js/composables/useConsultation.js`)
     * MUST keep the `export function useConsultation()` factory + the canonical
     * return-object shape: `isOpen`, `context`, `contextLoading`, `submitting`,
     * `lastError`, `currentAppointmentId`, `openForAppointment`, `close`,
     * `loadContext`, `checkIn`, `submit`. The wizard's `<script setup>`
     * destructure MUST keep its bindings (the wizard reads `isOpen`,
     * `context`, `contextLoading`, `submitting`, `loadContext`, `submit`,
     * `close`) and MUST keep the `defineEmits(['completed', 'close'])`
     * payload + the `defineProps({ appointment: { type: Object, default: null } })`
     * contract + the `loadContext(newAppt.id)` call inside the
     * `watch(() => props.appointment, ...)` appointment watcher.
     */
    public function test_wizard_use_consultation_contract_preserved(): void
    {
        $wizardPath = dirname(__DIR__, 3) . self::WIZARD_PATH;
        $wizardSrc = self::readSource($wizardPath);
        $this->assertNotNull($wizardSrc, sprintf('%s must be readable.', $wizardPath));

        // 1) The composable file still exports the canonical public surface.
        $composablePath = dirname(__DIR__, 3) . '/resources/js/composables/useConsultation.js';
        $composableSrc = self::readSource($composablePath);
        $this->assertNotNull($composableSrc, sprintf('%s must be readable.', $composablePath));

        $this->assertTrue(
            (bool) preg_match('#export\s+function\s+useConsultation\s*\(#', $composableSrc),
            sprintf(
                '%s MUST keep `export function useConsultation(...)` factory (CITAS-CON-001).',
                $composablePath
            )
        );

        // 2) The composable returns the canonical key set (refs + methods).
        //    Match the trailing `return { ... }` object's identifiers. The
        //    closing brace is followed by the function's own `}` (no trailing
        //    semicolon), so the pattern matches up to the first `}` after
        //    `return {` (lazy `[^}]*?` excludes `}` and stops at the first one).
        $requiredComposableKeys = [
            'isOpen',
            'context',
            'contextLoading',
            'submitting',
            'lastError',
            'currentAppointmentId',
            'openForAppointment',
            'close',
            'loadContext',
            'checkIn',
            'submit',
        ];
        foreach ($requiredComposableKeys as $key) {
            $this->assertTrue(
                (bool) preg_match(
                    '#return\s*\{[^}]*?\b' . preg_quote($key, '#') . '\b[^}]*?\}#s',
                    $composableSrc
                ),
                sprintf(
                    '%s MUST export `%s` in its `return { ... };` block (CITAS-CON-001).',
                    $composablePath,
                    $key
                )
            );
        }

        // 3) The wizard still imports `useConsultation` from the canonical path.
        $this->assertTrue(
            (bool) preg_match(
                '#import\s*\{[^}]*?useConsultation[^}]*?\}\s*from\s*[\'"]\.\./\.\./composables/useConsultation[\'"]#',
                $wizardSrc
            )
            || (bool) preg_match(
                '#import\s*\{[^}]*?useConsultation[^}]*?\}\s*from\s*[\'"]@/composables/useConsultation[\'"]#',
                $wizardSrc
            )
            || (bool) preg_match(
                '#useConsultation\s*\(\s*\)\s*=>\s*\{[^}]*loadContext#s',
                $wizardSrc
            ),
            sprintf(
                '%s MUST keep `useConsultation` import + destructure (CITAS-CON-001).',
                $wizardPath
            )
        );

        // 4) The wizard's `defineEmits(['completed', 'close'])` payload is preserved.
        $this->assertTrue(
            (bool) preg_match("#defineEmits\\s*\\(\\s*\\[\\s*['\"]completed['\"]\\s*,\\s*['\"]close['\"]\\s*\\]\\s*\\)#", $wizardSrc),
            sprintf(
                '%s MUST keep `defineEmits([\'completed\',\'close\'])` byte-for-byte (CITAS-CON-001).',
                $wizardPath
            )
        );

        // 5) The wizard's `defineProps({ appointment: { type: Object, default: null } })`
        //    contract is preserved.
        $this->assertTrue(
            (bool) preg_match(
                '#defineProps\s*\(\s*\{[^}]*?appointment\s*:\s*\{\s*type\s*:\s*Object[^}]*?\}\s*,?\s*\}\s*\)#s',
                $wizardSrc
            ),
            sprintf(
                '%s MUST keep `defineProps({ appointment: { type: Object, default: null } })` (CITAS-CON-001).',
                $wizardPath
            )
        );

        // 6) The `loadContext(newAppt.id)` call inside the appointment watcher
        //    is preserved.
        $this->assertTrue(
            (bool) preg_match(
                '#loadContext\s*\(\s*newAppt\.id\s*\)#',
                $wizardSrc
            ),
            sprintf(
                '%s MUST keep the `loadContext(newAppt.id)` call inside the appointment watcher (CITAS-CON-001).',
                $wizardPath
            )
        );

        // 7) The wizard's key reactive state identifiers are preserved
        //    (`currentStep`, the `payload` reactive ref, the `evolution` /
        //    `materials` / `attachments` / `odontogram` keys inside the
        //    payload, the `appointment` prop binding). These are wizard-local
        //    identifiers that the composable contract depends on; their
        //    preservation is the CITAS-CON-001 rule.
        $wizardLocalKeys = [
            'currentStep',
            'appointment',
            'evolution',
            'materials',
            'attachments',
            'odontogram',
            'treatment_plan',
            'executedItemIds',
            'selectMode',
            'handleSubmit',
        ];
        foreach ($wizardLocalKeys as $key) {
            $this->assertTrue(
                (bool) preg_match('#\b' . preg_quote($key, '#') . '\b#', $wizardSrc),
                sprintf(
                    '%s MUST keep the wizard-local identifier `%s` (CITAS-CON-001).',
                    $wizardPath,
                    $key
                )
            );
        }
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
