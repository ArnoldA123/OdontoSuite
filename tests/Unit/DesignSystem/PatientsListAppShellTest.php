<?php

namespace Tests\Unit\DesignSystem;

/**
 * PR-pacientes-01 — PatientsListAppShellTest.
 *
 * Asserts the list-section rule of PAC-LIST-001 (PatientsPage list view
 * polished on Apple language; 4 stat cards, status filter, search input,
 * desktop table, mobile card fallback, pagination). The two inlined modals
 * (New Patient + Edit Patient) are NOT in scope of this PR — they ride
 * PR-pacientes-02 with `<UiModal>` chrome + `<UiInput>` / `<UiSelect>` /
 * `<UiTextarea>` migration. The 2 `border-theme` literals inside the
 * modals are the only legacy aliases that remain in the file at this PR
 * boundary; the tests below scope to the LIST section via the file's
 * template structure.
 *
 * Inherited rules (from {@see ModuleAppShellTestCase}, applied via
 * `polishedFileProvider()`): DLR-R-001 canvas surface, DLR-R-002 no
 * `border-theme`, DLR-R-004 no legacy focus-ring aliases, DLR-R-021 no
 * `<style scoped>`. The list section's `border-theme` migration is
 * asserted here via the dedicated test below.
 *
 * PR-pacientes-01-only rules asserted here:
 *
 *   - PR-pacientes-01 / PAC-LIST-001  List view on Apple language
 *   - PR-pacientes-01 / DLR-R-002     `border-theme` literals removed
 *                                     from the list section
 *                                     (table dividers, row dividers,
 *                                     pagination border, mobile card
 *                                     border). The 2 inlined modals
 *                                     still carry `border-theme` (deferred
 *                                     to PR-pacientes-02).
 *   - PR-pacientes-01 / DLR-R-007     `tabular-nums` (`font-feature-
 *                                     settings: var(--font-features-
 *                                     tabular-nums)`) on stat-card value
 *                                     and on DNI + age cells.
 *   - PR-pacientes-01 / DLR-R-009     No `hover-lift` on stat cards
 *                                     (replaced with `<UiCard clickable>`).
 *   - PR-pacientes-01 / DLR-R-009     No `bg-success-badge` /
 *                                     `bg-danger-badge` status pills
 *                                     (replaced with tokenized
 *                                     systemGreen-50/700 + systemRed-
 *                                     50/700 classes).
 *   - PR-pacientes-01 / DLR-R-009     No raw `text-green-600` /
 *                                     `text-red-600` mobile action
 *                                     buttons (replaced with tokenized
 *                                     systemGreen-700 / systemRed-700).
 *   - PR-pacientes-01                 Status filter consumes `<UiSelect>`.
 *   - PR-pacientes-01                 Search input consumes `<UiInput>`.
 *   - PR-pacientes-01                 Stat cards consume `<UiCard>`
 *                                     with `clickable` attribute.
 *   - PR-pacientes-01 / DLR-R-021     No `<style scoped>` block remains.
 *
 * The `polishedFiles()` data provider enumerates the single file. The
 * inherited `ModuleAppShellTestCase::test_no_style_scoped` rule is GREEN
 * for this file after the migration; the test is also re-asserted here
 * for the pacientes-specific context.
 */
class PatientsListAppShellTest extends ModuleAppShellTestCase
{
    /** PatientsPage list file path constant — used by the data provider
     *  + the single-file rules. Keeps a single source of truth. */
    private const PATIENTS_LIST_PATH = '/resources/js/modules/patients/PatientsPage.vue';

    /** @return array<int, string> */
    protected static function polishedFiles(): array
    {
        return [
            dirname(__DIR__, 3) . self::PATIENTS_LIST_PATH,
        ];
    }

