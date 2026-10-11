<?php

namespace Tests\Unit\DesignSystem;

/**
 * PR-ambientes-02 + T3 (modal surfaces) — EnvironmentsModalChromeTest.
 *
 * Asserts the AMB-02-007 rule for the 2 inlined modals on `EnvironmentsPage.vue`:
 *
 *   - New Environment Modal: 4 form fields (1 `<UiInput>` name, 2
 *     `<UiTextarea>` description + equipment, 1 `<UiSelect>` status) on the
 *     canonical primitives. In the pre-migration source these were 4 raw
 *     form fields (1 `<input>`, 2 `<textarea>`, 1 `<select>`).
 *
 *   - Edit Environment Modal: 4 form fields (2 `<UiInput>` name + code,
 *     1 `<UiTextarea>` description, 1 `<UiSelect>` status) on the same
 *     primitives.
 *
 *   - View Environment Modal: DELETED by T3 (design decision D4). It was
 *     unreachable dead code fed only by the `viewEnvironment` handler that
 *     an eslint-disable kept alive; "Ver detalle" (EyeIcon row action) is
 *     the single path to the detail route. Its status-pill duty moved to
 *     the Estado-group preview badge inside the create + edit modals, which
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
     * AMB-02-007 — the 2 inlined modals MUST consume `<UiInput>` /
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
     * `<UiModal v-model="showNewEnvironmentModal"` and
     * `<UiModal v-model="showEditEnvironmentModal"` (the View modal was
     * deleted by T3/D4). To avoid matching the `<UiModal>` controls (which
     * contain `<input>` for search, etc.) we rely on the source already being
     * clean: the apply phase replaces the raw `<input>`/`<textarea>`/`<select>`
     * blocks inside the modals.
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
     * AMB-02-007 + T3/D4 — the View modal is deleted (dead code). The status
     * pill surfaces that remain MUST consume `<UiStatusBadge>` (NOT a raw
     * `<span>` with legacy class string): the list-row pill (PR-ambientes-01 /
     * AMB-01-008) and the Estado-group preview badge inside the create and
     * edit modals.
     *
     * POSITIVE rule: ≥2 `<Ui(?:Status)?Badge` references in the file (the
     * count rule from PR-ambientes-02 is kept verbatim; the second surface is
     * the modal Estado preview now, not the deleted View modal).
     *
     * NEGATIVE rule (D4 re-anchor): zero View modal remnants — the
     * `viewEnvironment` handler, the `viewingEnvironment` /
     * `showViewEnvironmentModal` refs, and the `Ver Ambiente` modal title
     * MUST be gone; "Ver detalle" remains the single path to the detail route.
     */
    public function test_view_modal_removed_status_pills_use_ui_badge(): void
    {
        $path = self::listPagePath();
        $src = self::readSource($path);
        $this->assertNotNull($src, sprintf('%s must be readable.', $path));

        // POSITIVE: ≥2 `<UiBadge>` / `<UiStatusBadge>` references (list-row
        // pill + create/edit Estado preview badges).
        $badgeRefs = preg_match_all(
            '#<Ui(?:Status)?Badge\b#',
            $src
        );
        $this->assertGreaterThanOrEqual(
            2,
            $badgeRefs,
            sprintf(
                '%s MUST consume `<UiStatusBadge>` for the list-row pill and the modal Estado-group '
                . 'preview pills (AMB-02-007 / AMB-01-008 / T3-D4). Found %d badge reference(s); '
                . 'expected at least 2.',
                $path,
                $badgeRefs
            )
        );

        // NEGATIVE (D4 re-anchor): the unreachable View modal and its handler
        // are gone. `viewEnvironment` was kept alive only by an eslint-disable
        // and never reachable from the template; T3 removes both surfaces.
        $this->assertSame(
            0,
            preg_match('#\bviewEnvironment\b#', $src),
            sprintf(
                '%s MUST NOT keep the dead `viewEnvironment` handler (T3/D4). '
                . '"Ver detalle" (EyeIcon row action) is the single path to the detail route.',
                $path
            )
        );
        $this->assertSame(
            0,
            preg_match('#viewingEnvironment|showViewEnvironmentModal#', $src),
            sprintf(
                '%s MUST NOT keep the `viewingEnvironment` / `showViewEnvironmentModal` refs '
                . '(T3/D4). The View modal is deleted.',
                $path
            )
        );
        $this->assertStringNotContainsString(
            'Ver Ambiente',
            $src,
            sprintf(
                '%s MUST NOT keep the `Ver Ambiente` modal title (T3/D4). The View modal is deleted.',
                $path
            )
        );
    }
}
