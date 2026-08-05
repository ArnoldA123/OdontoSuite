<?php

namespace Tests\Unit\Composables;

use PHPUnit\Framework\TestCase;

/**
 * Bugfix-2026-08 slice 09 — RED test for UXF-021 (PaymentModal 401 redirect).
 *
 * Before this slice, PaymentModal swallowed 401 errors inside the
 * `loadPaymentMethods` empty-catch branch and only surfaced a toast. The
 * session expiry was invisible: the user thought the modal was just broken
 * and never got bounced back to /login.
 *
 * The fix:
 *  - On 401, call `authLogout()` (clears token + user state).
 *  - Emit `router.push('/login')` to force the auth gate.
 *  - Surface the toast `'Tu sesión expiró'`.
 *
 * Source-based assertions because `openspec/config.yaml` -> `js_unit_runner:
 * none`. The test stays RED before the slice's edit and turns GREEN after.
 */
class PaymentModal401RedirectTest extends TestCase
{
    private const PROJECT_ROOT = 'E:/UNIVERSIDAD PRIVADA DEL NORTE/UPN 10 CICLO/Capstone/Proyecto/OdontoSuiteV2/OdontoSuite';

    private function paymentModalSource(): string
    {
        $path = self::PROJECT_ROOT . '/resources/js/modules/cash-register/components/PaymentModal.vue';
        $source = file_get_contents($path);
        $this->assertNotFalse($source, 'PaymentModal.vue must exist');
        return $source;
    }

    private function useAuthSource(): string
    {
        $path = self::PROJECT_ROOT . '/resources/js/composables/useAuth.js';
        $source = file_get_contents($path);
        $this->assertNotFalse($source, 'useAuth.js must exist');
        return $source;
    }

    /** @test UXF-021 */
    public function useAuth_exports_authLogout_alias(): void
    {
        $source = $this->useAuthSource();

        // `authLogout` must be a top-level export so PaymentModal can call it.
        $this->assertMatchesRegularExpression(
            '/\bauthLogout\b/',
            $source,
            'useAuth.js must export authLogout (UXF-021)'
        );
    }

    /** @test UXF-021 */
    public function PaymentModal_loadPaymentMethods_calls_authLogout_on_401(): void
    {
        $source = $this->paymentModalSource();

        // Find the loadPaymentMethods body — the 401 branch must call
        // either authLogout() directly or the handleSessionExpired()
        // wrapper (which itself calls authLogout() + router.push('/login')).
        $this->assertMatchesRegularExpression(
            '/loadPaymentMethods[\s\S]{0,800}status\s*===\s*401[\s\S]{0,400}(?:authLogout\s*\(|handleSessionExpired\s*\()/',
            $source,
            'PaymentModal loadPaymentMethods 401 branch must call authLogout() or handleSessionExpired() (UXF-021)'
        );
    }

    /** @test UXF-021 */
    public function PaymentModal_loadPaymentMethods_routes_to_login_on_401(): void
    {
        $source = $this->paymentModalSource();

        // The 401 branch must also push /login via vue-router (either
        // directly or via the handleSessionExpired wrapper).
        $this->assertMatchesRegularExpression(
            '/loadPaymentMethods[\s\S]{0,800}status\s*===\s*401[\s\S]{0,800}(?:router\.push\([\'"]\/login[\'"]\)|handleSessionExpired\s*\()/',
            $source,
            'PaymentModal loadPaymentMethods 401 branch must router.push("/login") or call handleSessionExpired() (UXF-021)'
        );
    }

    /** @test UXF-021 */
    public function PaymentModal_loadPaymentMethods_toast_on_401_says_session_expired(): void
    {
        $source = $this->paymentModalSource();

        // The toast text must communicate session expiry (matches the launch
        // prompt's approved wording).
        $this->assertStringContainsString(
            'Tu sesi',
            $source,
            'PaymentModal 401 branch must show a "Tu sesión ..." toast (UXF-021)'
        );
    }

    /** @test UXF-021 */
    public function PaymentModal_handles_401_in_loadPatientAppointments_too(): void
    {
        $source = $this->paymentModalSource();

        // loadPatientAppointments also has a 401 branch (slice 07 wired it);
        // ensure it ALSO triggers authLogout + redirect.
        $this->assertMatchesRegularExpression(
            '/loadPatientAppointments[\s\S]{0,1000}status\s*===\s*401[\s\S]{0,400}(?:authLogout\s*\(|handleSessionExpired\s*\()/',
            $source,
            'PaymentModal loadPatientAppointments 401 branch must also call authLogout() or handleSessionExpired() (UXF-021)'
        );
    }

    /** @test UXF-021 */
    public function PaymentModal_handleSubmit_also_redirects_on_401(): void
    {
        $source = $this->paymentModalSource();

        // The handleSubmit catch block must also forward a 401 through the
        // session-expiry handler (slice 09 / UXF-021).
        $this->assertMatchesRegularExpression(
            '/handleSubmit[\s\S]{0,2000}status\s*===\s*401[\s\S]{0,400}(?:authLogout\s*\(|handleSessionExpired\s*\()/',
            $source,
            'PaymentModal handleSubmit 401 branch must also call authLogout() / handleSessionExpired() (UXF-021)'
        );
    }

    /** @test UXF-021 */
    public function PaymentModal_handleSessionExpired_combines_toast_logout_and_router_push(): void
    {
        $source = $this->paymentModalSource();

        // The handleSessionExpired helper must bundle the three required
        // actions in one place. This is the canonical 401 response shape.
        // Match the helper definition + its body in one regex.
        $this->assertMatchesRegularExpression(
            '/const\s+handleSessionExpired\s*=\s*\(\)\s*=>\s*\{[\s\S]{0,500}toast\.error\([\'"]Tu sesi[^\'"]*[\'"][\s\S]{0,300}authLogout\s*\(\)[\s\S]{0,200}router\.push\([\'"]\/login[\'"]\)/',
            $source,
            'PaymentModal handleSessionExpired() must combine toast.error("Tu sesión...") + authLogout() + router.push("/login") (UXF-021)'
        );
    }
}