<?php

namespace Tests\Unit\DesignSystem;

use PHPUnit\Framework\Attributes\DataProvider;

/**
 * PR-pacientes-04 -- PatientDetailEditExportAppShellTest.
 *
 * Asserts PAC-EDIT-001 (PatientDetailPage.vue Edit Patient modal ->
 * UiModal chrome + UiSelect for gender + is_active) + PAC-EXP-001 (Export
 * action surface uses UiButton + binary download pattern preserved
 * byte-for-byte).
 *
 * PATIENT DETAIL REDESIGN — RE-ANCHORED PINS: the inlined edit form
 * moved from PatientDetailPage.vue into the shared
 * `PatientFormModal.vue` (single source of truth with the list page).
 * The edit-chrome rules below therefore scan the COMPONENT that owns
 * the form surface (they must still bite there), and a new pin
 * (`test_detail_edit_uses_shared_form_modal`) keeps the DETAIL page
 * honest: exactly one `mode="edit"` usage and NO second patient form on
 * the page. Re-anchored (same rule, new scan target):
 *
 *   - test_no_legacy_border_theme_literal      -> PatientFormModal.vue
 *   - test_no_legacy_focus_ring_alias          -> PatientFormModal.vue
 *   - test_detail_edit_no_bg_black_bg_opacity_50 -> PatientFormModal.vue
 *     (positive UiModal) + BOTH files (negative backdrop)
 *   - test_detail_edit_no_raw_select           -> PatientFormModal.vue
 *   - test_detail_edit_uses_ui_select          -> PatientFormModal.vue
 *   - test_detail_edit_422_duplicate_handled   -> PatientFormModal.vue
 *   - test_detail_edit_use_api_put_preserved   -> PatientFormModal.vue
 *     (positive put) + BOTH files (negative axios import)
 *
 * NOT weakened: PAC-EXP-001 export pins keep scanning the detail page
 * whole-file (the export action did not move), and the patient resource
 * endpoint + binary download chain stay byte-for-byte.
 *
 * The Edit Patient form now lives in `PatientFormModal.vue` (shared
 * with the list page); the detail page renders it via a single
 * `<PatientFormModal mode="edit">` usage under the same
 * `<!-- Edit Patient Modal -->` marker, and this test class pins the
 * Export action surface on the detail page (whole-file) plus the
 * re-anchored edit-chrome rules on the component.
 *
 * Rules asserted (each parameterized via polishedFileProvider() so a
 * failure pinpoints the exact file that regressed):
 *
 *   - PAC-EDIT-001  Edit modal MUST consume UiModal chrome; hand-built
 *                   fixed inset-0 bg-black bg-opacity-50 backdrop MUST
 *                   be absent.
 *   - PAC-EDIT-001  Edit modal MUST NOT contain border-theme literal
 *                   (header divider migrates to UiModal built-in
 *                   hairline divider).
 *   - PAC-EDIT-001  Edit modal MUST NOT contain raw select elements
 *                   (gender + is_active migrate to UiSelect).
 *   - PAC-EDIT-001  Edit modal MUST NOT contain the legacy focus-ring
 *                   alias focus:ring-primary-500 focus:border-transparent
 *                   (var(--focus-ring-default) consumed by UiSelect).
 *   - PAC-EDIT-001  Edit modal MUST consume UiSelect for the gender
 *                   + is_active dropdowns.
 *   - PAC-EXP-001   Export trigger button MUST use UiButton (NOT raw
 *                   button + manual class strings).
 *   - PAC-EXP-001   Binary download pattern MUST be preserved byte-for-byte:
 *                   window.URL.createObjectURL + a-download anchor
 *                   click + URL.revokeObjectURL cleanup.
 *   - PAC-EXP-001   Export fetch call MUST hit
 *                   /api/patients/${id}/export?format=... with a Bearer
 *                   token (the JSON wrapper from useApi() would corrupt
 *                   the binary stream).
 *
 * Inherited rules (from ModuleAppShellTestCase, applied via
 * polishedFileProvider()): DLR-R-001 canvas surface (overridden to
 * AppLayout reference), DLR-R-002 no border-theme literal (overridden
 * to scope to Edit modal section), DLR-R-004 no legacy focus-ring
 * aliases (overridden to scope to Edit modal section), DLR-R-021 no
 * style-scoped block.
 */
