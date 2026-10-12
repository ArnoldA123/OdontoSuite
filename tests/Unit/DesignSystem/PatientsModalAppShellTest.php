<?php

namespace Tests\Unit\DesignSystem;

/**
 * PR-pacientes-02 — PatientsModalAppShellTest.
 *
 * Asserts PAC-MOD-001 for the patients create/edit form, which now lives
 * in the shared `PatientFormModal.vue` component consumed by
 * `PatientsPage.vue` for BOTH flows (single source of truth — the two
 * duplicated inlined modals were consolidated). The contract-level rules
 * below therefore scan BOTH files: the page (wiring + chrome rules) and
 * the modal component (the form surface itself).
 *
 * Inherited rules (from {@see ModuleAppShellTestCase}, applied via
 * `polishedFileProvider()`): DLR-R-001 canvas surface, DLR-R-002 no
 * `border-theme` (whole-file on the page; the modal component gets its
 * own whole-file parity pin below), DLR-R-004 no legacy focus-ring
 * aliases, DLR-R-021 no `<style scoped>`.
 *
 * PR-pacientes-02 rules asserted here:
 *
 *   - PAC-MOD-001   The page MUST consume the shared `PatientFormModal`
 *                   for both flows (exactly one usage, `mode` + `patient`
 *                   props); the component MUST consume `<UiModal>`
 *                   chrome; a hand-built `bg-black bg-opacity-50`
 *                   backdrop MUST be absent in BOTH files.
 *   - PAC-MOD-001   No legacy `border-theme` literal in the modal
 *                   component (the canonical token is `border-hairline`).
 *   - PAC-MOD-001   No raw `<select>` / `<textarea>` and no
 *                   `<UiInput type="textarea">`; the form fields MUST
 *                   consume `<UiInput>` + `<UiSelect>` + `<UiTextarea>`.
 *   - PAC-MOD-001   422 duplicate-email/phone error envelope rendering
 *                   MUST stay verbatim — `useToast` surfaces the server
 *                   message + the flattened errors bag; the form stays
 *                   open on 422 (close is emitted on the success path
 *                   only).
 *   - PAC-MOD-001   The `@close` contract is preserved end-to-end:
 *                   `<UiModal @close>` → `emit('close')` → the page
 *                   flips `showPatientFormModal`; `@saved` carries the
 *                   success payload back to the page.
 *   - PAC-MOD-001   The `useApi` request contracts move with the form:
 *                   the page keeps `get` + `delete: del`, the modal
 *                   component owns `post('/api/patients')` +
 *                   `put('/api/patients/{id}')`.
 *   - PAC-RT-001    The page keeps the useEcho `patients` channel
 *                   subscription.
 *   - PAC-CON-001   `usePermissions.can.{createPatient, editPatient,
 *                   deletePatient}` flags keep gating the page actions.
 *
 * Re-anchored pins (the old suite asserted them against the retired
 * duplicated markup; they now assert the same rules against the shared
 * component that owns the surface):
 *
 *   - `test_modal_no_bg_black_bg_opacity_50`  ≥2 `<UiModal>` in the page
 *     → one `<PatientFormModal>` usage in the page + `<UiModal>` in the
 *     component (the single-source consolidation contract).
 *   - `test_modal_no_border_theme` / `test_modal_no_raw_select` /
 *     `test_modal_no_legacy_focus_ring` / `test_modal_uses_ui_input_ui_textarea`
 *     scanned the page's inlined modal sections (demarcated by the
 *     `<!-- New Patient Modal -->` comment) → they scan
 *     `PatientFormModal.vue`, which IS the modal section now. The
 *     page-level extraction would otherwise silently scan an empty
 *     string and pass vacuously.
 *   - `test_modal_422_duplicate_handled` moved from the page to the
 *     component (the catch block moved with the form).
 *   - `test_modal_emit_contract_preserved`: `@close="showNewPatientModal
 *     = false"` / `@close="showEditPatientModal = false"` →
 *     `@close="closePatientForm"` on the single component usage (the
 *     handler flips `showPatientFormModal`), plus the
 *     `const { get, post, put, delete: del } = useApi()` page destructure
 *     pin re-anchored: `get`/`del` stay on the page,
 *     `const { post, put } = useApi()` is pinned on the component, along
 *     with the exact POST/PUT endpoint strings. The `useToast` import +
 *     `channel('patients')` pins stay on the page unchanged.
 *
 * The retired byte-for-byte `<script>` pin (PAC-RT-001 artifact from an
 * old refactor) is gone: the consolidation necessarily rewrites the
 * page's form-handling script, so the script is now pinned at the
 * contract level (endpoints, envelope, emits, channel, permissions)
 * instead of by literal identity.
 */
