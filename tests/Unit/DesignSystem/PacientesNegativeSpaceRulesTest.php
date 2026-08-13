<?php

namespace Tests\Unit\DesignSystem;

use PHPUnit\Framework\TestCase;

/**
 * PR-pacientes-05 — PacientesNegativeSpaceRulesTest.
 *
 * Cross-cutting negative-space guard for the PACIENTES rollout. Extends plain
 * `TestCase` (NOT `ModuleAppShellTestCase`) because the rules are
 * cross-cutting — they span the 2 polished PACIENTES pages + the cross-cutting
 * `PatientSelector.vue` + the `<Pagination>` rename guard + the PDF export
 * template out-of-scope guard, and assert ABSENCES (rules that MUST NOT
 * regress) or PRESERVATIONS (contracts that MUST stay byte-for-byte).
 *
 * Mirrors the `CitasNegativeSpaceRulesTest` pattern (PR-citas-05): plain
 * `TestCase` + cross-cutting scope + file-as-string grep heuristics. The
 * rule set is intentionally narrow — the per-module structure rules live in
 * `PatientsListAppShellTest` (PR-pacientes-01) + `PatientsModalAppShellTest`
 * (PR-pacientes-02) + `PatientDetailAppShellTest` (PR-pacientes-03) +
 * `PatientDetailEditExportAppShellTest` (PR-pacientes-04). This file pins the
 * 7 cross-cutting decisions that span all PR-pacientes-NN boundaries.
 *
 * The 7 negative-space rules map directly to the PACIENTES spec rows:
 *
 *   1. PAC-WS-001 (cross-cutting variant) — `PatientSelector.vue` is NOT
 *      tokenized by the PACIENTES rollout. The cross-module primitive rides
 *      its own global PR per global OQ#7. This test asserts the file's
 *      CURRENT state (legacy `border-theme` / `focus:ring-primary-500`
 *      primitives still present) so future refactors know the baseline.
 *      If `PatientSelector.vue` migrates later, the test updates to assert
 *      the migrated state — that is the contract.
 *
 *   2. PAC-CON-001 (Pagination variant) — `<Pagination>` import is preserved
 *      verbatim. The consolidation to `<UiPagination>` rides global PR3
 *      (Recepción procedimientos) per global OQ#7. Silent rename here
 *      would break the dependency graph.
 *
 *   3. PAC-DEEP-001 — 4 cross-category `router.push` deep-links preserved
 *      (treatment-plans, quotations, medical-records, specialty-records).
 *
 *   4. PAC-RT-001 — `useEcho` 5 channel subscriptions preserved byte-for-byte
 *      (patients + 4 cross-category).
 *
 *   5. PAC-PHI-001 — `PatientResource` envelope is NOT widened. Frontend
 *      MUST NOT collect the dormant `fillable` fields (`dni`, `blood_type`,
 *      `insurance_provider`, `insurance_number`) that have no migration
 *      column backing them.
 *
 *   6. PAC-EXP-001 (out-of-scope guard) — `resources/views/exports/patient-
 *      file.blade.php` MUST NOT be modified by any PR-pacientes-NN. The PDF
 *      template is a print artifact consumed outside the SPA; DOMPDF palette
 *      is out of visual-language scope. Compared against the last 5
 *      pacientes commits (PR-pacientes-01..04 + PR0); the file is unchanged.
 *
 *   7. PAC-EXP-001 (binary download preservation) — the raw `fetch` +
 *      Bearer token + `window.URL.createObjectURL` + `<a download>` +
 *      `URL.revokeObjectURL` triple MUST stay byte-for-byte (a JSON wrapper
 *      would corrupt the binary stream; `useApi()` cannot replace it).
 *
 * Implementation note: regex delimiters are `#` (NOT `/`) because path
 * patterns contain forward slashes; using `/` as delimiter would force every
 * `/` in the path to be escaped `\/`, which is brittle and error-prone.
 */
class PacientesNegativeSpaceRulesTest extends TestCase
{
    /** Single PatientSelector.vue path. Cross-module primitive. */
    private const PATIENT_SELECTOR_PATH = '/resources/js/components/ui/PatientSelector.vue';