class PatientDetailEditExportAppShellTest extends ModuleAppShellTestCase
{
    /** PatientDetailPage file path constant -- single source of truth for the data provider. */
    private const PATIENT_DETAIL_PATH = '/resources/js/modules/patients/PatientDetailPage.vue';

    /** Shared create/edit form component that now owns the edit modal surface. */
    private const SHARED_FORM_PATH = '/resources/js/modules/patients/PatientFormModal.vue';

    /** @return array<int, string> */
    protected static function polishedFiles(): array
    {
        return [
            dirname(__DIR__, 3).self::PATIENT_DETAIL_PATH,
        ];
    }

    /**
     * Override the inherited test_page_references_canvas_token rule. The
     * page mounts inside AppLayout which provides the canvas surface
     * per DLR-CORE-001 + canvasRoutes (PR0 landed). The page file does
     * NOT need a direct bg-canvas reference. We pin the AppLayout
     * reference instead.
     */
    #[DataProvider('polishedFileProvider')]
    public function test_page_references_canvas_token(string $path): void
    {
        $src = self::readSource($path);
        $this->assertNotNull($src, sprintf('%s must exist and be readable.', $path));

        $this->assertTrue(
            (bool) preg_match('/<AppLayout\b/', $src),
            sprintf(
                '%s MUST reference AppLayout (canvas-surface wrapper per DLR-CORE-001). '
                    .'The pacientes design acknowledges the page file does not need a direct '
                    .'bg-canvas reference -- the AppLayout provides it.',
                $path
            )
        );
    }

    /**
     * Override the inherited test_no_legacy_border_theme_literal rule.
     *
     * RE-ANCHOR: the PR-pacientes-04 edit surface moved from the page's
     * inlined modal section (marker-delimited) to the shared
     * `PatientFormModal.vue`; the rule scans the component that owns
     * the form surface. UiModal owns the header divider via its
     * built-in hairline token.
     */
    #[DataProvider('polishedFileProvider')]
    public function test_no_legacy_border_theme_literal(string $path): void
    {
        $modal = self::sharedFormSource();
        $this->assertNotNull($modal, sprintf('%s must exist and be readable.', self::SHARED_FORM_PATH));

        $this->assertDoesNotMatchRegularExpression(
            '/(?<![\w-])border-theme(?![\w-])/',
            $modal,
            sprintf(
                '%s Edit modal surface MUST NOT contain the legacy border-theme literal '
                    .'(PAC-EDIT-001 / DLR-R-002). UiModal owns the header divider via '
                    .'its built-in hairline token.',
                self::SHARED_FORM_PATH
            )
        );
    }

    /**
     * Override the inherited test_no_legacy_focus_ring_alias rule.
     *
     * RE-ANCHOR: the gender + is_active dropdowns (and the rest of the
     * form fields) live in `PatientFormModal.vue` now; the rule scans
     * the component so the legacy alias cannot re-enter through the
     * shared surface.
     */
    #[DataProvider('polishedFileProvider')]
    public function test_no_legacy_focus_ring_alias(string $path): void
    {
        $modal = self::sharedFormSource();
        $this->assertNotNull($modal, sprintf('%s must exist and be readable.', self::SHARED_FORM_PATH));

        $this->assertSame(
            0,
            preg_match('/(?<![\w-])focus:ring-primary-500(?![\w-])/', $modal),
            sprintf(
                '%s Edit modal surface MUST NOT contain the legacy focus:ring-primary-500 '
                    .'alias (PAC-EDIT-001 / DLR-R-004). UiSelect consumes the composed '
                    .'var(--focus-ring-default) token.',
                self::SHARED_FORM_PATH
            )
        );
    }

