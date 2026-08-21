<?php

namespace Tests\Unit\DesignSystem;

/**
 * REC-* tokenization contract for the reception-procedures catalog page.
 */
class ReceptionProceduresAppShellTest extends ModuleAppShellTestCase
{
    private const PAGE_PATH = '/resources/js/modules/reception-procedures/ReceptionProceduresPage.vue';

    /** @return array<int, string> */
    protected static function polishedFiles(): array
    {
        return [dirname(__DIR__, 3) . self::PAGE_PATH];
    }

    private static function readSource(string $path): ?string
    {
        $src = file_get_contents($path);
        return $src === false ? null : $src;
    }

    public function test_canvas_routes_regression_guard_reception_procedures(): void
    {
        $root = dirname(__DIR__, 3);
        $layout = self::readSource($root . '/resources/js/components/layout/AppLayout.vue');
        $routeTest = self::readSource(
            $root . '/tests/Unit/DesignSystem/AppLayoutCanvasRoutesTest.php'
        );

        $this->assertNotNull($layout, 'AppLayout.vue must be readable.');
        $this->assertNotNull($routeTest, 'AppLayoutCanvasRoutesTest.php must be readable.');
        $this->assertMatchesRegularExpression(
            "/['\"]\\/reception-procedures['\"]/",
            $layout,
            'canvasRoutes must retain the /reception-procedures route.'
        );
        $this->assertMatchesRegularExpression(
            "/['\"]\\/reception-procedures['\"]/",
            $routeTest,
            'AppLayoutCanvasRoutesTest::EXPECTED_ROUTES must retain /reception-procedures.'
        );
    }

    public function test_search_uses_ui_input(): void
    {
        $src = self::readSource(dirname(__DIR__, 3) . self::PAGE_PATH);
        $this->assertNotNull($src, 'ReceptionProceduresPage.vue must be readable.');

        $this->assertMatchesRegularExpression('/<UiInput\b/', $src);
        $this->assertDoesNotMatchRegularExpression(
            '/<input\b[^>]*v-model\s*=\s*["\']filters\.search["\']/s',
            $src
        );
    }

    public function test_specialty_filter_uses_ui_select(): void
    {
        $src = self::readSource(dirname(__DIR__, 3) . self::PAGE_PATH);
        $this->assertNotNull($src, 'ReceptionProceduresPage.vue must be readable.');

        $this->assertMatchesRegularExpression('/<UiSelect\b/', $src);
        $this->assertDoesNotMatchRegularExpression(
            '/<select\b[^>]*v-model\s*=\s*["\']filters\.specialty["\']/s',
            $src
        );
    }

    public function test_procedure_code_chip_uses_ui_badge(): void
    {
        $src = self::readSource(dirname(__DIR__, 3) . self::PAGE_PATH);
        $this->assertNotNull($src, 'ReceptionProceduresPage.vue must be readable.');

        $this->assertMatchesRegularExpression('/<UiBadge\b/', $src);
        $this->assertDoesNotMatchRegularExpression('/(?<![\w-])bg-primary-50(?![\w-])/', $src);
        $this->assertDoesNotMatchRegularExpression('/(?<![\w-])text-primary-700(?![\w-])/', $src);
    }

    public function test_empty_state_uses_ui_empty_state(): void
    {
        $src = self::readSource(dirname(__DIR__, 3) . self::PAGE_PATH);
        $this->assertNotNull($src, 'ReceptionProceduresPage.vue must be readable.');

        $this->assertMatchesRegularExpression('/<UiEmptyState\b/', $src);
        $this->assertDoesNotMatchRegularExpression(
            '/py-12 text-center text-theme-secondary/',
            $src
        );
    }

    public function test_price_uses_system_blue_tabular_nums(): void
    {
        $src = self::readSource(dirname(__DIR__, 3) . self::PAGE_PATH);
        $this->assertNotNull($src, 'ReceptionProceduresPage.vue must be readable.');

        $this->assertMatchesRegularExpression('/text-systemBlue-600/', $src);
        $this->assertMatchesRegularExpression('/tabular-nums/', $src);
        $this->assertMatchesRegularExpression('/font-feature-settings:\s*var\(--font-features-tabular-nums\)/', $src);
        $this->assertDoesNotMatchRegularExpression('/(?<![\w-])text-accent(?![\w-])/', $src);
    }

    public function test_hairline_borders_and_no_hover_lift(): void
    {
        $src = self::readSource(dirname(__DIR__, 3) . self::PAGE_PATH);
        $this->assertNotNull($src, 'ReceptionProceduresPage.vue must be readable.');

        $this->assertDoesNotMatchRegularExpression('/(?<![\w-])border-theme(?![\w-])/', $src);
        $this->assertDoesNotMatchRegularExpression(
            '/(?<![\w-])hover-lift transition-shadow(?![\w-])/',
            $src
        );
        $this->assertMatchesRegularExpression(
            '/border-hairline|var\(--color-hairline\)/',
            $src
        );
    }
}
