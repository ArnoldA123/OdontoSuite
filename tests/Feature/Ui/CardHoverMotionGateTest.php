<?php

namespace Tests\Feature\Ui;

use Tests\TestCase;

/**
 * HOTFIX-DASH-011 + Card.vue regression guards.
 *
 * Pins two source-level rules that the dashboard hover fix relies on:
 *
 *   1. The Card primitive template MUST bind `:data-hover="hover || undefined"`
 *      so the scoped CSS `[data-hover='true']:hover` selector matches. Without
 *      this binding the hover transform is dead code.
 *
 *   2. The `@media (prefers-reduced-motion: reduce)` block in Card.vue MUST
 *      gate the hover transform via `[data-hover='true']:hover { transform: none; }`
 *      so the apple-design §14 rule (movement goes, feedback stays) holds.
 *
 * Rule citations:
 *   - apple-design §12 "deeper shadow on hover, gated by prefers-reduced-motion"
 *   - apple-design §14 "reduced motion: respond to the three signals (motion,
 *     transparency, contrast). Replace slide/spring with short opacity cross-fades."
 *
 * @see apple-design SKILL.md §12 + §14
 */
class CardHoverMotionGateTest extends TestCase
{
    private const CARD_VUE = '/resources/js/components/ui/Card.vue';

    private static function cardSource(): string
    {
        $path = dirname(__DIR__, 3) . self::CARD_VUE;
        if (!is_file($path)) {
            self::fail("Card.vue missing: {$path}");
        }
        return (string) file_get_contents($path);
    }

    /**
     * @test
     */
    public function card_root_binds_data_hover_attribute(): void
    {
        $source = self::cardSource();
        // Check that the file contains the literal ":data-hover" binding
        // (Vue 3 colon prefix for reactive attribute binding). Position
        // doesn't matter — Card.vue root <div> is the only place that
        // should bind it.
        $this->assertStringContainsString(
            ':data-hover',
            $source,
            "Card.vue must bind :data-hover so [data-hover='true']:hover scoped CSS matches."
        );
        // Confirm it is on the root <div>, not somewhere unrelated. The root
        // tag declares :class, :data-variant, :data-clickable, :data-hover
        // in that order (Vue 3 style guide).
        $rootSnippet = (string) preg_replace('/\s+/', ' ', $source);
        $this->assertMatchesRegularExpression(
            '/<div\s+:class="cardClasses"\s+:data-variant="variant"\s+:data-clickable="clickable"\s+:data-hover="/i',
            $rootSnippet,
            'Card.vue root <div> must bind :data-hover so [data-hover=\'true\']:hover scoped CSS matches.'
        );
    }

    /**
     * @test
     */
    public function card_has_no_tailwind_scale_on_hover(): void
    {
        $source = self::cardSource();
        // The earlier draft used `hover:scale-[1.02]` in the class list. A
        // Tailwind hover:scale utility wins specificity over the scoped
        // `[data-hover='true']:hover` rule AND bypasses the prefers-reduced-
        // motion gate (Tailwind does not auto-emit a `motion-reduce:` variant
        // for hover utilities).
        //
        // Strip line-comments before scanning so the explanatory prose in
        // <script setup> (which references the historical utility by name)
        // does not trip the assertion.
        $stripped = preg_replace('/\/\/[^\n]*/', '', $source);
        $this->assertNotSame(
            null,
            $stripped,
            'Failed to strip line-comments from Card.vue source for the audit.'
        );
        $this->assertDoesNotMatchRegularExpression(
            '/^\s*[\'"][^\'\"]*hover:scale-\[/m',
            $stripped,
            "Card.vue MUST NOT apply a Tailwind hover:scale-[1.02] utility — apple-design §14 prefers-reduced-motion gate would be bypassed. Found one inside an interpolated class string."
        );
    }

    /**
     * @test
     */
    public function scoped_hover_transform_is_translateY_minus_1px(): void
    {
        $source = self::cardSource();
        // The scoped CSS rule must use translateY(-1px) per the HOTFIX-DASH-010
        // spec literal — not scale(), not a different offset.
        $this->assertMatchesRegularExpression(
            "/\[data-hover=['\"]true['\"]\]:hover\s*\{[^}]*transform:\s*translateY\(\-1px\)/is",
            $source,
            "Card.vue [data-hover='true']:hover rule MUST declare transform: translateY(-1px) per HOTFIX-DASH-010 spec literal."
        );
    }

    /**
     * @test
     */
    public function prefers_reduced_motion_gate_nulls_hover_transform(): void
    {
        $source = self::cardSource();
        // Find the @media (prefers-reduced-motion: reduce) block, then assert
        // it contains the override `[data-hover='true']:hover { transform: none; }`.
        $this->assertMatchesRegularExpression(
            '/@media\s*\(prefers-reduced-motion:\s*reduce\)\s*\{[\s\S]*?\[data-hover=[\'"]true[\'"]\]:hover\s*\{[^}]*transform:\s*none[^}]*\}/is',
            $source,
            'Card.vue @media (prefers-reduced-motion: reduce) MUST null [data-hover=\'true\']:hover transform — apple-design §14.'
        );
    }
}
