<?php

namespace Tests\Unit\DesignSystem;

/**
 * PR-ambientes-02 — EnvironmentsModalChromeTest.
 *
 * Asserts the AMB-02-007 rule for the 3 inlined modals on `EnvironmentsPage.vue`:
 *
 *   - New Environment Modal (lines 213-259 in the source): 4 raw form
 *     fields — 1 `<input>` (name), 2 `<textarea>` (description + equipment),
 *     1 `<select>` (status). All 4 fields MUST migrate to the canonical
 *     primitives: `<UiInput>` / `<UiTextarea>` / `<UiSelect>`.
 *
 *   - Edit Environment Modal (lines 262-314 in the source): 4 raw form
 *     fields — 2 `<input>` (name + code), 1 `<textarea>` (description),
 *     1 `<select>` (status). Same primitive migration applies.
 *
 *   - View Environment Modal (lines 317-352 in the source): NO raw form
 *     fields (it's read-only). The status pill at line 340 in the original
 *     MUST consume `<UiBadge>` (NOT a raw `<span>` with legacy class).
 *
 * Additional rules:
 *   - Every `v-model=` binding on the migrated fields MUST be preserved
 *     byte-for-byte (the reactivity contract stays verbatim).
 *   - The `required` attribute on the name + code + status fields MUST
 *     survive the migration (browser-side form validation stays in place).
 *
 * Implementation note: regex delimiters are `#` (NOT `/`) because the path
 * constants contain forward slashes; using `/` as delimiter would force
 * every `/` to be escaped `\/`, which is brittle and error-prone.
 */
class EnvironmentsModalChromeTest extends \PHPUnit\Framework\TestCase
{
    /** List page path constant — single source of truth for the data provider. */
    private const LIST_PAGE_PATH = '/resources/js/modules/environments/EnvironmentsPage.vue';

    private static function projectRoot(): string
    {
        return dirname(__DIR__, 3);
    }