    /**
     * PAC-EDIT-001 -- the Edit modal MUST consume the UiModal primitive
     * (NOT a hand-built fixed inset-0 bg-black bg-opacity-50 backdrop).
     * UiModal owns the backdrop + the focus trap + the iOS motion.
     *
     * RE-ANCHOR: the positive `<UiModal>` assertion scans
     * `PatientFormModal.vue` (the component owns the chrome after the
     * consolidation); the negative backdrop rule keeps scanning BOTH
     * files so the legacy chrome cannot re-enter through either surface.
     */
    #[DataProvider('polishedFileProvider')]
    public function test_detail_edit_no_bg_black_bg_opacity_50(string $path): void
    {
        $src = self::readSource($path);
        $this->assertNotNull($src, sprintf('%s must exist and be readable.', $path));
        $modal = self::sharedFormSource();
        $this->assertNotNull($modal, sprintf('%s must exist and be readable.', self::SHARED_FORM_PATH));

        // POSITIVE: the UiModal primitive is referenced for the Edit modal.
        $this->assertGreaterThanOrEqual(
            1,
            preg_match_all('/<UiModal\b/', $modal),
            sprintf(
                '%s MUST consume UiModal for the Edit Patient modal '
                    .'(PAC-EDIT-001). Found %d.',
                self::SHARED_FORM_PATH,
                preg_match_all('/<UiModal\b/', $modal)
            )
        );

        // NEGATIVE: the hand-built bg-black bg-opacity-50 backdrop is gone
        // from BOTH files.
        foreach ([self::PATIENT_DETAIL_PATH => $src, self::SHARED_FORM_PATH => $modal] as $file => $content) {
            $this->assertSame(
                0,
                preg_match('/(?<![\w-])bg-black\s+bg-opacity-50(?![\w-])/', $content),
                sprintf(
                    '%s MUST NOT keep the legacy bg-black bg-opacity-50 modal backdrop '
                        .'(PAC-EDIT-001). UiModal owns the backdrop + focus trap + iOS motion.',
                    $file
                )
            );
        }
    }

    /**
     * PAC-EDIT-001 -- the Edit modal MUST NOT contain a raw select
     * element. The 2 raw select elements for gender + is_active MUST
     * migrate to UiSelect.
     *
     * RE-ANCHOR: scans `PatientFormModal.vue` (the edit surface after
     * the consolidation); a page-level extraction would scan the bare
     * `<PatientFormModal>` tag and pass vacuously.
     */
    #[DataProvider('polishedFileProvider')]
    public function test_detail_edit_no_raw_select(string $path): void
    {
        $modal = self::sharedFormSource();
        $this->assertNotNull($modal, sprintf('%s must exist and be readable.', self::SHARED_FORM_PATH));

        $this->assertSame(
            0,
            preg_match('/<select\b/i', $modal),
            sprintf(
                '%s Edit modal surface MUST NOT contain a raw select element '
                    .'(PAC-EDIT-001 / DLR-R-009). Use UiSelect for the gender + is_active '
                    .'dropdowns.',
                self::SHARED_FORM_PATH
            )
        );
    }

    /**
     * PAC-EDIT-001 -- the Edit modal MUST consume UiSelect for the
     * gender + is_active dropdowns. The UiSelect primitive takes an
     * options array; the rule asserts that UiSelect is referenced --
     * the exact options array shape is implementation detail.
     *
     * RE-ANCHOR: scans `PatientFormModal.vue` (dropdowns moved with the
     * form).
     */
    #[DataProvider('polishedFileProvider')]
    public function test_detail_edit_uses_ui_select(string $path): void
    {
        $modal = self::sharedFormSource();
        $this->assertNotNull($modal, sprintf('%s must exist and be readable.', self::SHARED_FORM_PATH));

        $this->assertGreaterThanOrEqual(
            2,
            preg_match_all('/<UiSelect\b/', $modal),
            sprintf(
                '%s Edit modal surface MUST consume UiSelect for BOTH the gender dropdown '
                    .'AND the is_active dropdown (PAC-EDIT-001). Found %d.',
                self::SHARED_FORM_PATH,
                preg_match_all('/<UiSelect\b/', $modal)
            )
        );
    }

