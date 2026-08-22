<?php

namespace Tests\Unit\DesignSystem;

/**
 * PR-tipos-01 — AppointmentTypesListCleanupTest.
 *
 * Asserts the 4 residual-cleanup rules for the `appointment-types` module
 * list page (`AppointmentTypesPage.vue`). The list page was already
 * substantially polished by PR-citas-04 (5 inherited DLR-R rules via
 * `ModuleAppShellTestCase` + 7 PR-citas-04 rules via
 * `AppointmentTypesAppShellTest::polishedFiles()`); PR-tipos-01 wires up
 * the residual raw inputs / textareas / spinner / empty state on the list
 * page only. The detail page (`AppointmentTypeDetailPage.vue`) is out of
 * scope — it is covered by PR-tipos-02 (`AppointmentTypesAppShellTest`
 * extension).
 *
 * PR-tipos-01-only rules asserted here (each additive, NOT replacing any
 * existing rule in `ModuleAppShellTestCase` or
 * `AppointmentTypesAppShellTest`):
 *
 *   - TIPOS-01-001  9 raw `<input>` (text + number) in New + Edit modals
 *                   migrated to `<UiInput v-model="..." />`. The 2
 *                   `<input type="color">` color pickers MAY remain raw
 *                   because the canonical `<UiInput>` primitive does not
 *                   formally support `type="color"`.
 *   - TIPOS-01-002  2 raw `<textarea>` (description in New + Edit modals)
 *                   migrated to `<UiTextarea v-model="..." />`.
 *   - TIPOS-01-003  Hand-rolled `border-b-2 border-accent` spinner on
 *                   line 85 migrated to `<LoadingSpinner />`. The
 *                   `border-accent` legacy alias MUST be removed.
 *   - TIPOS-01-004  Hand-rolled empty state (custom SVG + paragraph on
 *                   lines 89-104) migrated to `<UiEmptyState>`. The
 *                   `<UiEmptyState>` import at line 427 was unused before
 *                   this PR; it MUST now be consumed.
 *
 * Constraint: the `<script>` block MUST remain byte-for-byte unchanged
 * (no `useApi` / `useToast` / `useConfirm` / `useErrorHandler` /
 * `useFormatters` import edits, no reactivity edits). The `<script>` block
 * is not asserted here directly because the inherited
 * `AppointmentTypesAppShellTest::test_list_page_use_api_ownership_preserved`
 * already pins the script boundary.
 *
 * Implementation note: regex delimiters are `#` (NOT `/`) because the
 * file path contains forward slashes; using `/` as delimiter would force
 * every `/` to be escaped `\/`, which is brittle and error-prone.
 */
class AppointmentTypesListCleanupTest extends ModuleAppShellTestCase
{
    /** List page path constant — single source of truth for the data provider. */
    private const LIST_PAGE_PATH = '/resources/js/modules/appointment-types/AppointmentTypesPage.vue';

    /** @return array<int, string> */
    protected static function polishedFiles(): array
    {
        return [
            dirname(__DIR__, 3) . self::LIST_PAGE_PATH,
        ];
    }

