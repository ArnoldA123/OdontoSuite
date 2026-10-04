<?php

namespace Tests\Unit\DesignSystem;

use PHPUnit\Framework\TestCase;

use Tests\Support\SourceGrep;

/**
 * PR3 / Phase 3.1 — Login + 404 page anti-requirement guards (source-inspection).
 *
 * The login and 404 pages are Vue SPA screens, not server-rendered, so a
 * static source-inspection check against the .vue files is the appropriate
 * unit test boundary. Each assertion here pins a load-bearing contract that
 * the PR3 redesign must satisfy and that future regressions must keep
 * satisfying:
 *
 *  - 3.1.1 login_form_has_exactly_one_h1_and_programmatic_labels
 *  - 3.1.4 not_found_page_has_escape_link_and_image
 *  - 3.1.6 reset_token_not_in_reset_password_modal
 *  - PR3 anti-requirements: no hand-written hex literals in auth/errors
 *    modules; no references to the gitignored `images/pexels/` directory.
 *
 * Grep-based assertions use ripgrep via shell_exec for the same reason the
 * TokensModuleTest does — rg is column- and pipeline-aware where the
 * PowerShell Select-String parser is not.
 */
class LoginPageRenderTest extends TestCase
{
    private static function projectRootPath(): string { return dirname(__DIR__, 3); }

    private const LOGIN_PAGE_REL = '/resources/js/modules/auth/LoginPage.vue';
    private const FORGOT_MODAL_REL = '/resources/js/modules/auth/ForgotPasswordModal.vue';
    private const RESET_MODAL_REL = '/resources/js/modules/auth/ResetPasswordModal.vue';
    private const NOT_FOUND_REL = '/resources/js/modules/errors/NotFoundPage.vue';
    private const AUTH_DIR_REL = '/resources/js/modules/auth';
    private const ERRORS_DIR_REL = '/resources/js/modules/errors';

    private static function loginPagePath(): string
    {
        return self::projectRootPath() . self::LOGIN_PAGE_REL;
    }

    private static function forgotModalPath(): string
    {
        return self::projectRootPath() . self::FORGOT_MODAL_REL;
    }

    private static function resetModalPath(): string
    {
        return self::projectRootPath() . self::RESET_MODAL_REL;
    }

    private static function notFoundPath(): string
    {
        return self::projectRootPath() . self::NOT_FOUND_REL;
    }

    /**
     * Sum ripgrep --count-matches results across one or more paths.
     *
     * @param string $pattern
     * @param string ...$paths
     * @return int
     */
    private static function grepCount(string $pattern, string ...$paths): int
    {
        return SourceGrep::count($pattern, ...$paths);
    }

    /**
     * @test
     */
    public function login_page_has_exactly_one_h1(): void
    {
        $this->assertFileExists(
            self::loginPagePath(),
            'LoginPage.vue must exist for the PR3 login redesign'
        );

        $source = (string) file_get_contents(self::loginPagePath());

        // Count <h1> opening tags (allow whitespace before the tag).
        $count = preg_match_all('/<h1[\s>]/i', $source);
        $this->assertSame(
            1,
            (int) $count,
            'LoginPage.vue must contain exactly one <h1> for the headline (spec §login-experience / Wayfinding)'
        );
    }

    /**
     * @test
     */
    public function login_page_username_field_has_username_autocomplete(): void
    {
        $source = (string) file_get_contents(self::loginPagePath());

        // The username input must declare the correct autocomplete token so
        // the browser password manager does not warn. The current page used
        // to fall back to default (no autocomplete) which triggers the
        // "Password field is not contained in a form" warning in DevTools.
        $this->assertSame(
            1,
            (int) preg_match('/autocomplete\s*=\s*"username"/i', $source),
            'LoginPage.vue must declare autocomplete="username" on the username input (a11y + browser password manager contract)'
        );
    }

    /**
     * @test
     */
    public function login_page_password_field_has_current_password_autocomplete(): void
    {
        $source = (string) file_get_contents(self::loginPagePath());

        $this->assertSame(
            1,
            (int) preg_match('/autocomplete\s*=\s*"current-password"/i', $source),
            'LoginPage.vue must declare autocomplete="current-password" on the password input'
        );
    }