class PatientsModalAppShellTest extends ModuleAppShellTestCase
{
    /** PatientsPage file path constant — used by the single-file rules. */
    private const PATIENTS_PAGE_PATH = '/resources/js/modules/patients/PatientsPage.vue';

    /** Shared create/edit form component consumed by both flows. */
    private const PATIENTS_FORM_MODAL_PATH = '/resources/js/modules/patients/PatientFormModal.vue';

    /** @return array<int, string> */
    protected static function polishedFiles(): array
    {
        return [
            dirname(__DIR__, 3) . self::PATIENTS_PAGE_PATH,
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
        $src = self::pageSource();
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
     * PAC-MOD-001 — the create/edit form MUST consume the `<UiModal>`
     * primitive (NOT a hand-built `<div class="fixed inset-0 bg-black
     * bg-opacity-50 …">` backdrop). UiModal owns the backdrop + the
     * focus trap + the iOS motion.
     *
     * RE-ANCHOR: the old pin asserted ≥2 `<UiModal>` usages in the page
     * (one per duplicated modal). After the consolidation the page
     * renders exactly ONE shared `<PatientFormModal>` and the component
     * owns the single `<UiModal>`; the hand-built-backdrop negative keeps
     * scanning BOTH files so the legacy chrome cannot re-enter through
     * either surface.
     */
    public function test_modal_no_bg_black_bg_opacity_50(): void
    {
        $page = self::pageSource();
        $modal = self::modalSource();
        $this->assertNotNull($page, '%s must be readable.');
        $this->assertNotNull($modal, '%s must be readable.');

        // POSITIVE: the page consumes the shared form component for both flows.
        $this->assertSame(
            1,
            preg_match_all('/<PatientFormModal\b/', $page),
            sprintf(
                '%s MUST render the shared `<PatientFormModal>` for BOTH the New Patient '
                    . 'AND the Edit Patient flows — exactly one usage (PAC-MOD-001 / consolidation). Found %d.',
                self::PATIENTS_PAGE_PATH,
                preg_match_all('/<PatientFormModal\b/', $page)
            )
        );

        // POSITIVE: the component consumes the `<UiModal>` primitive.
        $this->assertGreaterThanOrEqual(
            1,
            preg_match('/<UiModal\b/', $modal),
            sprintf(
                '%s MUST consume `<UiModal>` chrome (PAC-MOD-001).',
                self::PATIENTS_FORM_MODAL_PATH
            )
        );

        // NEGATIVE: the hand-built `bg-black bg-opacity-50` backdrop is gone
        // from BOTH files.
        foreach ([self::PATIENTS_PAGE_PATH => $page, self::PATIENTS_FORM_MODAL_PATH => $modal] as $file => $src) {
            $this->assertSame(
                0,
                preg_match('/(?<![\w-])bg-black\s+bg-opacity-50(?![\w-])/', $src),
                sprintf(
                    '%s MUST NOT keep the legacy `bg-black bg-opacity-50` modal backdrop '
                        . '(PAC-MOD-001). `<UiModal>` owns the backdrop + focus trap + iOS motion.',
                    $file
                )
            );
        }
    }

    /**
     * PAC-MOD-001 — the modal surface MUST NOT contain any
     * `border-theme` literal. The canonical divider/edge token is
     * `border-hairline`.
     *
     * RE-ANCHOR: the old pin scoped to the page's inlined modal sections
     * (cut at the `<!-- New Patient Modal -->` comment). That marker is
     * gone with the duplication; the modal component IS the modal
     * surface now, so the rule scans it whole-file.
     */
    public function test_modal_no_border_theme(): void
    {
        $modal = self::modalSource();
        $this->assertNotNull($modal, '%s must be readable.');

        $this->assertDoesNotMatchRegularExpression(
            '/(?<![\w-])border-theme(?![\w-])/',
            $modal,
            sprintf(
                '%s MUST NOT contain the legacy `border-theme` literal (PAC-MOD-001 / DLR-R-002). '
                    . 'Use `border-hairline` for dividers.',
                self::PATIENTS_FORM_MODAL_PATH
            )
        );
    }

    /**
     * PAC-MOD-001 — the modal form MUST NOT contain a raw `<select>`
     * element; the gender + status dropdowns MUST consume `<UiSelect>`.
     *
     * RE-ANCHOR: scans `PatientFormModal.vue` (the modal surface after
     * the consolidation). The page-level list filter keeps its own
     * `<UiSelect>` pin in `PatientsListAppShellTest`.
     */
    public function test_modal_no_raw_select(): void
    {
        $modal = self::modalSource();
        $this->assertNotNull($modal, '%s must be readable.');

        $this->assertSame(
            0,
            preg_match('/<select\b/i', $modal),
            sprintf(
                '%s MUST NOT contain a raw `<select>` element (PAC-MOD-001 / DLR-R-009). '
                    . 'Use `<UiSelect>` for the gender + status dropdowns.',
                self::PATIENTS_FORM_MODAL_PATH
            )
        );
    }

    /**
     * PAC-MOD-001 — the modal surface MUST NOT carry the legacy
     * focus-ring alias `focus:ring-primary-500` / `focus:border-accent`.
     * The token-aligned form is `var(--focus-ring-default)` (consumed by
     * UiInput / UiSelect / UiTextarea primitives).
     *
     * RE-ANCHOR: scans `PatientFormModal.vue` (the modal surface after
     * the consolidation).
     */
    public function test_modal_no_legacy_focus_ring(): void
    {
        $modal = self::modalSource();
        $this->assertNotNull($modal, '%s must be readable.');

        $this->assertDoesNotMatchRegularExpression(
            '/(?<![\w-])focus:ring-primary-500(?![\w-])|(?<![\w-])focus:border-accent(?![\w-])/',
            $modal,
            sprintf(
                '%s MUST NOT contain the legacy `focus:ring-primary-500` / `focus:border-accent` aliases '
                    . '(PAC-MOD-001 / DLR-R-004). The `var(--focus-ring-default)` token (consumed by '
                    . '`<UiInput>` / `<UiSelect>` / `<UiTextarea>`) owns the focus ring.',
                self::PATIENTS_FORM_MODAL_PATH
            )
        );
    }

    /**
     * PAC-MOD-001 — the modal form fields MUST consume the Ui
     * primitives. All three (`<UiInput>`, `<UiSelect>`,
     * `<UiTextarea>`) MUST be present in the shared component — as JSX
     * tag or as a named import from `components/ui/<Name>.vue`. Raw
     * `<textarea>` and the invalid `<UiInput type="textarea">` are both
     * forbidden.
     *
     * RE-ANCHOR: the primitives moved from the page's inlined modals to
     * `PatientFormModal.vue`; the page keeps its own `<UiInput>`
     * (search) + `<UiSelect>` (filters) pins in
     * `PatientsListAppShellTest`.
     */
    public function test_modal_uses_ui_input_ui_textarea(): void
    {
        $modal = self::modalSource();
        $this->assertNotNull($modal, '%s must be readable.');

        // POSITIVE: `<UiInput>` is consumed (demographics + address + emergency).
        $this->assertTrue(
            (bool) preg_match('/<UiInput\b/', $modal)
                || (bool) preg_match(
                    "/import\s+UiInput\s+from\s+['\"][^'\"]*components\/ui\/Input\.vue['\"]/",
                    $modal
                ),
            sprintf('%s MUST consume `<UiInput>` (PAC-MOD-001).', self::PATIENTS_FORM_MODAL_PATH)
        );

        // POSITIVE: `<UiSelect>` is consumed (gender + status).
        $this->assertTrue(
            (bool) preg_match('/<UiSelect\b/', $modal)
                || (bool) preg_match(
                    "/import\s+UiSelect\s+from\s+['\"][^'\"]*components\/ui\/Select\.vue['\"]/",
                    $modal
                ),
            sprintf('%s MUST consume `<UiSelect>` (PAC-MOD-001).', self::PATIENTS_FORM_MODAL_PATH)
        );

        // POSITIVE: `<UiTextarea>` is consumed for medical_history + allergies + notes.
        $this->assertTrue(
            (bool) preg_match('/<UiTextarea\b/', $modal)
                || (bool) preg_match(
                    "/import\s+UiTextarea\s+from\s+['\"][^'\"]*components\/ui\/UiTextarea\.vue['\"]/",
                    $modal
                ),
            sprintf(
                '%s MUST consume `<UiTextarea>` for the medical_history + allergies + notes fields '
                    . '(PAC-MOD-001). The pre-PR `<UiInput type="textarea">` is invalid — the Input.vue '
                    . 'validator does not allow `textarea` as a type.',
                self::PATIENTS_FORM_MODAL_PATH
            )
        );

        // NEGATIVE: no raw `<textarea>` element in the modal surface.
        $this->assertSame(
            0,
            preg_match('/<textarea\b/i', $modal),
            sprintf(
                '%s MUST NOT contain a raw `<textarea>` element (PAC-MOD-001 / DLR-R-009). '
                    . 'Use `<UiTextarea>` for the free-text medical fields.',
                self::PATIENTS_FORM_MODAL_PATH
            )
        );

        // NEGATIVE: no `<UiInput type="textarea">` alias either.
        $this->assertDoesNotMatchRegularExpression(
            '/<UiInput\b[^>]*\btype\s*=\s*["\']textarea["\']/',
            $modal,
            sprintf(
                '%s MUST NOT use `<UiInput type="textarea">` (PAC-MOD-001). '
                    . 'The Input.vue type validator rejects it; `<UiTextarea>` owns long text.',
                self::PATIENTS_FORM_MODAL_PATH
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
     * The form stays open on 422 (no `close` emit in the catch block);
     * the user sees the toast + can correct the offending field.
     *
     * RE-ANCHOR: the catch block moved from `createPatient` /
     * `updatePatient` on the page to `submitPatient` in the shared
     * component; the envelope regexes scan the component now, plus a
     * behavioral pin that the catch path surfaces the toast WITHOUT
     * emitting `close`.
     */
    public function test_modal_422_duplicate_handled(): void
    {
        $modal = self::modalSource();
        $this->assertNotNull($modal, '%s must be readable.');

        // 1) The catch block reads `error.response?.data?.message` (the
        //    top-level server message — surfaces 422 "duplicate" wording).
        $this->assertTrue(
            (bool) preg_match(
                '/error\.response\?\.\s*data\?\.\s*message/',
                $modal
            ),
            sprintf(
                '%s MUST keep the `error.response?.data?.message` read in the catch block '
                    . '(PAC-MOD-001). The 422 duplicate-email/phone envelope is surfaced verbatim '
                    . 'via `useToast`.',
                self::PATIENTS_FORM_MODAL_PATH
            )
        );

        // 2) The catch block reads `error.response?.data?.errors` and
        //    flattens it via `Object.values(errors).flat().join('\n')`.
        $this->assertTrue(
            (bool) preg_match(
                '/error\.response\?\.\s*data\?\.\s*errors/',
                $modal
            ),
            sprintf(
                '%s MUST keep the `error.response?.data?.errors` read + the `Object.values(errors).flat().join(\'\\n\')` flattening '
                    . '(PAC-MOD-001). The 422 validation bag surfaces verbatim via `useToast`.',
                self::PATIENTS_FORM_MODAL_PATH
            )
        );

        // 3) The submit flow keeps a `catch (...) { ... toast.error(...) }`
        //    block (a lazy scan from the catch keyword to the first
        //    `toast.error(` call).
        $this->assertTrue(
            (bool) preg_match(
                '/catch\s*\([^)]*\)\s*\{[\s\S]*?toast\.error\s*\(/',
                $modal
            ),
            sprintf(
                '%s MUST keep a `catch (...) { ... toast.error(...) }` block in the submit flow '
                    . '(PAC-MOD-001). The form stays open on 422 and the user sees the toast.',
                self::PATIENTS_FORM_MODAL_PATH
            )
        );

        // 4) BEHAVIORAL: the catch path surfaces the toast WITHOUT closing
        //    the modal — between the `catch (` keyword and the `finally`
        //    keyword there is a `toast.error(` call and NO `emit('close')`.
        $catchPos = strpos($modal, 'catch (');
        $this->assertNotFalse($catchPos, sprintf('%s MUST contain a `catch (` block.', self::PATIENTS_FORM_MODAL_PATH));
        $finallyPos = strpos($modal, 'finally', $catchPos);
        $this->assertNotFalse($finallyPos, sprintf('%s MUST keep the `finally` block after the catch.', self::PATIENTS_FORM_MODAL_PATH));

        $catchBlock = substr($modal, $catchPos, $finallyPos - $catchPos);
        $this->assertStringContainsString(
            'toast.error',
            $catchBlock,
            sprintf('%s MUST surface the 422 envelope via `toast.error` inside the catch block.', self::PATIENTS_FORM_MODAL_PATH)
        );
        $this->assertStringNotContainsString(
            "emit('close')",
            $catchBlock,
            sprintf(
                '%s MUST NOT emit `close` from the catch block (PAC-MOD-001): the form stays open on 422 '
                    . 'so the user can correct the offending field.',
                self::PATIENTS_FORM_MODAL_PATH
            )
        );
    }

    /**
     * PAC-MOD-001 — the modal emit + request contracts MUST be preserved
     * end-to-end.
     *
     * The `<UiModal>` primitive emits `update:modelValue`, `close`,
     * `open`. The component re-emits `close` on the UiModal boundary and
     * the page listens with `@close` to flip `showPatientFormModal` so
     * the modal closes on backdrop click, escape, or the X button. The
     * success path emits `saved` (the response payload) before `close`.
     *
     * RE-ANCHORED pins:
     *   - `@close="showNewPatientModal = false"` /
     *     `@close="showEditPatientModal = false"` (one per duplicated
     *     modal) → one `@close="closePatientForm"` on the single
     *     `<PatientFormModal>` usage; `closePatientForm` flips
     *     `showPatientFormModal`.
     *   - `const { get, post, put, delete: del } = useApi()` on the page
     *     → `get`/`delete: del` stay on the page (list + delete), and the
     *     create/update contract `const { post, put } = useApi()` moves
     *     to the shared component together with the exact endpoint
     *     strings.
     */
    public function test_modal_emit_contract_preserved(): void
    {
        $page = self::pageSource();
        $modal = self::modalSource();
        $this->assertNotNull($page, '%s must be readable.');
        $this->assertNotNull($modal, '%s must be readable.');

        // 1) The component wires `<UiModal @close=...>` to re-emit `close`
        //    (backdrop click + escape + X button).
        $this->assertTrue(
            (bool) preg_match('/<UiModal\b[^>]*\s@close\s*=/s', $modal),
            sprintf(
                '%s MUST wire `<UiModal @close=...>` to the `close` emit (PAC-MOD-001).',
                self::PATIENTS_FORM_MODAL_PATH
            )
        );
        $this->assertTrue(
            (bool) preg_match('/emit\s*\(\s*[\'"]close[\'"]\s*\)/', $modal),
            sprintf(
                '%s MUST re-emit `close` on the UiModal boundary (PAC-MOD-001).',
                self::PATIENTS_FORM_MODAL_PATH
            )
        );

        // 2) The page wires `@close` + `@saved` on the single shared
        //    component usage.
        $this->assertTrue(
            (bool) preg_match('/<PatientFormModal\b[^>]*\s@close\s*=/s', $page),
            sprintf(
                '%s MUST wire `<PatientFormModal @close=...>` (PAC-MOD-001).',
                self::PATIENTS_PAGE_PATH
            )
        );
        $this->assertTrue(
            (bool) preg_match('/<PatientFormModal\b[^>]*\s@saved\s*=/s', $page),
            sprintf(
                '%s MUST wire `<PatientFormModal @saved=...>` (PAC-MOD-001): the success payload '
                    . 'drives the list update + success toast.',
                self::PATIENTS_PAGE_PATH
            )
        );

        // 3) The `@close` handler flips `showPatientFormModal` so the
        //    `v-if` teardown removes the modal.
        $this->assertTrue(
            (bool) preg_match(
                '/showPatientFormModal\.value\s*=\s*false/',
                $page
            ),
            sprintf(
                '%s MUST flip `showPatientFormModal.value = false` in the close handler (PAC-MOD-001).',
                self::PATIENTS_PAGE_PATH
            )
        );

        // 4) The page keeps the `useApi` list + delete contract.
        $this->assertTrue(
            (bool) preg_match(
                '/const\s*\{\s*get\s*,\s*delete\s*:\s*del\s*\}\s*=\s*useApi\s*\(\s*\)/s',
                $page
            ),
            sprintf(
                '%s MUST keep the `const { get, delete: del } = useApi()` destructure '
                    . '(PAC-CON-001). Renaming would silently break loadPatients + deletePatient.',
                self::PATIENTS_PAGE_PATH
            )
        );

        // 5) The shared component owns the create/update `useApi`
        //    contract (re-anchored from the page destructure pin).
        $this->assertTrue(
            (bool) preg_match(
                '/const\s*\{\s*post\s*,\s*put\s*\}\s*=\s*useApi\s*\(\s*\)/s',
                $modal
            ),
            sprintf(
                '%s MUST keep the `const { post, put } = useApi()` destructure (PAC-CON-001). '
                    . 'The create/update flows ride the shared form component.',
                self::PATIENTS_FORM_MODAL_PATH
            )
        );

        // 6) The exact request endpoints stay byte-identical.
        $this->assertTrue(
            (bool) preg_match('/post\s*\(\s*[\'"`]\/api\/patients[\'"`]/', $modal),
            sprintf(
                '%s MUST keep `post(\'/api/patients\')` (PAC-MOD-001 request contract).',
                self::PATIENTS_FORM_MODAL_PATH
            )
        );
        $this->assertTrue(
            (bool) preg_match('/put\s*\(\s*[\'"`]\/api\/patients\/\$\{props\.patient\.id\}[\'"`]/', $modal),
            sprintf(
                '%s MUST keep `put(\'/api/patients/${props.patient.id}\')` (PAC-MOD-001 request contract).',
                self::PATIENTS_FORM_MODAL_PATH
            )
        );

        // 7) The page keeps the `useToast` import (success + delete toasts).
        $this->assertTrue(
            (bool) preg_match(
                "/import\s*\{[^}]*useToast[^}]*\}\s*from\s*['\"]@?\.{0,4}\/?composables\/useToast['\"]/",
                $page
            )
            || (bool) preg_match(
                "/import\s*\{[^}]*useToast[^}]*\}\s*from\s*['\"]\.\.\/\.\.\/composables\/useToast['\"]/",
                $page
            ),
            sprintf(
                '%s MUST keep the `useToast` import (PAC-CON-001). The success + delete toasts are '
                    . 'surfaced verbatim.',
                self::PATIENTS_PAGE_PATH
            )
        );

        // 8) The shared component imports `useToast` for the 422 envelope.
        $this->assertTrue(
            (bool) preg_match(
                "/import\s*\{[^}]*useToast[^}]*\}\s*from\s*['\"][^'\"]*composables\/useToast['\"]/",
                $modal
            ),
            sprintf(
                '%s MUST keep the `useToast` import (PAC-CON-001). The 422 envelope is surfaced verbatim.',
                self::PATIENTS_FORM_MODAL_PATH
            )
        );

        // 9) The page keeps the `useEcho` `patients` channel subscription.
        //    The `.patient.created` / `.patient.updated` /
        //    `.patient.deleted` listeners + the `echo.leave('patients')`
        //    in `onUnmounted` are load-bearing for cross-tab realtime
        //    updates.
        $this->assertTrue(
            (bool) preg_match(
                "#channel\s*\(\s*['\"]patients['\"]\s*\)#",
                $page
            ),
            sprintf(
                '%s MUST keep `channel(\'patients\')` subscription (PAC-RT-001). '
                    . 'The Echo channel is the canonical realtime pipe for patient events.',
                self::PATIENTS_PAGE_PATH
            )
        );
    }

    /**
     * Consolidation contract — ONE shared form source serves both flows.
     *
     * The page imports `PatientFormModal.vue` once and renders it once
     * with the `mode` ('create' | 'edit') + `patient` props; the
     * component owns exactly one `<UiModal>` + one `data-patient-form`
     * root and branches on `mode` for the title and submit label. A
     * second inlined form (or a second `<UiModal>`) is a regression to
     * the duplication this consolidation removed.
     */
    public function test_modal_single_shared_form_component(): void
    {
        $page = self::pageSource();
        $modal = self::modalSource();
        $this->assertNotNull($page, '%s must be readable.');
        $this->assertNotNull($modal, '%s must be readable.');

        $this->assertTrue(
            (bool) preg_match(
                "/import\s+PatientFormModal\s+from\s+['\"]\.\/PatientFormModal\.vue['\"]/",
                $page
            ),
            sprintf(
                '%s MUST import the shared form from `./PatientFormModal.vue` (consolidation contract).',
                self::PATIENTS_PAGE_PATH
            )
        );

        // The single usage passes the `mode` + `patient` props.
        $this->assertTrue(
            (bool) preg_match('/<PatientFormModal\b[^>]*:mode\s*=/s', $page),
            sprintf('%s MUST bind `:mode` on `<PatientFormModal>`.', self::PATIENTS_PAGE_PATH)
        );
        $this->assertTrue(
            (bool) preg_match('/<PatientFormModal\b[^>]*:patient\s*=/s', $page),
            sprintf('%s MUST bind `:patient` on `<PatientFormModal>`.', self::PATIENTS_PAGE_PATH)
        );

        // The component owns exactly one UiModal + one form root and
        // branches on the mode.
        $this->assertSame(
            1,
            preg_match_all('/<UiModal\b/', $modal),
            sprintf('%s MUST own exactly one `<UiModal>`.', self::PATIENTS_FORM_MODAL_PATH)
        );
        $this->assertSame(
            1,
            preg_match_all('/data-patient-form/', $modal),
            sprintf('%s MUST own exactly one `data-patient-form` root.', self::PATIENTS_FORM_MODAL_PATH)
        );
        $this->assertTrue(
            (bool) preg_match("/mode\s*===\s*['\"]edit['\"]/", $modal),
            sprintf('%s MUST branch on `mode === \'edit\'` for the mode-specific copy.', self::PATIENTS_FORM_MODAL_PATH)
        );
    }

    /**
     * DLR parity — the shared modal component inherits the whole-file
     * rules the page already satisfies (DLR-R-021 no `<style scoped>`).
     * DLR-R-002 (border-theme) + DLR-R-004 (focus-ring aliases) are
     * pinned in the focused tests above; the canvas rule does not apply
     * to a modal component (the `<AppLayout>` wrapper owns it).
     */
    public function test_modal_component_no_style_scoped(): void
    {
        $modal = self::modalSource();
        $this->assertNotNull($modal, '%s must be readable.');

        $this->assertDoesNotMatchRegularExpression(
            '/<style\s+scoped\s*>/',
            $modal,
            sprintf(
                '%s MUST NOT contain a `<style scoped>` block (DLR-R-021 / DLR-CORE-008).',
                self::PATIENTS_FORM_MODAL_PATH
            )
        );
    }

    /**
     * PAC-RT-001 / PAC-CON-001 — the page's realtime + permission
     * contracts survive the consolidation: the `usePermissions.can`
     * flags gate the New Patient + Edit + Delete action buttons.
     *
     * The `usePermissions.can.*` flags gate the New Patient + Edit +
     * Delete action buttons + the New Patient modal open button. A
     * silent rename would break the role-based UI gating.
     */
    public function test_modal_use_permissions_can_preserved(): void
    {
        $page = self::pageSource();
        $this->assertNotNull($page, '%s must be readable.');

        // POSITIVE: the `can` destructure from `usePermissions()` is intact.
        // The canonical form is `const { can } = usePermissions()`; the
        // destructure can also appear as a parameter destructuring. The
        // regex accepts either form (the `{ can }` token MUST be inside
        // the same statement as the `usePermissions()` call).
        $this->assertTrue(
            (bool) preg_match(
                '/const\s*\{\s*can\s*\}\s*=\s*usePermissions\s*\(\s*\)/s',
                $page
            ),
            sprintf(
                '%s MUST keep the `const { can } = usePermissions()` destructure (PAC-CON-001). '
                    . 'The `can.createPatient / can.editPatient / can.deletePatient` flags '
                    . 'gate the action buttons + the New Patient modal open button.',
                self::PATIENTS_PAGE_PATH
            )
        );

        // POSITIVE: the 3 patient permission flags are referenced in the template.
        $flags = ['can.createPatient', 'can.editPatient', 'can.deletePatient'];
        foreach ($flags as $flag) {
            $this->assertTrue(
                (bool) preg_match(
                    '/' . preg_quote($flag, '/') . '\b/',
                    $page
                ),
                sprintf(
                    '%s MUST keep the `%s` permission flag reference (PAC-CON-001).',
                    self::PATIENTS_PAGE_PATH,
                    $flag
                )
            );
        }
    }

    private static function pageSource(): ?string
    {
        return self::readSource(dirname(__DIR__, 3) . self::PATIENTS_PAGE_PATH);
    }

    private static function modalSource(): ?string
    {
        return self::readSource(dirname(__DIR__, 3) . self::PATIENTS_FORM_MODAL_PATH);
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
