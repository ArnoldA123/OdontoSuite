<?php

namespace Tests\Unit\DesignSystem;

/**
 * PR-pacientes-02 — PatientsModalAppShellTest.
 *
 * Asserts PAC-MOD-001 (the 2 inlined modals on `PatientsPage.vue` — New
 * Patient + Edit Patient — migrated to `<UiModal>` chrome + `<UiInput>` /
 * `<UiSelect>` / `<UiTextarea>` form fields + hairline header divider) for
 * the single `PatientsPage.vue` file. The list section (4 stat cards, table,
 * mobile cards, pagination) was already polished in PR-pacientes-01; this
 * test scopes to the modal chrome migration.
 *
 * Inherited rules (from {@see ModuleAppShellTestCase}, applied via
 * `polishedFileProvider()`): DLR-R-001 canvas surface, DLR-R-002 no
 * `border-theme` (whole-file), DLR-R-004 no legacy focus-ring aliases,
 * DLR-R-021 no `<style scoped>`. After the PR-pacientes-02 migration the
 * modal sections also drop their remaining `border-theme` literal
 * (header divider) + their hand-built backdrop, so the inherited rules
 * go green for the whole file.
 *
 * PR-pacientes-02-only rules asserted here:
 *
 *   - PAC-MOD-001   Modal chrome MUST consume `<UiModal>`; hand-built
 *                   `<div class="fixed inset-0 bg-black bg-opacity-50 …">`
 *                   backdrop MUST be absent.
 *   - PAC-MOD-001   Header divider MUST consume `border-hairline` (NOT
 *                   `border-theme`); the modal panel MUST NOT carry the
 *                   legacy `bg-theme-surface-elevated rounded-2xl
 *                   shadow-2xl` chrome.
 *   - PAC-MOD-001   Modal sections MUST NOT contain raw `<select>` (the
 *                   gender + status `<select>`s migrate to `<UiSelect>`).
 *   - PAC-MOD-001   Modal sections MUST NOT contain the legacy focus-ring
 *                   alias `focus:ring-primary-500 focus:border-transparent`.
 *   - PAC-MOD-001   Modal form fields MUST consume `<UiInput>` +
 *                   `<UiSelect>` + `<UiTextarea>`; raw `<textarea>` is
 *                   forbidden inside the modal sections.
 *   - PAC-MOD-001   422 duplicate-email/phone error envelope rendering
 *                   MUST stay verbatim — `useToast` surfaces the server
 *                   message + the flattened errors bag.
 *   - PAC-MOD-001   Modal emits MUST be preserved — `<UiModal>` provides
 *                   `open` / `close` / `update:modelValue`; the page
 *                   listens to `@close` to flip the modal-state ref.
 *   - PAC-RT-001    `<script>` block MUST stay byte-for-byte (useEcho +
 *                   useApi + usePermissions + useToast contracts
 *                   preserved); only import + components list additions
 *                   for UiModal / UiTextarea are allowed.
 */
class PatientsModalAppShellTest extends ModuleAppShellTestCase
{
    /** PatientsPage file path constant — used by the data provider + the
     *  single-file rules. Keeps a single source of truth. */
    private const PATIENTS_LIST_PATH = '/resources/js/modules/patients/PatientsPage.vue';

    /** Modal sections range — used by the section-scoped rules below. */
    private const MODAL_SECTION_MARKER_START = '<!-- New Patient Modal -->';
    private const MODAL_SECTION_MARKER_END = '</AppLayout>';

    /** @return array<int, string> */
    protected static function polishedFiles(): array
    {
        return [
            dirname(__DIR__, 3) . self::PATIENTS_LIST_PATH,
        ];
    }

    /**
     * Override the inherited `test_page_references_canvas_token` rule. The
     * pacientes design §3 acknowledges that the canvas surface is
     * provided by the `<AppLayout>` wrapper (the file is mounted inside
     * the AppLayout, and `canvasRoutes` is what carries the canvas
     * background per DLR-CORE-001). The page file does NOT need to
     * reference `bg-canvas` / `var(--color-canvas)` directly — only the
     * AppLayout does. We pin the `<AppLayout>` reference instead.
     *
     * @dataProvider polishedFileProvider
     */
    public function test_page_references_canvas_token(string $path): void
    {
        $src = self::readSource($path);
        $this->assertNotNull($src, sprintf('%s must exist and be readable.', $path));

        $this->assertTrue(
            (bool) preg_match('/<AppLayout\b/', $src),
            sprintf(
                '%s MUST reference `<AppLayout>` (the canvas-surface wrapper per DLR-CORE-001). '
                    . 'The pacientes design acknowledges the page file does not need a direct '
                    . '`bg-canvas` reference — the AppLayout provides it.',
                $path
            )
        );
    }