    /**
     * @test
     */
    public function login_page_has_aria_live_region_for_errors(): void
    {
        $source = (string) file_get_contents(self::loginPagePath());

        // The auth failure message must be announced to assistive tech via
        // an aria-live="polite" region — spec requires inline aria-live,
        // NOT a toast that disappears.
        $this->assertSame(
            1,
            (int) preg_match('/aria-live\s*=\s*"(polite|assertive)"/i', $source),
            'LoginPage.vue must declare aria-live="polite" (or assertive) so the auth error is announced (spec §login-experience / States)'
        );
    }

    /**
     * @test
     */
    public function login_page_no_animated_background_blobs(): void
    {
        $source = (string) file_get_contents(self::loginPagePath());

        // The PR2 baseline carried three infinitely-animating .shape divs
        // with @keyframes float. PR3 deletes them. The Vue template must not
        // contain the literal class or @keyframes declaration.
        $this->assertSame(
            0,
            substr_count($source, 'shape shape-'),
            'LoginPage.vue must delete the three animated background blobs (per design contract — no looping background animation)'
        );

        // The scoped style block must not carry the legacy @keyframes float.
        $this->assertStringNotContainsString(
            '@keyframes float',
            $source,
            'LoginPage.vue must not declare @keyframes float (legacy blob animation — design contract forbids looping background animation)'
        );
    }

    /**
     * @test
     */
    public function login_page_references_no_stock_photography(): void
    {
        $source = (string) file_get_contents(self::loginPagePath());

        // The redesign dropped stock photography: the backdrop is the vector
        // parallax scene. The Pexels tree stays banned because it is
        // gitignored and absent on a fresh clone.
        $this->assertSame(
            0,
            substr_count($source, 'images/pexels'),
            'LoginPage.vue must NOT reference images/pexels (that directory is gitignored and absent on a fresh clone)'
        );

        $this->assertSame(
            0,
            substr_count($source, '<img'),
            'LoginPage.vue must render no stock photography: the backdrop is the vector parallax scene'
        );
    }

    /**
     * @test
     */
    public function login_page_hosts_one_aria_hidden_parallax_backdrop(): void
    {
        $source = (string) file_get_contents(self::loginPagePath());

        // Exactly one decorative scene: a second mount would stack two
        // parallax roots and double the pointer work for no visual gain.
        $this->assertSame(
            1,
            preg_match_all('/<DentalParallaxBackground\b/', $source),
            'LoginPage.vue must mount exactly one DentalParallaxBackground (the full-bleed decorative scene)'
        );

        $this->assertMatchesRegularExpression(
            '/<div\b[^>]*class\s*=\s*"login-backdrop"[^>]*aria-hidden\s*=\s*"true"/is',
            $source,
            'the parallax backdrop wrapper must be aria-hidden="true" so the decorative scene stays out of the accessibility tree'
        );
    }

    /**
     * @test
     */
    public function login_page_has_no_hand_written_hex_literals(): void
    {
        $path = self::loginPagePath();
        $count = self::grepCount('#[0-9a-fA-F]{6}', $path);
        $this->assertSame(
            0,
            $count,
            'LoginPage.vue must not contain hand-written hex literals — use Tailwind token classes (design contract)'
        );
    }

    /**
     * @test
     */
    public function not_found_page_has_escape_link_to_login(): void
    {
        $this->assertFileExists(
            self::notFoundPath(),
            'NotFoundPage.vue must exist'
        );

        $source = (string) file_get_contents(self::notFoundPath());

        // The 404 page must route the primary escape path to /login. The
        // router.push('/login') inside the template's goHome handler is the
        // user-visible escape path; the duplicate in goBack is a defensive
        // fallback (router.back → /login if no history). Spec §not-found-
        // experience — "Volver al inicio".
        $this->assertGreaterThanOrEqual(
            1,
            substr_count($source, "'/login'"),
            'NotFoundPage.vue must route at least one escape path to /login (wayfinding)'
        );
        // And the rendered surface must include the literal CTA label so
        // screen-reader users know where they are going.
        $this->assertStringContainsString(
            'Ir al inicio',
            $source,
            'NotFoundPage.vue must render the "Ir al inicio" CTA label'
        );
    }

    /**
     * @test
     */
    public function not_found_page_has_exactly_one_h1(): void
    {
        $source = (string) file_get_contents(self::notFoundPath());

        $count = preg_match_all('/<h1[\s>]/i', $source);
        $this->assertSame(
            1,
            (int) $count,
            'NotFoundPage.vue must contain exactly one <h1> (wayfinding contract)'
        );
    }