    /**
     * PAC-LIST-001 (list-section specific) — the list section MUST NOT
     * contain any `border-theme` literal. The inherited
     * `ModuleAppShellTestCase::test_no_legacy_border_theme_literal` rule
     * already covers the whole file; this focused re-assertion is a
     * documentation guard for the list-section migration specifically.
     *
     * The 2 `border-theme` literals that remain in the file are inside
     * the 2 inlined modals (New Patient + Edit Patient header dividers)
     * which are out of scope for PR-pacientes-01 (deferred to
     * PR-pacientes-02). They appear at lines 347 + 476 of the post-PR
     * file. This test only asserts the LIST section is clean; the
     * inlined modals are tracked separately in `PatientModalChromeTest`
     * (PR-pacientes-02).
     */
    public function test_list_no_border_theme(): void
    {
        $path = dirname(__DIR__, 3) . self::PATIENTS_LIST_PATH;
        $src = self::readSource($path);
        $this->assertNotNull($src, sprintf('%s must be readable.', $path));

        // Strip the 2 inlined modal sections (which still carry
        // `border-theme` per PR-pacientes-02 deferral) before scanning
        // the LIST section.
        $listSection = self::stripInlinedModals($src);

        $this->assertDoesNotMatchRegularExpression(
            '/(?<![\w-])border-theme(?![\w-])/',
            $listSection,
            sprintf(
                '%s MUST NOT contain `border-theme` in the LIST section (PAC-LIST-001 / DLR-R-002). '
                    . 'Use `border-hairline` or `divide-[color:var(--color-hairline)]`. '
                    . 'The 2 remaining `border-theme` literals live inside the inlined modals (PR-pacientes-02).',
                $path
            )
        );

        $this->assertDoesNotMatchRegularExpression(
            '/(?<![\w-])divide-theme(?![\w-])/',
            $listSection,
            sprintf(
                '%s MUST NOT contain `divide-theme` in the LIST section (PAC-LIST-001 / DLR-R-002). '
                    . 'Use `divide-[color:var(--color-hairline)]` for row dividers.',
                $path
            )
        );
    }

    /**
     * PAC-LIST-001 (list-section specific) — the row status pill MUST
     * NOT use the legacy `bg-success-badge` / `bg-danger-badge` aliases.
     * The tokenized Apple-language form uses `bg-systemGreen-50 text-
     * systemGreen-700` (active) and `bg-systemRed-50 text-systemRed-700`
     * (inactive), matching the `<UiStatusBadge variant="success|error">`
     * ramps.
     */
    public function test_list_no_legacy_status_pills(): void
    {
        $path = dirname(__DIR__, 3) . self::PATIENTS_LIST_PATH;
        $src = self::readSource($path);
        $this->assertNotNull($src, sprintf('%s must be readable.', $path));

        $listSection = self::stripInlinedModals($src);

        $forbidden = ['bg-success-badge', 'bg-danger-badge'];
        foreach ($forbidden as $alias) {
            $this->assertDoesNotMatchRegularExpression(
                '/(?<![\w-])' . preg_quote($alias, '/') . '(?![\w-])/',
                $listSection,
                sprintf(
                    '%s MUST NOT keep the legacy status-pill alias `%s` (PAC-LIST-001 / DLR-R-009). '
                        . 'Replace with the tokenized systemGreen-50/700 or systemRed-50/700 ramps.',
                    $path,
                    $alias
                )
            );
        }
    }

    /**
     * PAC-LIST-001 — the status filter MUST consume the `<UiSelect>`
     * primitive (NOT a raw `<select>` with `focus:ring-primary-500`).
     * The PatientsPage already used `<UiSelect>` before the rollout
     * (vertical-slice adoption); this rule pins the contract.
     */
    public function test_list_uses_ui_select_for_status_filter(): void
    {
        $path = dirname(__DIR__, 3) . self::PATIENTS_LIST_PATH;
        $src = self::readSource($path);
        $this->assertNotNull($src, sprintf('%s must be readable.', $path));

        // POSITIVE: the `<UiSelect>` primitive is wired to `statusFilter`
        // (the v-model binding). The contract: `<UiSelect ... v-model="statusFilter" ...>`.
        $this->assertTrue(
            (bool) preg_match(
                '/<UiSelect\b[^>]*\bv-model\s*=\s*["\']statusFilter["\']/',
                $src
            ),
            sprintf(
                '%s MUST consume `<UiSelect v-model="statusFilter">` for the status filter (PAC-LIST-001). '
                    . 'The raw `<select>` with `focus:ring-primary-500` is forbidden by DLR-R-004.',
                $path
            )
        );

        // NEGATIVE: the file MUST NOT contain a raw `<select>` element
        // anywhere in the LIST section (the 2 inlined modals use
        // `<UiSelect>` for gender/status, so a raw `<select>` would
        // be a regression).
        $listSection = self::stripInlinedModals($src);
        $this->assertSame(
            0,
            preg_match('/<select\b/i', $listSection),
            sprintf(
                '%s MUST NOT contain a raw `<select>` element in the LIST section (PAC-LIST-001 / DLR-R-009).',
                $path
            )
        );
    }