    /**
     * PAC-MOD-001 — the 2 inlined modals MUST consume the `<UiModal>`
     * primitive (NOT a hand-built `<div class="fixed inset-0 bg-black
     * bg-opacity-50 …">` backdrop). UiModal owns the backdrop + the
     * focus trap + the iOS motion.
     *
     * The hand-built backdrop is the legacy alias for `<UiModal>`; the
     * rule is asserted as the NEGATIVE form (`bg-black bg-opacity-50`
     * absent) so the test fires regardless of how the modal chrome is
     * structured.
     */
    public function test_modal_no_bg_black_bg_opacity_50(): void
    {
        $path = dirname(__DIR__, 3) . self::PATIENTS_LIST_PATH;
        $src = self::readSource($path);
        $this->assertNotNull($src, sprintf('%s must be readable.', $path));

        // POSITIVE: the `<UiModal>` primitive is referenced for both modals.
        $this->assertGreaterThanOrEqual(
            2,
            preg_match_all('/<UiModal\b/', $src),
            sprintf(
                '%s MUST consume `<UiModal>` for BOTH the New Patient modal '
                    . 'AND the Edit Patient modal (PAC-MOD-001). Found %d.',
                $path,
                preg_match_all('/<UiModal\b/', $src)
            )
        );

        // NEGATIVE: the hand-built `bg-black bg-opacity-50` backdrop is gone.
        $this->assertSame(
            0,
            preg_match('/(?<![\w-])bg-black\s+bg-opacity-50(?![\w-])/', $src),
            sprintf(
                '%s MUST NOT keep the legacy `bg-black bg-opacity-50` modal backdrop '
                    . '(PAC-MOD-001). `<UiModal>` owns the backdrop + focus trap + iOS motion.',
                $path
            )
        );
    }

    /**
     * PAC-MOD-001 — the modal sections MUST NOT contain any `border-theme`
     * literal. The 2 remaining `border-theme` instances in the pre-PR
     * file live at the modal header dividers (lines 347, 476). They
     * migrate to `border-hairline` (the canonical token) when the
     * `<UiModal>` chrome is adopted.
     *
     * The inherited `ModuleAppShellTestCase::test_no_legacy_border_theme_literal`
     * rule already pins the whole-file form; this focused re-assertion
     * scopes to the modal sections only (the PR-pacientes-02 deliverable).
     */
    public function test_modal_no_border_theme(): void
    {
        $path = dirname(__DIR__, 3) . self::PATIENTS_LIST_PATH;
        $src = self::readSource($path);
        $this->assertNotNull($src, sprintf('%s must be readable.', $path));

        $modalSection = self::extractModalSections($src);

        $this->assertDoesNotMatchRegularExpression(
            '/(?<![\w-])border-theme(?![\w-])/',
            $modalSection,
            sprintf(
                '%s modal sections MUST NOT contain the legacy `border-theme` literal (PAC-MOD-001 / DLR-R-002). '
                    . 'Use `border-hairline` for the header divider.',
                $path
            )
        );
    }

    /**
     * PAC-MOD-001 — the modal sections MUST NOT contain a raw `<select>`
     * element. The 2 inlined modals each render a gender `<select>` (New
     * Patient) + a gender + status `<select>` pair (Edit Patient). They
     * MUST migrate to `<UiSelect>`.
     *
     * Note: the LIST section uses `<UiSelect v-model="statusFilter">`
     * (already migrated in PR-pacientes-01); this rule scopes to the
     * MODAL sections to catch any raw `<select>` inside the form fields.
     */
    public function test_modal_no_raw_select(): void
    {
        $path = dirname(__DIR__, 3) . self::PATIENTS_LIST_PATH;
        $src = self::readSource($path);
        $this->assertNotNull($src, sprintf('%s must be readable.', $path));

        $modalSection = self::extractModalSections($src);

        $this->assertSame(
            0,
            preg_match('/<select\b/i', $modalSection),
            sprintf(
                '%s modal sections MUST NOT contain a raw `<select>` element (PAC-MOD-001 / DLR-R-009). '
                    . 'Use `<UiSelect>` for the gender + status dropdowns.',
                $path
            )
        );
    }