    /**
     * @test
     */
    public function not_found_page_references_committed_image(): void
    {
        $source = (string) file_get_contents(self::notFoundPath());

        // The 404 image path is /images/ui/not-found.jpg (committed on the
        // PR3 branch and NOT under the gitignored images/pexels tree).
        $this->assertSame(
            0,
            substr_count($source, 'images/pexels'),
            'NotFoundPage.vue must NOT reference images/pexels (gitignored directory — breaks on fresh clones)'
        );
        $this->assertSame(
            1,
            substr_count($source, '/images/ui/not-found.jpg'),
            'NotFoundPage.vue must reference the committed 404 image at /images/ui/not-found.jpg'
        );
    }

    /**
     * @test
     */
    public function not_found_page_has_no_hand_written_hex_literals(): void
    {
        $count = self::grepCount('#[0-9a-fA-F]{6}', self::notFoundPath());
        $this->assertSame(
            0,
            $count,
            'NotFoundPage.vue must not contain hand-written hex literals — use Tailwind token classes'
        );
    }

    /**
     * @test
     */
    public function reset_password_modal_has_no_reset_token_in_ui(): void
    {
        $source = (string) file_get_contents(self::resetModalPath());

        // The dev-only reset_token field must be gone from the UI flow.
        // The API surface still accepts the token (backend is unchanged).
        // Strip <script> and <template> blocks are allowed to mention the
        // token in passing (e.g. an explanatory comment); what must NOT
        // appear is any *user-facing* input/v-model binding for it.
        //
        // Search for `v-model` bound to reset_token and any `<input>` whose
        // attributes include the literal string. A mere comment is fine.
        $this->assertSame(
            0,
            (int) preg_match('/v-model\s*=\s*"reset_token"/i', $source),
            'ResetPasswordModal.vue must not bind reset_token via v-model (dev-only field removed from UI)'
        );
        $this->assertSame(
            0,
            (int) preg_match('/<input[^>]*reset_token/i', $source),
            'ResetPasswordModal.vue must not render an input element with reset_token'
        );
    }

    /**
     * @test
     */
    public function auth_and_errors_modules_have_no_hand_written_hex_literals(): void
    {
        $authCount = self::grepCount('#[0-9a-fA-F]{6}', self::projectRootPath() . self::AUTH_DIR_REL);
        $errorsCount = self::grepCount('#[0-9a-fA-F]{6}', self::projectRootPath() . self::ERRORS_DIR_REL);

        $this->assertSame(
            0,
            $authCount,
            'No hand-written hex literals allowed in resources/js/modules/auth/ (use Tailwind token classes)'
        );
        $this->assertSame(
            0,
            $errorsCount,
            'No hand-written hex literals allowed in resources/js/modules/errors/ (use Tailwind token classes)'
        );
    }

    /**
     * @test
     */
    public function auth_and_errors_modules_do_not_reference_gitignored_pexels_directory(): void
    {
        // Scope: the two module directories this PR3 slice owns.
        $authCount = self::grepCount('images/pexels', self::projectRootPath() . self::AUTH_DIR_REL);
        $errorsCount = self::grepCount('images/pexels', self::projectRootPath() . self::ERRORS_DIR_REL);

        $this->assertSame(
            0,
            $authCount,
            'No references to images/pexels/ allowed in resources/js/modules/auth/ (directory is gitignored)'
        );
        $this->assertSame(
            0,
            $errorsCount,
            'No references to images/pexels/ allowed in resources/js/modules/errors/ (directory is gitignored)'
        );
    }

    public function testPr5_login_inputs_have_no_placeholders(): void
    {
        $source = (string) file_get_contents(self::loginPagePath());
        preg_match_all('/<input\\b[^>]*id="login-(?:username|password)"[^>]*>/i', $source, $inputs);
        $this->assertCount(2, $inputs[0]);
        // Premium craft pass (2026-09): the visible <label> already names the
        // field, so a placeholder is duplication. "Mínimo 8 caracteres" was
        // worse than duplication: registration semantics on a LOGIN screen,
        // and false.
        foreach ($inputs[0] as $input) {
            $this->assertDoesNotMatchRegularExpression(
                '/placeholder\\s*=/i',
                $input,
                'Login inputs must not carry a placeholder: the visible label already names the field'
            );
        }
    }