    /**
     * PAC-LIST-001 — the search input MUST consume the `<UiInput>`
     * primitive (NOT a raw `<input>` with `focus:ring-primary-500`).
     * The PatientsPage already used `<UiInput>` before the rollout;
     * this rule pins the contract.
     */
    public function test_list_uses_ui_input_for_search(): void
    {
        $path = dirname(__DIR__, 3) . self::PATIENTS_LIST_PATH;
        $src = self::readSource($path);
        $this->assertNotNull($src, sprintf('%s must be readable.', $path));

        $listSection = self::stripInlinedModals($src);

        $this->assertTrue(
            (bool) preg_match(
                '/<UiInput\b[^>]*\bv-model\s*=\s*["\']searchQuery["\']/',
                $listSection
            ),
            sprintf(
                '%s MUST consume `<UiInput v-model="searchQuery">` for the search input (PAC-LIST-001).',
                $path
            )
        );

        // NEGATIVE: no raw `<input>` in the LIST section.
        $this->assertSame(
            0,
            preg_match('/<input\b/i', $listSection),
            sprintf(
                '%s MUST NOT contain a raw `<input>` element in the LIST section (PAC-LIST-001).',
                $path
            )
        );
    }

    /**
     * PAC-LIST-001 — the 4 stat cards MUST NOT carry the legacy
     * `hover-lift` affordance (replaced with `<UiCard clickable>` which
     * owns focus + hover + press via the composed focus-ring and the
     * iOS press mechanism).
     */
    public function test_list_no_hover_lift(): void
    {
        $path = dirname(__DIR__, 3) . self::PATIENTS_LIST_PATH;
        $src = self::readSource($path);
        $this->assertNotNull($src, sprintf('%s must be readable.', $path));

        $listSection = self::stripInlinedModals($src);

        $this->assertDoesNotMatchRegularExpression(
            '/(?<![\w-])hover-lift(?![\w-])/',
            $listSection,
            sprintf(
                '%s MUST NOT keep the legacy `hover-lift` affordance on the 4 stat cards '
                    . '(PAC-LIST-001 / DLR-R-009). The token-aligned affordance is `<UiCard clickable>`.',
                $path
            )
        );
    }

    /**
     * PAC-LIST-001 — the 4 stat cards MUST consume `<UiCard>` with the
     * `clickable` attribute. The card's primitive handles focus + hover
     * + press; the click handler stays verbatim in `<script>`.
     *
     * Asserts 4 `<UiCard ... clickable>` references in the stat-cards
     * grid (one per stat card: Total / Activos / Inactivos / Filtrados).
     */
    public function test_list_stat_cards_use_ui_card_clickable(): void
    {
        $path = dirname(__DIR__, 3) . self::PATIENTS_LIST_PATH;
        $src = self::readSource($path);
        $this->assertNotNull($src, sprintf('%s must be readable.', $path));

        $listSection = self::stripInlinedModals($src);

        // The 4 stat cards each render `<UiCard variant="glass" clickable>`.
        $count = preg_match_all(
            '/<UiCard\b[^>]*\bclickable\b/',
            $listSection
        );
        $this->assertGreaterThanOrEqual(
            4,
            $count,
            sprintf(
                '%s MUST render 4 stat cards with `<UiCard clickable>` (PAC-LIST-001). Found %d.',
                $path,
                $count
            )
        );
    }