    /**
     * TIPOS-01-001 — 7 raw text/number `<input>` in the New + Edit modals
     * MUST migrate to `<UiInput v-model="..." />`. The 2 `<input
     * type="color">` color pickers MAY remain raw because `<UiInput>` does
     * not formally support `type="color"`.
     *
     * POSITIVE rule: ≥7 `<UiInput v-model="...">` references in the modal
     * sections (4 in New modal + 3 in Edit modal — name + duration +
     * price in both modals + the color text-input in New modal).
     *
     * NEGATIVE rule: zero raw `<input type="text">` or `<input
     * type="number">` elements anywhere in the file. Color pickers
     * (`type="color"`) are excluded from this assertion.
     */
    public function test_list_uses_ui_input_for_modal_form_fields(): void
    {
        $path = dirname(__DIR__, 3) . self::LIST_PAGE_PATH;
        $src = self::readSource($path);
        $this->assertNotNull($src, sprintf('%s must be readable.', $path));

        // POSITIVE: count `<UiInput v-model="...">` references. The
        // migration targets 7 text/number inputs (name / duration / price
        // / color text-input × 1 in New modal + name / duration / price in
        // Edit modal). Assert ≥7 to allow the future addition of a search
        // input or other modal field to also consume `<UiInput>` without
        // breaking this rule.
        $uiInputCount = preg_match_all('#<UiInput\b[^>]*\bv-model=#', $src);
        $this->assertGreaterThanOrEqual(
            7,
            $uiInputCount,
            sprintf(
                '%s MUST consume <UiInput v-model="..."> for the 7 text/number modal form fields (TIPOS-01-001). '
                . 'Found %d <UiInput> references.',
                $path,
                $uiInputCount
            )
        );

        // NEGATIVE: zero raw `<input type="text">` or `<input type="number">`
        // elements. The 2 `<input type="color">` color pickers are excluded
        // because `<UiInput>` does not formally support `type="color"`.
        $rawTextInputCount = preg_match_all(
            '#<input\b[^>]*\btype=["\']text["\']#',
            $src
        );
        $this->assertSame(
            0,
            $rawTextInputCount,
            sprintf(
                '%s MUST NOT keep raw `<input type="text">` modal fields (TIPOS-01-001). '
                . 'Replace with <UiInput v-model="...">. Found %d raw `<input type="text">` element(s).',
                $path,
                $rawTextInputCount
            )
        );

        $rawNumberInputCount = preg_match_all(
            '#<input\b[^>]*\btype=["\']number["\']#',
            $src
        );
        $this->assertSame(
            0,
            $rawNumberInputCount,
            sprintf(
                '%s MUST NOT keep raw `<input type="number">` modal fields (TIPOS-01-001). '
                . 'Replace with <UiInput v-model="..." type="number">. Found %d raw `<input type="number">` element(s).',
                $path,
                $rawNumberInputCount
            )
        );
    }

    /**
     * TIPOS-01-002 — 2 raw `<textarea>` (description in New + Edit
     * modals) MUST migrate to `<UiTextarea v-model="..." />`. The
     * `v-model="newType.description"` and `v-model="editingType.description"`
     * bindings MUST be preserved verbatim.
     *
     * POSITIVE rule: ≥2 `<UiTextarea v-model="...">` references.
     *
     * NEGATIVE rule: zero raw `<textarea>` elements.
     */
    public function test_list_uses_ui_textarea_for_description_fields(): void
    {
        $path = dirname(__DIR__, 3) . self::LIST_PAGE_PATH;
        $src = self::readSource($path);
        $this->assertNotNull($src, sprintf('%s must be readable.', $path));

        // POSITIVE: ≥2 `<UiTextarea v-model="...">` references (one per
        // modal — New modal + Edit modal description fields).
        $uiTextareaCount = preg_match_all('#<UiTextarea\b[^>]*\bv-model=#', $src);
        $this->assertGreaterThanOrEqual(
            2,
            $uiTextareaCount,
            sprintf(
                '%s MUST consume <UiTextarea v-model="..."> for the 2 description fields (TIPOS-01-002). '
                . 'Found %d <UiTextarea> reference(s).',
                $path,
                $uiTextareaCount
            )
        );

        // NEGATIVE: zero raw `<textarea>` elements. The `v-model="newType.description"`
        // and `v-model="editingType.description"` bindings must now live on
        // `<UiTextarea>` tags.
        $rawTextareaCount = preg_match_all('#<textarea\b#', $src);
        $this->assertSame(
            0,
            $rawTextareaCount,
            sprintf(
                '%s MUST NOT keep raw `<textarea>` modal fields (TIPOS-01-002). '
                . 'Replace with <UiTextarea v-model="...">. Found %d raw `<textarea>` element(s).',
                $path,
                $rawTextareaCount
            )
        );
    }

    /**
     * TIPOS-01-003 — hand-rolled `border-b-2 border-accent` spinner on
     * the list loading state (line 85) MUST migrate to `<LoadingSpinner />`.
     * The `border-accent` legacy alias MUST be removed.
     *
     * POSITIVE rule: ≥1 `<LoadingSpinner` reference in the file.
     *
     * NEGATIVE rule: zero `border-accent` legacy alias anywhere in the file.
     */
    public function test_list_uses_loading_spinner(): void
    {
        $path = dirname(__DIR__, 3) . self::LIST_PAGE_PATH;
        $src = self::readSource($path);
        $this->assertNotNull($src, sprintf('%s must be readable.', $path));

        // POSITIVE: ≥1 `<LoadingSpinner` reference. The list loading
        // state (line 85) MUST now consume the canonical primitive.
        $this->assertTrue(
            (bool) preg_match('#<LoadingSpinner\b#', $src),
            sprintf(
                '%s MUST consume <LoadingSpinner /> for the list loading state (TIPOS-01-003). '
                . 'Found no <LoadingSpinner> reference.',
                $path
            )
        );

        // NEGATIVE: zero `border-accent` legacy alias anywhere in the file.
        // The hand-rolled spinner carried `border-b-2 border-accent`; that
        // alias MUST be removed entirely (the canonical primitive does not
        // use any `border-accent` class).
        $borderAccentCount = preg_match_all('#(?<![\w-])border-accent(?![\w-])#', $src);
        $this->assertSame(
            0,
            $borderAccentCount,
            sprintf(
                '%s MUST NOT keep the legacy `border-accent` spinner alias (TIPOS-01-003). '
                . 'Found %d `border-accent` match(es).',
                $path,
                $borderAccentCount
            )
        );
    }

