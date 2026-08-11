<?php

namespace Tests\Unit\DesignSystem;

use PHPUnit\Framework\TestCase;

/**
 * PR2 (ui-premium-microdetail-2026-08) — primitive interaction states.
 *
 * Source-grep tests over the `.vue` primitives in `resources/js/components/ui/`.
 * There is no JS test runner here, so the scoped `<style>` block is the only
 * durable surface PHPUnit can assert against. Every expectation is pinned by a
 * design decision; re-read the decision before editing one.
 *
 *  - D6  Focus rings are tokenised: primitives consume `var(--focus-ring-default)`
 *        instead of re-declaring a ring. The Input error/success rings are the
 *        permitted variation — they re-tint, but only from the token PARTS
 *        (`--focus-ring-width` / `--focus-ring-alpha`) and only with this
 *        project's systemRed / systemGreen ramps, never Tailwind's.
 *  - D8  Transform transitions use the iOS curve `var(--motion-easing-ios)`.
 *        (`ease-ios` is a Tailwind utility, not valid inside a scoped style
 *        block, so the emitted custom property is the consumable form.) Pure
 *        colour washes keep standard easing — Apple does the same deliberately.
 *  - D10 Press feedback is a pure CSS `:active` transform: no useSpring, no rAF,
 *        no JS, and no `data-pressed` attribute (CSS cannot set one). Existing
 *        press values are kept verbatim — Card 0.98, Avatar 0.95, Button -1px.
 *  - D11 Under `prefers-reduced-motion: reduce` the transform collapses to an
 *        opacity change of at most 200ms. Feedback survives, movement goes;
 *        durations are never flipped to 0.
 *
 * ADDITIVE GUARANTEE: these primitives are consumed by ~17 modules that are not
 * being retouched, so nothing here may permit a change to the DEFAULT (resting)
 * visual state. `test_transform_declarations_are_state_scoped` is the
 * machine-checked half of that guarantee — it passed on the pre-PR2 tree too,
 * which is what makes it a regression guard rather than a rubber stamp.
 */
class PrimitivePressTest extends TestCase
{
    /** The five primitives this slice retouches. */
    private const HEADLINE = ['Card.vue', 'Button.vue', 'Input.vue', 'Badge.vue', 'Avatar.vue'];

    private const EASE_IOS = 'var(--motion-easing-ios)';
    private const FOCUS_RING = 'box-shadow: var(--focus-ring-default);';

    /** The canonical transform transition: fast rung (120ms) on the iOS curve. */
    private const TRANSFORM_TRANSITION = 'transform var(--motion-duration-fast) var(--motion-easing-ios)';

    private static function projectRoot(): string
    {
        return dirname(__DIR__, 3);
    }

    /** Read a primitive's source, failing loudly if the file moved or vanished. */
    private static function primitive(string $name): string
    {
        $path = self::projectRoot() . '/resources/js/components/ui/' . $name;
        self::assertFileExists($path, $name . ' must exist under resources/js/components/ui/');
        $src = file_get_contents($path);
        self::assertIsString($src, $name . ' must be readable');

        return (string) $src;
    }

    /**
     * Extract the `@media (prefers-reduced-motion: reduce)` block by brace
     * matching, so nested rules are included. Null when none is declared.
     */
    private static function reducedMotionBlock(string $src): ?string
    {
        $start = strpos($src, '@media (prefers-reduced-motion: reduce)');
        $open = $start === false ? false : strpos($src, '{', $start);
        if ($open === false) {
            return null;
        }

        $depth = 0;
        for ($i = $open, $len = strlen($src); $i < $len; $i++) {
            $depth += $src[$i] === '{' ? 1 : ($src[$i] === '}' ? -1 : 0);
            if ($depth === 0) {
                return substr($src, $open, $i - $open + 1);
            }
        }

        return null;
    }

    /** Tasks 2.1.1 / 2.2.1 / 2.3.3 / 2.4.1 / 2.5.1 — D8 adoption across all five. */
    public function test_all_five_primitives_transition_transform_on_ease_ios_fast_rung(): void
    {
        foreach (self::HEADLINE as $name) {
            $this->assertStringContainsString(
                self::TRANSFORM_TRANSITION,
                self::primitive($name),
                $name . ' must transition transform on the fast rung with the iOS curve (D8)'
            );
        }
    }

    /** D8 — colour washes keep standard easing; the iOS curve is not sprayed everywhere. */
    public function test_colour_washes_keep_standard_easing(): void
    {
        foreach (self::HEADLINE as $name) {
            $this->assertMatchesRegularExpression(
                '/background-color\s+var\(--motion-duration-normal\)\s+ease-out/',
                self::primitive($name),
                $name . ' must keep standard easing for its colour wash (D8) — only transforms take the iOS curve'
            );
        }
    }