    /**
     * PAC-EDIT-001 / consolidation — the detail page MUST consume the
     * shared `PatientFormModal` for the Edit Patient flow and MUST NOT
     * own a second patient form.
     *
     * NEW PIN (patient detail redesign): the inlined edit form moved to
     * `PatientFormModal.vue` (single source of truth shared with the list
     * page). The chrome pins below (UiModal / UiSelect / no raw select /
     * 422 envelope / useApi PUT) were re-anchored to the component that
     * owns the form; this pin keeps the DETAIL page honest: exactly one
     * shared-component usage wired `mode="edit"` + `:patient`, and no
     * leftover `<UiModal>`, `<form>`, `editPatientData` or
     * `updatePatient` markup on the page itself.
     */
    #[DataProvider('polishedFileProvider')]
    public function test_detail_edit_uses_shared_form_modal(string $path): void
    {
        $src = self::readSource($path);
        $this->assertNotNull($src, sprintf('%s must exist and be readable.', $path));

        // 1) The page imports + renders the shared form exactly once.
        $this->assertTrue(
            (bool) preg_match(
                "/import\s+PatientFormModal\s+from\s+['\"]\.\/PatientFormModal\.vue['\"]/",
                $src
            ),
            sprintf(
                '%s MUST import the shared form from `./PatientFormModal.vue` (consolidation contract).',
                $path
            )
        );
        $this->assertSame(
            1,
            preg_match_all('/<PatientFormModal\b/', $src),
            sprintf(
                '%s MUST render exactly ONE `<PatientFormModal>` usage (PAC-EDIT-001 / consolidation). Found %d.',
                $path,
                preg_match_all('/<PatientFormModal\b/', $src)
            )
        );

        // 2) The usage is wired for the edit flow: mode + patient +
        //    close/saved handlers.
        $this->assertTrue(
            (bool) preg_match('/<PatientFormModal\b[^>]*\smode\s*=\s*"edit"/s', $src),
            sprintf('%s MUST bind `mode="edit"` on `<PatientFormModal>` (PAC-EDIT-001).', $path)
        );
        $this->assertTrue(
            (bool) preg_match('/<PatientFormModal\b[^>]*:patient\s*=/s', $src),
            sprintf('%s MUST bind `:patient` on `<PatientFormModal>` (PAC-EDIT-001).', $path)
        );
        $this->assertTrue(
            (bool) preg_match('/<PatientFormModal\b[^>]*@close\s*=/s', $src),
            sprintf('%s MUST wire `@close` on `<PatientFormModal>` (PAC-EDIT-001).', $path)
        );
        $this->assertTrue(
            (bool) preg_match('/<PatientFormModal\b[^>]*@saved\s*=/s', $src),
            sprintf('%s MUST wire `@saved` on `<PatientFormModal>` (PAC-EDIT-001).', $path)
        );

        // 3) NEGATIVE: the detail page owns NO second patient form —
        //    no inlined modal chrome, no raw form, no duplicated form
        //    state/handler left on the page.
        foreach ([
            '/<UiModal\b/' => 'the `<UiModal>` chrome (owned by the shared component)',
            '/<form\b/' => 'a raw `<form>` element (owned by the shared component)',
            '/editPatientData/' => 'the duplicated `editPatientData` form state',
            '/updatePatient/' => 'the duplicated `updatePatient` submit handler',
            '/<UiInput\b|<UiSelect\b|<UiTextarea\b/' => 'form field primitives (owned by the shared component)',
        ] as $pattern => $what) {
            $this->assertSame(
                0,
                preg_match($pattern, $src),
                sprintf(
                    '%s MUST NOT carry %s — the detail page renders the shared `<PatientFormModal>` instead (PAC-EDIT-001 / consolidation).',
                    $path,
                    $what
                )
            );
        }
    }