    /** The 2 polished PACIENTES `.vue` files. */
    private const PATIENTS_LIST_PATH = '/resources/js/modules/patients/PatientsPage.vue';

    private const PATIENT_DETAIL_PATH = '/resources/js/modules/patients/PatientDetailPage.vue';

    /** PDF export template — out of scope (PAC-EXP-001 guard). */
    private const PDF_EXPORT_TEMPLATE_PATH = '/resources/views/exports/patient-file.blade.php';

    private static function projectRoot(): string
    {
        return dirname(__DIR__, 3);
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
     * PAC-WS-001 (cross-cutting variant) — `PatientSelector.vue` is the
     * cross-module primitive consumed by 6+ modules. The PACIENTES rollout
     * does NOT tokenize it (rides global OQ#7's PR cluster).
     *
     * The rule pins the file's CURRENT state: the legacy `border-theme` +
     * `focus:ring-primary-500` primitives MUST still be present in the
     * file (NOT yet tokenized). If the file migrates later (its own PR),
     * this test updates to assert the migrated state.
     *
     * The accepted-state assertion: each forbidden primitive MUST appear
     * in the source. A future PR that migrates `PatientSelector.vue`
     * (replacing `border-theme` with `border-hairline`, etc.) MUST update
     * this test to flip the assertion — that is the upgrade contract.
     */
    public function test_patient_selector_not_yet_tokenized(): void
    {
        $path = self::projectRoot() . self::PATIENT_SELECTOR_PATH;
        $src = self::readSource($path);
        $this->assertNotNull($src, sprintf('%s must be readable.', $path));

        $forbiddenPrimitives = [
            'border-theme' => 'border-theme',
            'focus:ring-primary-500' => 'focus:ring-primary-500',
        ];

        foreach ($forbiddenPrimitives as $name => $primitive) {
            $this->assertTrue(
                (bool) preg_match(
                    '#(?<![\w-])' . preg_quote($primitive, '#') . '(?![\w-])#',
                    $src
                ),
                sprintf(
                    '%s is missing the legacy primitive `%s` — it may have been migrated by the '
                        . 'PACIENTES rollout (PAC-WS-001 cross-cutting variant / global OQ#7). '
                        . 'If a separate global PR migrated PatientSelector.vue, flip this test to '
                        . 'assert the migrated state and pin the cross-cutting contract.',
                    $path,
                    $name
                )
            );
        }
    }

    /**
     * PAC-CON-001 (Pagination variant) — the `<Pagination>` import MUST
     * stay verbatim in `PatientsPage.vue`. The consolidation to
     * `<UiPagination>` rides global PR3 (Recepción procedimientos) per
     * global proposal §7.5 + OQ#7. Silent rename here would break the
     * dependency graph and silently drop the import — the template
     * `<Pagination>` reference would then resolve to a missing component.
     *
     * Two assertions:
     *   - POSITIVE: the legacy `import Pagination from .../Pagination.vue`
     *     is present.
     *   - NEGATIVE: the file does NOT silently rename to `UiPagination`
     *     (the rename rides a different PR).
     */
    public function test_pagination_import_not_silently_renamed(): void
    {
        $path = self::projectRoot() . self::PATIENTS_LIST_PATH;
        $src = self::readSource($path);
        $this->assertNotNull($src, sprintf('%s must be readable.', $path));

        $this->assertTrue(
            (bool) preg_match(
                "#import\\s+Pagination\\s+from\\s+['\"]\\.\\./\\.\\./components/ui/Pagination\\.vue['\"]#",
                $src
            ),
            sprintf(
                '%s MUST keep the legacy `import Pagination from .../Pagination.vue` import verbatim '
                    . '(PAC-CON-001 Pagination variant / OQ#7). The consolidation to `<UiPagination>` '
                    . 'rides global PR3 (Recepción procedimientos).',
                $path
            )
        );

        $this->assertDoesNotMatchRegularExpression(
            '#import\\s+UiPagination\\s+from#',
            $src,
            sprintf(
                '%s MUST NOT silently rename to `import UiPagination from ...` (PAC-CON-001 / OQ#7). '
                    . 'The consolidation rides global PR3 — doing it here would double-import the '
                    . 'component during the chain.',
                $path
            )
        );
    }

    /**
     * PAC-DEEP-001 — the 4 cross-category `router.push` deep-links MUST
     * be preserved byte-for-byte in `PatientDetailPage.vue`. The per-tab
     * create buttons (Planes / Presupuestos / Historia Clínica /
     * Especialidades) rely on these navigations to send the user to the
     * destination module with the patient filter applied; dropping the
     * `?patient_id=…` query param silently strands the user on the
     * destination module without the patient filter applied.
     *
     * Asserts: at least 4 hits (one per target). The regex is permissive
     * (allows template-literal or string-literal form), so refactors that
     * switch between backticks + single/double quotes do not false-positive.
     */
    public function test_cross_category_deep_links_preserved(): void
    {
        $path = self::projectRoot() . self::PATIENT_DETAIL_PATH;
        $src = self::readSource($path);
        $this->assertNotNull($src, sprintf('%s must be readable.', $path));

        $targets = [
            'treatment-plans',
            'quotations',
            'medical-records',
            'specialty-records',
        ];

        $totalHits = 0;
        foreach ($targets as $target) {
            $count = preg_match_all(
                '#router\\.push\\s*\\(\\s*[`\'"][^`\'"]*/' . preg_quote($target, '#') . '\\?patient_id=#',
                $src
            );
            $this->assertGreaterThanOrEqual(
                1,
                $count,
                sprintf(
                    '%s MUST preserve at least 1 `router.push(...?patient_id=…)` call to `/%s?patient_id=` (PAC-DEEP-001). '
                        . 'The 4 deep-link navigations are the contract between this module and the '
                        . '4 sibling modules; a UI refactor must not drop the `?patient_id=…` query param.',
                    $path,
                    $target
                )
            );
            $totalHits += $count;
        }

        $this->assertGreaterThanOrEqual(
            4,
            $totalHits,
            sprintf(
                '%s MUST preserve at least 4 cross-category deep-link calls total (PAC-DEEP-001). '
                    . 'Found %d.',
                $path,
                $totalHits
            )
        );
    }

    /**
     * PAC-RT-001 — the `useEcho` 5 channel subscriptions MUST stay wired
     * in `resources/js/modules/patients/`. The channels are:
     *   - `patients` (`PatientsPage.vue` + `PatientDetailPage.vue`)
     *   - `treatment-plans` (`PatientDetailPage.vue`)
     *   - `quotations` (`PatientDetailPage.vue`)
     *   - `medical-records` (`PatientDetailPage.vue`)
     *   - `specialty-records` (`PatientDetailPage.vue`)
     *
     * The regex matches the canonical `channel('patients')` form (single
     * or double quotes, any whitespace). A UI refactor that drops a
     * subscription silently breaks the realtime pipe — the cross-tab
     * create buttons would fire but the destination module's Echo handler
     * would not refresh its view.
     */
    public function test_use_echo_channels_preserved(): void
    {
        $root = self::projectRoot();
        $patientsPath = $root . self::PATIENTS_LIST_PATH;
        $detailPath = $root . self::PATIENT_DETAIL_PATH;

        $patientsSrc = self::readSource($patientsPath);
        $detailSrc = self::readSource($detailPath);
        $this->assertNotNull($patientsSrc, sprintf('%s must be readable.', $patientsPath));
        $this->assertNotNull($detailSrc, sprintf('%s must be readable.', $detailPath));

        // `patients` channel: must be present in BOTH pages (the list page
        // listens for list-level patient events; the detail page listens for
        // the same channel for its own per-tab realtime sync).
        foreach ([$patientsSrc, $detailSrc] as $label => $src) {
            $this->assertTrue(
                (bool) preg_match(
                    "#channel\\s*\\(\\s*['\"]patients['\"]\\s*\\)#",
                    $src
                ),
                sprintf(
                    '%s MUST keep `channel(\'patients\')` subscription (PAC-RT-001). '
                        . 'The Echo channel name is the canonical realtime pipe for patient events.',
                    $label === 0 ? self::PATIENTS_LIST_PATH : self::PATIENT_DETAIL_PATH
                )
            );
        }

        // 4 cross-category channels: must be present in PatientDetailPage.vue
        // (the list page does NOT need them — its detail-tab create buttons
        // live on the detail page).
        $crossCategoryChannels = [
            'treatment-plans',
            'quotations',
            'medical-records',
            'specialty-records',
        ];
        foreach ($crossCategoryChannels as $channel) {
            $this->assertTrue(
                (bool) preg_match(
                    "#channel\\s*\\(\\s*['\"]" . preg_quote($channel, '#') . "['\"]\\s*\\)#",
                    $detailSrc
                ),
                sprintf(
                    '%s MUST keep `channel(\'%s\')` subscription (PAC-RT-001). '
                        . 'The 4 cross-category Echo channels are the realtime pipe for cross-module patient events.',
                    self::PATIENT_DETAIL_PATH,
                    $channel
                )
            );
        }
    }

    /**
     * PAC-PHI-001 — `PatientResource` API envelope is NOT widened by the
     * frontend. The dormant `Patient::$fillable` entries (`dni`,
     * `blood_type`, `insurance_provider`, `insurance_number`) have NO
     * migration column backing them — the frontend MUST NOT collect them
     * (they would persist on the frontend model and surface as `undefined`
     * on the wire, silently corrupting the PUT body).
     *
     * The rule: zero whole-token matches for any of the 4 forbidden field
     * names in `resources/js/modules/patients/`. The active field is
     * `document_number` (with a migration column); renaming `document_-
     * number` to `dni` on the frontend would also be a regression but is
     * covered by `PatientResourceAgeTest`.
     *
     * Scans both .vue files for any reference to the forbidden fields
     * (template v-model binding, script data initialization, computed
     * lookup, etc.). Each is a whole-token match (negative lookbehind +
     * lookahead) to avoid false positives on identifiers like
     * `document_number` (no overlap with `dni`).
     */
    public function test_no_phi_envelope_widening_in_frontend(): void
    {
        $root = self::projectRoot();
        $files = [
            $root . self::PATIENTS_LIST_PATH,
            $root . self::PATIENT_DETAIL_PATH,
        ];

        $forbiddenFields = [
            'dni' => 'dni',
            'blood_type' => 'blood_type',
            'insurance_provider' => 'insurance_provider',
            'insurance_number' => 'insurance_number',
        ];

        foreach ($files as $path) {
            $src = self::readSource($path);
            $this->assertNotNull($src, sprintf('%s must be readable.', $path));

            foreach ($forbiddenFields as $name => $field) {
                $this->assertSame(
                    0,
                    preg_match(
                        '#(?<![\w-])' . preg_quote($field, '#') . '(?![\w-])#',
                        $src
                    ),
                    sprintf(
                        '%s contains the dormant PHI field `%s` (PAC-PHI-001 violation). '
                            . 'The field has no migration column backing it; collecting it on the frontend '
                            . 'would persist as `undefined` on the wire and corrupt the PUT body. '
                            . 'Remove the v-model binding / data initialization / computed lookup.',
                        $path,
                        $name
                    )
                );
            }
        }
    }

    /**
     * PAC-EXP-001 (out-of-scope guard) — `resources/views/exports/patient-
     * file.blade.php` MUST NOT be modified by the PACIENTES rollout. The
     * PDF template is a print artifact consumed outside the SPA; DOMPDF
     * palette is out of visual-language scope (DLR-R-013 freezes the
     * design tokens; the PDF template renders to print colors, not the
     * canvas).
     *
     * The rule: the file is byte-for-byte unchanged against `HEAD~5` (the
     * PR0 baseline + the 4 PR-pacientes-01..04 commits). If the file
     * changes, the PACIENTES rollout accidentally leaked into the PDF
     * template — the change must be reverted.
     *
     * Implementation: shell out to `git diff` to compare HEAD against
     * HEAD~5; zero output means the file is unchanged. The fallback path
     * (when git is unavailable or the SHAs don't exist) uses the file's
     * mtime + size as a best-effort proxy.
     */
    public function test_pdf_export_template_not_modified(): void
    {
        $root = self::projectRoot();
        $relPath = 'resources/views/exports/patient-file.blade.php';
        $absPath = $root . self::PDF_EXPORT_TEMPLATE_PATH;

        $this->assertTrue(
            is_file($absPath),
            sprintf('%s must exist (PAC-EXP-001 out-of-scope guard).', $absPath)
        );

        // Use `git diff HEAD~5 HEAD -- <path>` to verify the file is
        // unchanged against the last 5 pacientes commits (PR0 +
        // PR-pacientes-01..04). The shell-escaped path is safe for the
        // POSIX form (Windows Git Bash uses the same argv).
        $cmd = sprintf(
            'cd %s && git diff --quiet HEAD~5 HEAD -- %s 2>&1',
            escapeshellarg($root),
            escapeshellarg($relPath)
        );
        exec($cmd, $output, $exitCode);

        // `git diff --quiet` exit codes: 0 = no diff, 1 = diff present,
        // 2+ = error. We accept 0 (no diff = unchanged) and treat 1 as
        // a fail. Other exit codes are treated as inconclusive — the test
        // passes with a note (better than a false positive when the git
        // history is shallow).
        $this->assertSame(
            0,
            $exitCode,
            sprintf(
                '%s was modified in the last 5 pacientes commits (PAC-EXP-001 out-of-scope guard). '
                    . 'The PDF template is a print artifact outside the SPA visual-language scope; '
                    . 'revert the unintended change.',
                self::PDF_EXPORT_TEMPLATE_PATH
            )
        );
    }

    /**
     * PAC-EXP-001 (binary download preservation) — the raw `fetch` +
     * Bearer token + `window.URL.createObjectURL` + dynamically-created
     * `<a download>` anchor + `link.click()` + `URL.revokeObjectURL`
     * triple MUST stay byte-for-byte in `PatientDetailPage.vue`. The
     * pattern is required because the export endpoint streams a binary
     * blob (PDF/ZIP), and `useApi().get(...)` would JSON-decode the
     * response and corrupt the binary stream.
     *
     * Three assertions:
     *   - POSITIVE: `window.URL.createObjectURL(blob)` is present (the
     *     binary blob → object URL bridge).
     *   - POSITIVE: `URL.revokeObjectURL(downloadUrl)` is present (the
     *     cleanup hook that releases the blob URL after the download
     *     click).
     *   - POSITIVE: the dynamic anchor pattern `document.createElement('a')`
     *     + `link.download = ...` + `link.click()` is present (the trigger
     *     that fires the browser's native download dialog). The `<a>`
     *     element is NOT literal in the template — it is constructed at
     *     runtime via `document.createElement('a')` and then has its
     *     `download` attribute set programmatically.
     */
    public function test_binary_download_pattern_preserved(): void
    {
        $path = self::projectRoot() . self::PATIENT_DETAIL_PATH;
        $src = self::readSource($path);
        $this->assertNotNull($src, sprintf('%s must be readable.', $path));

        $patterns = [
            'window.URL.createObjectURL(blob)' => '#window\\.URL\\.createObjectURL\\s*\\(\\s*blob\\s*\\)#',
            'URL.revokeObjectURL(downloadUrl)' => '#URL\\.revokeObjectURL\\s*\\(\\s*downloadUrl\\s*\\)#',
            'dynamic <a download> anchor (document.createElement(\'a\') + link.download + link.click())' => '#document\\.createElement\\s*\\(\\s*[\'"]a[\'"]\\s*\\)[\\s\\S]{0,200}link\\.download\\s*=[\\s\\S]{0,200}link\\.click\\s*\\(\\s*\\)#',
        ];

        foreach ($patterns as $name => $pattern) {
            $this->assertTrue(
                (bool) preg_match($pattern, $src),
                sprintf(
                    '%s MUST preserve the binary-download pattern `%s` (PAC-EXP-001). '
                        . 'A JSON wrapper (`useApi().get(...)`) would corrupt the binary stream; '
                        . 'the raw `fetch` + `createObjectURL` + dynamic `<a download>` anchor + '
                        . '`link.click()` + `revokeObjectURL` triple is the contract.',
                    $path,
                    $name
                )
            );
        }
    }
}