    /**
     * Tasks 2.1.4 / 2.2.2 / 2.4.2 — R10 rejects churning working press values.
     * Card stays 0.98 (not 0.97), Avatar keeps its Tailwind utility, Button keeps
     * the -1px hover lift.
     */
    public function test_existing_press_and_hover_values_are_preserved(): void
    {
        $this->assertMatchesRegularExpression(
            '/\[data-clickable="true"\]:active\s*\{[^}]*transform:\s*scale\(0\.98\)/s',
            self::primitive('Card.vue'),
            'Card.vue must keep :active scale(0.98) — D10/R10 rejects changing it to 0.97'
        );
        $this->assertStringContainsString(
            'active:scale-95',
            self::primitive('Avatar.vue'),
            'Avatar.vue must keep its existing active:scale-95 utility (D10/R10)'
        );
        $this->assertMatchesRegularExpression(
            '/button:not\(:disabled\):hover\s*\{[^}]*transform:\s*translateY\(-1px\)/s',
            self::primitive('Button.vue'),
            'Button.vue must keep its existing translateY(-1px) hover lift (D10/R10)'
        );
    }

    /** D10 — Input gets no press transform: a scale on :active fights text selection. */
    public function test_input_has_no_press_transform(): void
    {
        $this->assertDoesNotMatchRegularExpression(
            '/input[^{]*:active\s*\{[^}]*transform:\s*scale/s',
            self::primitive('Input.vue'),
            'Input.vue must not carry a press scale — it would interfere with text selection (D10)'
        );
    }

    /** Tasks 2.1.3 / 2.3.1 / 2.4.1 — D6 across the five, with no ring left behind. */
    public function test_headline_primitives_consume_the_ring_token_exclusively(): void
    {
        foreach (self::HEADLINE as $name) {
            $src = self::primitive($name);
            $this->assertStringContainsString(
                self::FOCUS_RING,
                $src,
                $name . ' must consume var(--focus-ring-default) (D6)'
            );
            $this->assertDoesNotMatchRegularExpression(
                '/outline:\s*\d+px solid/',
                $src,
                $name . ' must not keep a hand-rolled focus outline alongside the token (D6)'
            );
            $this->assertDoesNotMatchRegularExpression(
                '/focus:ring-2/',
                $src,
                $name . ' must not keep a Tailwind focus:ring-2 utility — it out-specifies the token (D6)'
            );
        }

        $this->assertDoesNotMatchRegularExpression(
            '/box-shadow:\s*0 0 0 3px rgba\(0,\s*122,\s*255/',
            self::primitive('Input.vue'),
            'Input.vue must not re-declare the blue focus ring inline — it is tokenised (D6)'
        );
    }

    /**
     * D6 override of task 2.3.2 — the error/success rings stay visually distinct
     * but are composed from the token parts, tinted with this project's ramps
     * (systemRed-500 = 255,59,48; systemGreen-500 = 52,199,89). Tailwind's
     * rgba(239, 68, 68, ...) is a foreign palette and must not appear.
     */
    public function test_input_state_rings_compose_from_token_parts_with_project_ramps(): void
    {
        $src = self::primitive('Input.vue');

        $this->assertStringContainsString(
            'box-shadow: 0 0 0 var(--focus-ring-width) rgba(255, 59, 48, var(--focus-ring-alpha));',
            $src,
            'Input.vue error ring must compose from the focus-ring parts with the systemRed-500 channels'
        );
        $this->assertStringContainsString(
            'box-shadow: 0 0 0 var(--focus-ring-width) rgba(52, 199, 89, var(--focus-ring-alpha));',
            $src,
            'Input.vue success ring must compose from the focus-ring parts with the systemGreen-500 channels'
        );
        $this->assertStringNotContainsString(
            'rgba(239, 68, 68',
            $src,
            "Input.vue must not use Tailwind's red — the palette is systemRed"
        );
    }

    /** Task 2.6.1 — the remaining focusable primitives consume the ring token (G1). */
    public function test_modal_sheet_toast_select_use_focus_ring_token(): void
    {
        foreach (['Modal.vue', 'Sheet.vue', 'Toast.vue', 'Select.vue'] as $name) {
            $src = self::primitive($name);
            $this->assertStringContainsString(
                self::FOCUS_RING,
                $src,
                $name . ' must consume var(--focus-ring-default) on its focusable element (D6/G1)'
            );
            $this->assertDoesNotMatchRegularExpression(
                '/focus:ring-2/',
                $src,
                $name . ' must not keep a Tailwind focus:ring-2 utility — it out-specifies the token (D6)'
            );
        }
    }