    /**
     * PAC-EXP-001 -- the Export trigger button MUST use UiButton (NOT
     * a raw button with manual class strings). The Export trigger
     * lives in the page header (the actions slot of PageHeader).
     *
     * The UiButton adoption is the minimal change that satisfies
     * PAC-EXP-001 -- the underlying exportPatientFile function stays
     * verbatim (per the binary download pattern preservation rule).
     */
    #[DataProvider('polishedFileProvider')]
    public function test_detail_export_button_uses_ui_button(string $path): void
    {
        $src = self::readSource($path);
        $this->assertNotNull($src, sprintf('%s must exist and be readable.', $path));

        // POSITIVE: the Export trigger uses UiButton.
        $this->assertTrue(
            (bool) preg_match(
                '/<UiButton\b[^>]*@click\s*=\s*["\']exportPatientFile/',
                $src
            ),
            sprintf(
                '%s MUST use UiButton for the Export trigger with @click="exportPatientFile" '
                    .'(PAC-EXP-001). The raw button + manual class strings are legacy.',
                $path
            )
        );

        // NEGATIVE: no raw button element with the export click handler.
        $this->assertSame(
            0,
            preg_match(
                '/<button\b[^>]*@click\s*=\s*["\']exportPatientFile/',
                $src
            ),
            sprintf(
                '%s MUST NOT keep a raw button for the Export trigger '
                    .'(PAC-EXP-001). The UiButton primitive is the canonical affordance.',
                $path
            )
        );
    }

    /**
     * PAC-EXP-001 -- the binary download pattern MUST be preserved
     * byte-for-byte. The pattern is:
     *
     *   1. localStorage.getItem('auth_token')
     *   2. Authorization: Bearer ${token} header
     *   3. response.blob()
     *   4. window.URL.createObjectURL(blob)
     *   5. Anchor element with download attribute
     *   6. link.click() to trigger the download
     *   7. window.URL.revokeObjectURL(...) cleanup
     *
     * Wrapping this in useApi() (JSON envelope) would corrupt the
     * binary stream -- the raw fetch + Bearer token pattern is the only
     * safe path. The rule asserts each pattern element is present.
     */
    #[DataProvider('polishedFileProvider')]
    public function test_detail_export_binary_download_pattern_preserved(string $path): void
    {
        $src = self::readSource($path);
        $this->assertNotNull($src, sprintf('%s must exist and be readable.', $path));

        // 1) Bearer token read from localStorage.
        $this->assertTrue(
            (bool) preg_match(
                "/localStorage\.getItem\s*\(\s*['\"]auth_token['\"]\s*\)/",
                $src
            ),
            sprintf(
                '%s MUST preserve localStorage.getItem(auth_token) (PAC-EXP-001). '
                    .'The Bearer token read is the first link in the binary download chain.',
                $path
            )
        );

        // 2) Authorization: Bearer ${token} header.
        $this->assertTrue(
            (bool) preg_match(
                '/Authorization\s*:\s*[`\'"]Bearer\s+\$\{token\}/',
                $src
            ),
            sprintf(
                '%s MUST preserve the Authorization Bearer token header (PAC-EXP-001). '
                    .'The Bearer token is the auth contract for the binary download endpoint.',
                $path
            )
        );

        // 3) Blob extraction from response.
        $this->assertTrue(
            (bool) preg_match(
                '/response\.blob\s*\(\s*\)/',
                $src
            ),
            sprintf(
                '%s MUST preserve response.blob() (PAC-EXP-001). The blob is the binary '
                    .'stream that gets anchored to the download link.',
                $path
            )
        );

        // 4) Object URL creation -- the load-bearing line.
        $this->assertTrue(
            (bool) preg_match(
                '/window\.URL\.createObjectURL\s*\(\s*blob\s*\)/',
                $src
            ),
            sprintf(
                '%s MUST preserve window.URL.createObjectURL(blob) (PAC-EXP-001). '
                    .'The Object URL is the only safe way to download a Bearer-token-authenticated '
                    .'binary stream; useApi() JSON wrapper would corrupt the stream.',
                $path
            )
        );

        // 5) Anchor element with download attribute.
        $this->assertTrue(
            (bool) preg_match(
                '/link\.download\s*=/',
                $src
            ),
            sprintf(
                '%s MUST preserve link.download = ... (PAC-EXP-001). The download attribute '
                    .'triggers the browser native download flow with the binary payload.',
                $path
            )
        );

        // 6) Anchor click trigger.
        $this->assertTrue(
            (bool) preg_match(
                '/link\.click\s*\(\s*\)/',
                $src
            ),
            sprintf(
                '%s MUST preserve link.click() (PAC-EXP-001). The click is the trigger for '
                    .'the browser native download flow.',
                $path
            )
        );

        // 7) Object URL revocation cleanup.
        $this->assertTrue(
            (bool) preg_match(
                '/window\.URL\.revokeObjectURL\s*\(/',
                $src
            ),
            sprintf(
                '%s MUST preserve window.URL.revokeObjectURL(...) (PAC-EXP-001). The '
                    .'revocation releases the Object URL memory after the download completes.',
                $path
            )
        );
    }