    /**
     * TIPOS-01-004 — hand-rolled empty state with custom SVG (lines
     * 89-104) MUST migrate to `<UiEmptyState title="..." description="..." />`.
     * The `<UiEmptyState>` import at line 427 was unused before this PR;
     * it MUST now be consumed (no dead import remains).
     *
     * POSITIVE rule: ≥1 `<UiEmptyState` reference in the file.
     *
     * NEGATIVE rule: zero hand-rolled custom-SVG empty container. The
     * hand-rolled `<svg class="mx-auto h-12 w-12 text-theme-secondary">`
     * empty-state icon MUST be replaced.
     */
    public function test_list_uses_ui_empty_state(): void
    {
        $path = dirname(__DIR__, 3) . self::LIST_PAGE_PATH;
        $src = self::readSource($path);
        $this->assertNotNull($src, sprintf('%s must be readable.', $path));

        // POSITIVE: ≥1 `<UiEmptyState` reference. The list empty state
        // (lines 89-104) MUST now consume the canonical primitive. The
        // unused import at line 427 is consumed by this single reference.
        $uiEmptyStateCount = preg_match_all('#<UiEmptyState\b#', $src);
        $this->assertGreaterThanOrEqual(
            1,
            $uiEmptyStateCount,
            sprintf(
                '%s MUST consume <UiEmptyState title="..." description="..." /> for the list empty state (TIPOS-01-004). '
                . 'Found %d <UiEmptyState> reference(s). The unused import at line 427 must be wired up.',
                $path,
                $uiEmptyStateCount
            )
        );

        // NEGATIVE: zero hand-rolled custom-SVG empty container. The
        // hand-rolled empty state carried a `<svg class="mx-auto h-12 w-12 text-theme-secondary">`
        // icon paired with a `<p class="mt-2 text-theme-secondary">No se encontraron tipos de cita</p>`
        // paragraph. The hand-rolled SVG MUST be removed.
        $this->assertDoesNotMatchRegularExpression(
            '#<svg\b[^>]*\bclass=["\'][^"\']*\bmx-auto\b[^"\']*\bh-12\b#',
            $src,
            sprintf(
                '%s MUST NOT keep the hand-rolled `<svg class="mx-auto h-12 ...">` empty-state container (TIPOS-01-004). '
                . 'Replace with <UiEmptyState title="..." description="..." />.',
                $path
            )
        );

        // Companion NEGATIVE: the literal empty-state title text
        // `No se encontraron tipos de cita` MUST NOT remain in a raw
        // `<p>` element. After migration the title lives on the
        // `<UiEmptyState title="...">` prop.
        $this->assertDoesNotMatchRegularExpression(
            '#<p\b[^>]*>\s*No se encontraron tipos de cita\s*</p>#',
            $src,
            sprintf(
                '%s MUST NOT keep the hand-rolled `<p>No se encontraron tipos de cita</p>` empty-state paragraph (TIPOS-01-004). '
                . 'The title MUST live on the <UiEmptyState title="..."> prop.',
                $path
            )
        );
    }

    /**
     * Local helper — read the file source, or null if unreadable.
     * Mirrors `AppointmentTypesAppShellTest::readSource()` so this test
     * is self-contained without extending the existing class (we extend
     * `ModuleAppShellTestCase` directly per `PatientsListAppShellTest`
     * precedent).
     */
    private static function readSource(string $path): ?string
    {
        if (!is_file($path)) {
            return null;
        }
        $src = file_get_contents($path);
        return $src === false ? null : $src;
    }
}