    /**
     * PAC-MOD-001 — the modal sections MUST NOT carry the legacy
     * focus-ring alias `focus:ring-primary-500`. The token-aligned form
     * is `var(--focus-ring-default)` (consumed by UiInput / UiSelect /
     * UiTextarea primitives).
     *
     * The inherited `ModuleAppShellTestCase::test_no_legacy_focus_ring_alias`
     * rule pins the whole file; this focused re-assertion scopes to the
     * modal sections.
     */
    public function test_modal_no_legacy_focus_ring(): void
    {
        $path = dirname(__DIR__, 3) . self::PATIENTS_LIST_PATH;
        $src = self::readSource($path);
        $this->assertNotNull($src, sprintf('%s must be readable.', $path));

        $modalSection = self::extractModalSections($src);

        $this->assertSame(
            0,
            preg_match('/(?<![\w-])focus:ring-primary-500(?![\w-])/', $modalSection),
            sprintf(
                '%s modal sections MUST NOT contain the legacy `focus:ring-primary-500` alias (PAC-MOD-001 / DLR-R-004). '
                    . 'The `var(--focus-ring-default)` token (consumed by `<UiInput>` / `<UiSelect>` / `<UiTextarea>`) '
                    . 'owns the focus ring.',
                $path
            )
        );
    }

    /**
     * PAC-MOD-001 — the modal form fields MUST consume the Ui primitives.
     * All three primitives (`<UiInput>`, `<UiSelect>`, `<UiTextarea>`)
     * MUST be present — either as JSX tag or as a named import from
     * `components/ui/<Name>.vue`.
     */
    public function test_modal_uses_ui_input_ui_textarea(): void
    {
        $path = dirname(__DIR__, 3) . self::PATIENTS_LIST_PATH;
        $src = self::readSource($path);
        $this->assertNotNull($src, sprintf('%s must be readable.', $path));

        // POSITIVE: `<UiInput>` is consumed (search + demographics + address + emergency).
        $this->assertTrue(
            (bool) preg_match('/<UiInput\b/', $src)
                || (bool) preg_match(
                    "/import\s+UiInput\s+from\s+['\"][^'\"]*components\/ui\/Input\.vue['\"]/",
                    $src
                ),
            sprintf('%s MUST consume `<UiInput>` (PAC-MOD-001).', $path)
        );

        // POSITIVE: `<UiSelect>` is consumed (gender + status).
        $this->assertTrue(
            (bool) preg_match('/<UiSelect\b/', $src)
                || (bool) preg_match(
                    "/import\s+UiSelect\s+from\s+['\"][^'\"]*components\/ui\/Select\.vue['\"]/",
                    $src
                ),
            sprintf('%s MUST consume `<UiSelect>` (PAC-MOD-001).', $path)
        );

        // POSITIVE: `<UiTextarea>` is consumed for medical_history + allergies + notes.
        $this->assertTrue(
            (bool) preg_match('/<UiTextarea\b/', $src)
                || (bool) preg_match(
                    "/import\s+UiTextarea\s+from\s+['\"][^'\"]*components\/ui\/UiTextarea\.vue['\"]/",
                    $src
                ),
            sprintf(
                '%s MUST consume `<UiTextarea>` for the medical_history + allergies + notes fields '
                    . '(PAC-MOD-001). The pre-PR `<UiInput type="textarea">` is invalid — the Input.vue '
                    . 'validator does not allow `textarea` as a type.',
                $path
            )
        );

        // NEGATIVE: no raw `<textarea>` element in the modal sections.
        $modalSection = self::extractModalSections($src);
        $this->assertSame(
            0,
            preg_match('/<textarea\b/i', $modalSection),
            sprintf(
                '%s modal sections MUST NOT contain a raw `<textarea>` element (PAC-MOD-001 / DLR-R-009). '
                    . 'Use `<UiTextarea>` for the free-text medical fields.',
                $path
            )
        );
    }