    /**
     * PAC-EXP-001 -- the Export fetch call MUST hit the patient resource
     * endpoint /api/patients/${id}/export?format=... The URL is built
     * from ${baseUrl}/api/patients/${patient.value.id}/export?format=${exportFormat}.
     *
     * The endpoint contract is the load-bearing path -- the JSON wrapper
     * from useApi() cannot replace it.
     */
    #[DataProvider('polishedFileProvider')]
    public function test_detail_export_calls_patient_resource_endpoint(string $path): void
    {
        $src = self::readSource($path);
        $this->assertNotNull($src, sprintf('%s must exist and be readable.', $path));

        $this->assertTrue(
            (bool) preg_match(
                '#/api/patients/\$\{(?:patient\.value\.id|patientId|patient\.id)\}/export\?format=#',
                $src
            ),
            sprintf(
                '%s MUST preserve the /api/patients/INTERPOLATION/export?format=... endpoint URL '
                    .'(PAC-EXP-001). The patient resource endpoint is the contract for the '
                    .'binary export action.',
                $path
            )
        );

        // Negative: the export MUST NOT be wrapped in useApi() (the
        // JSON envelope corrupts the binary stream).
        $this->assertFalse(
            (bool) preg_match(
                '/useApi\s*\(\s*\)\s*\.\s*(?:get|post)\s*\(\s*[`\'"][^`\'"]*\/api\/patients\/[^`\'"]*\/export/',
                $src
            ),
            sprintf(
                '%s MUST NOT wrap the export fetch in useApi() (PAC-EXP-001). '
                    .'The JSON envelope from useApi() would corrupt the binary stream; '
                    .'the raw fetch + Bearer token pattern is the only safe path.',
                $path
            )
        );
    }