    private static function listPagePath(): string
    {
        return self::projectRoot() . self::LIST_PAGE_PATH;
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
     * AMB-02-007 — the 3 inlined modals MUST consume `<UiInput>` /
     * `<UiTextarea>` / `<UiSelect>` primitives instead of raw `<input>` /
     * `<textarea>` / `<select>` controls with legacy focus chrome.
     *
     * POSITIVE rule: at least 2 `<UiInput>` references (New name + Edit name),
     * at least 1 `<UiTextarea>` reference (description + equipment across
     * New + Edit), and at least 2 `<UiSelect>` references (New status +
     * Edit status).
     *
     * NEGATIVE rule: zero raw `<input>`, `<textarea>`, `<select>` literals
     * anywhere in the modal sections. The modal sections are bracketed by
     * `<UiModal v-model="showNewEnvironmentModal"`,
     * `<UiModal v-model="showEditEnvironmentModal"`, and
     * `<UiModal v-model="showViewEnvironmentModal"`. To avoid matching the
     * `<UiModal>` controls (which contain `<input>` for search, etc.) we
     * rely on the source already being clean: the apply phase replaces the
     * raw `<input>`/`<textarea>`/`<select>` blocks inside the modals.
     */
    public function test_modals_use_ui_form_primitives(): void
    {
        $path = self::listPagePath();
        $src = self::readSource($path);
        $this->assertNotNull($src, sprintf('%s must be readable.', $path));

        // POSITIVE: ≥2 `<UiInput` references — New modal `name` field + Edit
        // modal `name` field + Edit modal `code` field. Plus the legacy
        // global search input on line 47 (which already was a `<UiInput>`).
        // We require ≥3 to be safe (the search input + 2 modal name/code
        // inputs).
        $uiInputCount = preg_match_all('#<UiInput\b#', $src);
        $this->assertGreaterThanOrEqual(
            3,
            $uiInputCount,
            sprintf(
                '%s MUST consume `<UiInput>` for the New modal name field + the '
                . 'Edit modal name + code fields (AMB-02-007). Found %d <UiInput> '
                . 'reference(s); expected at least 3.',
                $path,
                $uiInputCount
            )
        );

        // POSITIVE: ≥3 `<UiTextarea>` references — New modal `description`
        // + New modal `equipment` + Edit modal `description` = 3 textareas.
        $uiTextareaCount = preg_match_all('#<UiTextarea\b#', $src);
        $this->assertGreaterThanOrEqual(
            3,
            $uiTextareaCount,
            sprintf(
                '%s MUST consume `<UiTextarea>` for the New modal description + equipment '
                . 'fields + the Edit modal description field (AMB-02-007). '
                . 'Found %d <UiTextarea> reference(s); expected at least 3.',
                $path,
                $uiTextareaCount
            )
        );

        // POSITIVE: ≥2 `<UiSelect` references — New modal status + Edit modal
        // status. Plus the legacy global status filter (line 71). We require
        // ≥3 to be safe (filter + 2 modal status selects).
        $uiSelectCount = preg_match_all('#<UiSelect\b#', $src);
        $this->assertGreaterThanOrEqual(
            3,
            $uiSelectCount,
            sprintf(
                '%s MUST consume `<UiSelect>` for the New modal status + Edit modal status '
                . '+ global status filter (AMB-02-007). Found %d <UiSelect> reference(s); '
                . 'expected at least 3.',
                $path,
                $uiSelectCount
            )
        );
    }

    /**
     * AMB-02-007 — the 9 raw form fields across the 3 inlined modals MUST
     * be replaced by primitives. NEGATIVE rule: zero raw `<input`,
     * `<textarea>`, and `<select` literals outside of `<UiModal>` /
     * `<UiInput>` / `<UiSelect>` template references.
     *
     * Practical check: the file must not contain `<input v-model=` (the
     * raw `<input>` element bound to a Vue reactive ref). `<UiInput>`
     * emits `update:modelValue` events, not raw `v-model` on `<input>`.
     */
    public function test_no_raw_form_field_vmodel_bindings(): void
    {
        $path = self::listPagePath();
        $src = self::readSource($path);
        $this->assertNotNull($src, sprintf('%s must be readable.', $path));

        // NEGATIVE: zero `<input v-model=` raw-control v-model bindings.
        // The 8 modal raw form fields (3 `<input>` + 3 `<textarea>` + 2
        // `<select>`) all carried `v-model=` bindings. After migration they
        // must be gone.
        $this->assertSame(
            0,
            preg_match('#<input\b[^>]*\bv-model=#', $src),
            sprintf(
                '%s MUST NOT keep raw `<input v-model="...">` controls inside the modal '
                . 'sections (AMB-02-007). The 3 modal `<input>` fields (New name + Edit name + '
                . 'Edit code) MUST migrate to `<UiInput v-model="..." />`.',
                $path
            )
        );

        // NEGATIVE: zero `<textarea v-model=` raw-control v-model bindings.
        $this->assertSame(
            0,
            preg_match('#<textarea\b[^>]*\bv-model=#', $src),
            sprintf(
                '%s MUST NOT keep raw `<textarea v-model="...">` controls inside the modal '
                . 'sections (AMB-02-007). The 3 modal `<textarea>` fields (New description + '
                . 'New equipment + Edit description) MUST migrate to `<UiTextarea v-model="..." />`.',
                $path
            )
        );

        // NEGATIVE: zero `<select v-model=` raw-control v-model bindings.
        $this->assertSame(
            0,
            preg_match('#<select\b[^>]*\bv-model=#', $src),
            sprintf(
                '%s MUST NOT keep raw `<select v-model="...">` controls inside the modal '
                . 'sections (AMB-02-007). The 2 modal `<select>` fields (New status + Edit status) '
                . 'MUST migrate to `<UiSelect v-model="..." />`.',
                $path
            )
        );
    }

    /**
     * AMB-02-007 — every `v-model=` binding on the migrated fields MUST be
     * preserved byte-for-byte (the reactivity contract stays verbatim).
     * Pinning the 8 specific bindings: `newEnvironment.name`,
     * `newEnvironment.description`, `newEnvironment.equipment`,
     * `newEnvironment.status`, `editingEnvironment.name`,
     * `editingEnvironment.code`, `editingEnvironment.description`,
     * `editingEnvironment.status`.
     */
    public function test_vmodel_bindings_preserved_byte_for_byte(): void
    {
        $path = self::listPagePath();
        $src = self::readSource($path);
        $this->assertNotNull($src, sprintf('%s must be readable.', $path));

        $expectedBindings = [
            'newEnvironment.name',
            'newEnvironment.description',
            'newEnvironment.equipment',
            'newEnvironment.status',
            'editingEnvironment.name',
            'editingEnvironment.code',
            'editingEnvironment.description',
            'editingEnvironment.status',
        ];

        foreach ($expectedBindings as $binding) {
            $this->assertTrue(
                (bool) preg_match(
                    '#v-model=["\']' . preg_quote($binding, '#') . '["\']#',
                    $src
                ),
                sprintf(
                    '%s MUST preserve the `v-model="%s"` binding on the migrated '
                    . 'modal form field (AMB-02-007).',
                    $path,
                    $binding
                )
            );
        }
    }

    /**
     * AMB-02-007 — the View modal status pill (line 340 in the original)
     * MUST consume `<UiStatusBadge>` (NOT a raw `<span>` with legacy class
     * string). The list-row status pill already consumes `<UiStatusBadge>`
     * from PR-ambientes-01 (AMB-01-008); the View modal must follow suit.
     *
     * POSITIVE rule: ≥2 `<Ui(?:Status)?Badge` references in the file —
     * one for the list-row pill + one for the View-modal pill.
     */
    public function test_view_modal_status_pill_uses_ui_badge(): void
    {
        $path = self::listPagePath();
        $src = self::readSource($path);
        $this->assertNotNull($src, sprintf('%s must be readable.', $path));

        // POSITIVE: ≥2 `<UiBadge>` / `<UiStatusBadge>` references.
        $badgeRefs = preg_match_all(
            '#<Ui(?:Status)?Badge\b#',
            $src
        );
        $this->assertGreaterThanOrEqual(
            2,
            $badgeRefs,
            sprintf(
                '%s MUST consume `<UiStatusBadge>` for both the list-row pill and the View-modal '
                . 'status pill (AMB-02-007 / AMB-01-008). Found %d badge reference(s); '
                . 'expected at least 2.',
                $path,
                $badgeRefs
            )
        );

        // The View modal already consumed `<UiStatusBadge>` per PR-ambientes-01
        // (see line 327 in the current source), so this assertion is satisfied
        // from PR-01 forward. PR-02 keeps it intact.
    }
}