    /**
     * DLR-R-007 — stat card values + DNI + age cells MUST carry
     * `tabular-nums` (the token-aligned form:
     * `font-feature-settings: var(--font-features-tabular-nums)`).
     * The rule is the same as the global DLR-R-007 design contract;
     * the pacientes application uses inline `style` on the affected
     * cells, matching the precedent in `DashboardPage.vue`.
     */
    public function test_list_dni_age_columns_have_tabular_nums(): void
    {
        $path = dirname(__DIR__, 3) . self::PATIENTS_LIST_PATH;
        $src = self::readSource($path);
        $this->assertNotNull($src, sprintf('%s must be readable.', $path));

        $listSection = self::stripInlinedModals($src);

        // 1) The 4 stat-card value cells (one per card: Total /
        // Activos / Inactivos / Filtrados) each carry the inline style.
        $statCardCount = preg_match_all(
            '/style\s*=\s*["\']font-feature-settings:\s*var\(--font-features-tabular-nums\)["\']/',
            $listSection
        );
        $this->assertGreaterThanOrEqual(
            4,
            $statCardCount,
            sprintf(
                '%s MUST carry `font-feature-settings: var(--font-features-tabular-nums)` on the 4 stat-card values (DLR-R-007 / PAC-LIST-001). Found %d.',
                $path,
                $statCardCount
            )
        );

        // 2) The desktop-table DNI/age cells (the "ID: ..." line on the
        // Paciente column + the Edad column) each carry the same inline
        // style. Mobile card also carries the same on its DNI/age spans.
        // Total expected: 4 stat-card values + 2 desktop table cells + 2
        // mobile card spans = 8.
        $this->assertGreaterThanOrEqual(
            8,
            $statCardCount,
            sprintf(
                '%s MUST carry `tabular-nums` on the 4 stat-card values + the 2 desktop table DNI/age cells + the 2 mobile card DNI/age spans '
                    . '(DLR-R-007 / PAC-LIST-001). Found %d.',
                $path,
                $statCardCount
            )
        );
    }

    /**
     * PR-pacientes-01 / DLR-R-021 — the file MUST NOT contain a
     * `<style scoped>` block (re-asserted for the pacientes-specific
     * context; the inherited `ModuleAppShellTestCase::test_no_style_scoped`
     * rule is the canonical pin).
     */
    public function test_list_no_style_scoped(): void
    {
        $path = dirname(__DIR__, 3) . self::PATIENTS_LIST_PATH;
        $src = self::readSource($path);
        $this->assertNotNull($src, sprintf('%s must be readable.', $path));

        $this->assertDoesNotMatchRegularExpression(
            '/<style\s+scoped\s*>/',
            $src,
            sprintf(
                '%s MUST NOT contain a `<style scoped>` block (PR-pacientes-01 / DLR-R-021 / DLR-CORE-008). '
                    . 'The single `@media (max-width: 640px)` rule at the old line 1315 is a no-op; remove it.',
                $path
            )
        );
    }

    /**
     * PR-pacientes-01 — the mobile action buttons (Editar / Eliminar)
     * MUST NOT use the raw Tailwind `text-green-600` / `text-red-600`
     * palette ramps (DLR-R-009 — non-token colour ramps). The tokenized
     * Apple-language form uses `text-systemGreen-700` /
     * `text-systemRed-700` to match the desktop pills' color logic.
     *
     * The desktop "Ver" link button uses `text-systemBlue-600`; the
     * "Editar" / "Eliminar" buttons in BOTH desktop and mobile views use
     * the systemGreen/systemRed ramps. This rule pins the absence of the
     * raw 600-step colour classes.
     */
    public function test_list_no_text_green_red_600_action_buttons(): void
    {
        $path = dirname(__DIR__, 3) . self::PATIENTS_LIST_PATH;
        $src = self::readSource($path);
        $this->assertNotNull($src, sprintf('%s must be readable.', $path));

        $listSection = self::stripInlinedModals($src);

        $forbidden = ['text-green-600', 'text-red-600'];
        foreach ($forbidden as $alias) {
            $this->assertDoesNotMatchRegularExpression(
                '/(?<![\w-])' . preg_quote($alias, '/') . '(?![\w-])/',
                $listSection,
                sprintf(
                    '%s MUST NOT keep the raw Tailwind colour alias `%s` on mobile action buttons '
                        . '(PR-pacientes-01 / DLR-R-009). Use the tokenized `text-systemGreen-700` / `text-systemRed-700` ramps.',
                    $path,
                    $alias
                )
            );
        }
    }