    public function testPr5_login_has_no_redundant_helper_text(): void
    {
        $source = (string) file_get_contents(self::loginPagePath());
        preg_match_all('/<p\\s+[^>]*class="field-hint"[^>]*>(.*?)<\\/p>/is', $source, $hints);
        $this->assertCount(0, $hints[1]);
        foreach ($hints[1] as $hint) {
            $this->assertDoesNotMatchRegularExpression('/usuario|contraseña/i', strip_tags($hint));
        }
    }

    public function testPr5_login_password_reveal_is_inside_frame(): void
    {
        $source = (string) file_get_contents(self::loginPagePath());
        $this->assertMatchesRegularExpression('/\\.password-toggle\\s*\\{[^}]*right:\\s*12px/s', $source);
    }

    public function testPr5_login_primary_button_has_elevation_and_highlight(): void
    {
        $source = (string) file_get_contents(self::loginPagePath());
        // The inset highlight and the 1px ring were dropped by the subtractive
        // pass (flat surfaces, no freeform rgba in the component): the login
        // CTA elevates through the token alone.
        $this->assertStringContainsString('var(--elevation-3)', $source);
    }

    public function testPr5_login_card_keeps_neutral_panel_surface(): void
    {
        $source = (string) file_get_contents(self::loginPagePath());
        // The floating card keeps the neutral system background and the
        // shared panel radius rung. The hero surface it replaced is gone,
        // along with the glass overlay widgets that sat on it.
        $this->assertMatchesRegularExpression(
            '/\.login-card\s*\{[^}]*background:\s*var\(--color-background-system-background\)/s',
            $source,
            'the floating login card must keep the neutral system background token'
        );
        $this->assertStringContainsString('border-radius: var(--login-radius-panel)', $source);
    }

    public function testPr5_not_found_hero_uses_card_radius_hairline_and_scrim(): void
    {
        $source = (string) file_get_contents(self::notFoundPath());
        $this->assertStringContainsString('border-radius: var(--radius-card-lg)', $source);
        $this->assertStringContainsString('border: 1px solid var(--color-hairline)', $source);
        $this->assertStringContainsString('var(--elevation-2)', $source);

        // The scrim, asserted as a PROPERTY rather than as a literal.
        //
        // This spot used to pin `rgba(60, 60, 67, 0.55)` — the COOL label hue.
        // A1 re-tempered that family to warm ink and re-pointed the elevation
        // ramp, but it never touched NotFoundPage.vue, so this assertion kept
        // passing while the 404 hero quietly stayed the LAST surface in the app
        // painted with the retired blue-grey. Correcting the page then broke
        // the test — the same defect as pinning a palette hex, and the same one
        // the token suite carried before it was rewritten to assert
        // relationships. The assertion described the value that happened to be
        // there, not the rule it was there to satisfy.
        //
        // The rule: a gradient scrim drawn from the palette's warm ink, and
        // never the retired cool hue. The alpha stops are a design choice and
        // are deliberately NOT pinned.
        $this->assertMatchesRegularExpression(
            '/linear-gradient\([^;]*rgba\(92,\s*90,\s*85,/',
            $source,
            'the 404 hero scrim must be a gradient drawn from the palette warm ink (rgb(92, 90, 85))'
        );
        $this->assertStringNotContainsString(
            'rgba(60, 60, 67',
            $source,
            'the 404 hero must not be painted with the retired cool label hue'
        );
    }

        // ====================================================================
        // PR-apply / Phase 2 — editorial split + dental hero + shape-morph
        // (`ui-login-refinement-dental-split-2026-09`).
        //
        // Each test pins a load-bearing source-inspection contract that the
        // LoginPage rewrite must satisfy. The grep targets are intentionally
        // liberal (substring or single-line matches) so future cosmetic
        // refactors do not break the suite.
        // ====================================================================

        /**
         * @test
         */
        public function login_page_has_floating_login_card(): void
        {
            $source = (string) file_get_contents(self::loginPagePath());

            $this->assertStringContainsString(
                'login-card',
                $source,
                'LoginPage.vue must wrap the form in the floating .login-card surface over the backdrop'
            );
            // The card takes the panel rung of the radius ladder. Both the
            // local alias and the shared token are valid spellings — pinning only the
            // literal turned a cosmetic refactor into a false defect.
            $this->assertMatchesRegularExpression(
                '/border-radius:\s*var\(--(?:login-)?radius-panel\)/',
                $source,
                'LoginPage.vue must give the floating card the panel radius rung, by token reference or by value'
            );
            $this->assertStringContainsString(
                'box-shadow: var(--elevation-4)',
                $source,
                'LoginPage.vue must float the card on the elevation-4 token'
            );
        }