    /**
     * PAC-EDIT-001 / PAC-CON-001 -- the update contract MUST stay
     * byte-for-byte.
     *
     * RE-ANCHORED: the catch block moved from `updatePatient` on the
     * detail page to `submitPatient` in the shared `PatientFormModal`;
     * the rule pins the 422 error envelope rendering (the envelope is
     * surfaced verbatim via useToast) against the component that owns
     * the submit path, so the contract still bites.
     */
    #[DataProvider('polishedFileProvider')]
    public function test_detail_edit_422_duplicate_handled(string $path): void
    {
        $modal = self::sharedFormSource();
        $this->assertNotNull($modal, sprintf('%s must exist and be readable.', self::SHARED_FORM_PATH));

        // 1) The catch block reads the API envelope (`error.response.data`).
        $this->assertTrue(
            (bool) preg_match(
                '/error\.response\?\.\s*data/',
                $modal
            ),
            sprintf(
                '%s MUST read the API envelope in the catch block '
                    .'(PAC-EDIT-001). The 422 duplicate-email/phone reason arrives there.',
                self::SHARED_FORM_PATH
            )
        );

        // 2) The catch block maps the validation bag onto its own fields
        //    (#403: the reason lands on the input, not only on a toast) and
        //    picks the server's message for the notice.
        $this->assertTrue(
            (bool) preg_match(
                '/mapFieldErrors\s*\(\s*body\?\.\s*errors/',
                $modal
            ),
            sprintf(
                '%s MUST map `body?.errors` with mapFieldErrors(...) (PAC-EDIT-001 / #403). '
                    .'The 422 validation bag surfaces on its field.',
                self::SHARED_FORM_PATH
            )
        );
        $this->assertTrue(
            (bool) preg_match('/pickValidationMessage\s*\(/', $modal),
            sprintf(
                '%s MUST use pickValidationMessage(...) for the notice (PAC-EDIT-001 / #403).',
                self::SHARED_FORM_PATH
            )
        );
    }

    /**
     * PAC-CON-001 -- the Edit modal uses useApi for the PUT call.
     *
     * RE-ANCHORED: the PUT call moved with the form to
     * `PatientFormModal.submitPatient`; the signature
     * put(/api/patients/${id}, data) MUST stay verbatim on the
     * component (a refactor to axios direct would break the guard rail:
     * useApi() wrapper only; NO axios direct). The axios-negative keeps
     * scanning BOTH files.
     */
    #[DataProvider('polishedFileProvider')]
    public function test_detail_edit_use_api_put_preserved(string $path): void
    {
        $src = self::readSource($path);
        $this->assertNotNull($src, sprintf('%s must exist and be readable.', $path));
        $modal = self::sharedFormSource();
        $this->assertNotNull($modal, sprintf('%s must exist and be readable.', self::SHARED_FORM_PATH));

        // POSITIVE: the submit function calls put(...) from useApi().
        $this->assertTrue(
            (bool) preg_match(
                '#put\s*\(\s*[`\'"]/api/patients/\$\{[^`\'"]+\}[^`\'"]*[`\'"]\s*,#',
                $modal
            ),
            sprintf(
                '%s MUST keep put(/api/patients/INTERPOLATION, data) in the submit function '
                    .'(PAC-EDIT-001 / PAC-CON-001). The useApi() PUT signature is '
                    .'the canonical pattern for the Edit modal form submit.',
                self::SHARED_FORM_PATH
            )
        );

        // NEGATIVE: no axios direct import on either surface.
        foreach ([self::PATIENT_DETAIL_PATH => $src, self::SHARED_FORM_PATH => $modal] as $file => $content) {
            $this->assertSame(
                0,
                preg_match(
                    '#import\s+axios\s+from\s+[\'"]axios[\'"]#',
                    $content
                ),
                sprintf(
                    '%s MUST NOT import axios directly (PAC-CON-001). The useApi() wrapper is '
                        .'the canonical HTTP primitive; axios-direct would silently bypass the '
                        .'401 redirect + JSON envelope handling.',
                    $file
                )
            );
        }
    }

    private static function readSource(string $path): ?string
    {
        if (! is_file($path)) {
            return null;
        }
        $src = file_get_contents($path);

        return $src === false ? null : $src;
    }

    /** Source of the shared edit/create form component (re-anchored scan target). */
    private static function sharedFormSource(): ?string
    {
        return self::readSource(dirname(__DIR__, 3).self::SHARED_FORM_PATH);
    }
}