    /**
     * Override the inherited `ModuleAppShellTestCase::test_page_references_canvas_token`
     * rule. The pacientes design §3 acknowledges that the canvas surface
     * is provided by the `<AppLayout>` wrapper (the file is mounted
     * inside the AppLayout, and `canvasRoutes` is what carries the
     * canvas background per DLR-CORE-001). The page file does NOT need
     * to reference `bg-canvas` / `var(--color-canvas)` directly — only
     * the AppLayout does. We pin the `<AppLayout>` reference instead.
     *
     * @dataProvider polishedFileProvider
     */
    public function test_page_references_canvas_token(string $path): void
    {
        $src = self::readSource($path);
        $this->assertNotNull($src, sprintf('%s must exist and be readable.', $path));

        // The file MUST reference `<AppLayout>` (which provides the
        // canvas surface per `canvasRoutes`). The `<AppLayout>` element
        // is the canonical mount point that inherits the canvas token
        // (see `categories/pacientes/design.md` §3 + `specs/pacientes/spec.md`
        // DLR-CORE-001 note).
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
     * Override the inherited `ModuleAppShellTestCase::test_no_legacy_border_theme_literal`
     * rule to scope the assertion to the LIST section only. The 2
     * inlined modals (New Patient + Edit Patient) still carry
     * `border-theme` literals (deferred to PR-pacientes-02 with the
     * `<UiModal>` chrome migration); asserting whole-file purity here
     * would RED until PR-pacientes-02 lands. The per-PR scope rule is
     * documented in `categories/pacientes/design.md` §3.4.
     *
     * The assertion shape is the same as the inherited rule (whole-token
     * regex via negative lookbehind + lookahead; modifier variants
     * `border-theme-light` etc. are excluded). Only the input source
     * is scoped to the list section.
     *
     * @dataProvider polishedFileProvider
     */
    public function test_no_legacy_border_theme_literal(string $path): void
    {
        $src = self::readSource($path);
        $this->assertNotNull($src, sprintf('%s must be readable.', $path));

        $listSection = self::stripInlinedModals($src);

        $pattern = '/(?<![\w-])border-theme(?![\w-])/';
        $this->assertDoesNotMatchRegularExpression(
            $pattern,
            $listSection,
            sprintf(
                '%s LIST section must not contain the legacy `border-theme` literal (DLR-R-002). '
                    . 'Use the `border-hairline` / `--color-hairline` token instead. '
                    . 'The 2 remaining `border-theme` literals live inside the inlined modals (PR-pacientes-02).',
                $path
            )
        );
    }

    /**
     * PAC-RT-001 / PAC-CON-001 — the `<script>` block of `PatientsPage.vue`
     * MUST keep the `useEcho` `patients` channel subscription byte-for-
     * byte: `channel('patients')` + `.listen('.patient.created', ...)` +
     * `.listen('.patient.updated', ...)` + `.listen('.patient.deleted', ...)`
     * + `echo.leave('patients')` in `onUnmounted`.
     *
     * The `<script>` block is NEVER edited in any pacientes PR; the rule
     * is asserted here for the list-section surface (the channels are
     * pinned to the LIST page; the cross-category channels live on
     * `PatientDetailPage.vue`).
     */
    public function test_list_use_echo_patients_channel_preserved(): void
    {
        $path = dirname(__DIR__, 3) . self::PATIENTS_LIST_PATH;
        $src = self::readSource($path);
        $this->assertNotNull($src, sprintf('%s must be readable.', $path));

        // The `patients` channel is fetched via the `channel('patients')` helper.
        $this->assertTrue(
            (bool) preg_match("#channel\s*\(\s*['\"]patients['\"]\s*\)#", $src),
            sprintf(
                '%s MUST keep `channel(\'patients\')` subscription (PAC-RT-001). '
                    . 'The Echo channel name is the canonical realtime pipe for patient events on the list page.',
                $path
            )
        );

        // The 3 patient-event listeners.
        $requiredEvents = [
            '.patient.created',
            '.patient.updated',
            '.patient.deleted',
        ];
        foreach ($requiredEvents as $event) {
            $this->assertTrue(
                (bool) preg_match(
                    '#\.listen\s*\(\s*[\'"]' . preg_quote($event, '#') . '[\'"]#',
                    $src
                ),
                sprintf(
                    '%s MUST keep the `.listen("%s")` event listener (PAC-RT-001).',
                    $path,
                    $event
                )
            );
        }

        // The `onUnmounted` hook calls `echo.leave('patients')`.
        $this->assertTrue(
            (bool) preg_match(
                "#echo\.leave\s*\(\s*['\"]patients['\"]\s*\)#",
                $src
            ),
            sprintf(
                '%s MUST keep `echo.leave(\'patients\')` in the `onUnmounted` hook (PAC-RT-001).',
                $path
            )
        );
    }

    /**
     * PAC-REV-001 — the legacy `<Pagination>` import MUST stay verbatim
     * (the consolidation to `<UiPagination>` rides global PR3 / Recepción
     * procedimientos per OQ#7). Silent rename here would break the
     * dependency graph.
     */
    public function test_list_legacy_pagination_import_preserved(): void
    {
        $path = dirname(__DIR__, 3) . self::PATIENTS_LIST_PATH;
        $src = self::readSource($path);
        $this->assertNotNull($src, sprintf('%s must be readable.', $path));

        $this->assertTrue(
            (bool) preg_match(
                "/import\s+Pagination\s+from\s+['\"]\.\.\/\.\.\/components\/ui\/Pagination\.vue['\"]/",
                $src
            ),
            sprintf(
                '%s MUST keep the legacy `import Pagination from .../Pagination.vue` import verbatim '
                    . '(PAC-REV-001 / OQ#7). The consolidation to `<UiPagination>` rides global PR3.',
                $path
            )
        );

        // The `<Pagination>` component is referenced in the template.
        $this->assertTrue(
            (bool) preg_match('/<Pagination\b/', $src),
            sprintf(
                '%s MUST keep the `<Pagination>` component reference in the template (PAC-REV-001).',
                $path
            )
        );

        // The file MUST NOT import `<UiPagination>` (consolidation rides global PR3).
        $this->assertDoesNotMatchRegularExpression(
            '/import\s+UiPagination\s+from/',
            $src,
            sprintf(
                '%s MUST NOT silently rename to `import UiPagination` (PAC-REV-001 / OQ#7). '
                    . 'The consolidation rides global PR3 (Recepción procedimientos).',
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
     * Strip the 2 inlined modals (New Patient + Edit Patient) so the
     * LIST-section rules below scan only the relevant surface. The
     * modals are out of scope for PR-pacientes-01; they ride
     * PR-pacientes-02 with `<UiModal>` chrome + `<UiInput>` /
     * `<UiSelect>` / `<UiTextarea>` migration. The 2 `border-theme`
     * literals they still carry are tracked by
     * `PatientModalChromeTest` in PR-pacientes-02.
     *
     * The modals are demarcated by the `<!-- New Patient Modal -->`
     * and `<!-- Edit Patient Modal -->` comments. The simplest reliable
     * cut is to take only the prefix up to the start of the first
     * modal; everything after is OUT of scope.
     */
    private static function stripInlinedModals(string $src): string
    {
        $marker = '<!-- New Patient Modal -->';
        $pos = strpos($src, $marker);
        if ($pos === false) {
            return $src;
        }
        return substr($src, 0, $pos);
    }
}
