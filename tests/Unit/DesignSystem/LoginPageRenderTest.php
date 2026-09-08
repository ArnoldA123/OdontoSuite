<?php

namespace Tests\Unit\DesignSystem;

use PHPUnit\Framework\TestCase;

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
        $args = array_map('escapeshellarg', $paths);
        $pathsPart = implode(' ', $args);
        $cmd = sprintf(
            'rg --no-heading --count-matches --no-messages %s %s 2>&1',
            escapeshellarg($pattern),
            $pathsPart
        );
        $output = (string) shell_exec($cmd);
        if ($output === '') {
            return 0;
        }
        $total = 0;
        foreach (preg_split('/\r?\n/', $output) as $line) {
            $line = trim((string) $line);
            if ($line === '') {
                continue;
            }
            $count = (int) (str_contains($line, ':') ? substr($line, strrpos($line, ':') + 1) : $line);
            $total += $count;
        }
        return $total;
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
    public function login_page_references_hero_image_via_ui_subpath(): void
    {
        $source = (string) file_get_contents(self::loginPagePath());

        // PR3 ships two committed hero images under public/images/ui/.
        // The Pexels directory is gitignored — referencing it would break on
        // a fresh clone.
        $this->assertSame(
            0,
            substr_count($source, 'images/pexels'),
            'LoginPage.vue must NOT reference images/pexels (that directory is gitignored and absent on a fresh clone)'
        );

        $this->assertSame(
            1,
            substr_count($source, '/images/ui/login-hero.jpg'),
            'LoginPage.vue must reference the committed login hero at /images/ui/login-hero.jpg'
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

    public function testPr5_login_inputs_have_placeholders(): void
    {
        $source = (string) file_get_contents(self::loginPagePath());
        preg_match_all('/<input\\b[^>]*id="login-(?:username|password)"[^>]*>/i', $source, $inputs);
        $this->assertCount(2, $inputs[0]);
        foreach ($inputs[0] as $input) {
            $this->assertMatchesRegularExpression('/placeholder\\s*=\\s*"[^"]+"/i', $input);
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
        $this->assertStringContainsString('var(--elevation-3)', $source);
        $this->assertStringContainsString('inset 0 1px 0 rgba(255, 255, 255, 0.30)', $source);
    }

    public function testPr5_login_hero_uses_neutral_scrim_and_contrast_eyebrow(): void
    {
        $source = (string) file_get_contents(self::loginPagePath());
        $this->assertStringContainsString('rgba(60, 60, 67, 0.05)', $source);
        $this->assertStringContainsString('rgba(60, 60, 67, 0.55)', $source);
        $this->assertStringContainsString('var(--color-system-gray-50)', $source);
        $this->assertStringContainsString('border-radius: var(--radius-card-lg)', $source);
    }

    public function testPr5_not_found_hero_uses_card_radius_hairline_and_scrim(): void
    {
        $source = (string) file_get_contents(self::notFoundPath());
        $this->assertStringContainsString('border-radius: var(--radius-card-lg)', $source);
        $this->assertStringContainsString('border: 1px solid var(--color-hairline)', $source);
        $this->assertStringContainsString('rgba(60, 60, 67, 0.55)', $source);
        $this->assertStringContainsString('var(--elevation-2)', $source);
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
        public function login_page_has_outer_login_split_card(): void
        {
            $source = (string) file_get_contents(self::loginPagePath());

            $this->assertStringContainsString(
                'login-split-card',
                $source,
                'LoginPage.vue must wrap the editorial split in a .login-split-card container (Phase 2.1)'
            );
            $this->assertStringContainsString(
                'rounded-[32px]',
                $source,
                'LoginPage.vue must declare the outer-card radius as a Tailwind utility (Phase 2.1)'
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
        public function login_page_image_uses_committed_dental_pexels_asset(): void
        {
            $source = (string) file_get_contents(self::loginPagePath());

            // The dental Pexels still is the chosen hero image. The committed
            // path under public/images/ui/ is the safe location (the
            // public/images/pexels/ tree is gitignored — referencing it from
            // an auth module would fail the regression suite).
            $this->assertSame(
                0,
                substr_count($source, 'images/pexels'),
                'LoginPage.vue must NOT reference images/pexels (gitignored directory)'
            );
            $this->assertSame(
                1,
                substr_count($source, '/images/ui/login-hero.jpg'),
                'LoginPage.vue must reference the committed login hero at /images/ui/login-hero.jpg (Phase 2.2)'
            );
        }

        /**
         * @test
         */
        public function login_page_image_has_error_fallback(): void
        {
            $source = (string) file_get_contents(self::loginPagePath());

            // The hero image must declare an @error handler that swaps to
            // the SVG placeholder (Phase 2.2 — D6 in design.md).
            $this->assertSame(
                1,
                (int) preg_match('/<img\b[^>]*@error\s*=\s*"onImageError"/is', $source),
                'LoginPage.vue hero <img> must declare @error="onImageError" (Phase 2.2 — D6)'
            );
            $this->assertStringContainsString(
                'onImageError',
                $source,
                'LoginPage.vue must implement onImageError to swap to the SVG fallback'
            );
            $this->assertStringContainsString(
                'imageFailed',
                $source,
                'LoginPage.vue must track imageFailed state for the SVG fallback toggle'
            );
        }

        /**
         * @test
         */
        public function login_page_overlays_fetch_dashboard_stats(): void
        {
            $source = (string) file_get_contents(self::loginPagePath());

            $this->assertStringContainsString(
                '/api/dashboard/stats',
                $source,
                'LoginPage.vue must fetch /api/dashboard/stats for overlay Card 1 (Phase 2.3)'
            );
        }

        /**
         * @test
         */
        public function login_page_overlays_fetch_appointments_today(): void
        {
            $source = (string) file_get_contents(self::loginPagePath());

            $this->assertStringContainsString(
                '/api/dashboard/appointments-today',
                $source,
                'LoginPage.vue must fetch /api/dashboard/appointments-today for overlay Card 2 (Phase 2.3)'
            );
        }

        /**
         * @test
         */
        public function login_page_overlays_fetch_users_active(): void
        {
            $source = (string) file_get_contents(self::loginPagePath());

            $this->assertStringContainsString(
                '/api/users/active',
                $source,
                'LoginPage.vue must fetch /api/users/active for overlay Card 3 (Phase 2.3)'
            );
        }

        /**
         * @test
         */
        public function login_page_overlays_have_v_motion_stagger(): void
        {
            $source = (string) file_get_contents(self::loginPagePath());

            // v-motion on overlay cards with a stagger delay. The exact
            // delay value is unconstrained; the test pins that SOME
            // stagger is declared (an index-driven `100 + i * 80` or
            // equivalent). The total count of v-motion directives is
            // intentionally NOT asserted — the submit button also uses
            // v-motion for its polymorphic crossfade, so any strict
            // `== 3` would couple the test to a single implementation.
            $this->assertGreaterThanOrEqual(
                3,
                substr_count($source, 'v-motion'),
                'LoginPage.vue must declare at least 3 v-motion directives (overlay stagger + submit polymorphism)'
            );
            // The stagger delay can live inline (`v-motion="{ ..., delay: 100 + i * 80 }"`)
            // or inside a motion factory function (`enter: { ..., delay: 100 + index * 80 }`).
            // Both patterns are accepted; the test pins that the 80ms step is present.
            $this->assertMatchesRegularExpression(
                '/delay\s*:\s*[^,}]*\*\s*80/',
                $source,
                'LoginPage.vue overlay motion must declare a stagger delay with the 80ms step (Phase 2.3)'
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
                'LoginPage.vue must constrain .login-split-card height to the viewport (max-height: calc(100dvh - ...))'
            );
        }

        /**
         * @test
         */
        public function login_page_form_column_scrolls_internally(): void
        {
            $source = (string) file_get_contents(self::loginPagePath());

            $this->assertStringContainsString(
                '.login-form-column',
                $source,
                'LoginPage.vue must declare a .login-form-column rule'
            );
            $this->assertStringContainsString(
                'overflow-y: auto',
                $source,
                '.login-form-column must scroll internally (overflow-y: auto) when the form is taller than the column'
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
        public function login_page_collapses_to_single_column_below_768(): void
        {
            $source = (string) file_get_contents(self::loginPagePath());

            $this->assertStringContainsString(
                '@media (max-width: 767px)',
                $source,
                'LoginPage.vue must declare a @media (max-width: 767px) block for the mobile single-column collapse'
            );
            $this->assertStringContainsString(
                'grid-template-rows: 240px minmax(0, 1fr)',
                $source,
                'Mobile grid must stack hero (240px) on top of form (1fr) so the two do not overlap'
            );
        }
}
