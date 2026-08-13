<?php

namespace Tests\Unit\DesignSystem;

use PHPUnit\Framework\TestCase;

/**
 * PR0 (ui-rollout-all-modules-2026-08) — pins the forbidden legacy alias
 * list (scenario LEGACY-LIST-001 + cross-cutting rule DLR-R-009).
 *
 * Every polished module file MUST contain zero whole-token matches against
 * the `LEGACY_ALIASES` constant. The whole-token match is critical:
 * `bg-success-1000` must NOT match `bg-success-100`.
 *
 * The PR0 list EXCLUDES `border-theme` (AppLayout/Card/Sidebar/Topbar still
 * use it heavily; it will be re-included as those files migrate). The
 * `ModuleAppShellTestCase::test_no_legacy_border_theme_literal` rule is the
 * immediate pin for new module files.
 *
 * Categories extend opportunistically per LEGACY-LIST-002: when a defect
 * is observed during a module PR, the pattern is added in the same PR.
 */
class LegacyAliasForbiddenTest extends TestCase
{
    /**
     * PR0 forbidden legacy aliases. Whole-token regex
     * `/(?<![\w-])ALIAS(?![\w-])/` is enforced (per design.md §4.3 final
     * corrected form).
     *
     * Categories extend opportunistically.
     */
    private const LEGACY_ALIASES = [
        // Success ramp legacy (replaced by <UiStatusBadge variant="success">)
        'bg-success-100',
        'bg-success-500',
        'bg-success-600',
        'bg-success-700',
        'text-success-700',
        // Warning ramp legacy (replaced by <UiStatusBadge variant="warning">)
        'bg-warning-100',
        'text-warning-700',
        // Error ramp legacy (replaced by <UiStatusBadge variant="error">)
        'bg-error-100',
        'text-error-700',
        'bg-error-600',
        // Accent / primary legacy (replaced by tokenised systemBlue-* ramps)
        'text-accent',
        'bg-accent',
        'hover:text-primary-700',
        'bg-primary-50',
        'bg-primary-100',
        'bg-primary-200',
        // Focus ring legacy (replaced by var(--focus-ring-default))
        'focus:ring-primary-500',
        'focus:border-accent',
        // Modal backdrop legacy (replaced by <UiModal> chrome)
        // PR-citas-03 extension: hand-built `<Teleport to="body">` + `bg-black bg-opacity-50`
        // backdrop is deprecated; <UiModal> owns the backdrop.
        'bg-black bg-opacity-50',
    ];

    /**
     * Default polished file set: only the 2 PR0-touched files. Categories
     * override this via a per-test helper and append their module files.
     *
     * @return array<int, string>
     */
    private static function defaultPolishedFiles(): array
    {
        return [
            self::projectRootStatic() . '/resources/js/components/ui/StatusBadge.vue',
            self::projectRootStatic() . '/resources/js/components/layout/AppLayout.vue',
        ];
    }

    private static function projectRootStatic(): string
    {
        return dirname(__DIR__, 3);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function polishedFileProvider(): array
    {
        $cases = [];
        foreach (self::defaultPolishedFiles() as $path) {
            $cases[$path] = [$path];
        }

        return $cases;
    }

    public function test_legacy_aliases_constant_is_non_empty(): void
    {
        $this->assertNotEmpty(
            self::LEGACY_ALIASES,
            'LEGACY_ALIASES must be non-empty. The list is the rolling pin for legacy-class regressions (DLR-R-009).'
        );
    }

    /**
     * Sanity unit-test for the whole-token regex. Confirms the negative
     * lookbehind + lookahead excludes modifier variants (e.g.
     * `bg-success-1000` does NOT trigger `bg-success-100`).
     *
     * @dataProvider aliasPatternProvider
     */
    public function test_alias_patterns_are_whole_token(string $alias, string $haystack, bool $expectMatch): void
    {
        $pattern = '/(?<![\w-])' . preg_quote($alias, '/') . '(?![\w-])/';
        $matched = preg_match($pattern, $haystack) === 1;
        $this->assertSame(
            $expectMatch,
            $matched,
            sprintf(
                'Regex %s vs `%s` — expected %s, got %s.',
                $pattern,
                $haystack,
                $expectMatch ? 'MATCH' : 'no match',
                $matched ? 'MATCH' : 'no match'
            )
        );
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: bool}>
     */
    public static function aliasPatternProvider(): array
    {
        return [
            'bg-success-100 in bg-success-100 text-success-700 matches' => [
                'bg-success-100',
                'bg-success-100 text-success-700',
                true,
            ],
            'bg-success-100 does not match bg-success-1000' => [
                'bg-success-100',
                'bg-success-1000',
                false,
            ],
            'bg-success-100 does not match bg-success-100-foo' => [
                'bg-success-100',
                'bg-success-100-foo',
                false,
            ],
            'border-theme matches in border-theme' => [
                'border-theme',
                'border-theme',
                true,
            ],
            'border-theme does not match border-theme-light' => [
                'border-theme',
                'border-theme-light',
                false,
            ],
            'focus:ring-primary-500 matches in focus:ring-primary-500' => [
                'focus:ring-primary-500',
                'focus:ring-primary-500',
                true,
            ],
            'focus:ring-primary-500 does not match focus:ring-primary-5000' => [
                'focus:ring-primary-500',
                'focus:ring-primary-5000',
                false,
            ],
        ];
    }

    /**
     * Per-file + per-alias assertion: each polished file MUST contain zero
     * whole-token matches against every alias in LEGACY_ALIASES.
     *
     * @dataProvider polishedFileProvider
     */
    public function test_no_legacy_alias_in_polished_file(string $path): void
    {
        $src = file_get_contents($path);
        $this->assertIsString($src, sprintf('%s must be readable.', $path));

        foreach (self::LEGACY_ALIASES as $alias) {
            $pattern = '/(?<![\w-])' . preg_quote($alias, '/') . '(?![\w-])/';
            $this->assertDoesNotMatchRegularExpression(
                $pattern,
                $src,
                sprintf(
                    '%s contains the forbidden legacy alias `%s` (whole-token match). '
                    . 'Replace it with the tokenised counterpart per DLR-R-009.',
                    $path,
                    $alias
                )
            );
        }
    }
}