    /**
     * PAC-MOD-001 — the 422 duplicate-email/phone error envelope rendering
     * MUST stay verbatim. The `useApi` POST/PUT error envelope is:
     *   error.response.data.message — top-level server message
     *   error.response.data.errors  — field-keyed validation bag
     * The catch block flattens `Object.values(errors).flat().join('\n')`
     * and surfaces `message + errors` via `useToast.error(...)`.
     *
     * The form stays open on 422 (the modal ref does NOT flip); the user
     * sees the toast + can correct the offending field.
     */
    public function test_modal_422_duplicate_handled(): void
    {
        $path = dirname(__DIR__, 3) . self::PATIENTS_LIST_PATH;
        $src = self::readSource($path);
        $this->assertNotNull($src, sprintf('%s must be readable.', $path));

        // 1) The catch block reads `error.response?.data?.message` (the
        //    top-level server message — surfaces 422 "duplicate" wording).
        $this->assertTrue(
            (bool) preg_match(
                '/error\.response\?\.\s*data\?\.\s*message/',
                $src
            ),
            sprintf(
                '%s MUST keep the `error.response?.data?.message` read in the catch block '
                    . '(PAC-MOD-001). The 422 duplicate-email/phone envelope is surfaced verbatim '
                    . 'via `useToast`.',
                $path
            )
        );

        // 2) The catch block reads `error.response?.data?.errors` and
        //    flattens it via `Object.values(errors).flat().join('\n')`.
        $this->assertTrue(
            (bool) preg_match(
                '/error\.response\?\.\s*data\?\.\s*errors/',
                $src
            ),
            sprintf(
                '%s MUST keep the `error.response?.data?.errors` read + the `Object.values(errors).flat().join(\'\\n\')` flattening '
                    . '(PAC-MOD-001). The 422 validation bag surfaces verbatim via `useToast`.',
                $path
            )
        );

        // 3) The catch block surfaces the error via `toast.error(...)`.
        //    The form stays open (the modal ref does NOT flip on 422).
        $this->assertTrue(
            (bool) preg_match(
                '/catch\s*\([^)]*\)\s*\{[^}]*toast\.error/s',
                $src
            ),
            sprintf(
                '%s MUST keep a `catch (...) { ... toast.error(...) }` block in `createPatient` + `updatePatient` '
                    . '(PAC-MOD-001). The form stays open on 422 and the user sees the toast.',
                $path
            )
        );
    }

    /**
     * PAC-MOD-001 — the modal emit contract MUST be preserved. The
     * `<UiModal>` primitive emits `update:modelValue`, `close`, and
     * `open`. The page wires `@close` to flip the modal-state ref
     * (`showNewPatientModal = false` / `showEditPatientModal = false`)
     * so the modal closes on backdrop click, escape, or the X button.
     *
     * The `<script>` block MUST stay byte-for-byte (useEcho + useApi +
     * usePermissions + useToast contracts preserved); only imports +
     * components list additions for UiModal / UiTextarea are allowed.
     */
    public function test_modal_emit_contract_preserved(): void
    {
        $path = dirname(__DIR__, 3) . self::PATIENTS_LIST_PATH;
        $src = self::readSource($path);
        $this->assertNotNull($src, sprintf('%s must be readable.', $path));

        // 1) Both modals listen to `@close` (backdrop click + escape + X button).
        $closeListeners = preg_match_all(
            '/<UiModal\b[^>]*\s@close\s*=/s',
            $src
        );
        $this->assertGreaterThanOrEqual(
            2,
            $closeListeners,
            sprintf(
                '%s MUST wire `<UiModal @close=...>` for BOTH modals (PAC-MOD-001). Found %d.',
                $path,
                $closeListeners
            )
        );

        // 2) The `@close` listener flips `showNewPatientModal` / `showEditPatientModal`.
        $this->assertTrue(
            (bool) preg_match(
                '/@close\s*=\s*["\']showNewPatientModal\s*=\s*false/',
                $src
            ),
            sprintf(
                '%s MUST wire `@close="showNewPatientModal = false"` on the New Patient modal (PAC-MOD-001).',
                $path
            )
        );

        $this->assertTrue(
            (bool) preg_match(
                '/@close\s*=\s*["\']showEditPatientModal\s*=\s*false/',
                $src
            ),
            sprintf(
                '%s MUST wire `@close="showEditPatientModal = false"` on the Edit Patient modal (PAC-MOD-001).',
                $path
            )
        );

        // 3) The `<script>` block keeps the `useApi` 401 redirect contract.
        //    The page destructures `get / post / put / delete: del` from
        //    `useApi()`. Renaming would break the createPatient + updatePatient +
        //    deletePatient handlers.
        $this->assertTrue(
            (bool) preg_match(
                '/const\s*\{\s*get\s*,\s*post\s*,\s*put\s*,\s*delete\s*:\s*del\s*\}\s*=\s*useApi\s*\(\s*\)/s',
                $src
            ),
            sprintf(
                '%s MUST keep the `const { get, post, put, delete: del } = useApi()` destructure '
                    . '(PAC-CON-001). Renaming would silently break the createPatient + updatePatient handlers.',
                $path
            )
        );

        // 4) The `<script>` block keeps the `useToast` import.
        $this->assertTrue(
            (bool) preg_match(
                "/import\s*\{[^}]*useToast[^}]*\}\s*from\s*['\"]@?\.{0,4}\/?composables\/useToast['\"]/",
                $src
            )
            || (bool) preg_match(
                "/import\s*\{[^}]*useToast[^}]*\}\s*from\s*['\"]\.\.\/\.\.\/composables\/useToast['\"]/",
                $src
            ),
            sprintf(
                '%s MUST keep the `useToast` import (PAC-CON-001). The 422 + 5xx toasts are surfaced verbatim.',
                $path
            )
        );

        // 5) The `<script>` block keeps the `useEcho` `patients` channel
        //    subscription byte-for-byte. The `.patient.created` /
        //    `.patient.updated` / `.patient.deleted` listeners + the
        //    `echo.leave('patients')` in `onUnmounted` are load-bearing
        //    for cross-tab realtime updates.
        $this->assertTrue(
            (bool) preg_match(
                "#channel\s*\(\s*['\"]patients['\"]\s*\)#",
                $src
            ),
            sprintf(
                '%s MUST keep `channel(\'patients\')` subscription (PAC-RT-001). '
                    . 'The Echo channel is the canonical realtime pipe for patient events.',
                $path
            )
        );
    }