    /**
     * Task 2.6.1, ConfirmDialog — it owns no focusable element: it renders
     * `UiButton`s inside a `UiModal`, and Vue scoped styles do not pierce child
     * components. Declaring the ring there would be dead CSS that made a grep
     * pass while changing nothing on screen. The honest contract is delegation.
     */
    public function test_confirm_dialog_delegates_focus_ring_to_tokenised_children(): void
    {
        $src = self::primitive('ConfirmDialog.vue');

        $this->assertStringContainsString('<UiButton', $src, 'ConfirmDialog.vue must render UiButton, which carries the tokenised ring (D6/G1)');
        $this->assertStringNotContainsString('focus:ring-2', $src, 'ConfirmDialog.vue must not declare a competing Tailwind ring');
        $this->assertDoesNotMatchRegularExpression('/outline:\s*\d+px solid/', $src, 'ConfirmDialog.vue must not declare a competing inline outline');
    }

    /** Hover lift — clickable surfaces step up a PR1 `--elevation-*` rung, not a raw shadow. */
    public function test_clickable_surfaces_lift_using_elevation_tokens(): void
    {
        foreach (['Card.vue', 'Avatar.vue'] as $name) {
            $this->assertMatchesRegularExpression(
                '/:hover\s*\{[^}]*box-shadow:\s*var\(--elevation-[1-4]\)/s',
                self::primitive($name),
                $name . ' must step up an --elevation-* rung on hover rather than declaring a raw shadow'
            );
        }
    }

    /**
     * D10 anti-requirement — CSS cannot set a data attribute, so a `data-pressed`
     * hook would necessarily drag JS press bookkeeping back in.
     */
    public function test_no_primitive_introduces_a_data_pressed_attribute(): void
    {
        foreach (self::HEADLINE as $name) {
            $this->assertStringNotContainsString(
                'data-pressed',
                self::primitive($name),
                $name . ' must not introduce data-pressed — :active is the entire mechanism (D10)'
            );
        }
    }

    /**
     * D11 — every primitive that moves must declare a reduced-motion substitute
     * that keeps feedback (opacity) and drops movement, and no substituted
     * duration may be zeroed or exceed the 200ms ceiling.
     */
    public function test_reduced_motion_substitutes_opacity_without_zeroing_durations(): void
    {
        $movers = ['Card.vue', 'Button.vue', 'Avatar.vue'];

        foreach ($movers as $name) {
            $block = self::reducedMotionBlock(self::primitive($name));

            $this->assertNotNull($block, $name . ' must declare a prefers-reduced-motion: reduce block (D11)');
            $this->assertStringContainsString(
                'opacity',
                (string) $block,
                $name . ' reduced-motion block must substitute an opacity change for the transform (D11)'
            );
            $this->assertDoesNotMatchRegularExpression(
                '/\b0(ms|s)\b/',
                (string) $block,
                $name . ' must not flip a duration to 0 — feedback survives, movement goes (D11)'
            );

            if (preg_match_all('/(\d+)ms/', (string) $block, $m) > 0) {
                foreach ($m[1] as $ms) {
                    $this->assertLessThanOrEqual(200, (int) $ms, $name . ' reduced-motion substitute must be at most 200ms (D11)');
                }
            }
        }
    }

    /**
     * ADDITIVE GUARANTEE (machine-checked) — every `transform` declaration in the
     * retouched primitives must sit inside an interaction-state rule (`:hover` /
     * `:active` / `:focus-visible`), a keyframe, or the allow-list of pre-existing
     * positioning helpers below. A bare `transform` on a resting selector would
     * move the component for every module that consumes it untouched.
     */
    public function test_transform_declarations_are_state_scoped(): void
    {
        // Pre-existing resting transforms that are positioning or keyframe
        // concerns rather than motion. PR2 adds none of these.
        $allowedResting = [
            'transform: translateY(-50%)',   // Input prefix/suffix icon centring
            'transform: translateX(-100%)',  // Avatar skeleton keyframe start
            'transform: scale(0)',           // Button ripple / Badge dismiss start
            'transform: rotate(0deg)',       // spinner keyframe
            'transform: rotate(360deg)',     // spinner keyframe
            'transform: scale(4)',           // ripple keyframe end
        ];

        foreach (self::HEADLINE as $name) {
            preg_match_all('/([^{}]+)\{([^{}]*)\}/s', self::primitive($name), $rules, PREG_SET_ORDER);

            foreach ($rules as [, $selector, $body]) {
                $selector = trim($selector);

                // `(?<![a-z-])` keeps `text-transform` out of the match.
                if (!preg_match('/(?<![a-z-])transform:/', $body)) {
                    continue;
                }
                if (preg_match('/:hover|:active|:focus-visible|%|dismissing|\bto\b/', $selector)) {
                    continue;
                }

                preg_match_all('/(?<![a-z-])transform:\s*[^;]+/', $body, $decls);
                foreach ($decls[0] as $decl) {
                    $this->assertContains(
                        trim($decl),
                        $allowedResting,
                        $name . ' declares "' . trim($decl) . '" on resting selector "' . $selector
                            . '". Only :hover/:active/:focus-visible may change in PR2 — a resting transform '
                            . 'would move this primitive for every module consuming it untouched.'
                    );
                }
            }
        }
    }
}