        /**
         * @test
         */
        public function login_page_has_footer_with_terms_and_contact(): void
        {
            $source = (string) file_get_contents(self::loginPagePath());

            $this->assertStringContainsString(
                'Términos y Condiciones',
                $source,
                'LoginPage.vue must render the Términos y Condiciones link in the footer row (Phase 2.1)'
            );
            $this->assertStringContainsString(
                'Contacta al administrador',
                $source,
                'LoginPage.vue must render the ¿No tienes cuenta? Contacta al administrador link in the footer row (Phase 2.1)'
            );
        }

        /**
         * @test
         */
        public function login_page_has_no_image_fallback_machinery(): void
        {
            $source = (string) file_get_contents(self::loginPagePath());

            // No stock photography means no @error fallback path either: the
            // vector scene always renders.
            foreach (['onImageError', 'imageFailed', 'login-hero-image', 'login-hero-fallback'] as $retired) {
                $this->assertStringNotContainsString(
                    $retired,
                    $source,
                    'LoginPage.vue must not carry hero image fallback machinery (' . $retired . ') now that the backdrop is vector'
                );
            }
        }

        /**
         * @test
         */
        public function login_page_overlays_do_not_fetch_dashboard_stats(): void
        {
            $source = (string) file_get_contents(self::loginPagePath());

            // Polarity inverted by the premium craft pass (2026-09). The
            // login is a PUBLIC screen: the dashboard stats endpoint answers
            // 401 for a guest, so fetching on mount rendered
            // "PACIENTES ACTIVOS 0". The widget reads a curated sample now.
            $this->assertSame(
                0,
                substr_count($source, '/api/dashboard/stats'),
                'LoginPage.vue must NOT call /api/dashboard/stats: the hero widget renders the curated sample (Phase 2.3, premium craft pass)'
            );
        }

        /**
         * @test
         */
        public function login_page_overlays_do_not_fetch_appointments_today(): void
        {
            $source = (string) file_get_contents(self::loginPagePath());

            $this->assertSame(
                0,
                substr_count($source, '/api/dashboard/appointments-today'),
                'LoginPage.vue must NOT call /api/dashboard/appointments-today: the agenda widget renders the curated sample (Phase 2.3, premium craft pass)'
            );
        }

        /**
         * @test
         */
        public function login_page_overlays_do_not_fetch_users_active(): void
        {
            $source = (string) file_get_contents(self::loginPagePath());

            $this->assertSame(
                0,
                substr_count($source, '/api/users/active'),
                'LoginPage.vue must NOT call /api/users/active: the team widget renders the curated sample (Phase 2.3, premium craft pass)'
            );
        }

        /**
         * @test
         */
        public function login_page_references_no_api_paths(): void
        {
            $source = (string) file_get_contents(self::loginPagePath());

            $this->assertSame(
                0,
                substr_count($source, '/api/'),
                'LoginPage.vue must reference no API path: the login is a public screen and fetches nothing on mount'
            );
        }

        /**
         * @test
         */
        public function login_page_uses_shape_morph_composable(): void
        {
            $source = (string) file_get_contents(self::loginPagePath());

            $this->assertStringContainsString(
                'useShapeMorph',
                $source,
                'LoginPage.vue must import and instantiate the useShapeMorph composable (Phase 2.4)'
            );
        }

        /**
         * @test
         */
        public function login_page_submit_button_has_four_polymorphic_states(): void
        {
            $source = (string) file_get_contents(self::loginPagePath());

            // The submit button must carry 4 visible state labels (idle,
            // validating, authenticating, success). The 5th (error) is
            // handled by the magnetic composable's error branch.
            foreach (['Iniciar sesión', 'Validando', 'Autenticando', 'Listo'] as $label) {
                $this->assertStringContainsString(
                    $label,
                    $source,
                    'LoginPage.vue must declare the polymorphic stage label ' . $label . ' (Phase 2.4)'
                );
            }
        }

