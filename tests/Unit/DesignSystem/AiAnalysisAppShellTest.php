<?php

namespace Tests\Unit\DesignSystem;

/**
 * Design contract for the AI-analysis module after #218 and #239.
 *
 * #218 moved the module onto the design system; #239 retired the embedded
 * action whose only consumer disappeared with the medical-records redesign, so
 * the module keeps a single polished surface: the page shell.
 *
 * The inherited DLR-R-001/002/004/021 assertions run through polishedFiles():
 * canvas surface, tokenised focus ring, no legacy colour aliases and no
 * `<style>` block. DOM behaviour lives in the module smokes
 * (tests/js/smoke/ai-analysis*.test.js).
 */
class AiAnalysisAppShellTest extends ModuleAppShellTestCase
{
    private const PAGE_PATH = '/resources/js/modules/ai-analysis/AiAnalysisPage.vue';

    /** @return array<int, string> */
    protected static function polishedFiles(): array
    {
        return [dirname(__DIR__, 3).self::PAGE_PATH];
    }
}