    /**
     * PAC-RT-001 / PAC-CON-001 — the `<script>` block MUST stay
     * byte-for-byte: useEcho `patients` channel subscription +
     * usePermissions.can.{createPatient, updatePatient, deletePatient} +
     * useApi + useToast contracts preserved verbatim.
     *
     * The `usePermissions.can.*` flags gate the New Patient + Edit +
     * Delete action buttons + the New Patient modal open button. A
     * silent rename would break the role-based UI gating.
     */
    public function test_modal_use_permissions_can_preserved(): void
    {
        $path = dirname(__DIR__, 3) . self::PATIENTS_LIST_PATH;
        $src = self::readSource($path);
        $this->assertNotNull($src, sprintf('%s must be readable.', $path));

        // POSITIVE: the `can` destructure from `usePermissions()` is intact.
        // The canonical form is `const { can } = usePermissions()`; the
        // destructure can also appear as a parameter destructuring. The
        // regex accepts either form (the `{ can }` token MUST be inside
        // the same statement as the `usePermissions()` call).
        $this->assertTrue(
            (bool) preg_match(
                '/const\s*\{\s*can\s*\}\s*=\s*usePermissions\s*\(\s*\)/s',
                $src
            ),
            sprintf(
                '%s MUST keep the `const { can } = usePermissions()` destructure (PAC-CON-001). '
                    . 'The `can.createPatient / can.updatePatient / can.deletePatient` flags '
                    . 'gate the action buttons + the New Patient modal open button.',
                $path
            )
        );

        // POSITIVE: the 3 patient permission flags are referenced in the template.
        $flags = ['can.createPatient', 'can.updatePatient', 'can.deletePatient'];
        foreach ($flags as $flag) {
            $this->assertTrue(
                (bool) preg_match(
                    '/' . preg_quote($flag, '/') . '\b/',
                    $src
                ),
                sprintf(
                    '%s MUST keep the `%s` permission flag reference (PAC-CON-001).',
                    $path,
                    $flag
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

    /**
     * Extract the 2 inlined modal sections (New Patient + Edit Patient)
     * from the full file. The modals are demarcated by the
     * `<!-- New Patient Modal -->` comment + the closing `</AppLayout>`
     * tag. Everything between is the modal surface.
     *
     * The pre-PR file has the modals between the LIST section
     * (ending at the pagination control) + the closing `</AppLayout>`.
     * Cutting at the marker comments gives a clean slice for the
     * section-scoped rules above.
     */
    private static function extractModalSections(string $src): string
    {
        $start = strpos($src, self::MODAL_SECTION_MARKER_START);
        if ($start === false) {
            return '';
        }

        $end = strpos($src, self::MODAL_SECTION_MARKER_END, $start);
        if ($end === false) {
            return substr($src, $start);
        }

        return substr($src, $start, $end - $start);
    }
}