        /**
         * @test
         */
        public function login_page_form_card_has_form_and_summary_states(): void
        {
            $source = (string) file_get_contents(self::loginPagePath());

            // The form Card must declare BOTH the <form> and the
            // <TransitionGroup name="form-card-morph"> wrapper that
            // crossfades between <form> and the <MiniSummary> on success.
            $this->assertStringContainsString(
                '<form',
                $source,
                'LoginPage.vue must render the login <form> (Phase 2.5)'
            );
            $this->assertStringContainsString(
                'form-card-morph',
                $source,
                'LoginPage.vue must declare the form-card-morph TransitionGroup (Phase 2.5)'
            );
            $this->assertStringContainsString(
                'login-mini-summary',
                $source,
                'LoginPage.vue must render the login-mini-summary block on success (Phase 2.5)'
            );
        }

        /**
         * @test
         */
        public function login_page_prefers_reduced_motion_collapses_polymorphism(): void
        {
            $source = (string) file_get_contents(self::loginPagePath());

            // The polymorphic submit + form-card morph must be gated on
            // a reactive reduced-motion detector. The composable is
            // imported from resources/js/composables/useReducedMotion.js.
            $this->assertStringContainsString(
                'useReducedMotion',
                $source,
                'LoginPage.vue must import the useReducedMotion composable to gate motion paths (Phase 2.6)'
            );
            $this->assertStringContainsString(
                'prefers-reduced-motion: reduce',
                $source,
                'LoginPage.vue must declare a @media (prefers-reduced-motion: reduce) block (Phase 2.6)'
            );
        }

        /**
         * @test (bonus)
         */
        public function login_page_prefers_reduced_transparency_flattens_outer_card(): void
        {
            $source = (string) file_get_contents(self::loginPagePath());

            $this->assertStringContainsString(
                'prefers-reduced-transparency: reduce',
                $source,
                'LoginPage.vue must declare a @media (prefers-reduced-transparency: reduce) block (Phase 2.6)'
            );
    
                    }

            // ====================================================================
        // Slice 12 / fix-viewport-fit (`ui-login-viewport-fit-2026-09`).
        //
        // The login overflowed 98px on 1440x900 because the card was
        // content-sized rather than viewport-sized. These tests pin the
        // four CSS rules that fix the overflow and guarantee the page does
        // not regress into a vertically scrolling login.
        // ====================================================================

        /**
         * @test
         */
        public function login_page_constrains_card_to_viewport_height(): void
        {
            $source = (string) file_get_contents(self::loginPagePath());

            $this->assertStringContainsString(
                'max-height: calc(100dvh',
                $source,
                'LoginPage.vue must constrain the login card height to the viewport (max-height: calc(100dvh - ...))'
            );
        }

        /**
         * @test
         */
        public function login_page_card_scrolls_internally(): void
        {
            $source = (string) file_get_contents(self::loginPagePath());

            $this->assertStringContainsString(
                '.login-card',
                $source,
                'LoginPage.vue must declare the floating .login-card rule'
            );
            $this->assertStringContainsString(
                'overflow-y: auto',
                $source,
                '.login-card must scroll internally (overflow-y: auto) when the form is taller than the viewport'
            );
        }

        /**
         * @test
         */
        public function login_page_shell_uses_viewport_height(): void
        {
            $source = (string) file_get_contents(self::loginPagePath());

            $this->assertStringContainsString(
                'min-h-[100dvh]',
                $source,
                '.login-page must declare min-h-[100dvh] (or equivalent viewport-height rule)'
            );
        }

        /**
         * @test
         */
        public function login_page_condenses_card_below_768(): void
        {
            $source = (string) file_get_contents(self::loginPagePath());

            $this->assertStringContainsString(
                '@media (max-width: 767px)',
                $source,
                'LoginPage.vue must declare a @media (max-width: 767px) block for the mobile card insets'
            );
            $this->assertMatchesRegularExpression(
                '/@media\s*\(max-width:\s*767px\)\s*\{[\s\S]*?\.login-card\s*\{[^}]*max-height:\s*calc\(100dvh/s',
                $source,
                'Below 768px the card must condense to the viewport with reduced insets'
            );
            $this->assertMatchesRegularExpression(
                '/@media\s*\(max-width:\s*767px\)\s*\{[\s\S]*?\.login-form-wrap\s*\{[^}]*gap:\s*16px/s',
                $source,
                'Below 768px the form gap must tighten to 16px'
            );
        }

