<?php

namespace Tests\Unit\DesignSystem;

/**
 * PR-pacientes-04 -- PatientDetailEditExportAppShellTest.
 *
 * Asserts PAC-EDIT-001 (PatientDetailPage.vue Edit Patient modal ->
 * UiModal chrome + UiSelect for gender + is_active) + PAC-EXP-001 (Export
 * action surface uses UiButton + binary download pattern preserved
 * byte-for-byte).
 *
 * The Edit Patient modal lives at line 706+ of PatientDetailPage.vue and
 * was excluded from PatientDetailAppShellTest's polished section (that
 * test scoped to the header + 5-tab drawer + audit tab content). This
 * test class scopes its section-scoped assertions to the Edit modal
 * surface (between the <!-- Edit Patient Modal --> marker + the closing
 * </AppLayout> tag) and pins the Export action surface (whole-file).
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

    /** Marker that demarcates the START of the Edit Patient modal. */
    private const EDIT_MODAL_SECTION_START = '<!-- Edit Patient Modal -->';

    /** Closing tag that demarcates the END of the Edit modal section. */
    private const EDIT_MODAL_SECTION_END = '</AppLayout>';

    /** @return array<int, string> */
    protected static function polishedFiles(): array
    {
        return [
            dirname(__DIR__, 3) . self::PATIENT_DETAIL_PATH,
        ];
    }

    /**
     * Override the inherited test_page_references_canvas_token rule. The
     * page mounts inside AppLayout which provides the canvas surface
     * per DLR-CORE-001 + canvasRoutes (PR0 landed). The page file does
     * NOT need a direct bg-canvas reference. We pin the AppLayout
     * reference instead.
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
                '%s MUST reference AppLayout (canvas-surface wrapper per DLR-CORE-001). '
                    . 'The pacientes design acknowledges the page file does not need a direct '
                    . 'bg-canvas reference -- the AppLayout provides it.',
                $path
            )
        );
    }

    /**
     * Override the inherited test_no_legacy_border_theme_literal rule.
     * Scope the assertion to the PR-pacientes-04 polished surface -- the
     * Edit modal section between the EDIT MODAL SECTION START marker +
     * the closing AppLayout tag.
     *
     * The header + 5-tab drawer + audit tab content are PR-pacientes-03
     * scope (asserted by PatientDetailAppShellTest); this rule scopes
     * to the Edit modal only. The Edit modal header divider
     * (border-b border-theme) MUST migrate to UiModal built-in
     * hairline divider.
     *
     * @dataProvider polishedFileProvider
     */
    public function test_no_legacy_border_theme_literal(string $path): void
    {
        $src = self::readSource($path);
        $this->assertNotNull($src, sprintf('%s must exist and be readable.', $path));

        $section = self::extractEditModalSection($src);

        $this->assertDoesNotMatchRegularExpression(
            '/(?<![\w-])border-theme(?![\w-])/',
            $section,
            sprintf(
                '%s Edit modal section MUST NOT contain the legacy border-theme literal '
                    . '(PAC-EDIT-001 / DLR-R-002). UiModal owns the header divider via '
                    . 'its built-in hairline token.',
                $path
            )
        );
    }

    /**
     * Override the inherited test_no_legacy_focus_ring_alias rule.
     * Scope to the Edit modal section. The two raw select elements
     * for gender + is_active carry
     * focus:ring-primary-500 focus:border-transparent; both MUST migrate
     * to UiSelect (which consumes var(--focus-ring-default)).
     *
     * @dataProvider polishedFileProvider
     */
    public function test_no_legacy_focus_ring_alias(string $path): void
    {
        $src = self::readSource($path);
        $this->assertNotNull($src, sprintf('%s must exist and be readable.', $path));

        $section = self::extractEditModalSection($src);

        $this->assertSame(
            0,
            preg_match('/(?<![\w-])focus:ring-primary-500(?![\w-])/', $section),
            sprintf(
                '%s Edit modal section MUST NOT contain the legacy focus:ring-primary-500 '
                    . 'alias (PAC-EDIT-001 / DLR-R-004). UiSelect consumes the composed '
                    . 'var(--focus-ring-default) token.',
                $path
            )
        );
    }

    /**
     * PAC-EDIT-001 -- the Edit modal MUST consume the UiModal primitive
     * (NOT a hand-built fixed inset-0 bg-black bg-opacity-50 backdrop).
     * UiModal owns the backdrop + the focus trap + the iOS motion.
     *
     * The hand-built backdrop is the legacy alias for UiModal; the
     * rule is asserted as the NEGATIVE form (bg-black bg-opacity-50
     * absent) so the test fires regardless of how the modal chrome is
     * structured.
     *
     * @dataProvider polishedFileProvider
     */
    public function test_detail_edit_no_bg_black_bg_opacity_50(string $path): void
    {
        $src = self::readSource($path);
        $this->assertNotNull($src, sprintf('%s must exist and be readable.', $path));

        // POSITIVE: the UiModal primitive is referenced for the Edit modal.
        $this->assertGreaterThanOrEqual(
            1,
            preg_match_all('/<UiModal\b/', $src),
            sprintf(
                '%s MUST consume UiModal for the Edit Patient modal '
                    . '(PAC-EDIT-001). Found %d.',
                $path,
                preg_match_all('/<UiModal\b/', $src)
            )
        );

        // NEGATIVE: the hand-built bg-black bg-opacity-50 backdrop is gone.
        $this->assertSame(
            0,
            preg_match('/(?<![\w-])bg-black\s+bg-opacity-50(?![\w-])/', $src),
            sprintf(
                '%s MUST NOT keep the legacy bg-black bg-opacity-50 modal backdrop '
                    . '(PAC-EDIT-001). UiModal owns the backdrop + focus trap + iOS motion.',
                $path
            )
        );
    }

    /**
     * PAC-EDIT-001 -- the Edit modal MUST NOT contain a raw select
     * element. The 2 raw select elements for gender + is_active MUST
     * migrate to UiSelect.
     *
     * Note: the LIST section uses UiSelect v-model=statusFilter (PR-
     * pacientes-01 already migrated). This rule scopes to the EDIT MODAL
     * section to catch any raw select inside the form fields.
     *
     * @dataProvider polishedFileProvider
     */
    public function test_detail_edit_no_raw_select(string $path): void
    {
        $src = self::readSource($path);
        $this->assertNotNull($src, sprintf('%s must exist and be readable.', $path));

        $section = self::extractEditModalSection($src);

        $this->assertSame(
            0,
            preg_match('/<select\b/i', $section),
            sprintf(
                '%s Edit modal section MUST NOT contain a raw select element '
                    . '(PAC-EDIT-001 / DLR-R-009). Use UiSelect for the gender + is_active '
                    . 'dropdowns.',
                $path
            )
        );
    }

    /**
     * PAC-EDIT-001 -- the Edit modal MUST consume UiSelect for the
     * gender + is_active dropdowns. The UiSelect primitive takes an
     * options array; the rule asserts that UiSelect is referenced --
     * the exact options array shape is implementation detail.
     *
     * @dataProvider polishedFileProvider
     */
    public function test_detail_edit_uses_ui_select(string $path): void
    {
        $src = self::readSource($path);
        $this->assertNotNull($src, sprintf('%s must exist and be readable.', $path));

        $section = self::extractEditModalSection($src);

        $this->assertGreaterThanOrEqual(
            2,
            preg_match_all('/<UiSelect\b/', $section),
            sprintf(
                '%s Edit modal section MUST consume UiSelect for BOTH the gender dropdown '
                    . 'AND the is_active dropdown (PAC-EDIT-001). Found %d.',
                $path,
                preg_match_all('/<UiSelect\b/', $section)
            )
        );
    }

    /**
     * PAC-EXP-001 -- the Export trigger button MUST use UiButton (NOT
     * a raw button with manual class strings). The Export trigger
     * lives in the page header (the actions slot of PageHeader).
     *
     * The UiButton adoption is the minimal change that satisfies
     * PAC-EXP-001 -- the underlying exportPatientFile function stays
     * verbatim (per the binary download pattern preservation rule).
     *
     * @dataProvider polishedFileProvider
     */
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
                    . '(PAC-EXP-001). The raw button + manual class strings are legacy.',
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
                    . '(PAC-EXP-001). The UiButton primitive is the canonical affordance.',
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
     *
     * @dataProvider polishedFileProvider
     */
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
                    . 'The Bearer token read is the first link in the binary download chain.',
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
                    . 'The Bearer token is the auth contract for the binary download endpoint.',
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
                    . 'stream that gets anchored to the download link.',
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
                    . 'The Object URL is the only safe way to download a Bearer-token-authenticated '
                    . 'binary stream; useApi() JSON wrapper would corrupt the stream.',
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
                    . 'triggers the browser native download flow with the binary payload.',
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
                    . 'the browser native download flow.',
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
                    . 'revocation releases the Object URL memory after the download completes.',
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
     *
     * @dataProvider polishedFileProvider
     */
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
                    . '(PAC-EXP-001). The patient resource endpoint is the contract for the '
                    . 'binary export action.',
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
                    . 'The JSON envelope from useApi() would corrupt the binary stream; '
                    . 'the raw fetch + Bearer token pattern is the only safe path.',
                $path
            )
        );
    }

    /**
     * PAC-EDIT-001 / PAC-CON-001 -- the script block MUST stay
     * byte-for-byte. The Edit modal migration is template-level only;
     * the updatePatient catch block + the useApi PUT call signature
     * + the useToast error envelope rendering all stay verbatim.
     *
     * This rule pins the 422 error envelope rendering for the Edit
     * modal updatePatient catch block: error.response.data.message
     * + error.response.data.errors + Object.values(errors).flat().
     *
     * @dataProvider polishedFileProvider
     */
    public function test_detail_edit_422_duplicate_handled(string $path): void
    {
        $src = self::readSource($path);
        $this->assertNotNull($src, sprintf('%s must exist and be readable.', $path));

        // 1) The catch block reads error.response.data.message.
        $this->assertTrue(
            (bool) preg_match(
                '/error\.response\?\.\s*data\?\.\s*message/',
                $src
            ),
            sprintf(
                '%s MUST keep the error.response.data.message read in the catch block '
                    . '(PAC-EDIT-001). The 422 duplicate-email/phone envelope is surfaced verbatim '
                    . 'via useToast.',
                $path
            )
        );

        // 2) The catch block reads error.response.data.errors.
        $this->assertTrue(
            (bool) preg_match(
                '/error\.response\?\.\s*data\?\.\s*errors/',
                $src
            ),
            sprintf(
                '%s MUST keep the error.response.data.errors read + the '
                    . 'Object.values(errors).flat() flattening '
                    . '(PAC-EDIT-001). The 422 validation bag surfaces verbatim via useToast.',
                $path
            )
        );
    }

    /**
     * PAC-CON-001 -- the Edit modal uses useApi for the PUT call (the
     * updatePatient function). The PUT call signature
     * put(/api/patients/${id}, data) MUST stay verbatim -- a refactor
     * to axios direct would break the guard rail (useApi() wrapper
     * only; NO axios direct).
     *
     * @dataProvider polishedFileProvider
     */
    public function test_detail_edit_use_api_put_preserved(string $path): void
    {
        $src = self::readSource($path);
        $this->assertNotNull($src, sprintf('%s must exist and be readable.', $path));

        // POSITIVE: the updatePatient function calls put(...) from useApi().
        $this->assertTrue(
            (bool) preg_match(
                '#put\s*\(\s*[`\'"]/api/patients/\$\{[^`\'"]+\}[^`\'"]*[`\'"]\s*,#',
                $src
            ),
            sprintf(
                '%s MUST keep put(/api/patients/INTERPOLATION, data) in the updatePatient '
                    . 'function (PAC-EDIT-001 / PAC-CON-001). The useApi() PUT signature is '
                    . 'the canonical pattern for the Edit modal form submit.',
                $path
            )
        );

        // NEGATIVE: no axios direct import.
        $this->assertSame(
            0,
            preg_match(
                '#import\s+axios\s+from\s+[\'"]axios[\'"]#',
                $src
            ),
            sprintf(
                '%s MUST NOT import axios directly (PAC-CON-001). The useApi() wrapper is '
                    . 'the canonical HTTP primitive; axios-direct would silently bypass the '
                    . '401 redirect + JSON envelope handling.',
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

    /**
     * Extract the PR-pacientes-04 polished surface from the full file.
     * The polished surface is the Edit modal section, demarcated by:
     *
     *   - START: the EDIT MODAL SECTION START comment marker
     *   - END:   the closing AppLayout tag
     *
     * The header + 5-tab drawer + audit tab content are PR-pacientes-03
     * scope (asserted by PatientDetailAppShellTest); the Edit modal is
     * the PR-pacientes-04 scope. Cutting at the marker comments gives a
     * clean slice for the section-scoped rules above.
     */
    private static function extractEditModalSection(string $src): string
    {
        $start = strpos($src, self::EDIT_MODAL_SECTION_START);
        if ($start === false) {
            return '';
        }

        $end = strpos($src, self::EDIT_MODAL_SECTION_END, $start);
        if ($end === false) {
            return substr($src, $start);
        }

        return substr($src, $start, $end - $start);
    }
}