        /**
         * @test
         */
        public function login_page_card_is_a_keyboard_reachable_labelled_scroll_region(): void
        {
            $source = (string) file_get_contents(self::loginPagePath());

            $this->assertMatchesRegularExpression(
                '/<section\b[^>]*class\s*=\s*"login-card"[^>]*role\s*=\s*"region"[^>]*tabindex\s*=\s*"0"/is',
                $source,
                'the scrollable login card must be announced as a region and be keyboard-scrollable (role="region" + tabindex="0")'
            );
            $this->assertStringContainsString(
                'aria-labelledby="login-headline"',
                $source,
                'the scrollable login card must keep its accessible name from the headline'
            );
            $this->assertMatchesRegularExpression(
                '/\.login-card:focus-visible\s*\{[^}]*outline\s*:\s*var\(--focus-ring-width\)\s*solid\s*var\(--focus-ring-color\)/s',
                $source,
                'the focusable scroll region must show the tokenised focus ring on :focus-visible'
            );
        }

        /**
         * @test
         */
        public function login_page_announces_valid_state_through_a_polite_live_region(): void
        {
            $source = (string) file_get_contents(self::loginPagePath());

            $this->assertStringContainsString(
                'id="login-username-success"',
                $source,
                'the username field must expose a screen-reader success text once valid'
            );
            $this->assertStringContainsString(
                'id="login-password-success"',
                $source,
                'the password field must expose a screen-reader success text once valid'
            );
            $this->assertMatchesRegularExpression(
                '/<p\b[^>]*class\s*=\s*"sr-only"[^>]*role\s*=\s*"status"[^>]*aria-live\s*=\s*"polite"[^>]*>\s*\{\{\s*validationAnnouncement\s*\}\}\s*<\/p>/s',
                $source,
                'the login must announce full-form validity through a single polite live region'
            );
        }

        /**
         * @test
         */
        public function login_page_min_length_rules_admit_seeded_credentials(): void
        {
            $source = (string) file_get_contents(self::loginPagePath());

            $this->assertSame(
                1,
                (int) preg_match('/MIN_USERNAME_LENGTH\s*=\s*(\d+)/', $source, $usernameMin),
                'LoginPage.vue must declare the username minimum length as a named constant'
            );
            $this->assertSame(
                1,
                (int) preg_match('/MIN_PASSWORD_LENGTH\s*=\s*(\d+)/', $source, $passwordMin),
                'LoginPage.vue must declare the password minimum length as a named constant'
            );

            $seeder = (string) file_get_contents(
                self::projectRootPath() . '/database/seeders/RoleBasedUsersSeeder.php'
            );

            preg_match_all("/'username'\s*=>\s*'([^']+)'/", $seeder, $usernames);
            $this->assertNotEmpty($usernames[1], 'RoleBasedUsersSeeder must declare demo usernames');

            foreach ($usernames[1] as $username) {
                $this->assertGreaterThanOrEqual(
                    (int) $usernameMin[1],
                    strlen($username),
                    "Seeded username '{$username}' must satisfy the login minimum-length rule"
                );
            }

            preg_match_all("/'password'\s*=>\s*Hash::make\('([^']+)'\)/", $seeder, $passwords);
            $this->assertNotEmpty($passwords[1], 'RoleBasedUsersSeeder must declare demo passwords');

            foreach (array_unique($passwords[1]) as $password) {
                $this->assertGreaterThanOrEqual(
                    (int) $passwordMin[1],
                    strlen($password),
                    'The seeded demo password must satisfy the login minimum-length rule'
                );
            }
        }

        /**
         * @test
         */
        public function login_page_centers_the_card_on_desktop(): void
        {
            $source = (string) file_get_contents(self::loginPagePath());

            // The centered layout is the design decision: the card no longer
            // anchors left on wide viewports, and the decorative scene
            // distributes around it. Both the old flex anchor and its
            // padding escape hatch must stay gone.
            $this->assertMatchesRegularExpression(
                '/\.login-stage\s*\{[^}]*justify-center/s',
                $source,
                'LoginPage.vue must center the floating card at every viewport'
            );
            $this->assertStringNotContainsString(
                'justify-content: flex-start',
                $source,
                'LoginPage.vue must not left-anchor the card on wide viewports'
            );
            $this->assertStringNotContainsString(
                'padding-left: clamp(56px, 14vw, 220px)',
                $source,
                'the retired left-anchor padding must not come back'
            );
        }
}
