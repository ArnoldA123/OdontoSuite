<?php

namespace Tests\Unit\Composables;

use PHPUnit\Framework\TestCase;

/**
 * Bugfix-2026-08 slice 08 — state handling RED tests.
 *
 * Covers findings FF-003 (useToast reactivity), FF-005 (bootstrap axios
 * import), FF-006 (useAiAnalysis options.headers), FF-007 (useCashRegister
 * double WS subscription), FF-009 (app.js useEcho before auth), FF-013
 * (useNotifications SSR guard), FF-016 (router/auth split-brain).
 *
 * Each test exercises the failure mode described in the finding. The test
 * stays RED before the slice's code fix and turns GREEN after. Test runner
 * is PHPUnit; the JS modules are loaded by shelling out to `node` (same
 * pattern as TokensModuleTest — works around Node 24 proc_open crash).
 */
class StateHandlingTest extends TestCase
{
    /** Project root. */
    private const PROJECT_ROOT = 'E:/UNIVERSIDAD PRIVADA DEL NORTE/UPN 10 CICLO/Capstone/Proyecto/OdontoSuiteV2/OdontoSuite';

    /**
     * Import a JS module via Node and return the JSON-serialized eval result.
     *
     * @param string $relPath  Path relative to project root.
     * @param string $body     JS body to evaluate after import.
     * @return mixed|null      Decoded result, or null on failure.
     */
    private static function evalModule(string $relPath, string $body)
    {
        $modulePath = self::PROJECT_ROOT . $relPath;
        if (!is_file($modulePath)) {
            return null;
        }
        $escapedPath = addcslashes($modulePath, "'\\");

        $loader = <<<'JS'
import { pathToFileURL } from 'node:url';
const url = pathToFileURL('TARGET_PATH').href;
const mod = await import(url);
// Bring every named export into the IIFE's scope as locals so test
// bodies can call them by their short name (useToast, globalToasts, etc.).
for (const k of Object.keys(mod)) {
  globalThis['__mod_' + k] = mod[k];
}
const __result = (function () {
  const useToast = globalThis.__mod_useToast;
  const globalToasts = globalThis.__mod_globalToasts;
USER_BODY
})();
process.stdout.write(JSON.stringify(__result));
JS;
        // Indent USER_BODY one level so it sits inside the IIFE.
        $indentedBody = preg_replace('/^/m', '  ', $body);
        $loader = str_replace('TARGET_PATH', $escapedPath, $loader);
        $loader = str_replace('USER_BODY', $indentedBody, $loader);

        $tmp = tempnam(sys_get_temp_dir(), 'state_loader_');
        $loaderFile = $tmp . '.mjs';
        file_put_contents($loaderFile, $loader);
        @unlink($tmp);

        $cmd = 'node "' . $loaderFile . '" 2>&1';
        $output = shell_exec($cmd);
        @unlink($loaderFile);

        if ($output === null || $output === '') {
            return null;
        }
        $jsonStart = strrpos($output, '{');
        $jsonStart = $jsonStart === false ? strrpos($output, '[') : $jsonStart;
        if ($jsonStart === false) {
            return null;
        }
        return json_decode(substr($output, $jsonStart), true);
    }

    /** @test FF-003 */
    public function useToast_returns_toasts_as_ref_not_plain_array(): void
    {
        $result = self::evalModule(
            '/resources/js/composables/useToast.js',
            <<<'JS'
const t = useToast();
const isRefLike = t.toasts !== null && typeof t.toasts === 'object'
  && ('value' in t.toasts) && Array.isArray(t.toasts.value);
const isPlainArray = Array.isArray(t.toasts);
return { isPlainArray, isRefLike };
JS
        );

        $this->assertNotNull($result, 'useToast module must load');
        $this->assertFalse(
            $result['isPlainArray'],
            'useToast().toasts must NOT be a plain array (FF-003 reactivity fix)'
        );
        $this->assertTrue(
            $result['isRefLike'],
            'useToast().toasts must be a reactive ref with .value as array'
        );
    }

    /** @test FF-003 */
    public function useToast_exposes_globalToasts_as_ref_too(): void
    {
        $result = self::evalModule(
            '/resources/js/composables/useToast.js',
            <<<'JS'
return {
  hasValue: globalToasts && typeof globalToasts === 'object' && 'value' in globalToasts,
  sameIdentity: globalToasts === useToast().toasts
};
JS
        );

        $this->assertNotNull($result);
        $this->assertTrue($result['hasValue'], 'globalToasts must be a Ref');
        $this->assertTrue(
            $result['sameIdentity'],
            'globalToasts must be the same Ref returned from useToast()'
        );
    }

    /** @test FF-006 */
    public function useAiAnalysis_uploadAndAnalyze_does_not_pass_ignored_headers_argument(): void
    {
        $source = file_get_contents(self::PROJECT_ROOT . '/resources/js/composables/useAiAnalysis.js');
        $this->assertNotFalse($source);

        // Slice 08 / FF-006: useAiAnalysis.uploadAndAnalyze used to pass a
        // 3rd argument with headers to useApi.post, which ignored it
        // silently. The fix removes the unused arg.
        $needle = "post('/api/ai-analysis/upload-and-analyze', formData, {\n        headers: {\n          'Content-Type': 'multipart/form-data'\n        }\n      })";
        $this->assertStringNotContainsString(
            $needle,
            $source,
            'useAiAnalysis.uploadAndAnalyze must not pass the unused options.headers argument (FF-006)'
        );
    }

    /** @test FF-006 */
    public function useAiAnalysis_uploadAndAnalyze_calls_post_with_two_arguments(): void
    {
        $source = file_get_contents(self::PROJECT_ROOT . '/resources/js/composables/useAiAnalysis.js');
        $this->assertNotFalse($source);

        // After the fix the call should be `post('/api/ai-analysis/upload-and-analyze', formData)`.
        $this->assertMatchesRegularExpression(
            "/post\\(\\s*['\\\"]\\/api\\/ai-analysis\\/upload-and-analyze['\\\"]\\s*,\\s*formData\\s*\\)/",
            $source,
            'useAiAnalysis.uploadAndAnalyze must call post(url, body) without options arg (FF-006)'
        );
    }

    /** @test FF-007 */
    public function useCashRegister_keeps_subscription_handlers_at_module_level(): void
    {
        $source = file_get_contents(self::PROJECT_ROOT . '/resources/js/composables/useCashRegister.js');
        $this->assertNotFalse($source);

        // Slice 08 / FF-007: the cashRegisterChannel + cashSessionChannel
        // refs must be declared at module scope so multiple useCashRegister()
        // invocations share the same subscription.
        $this->assertMatchesRegularExpression(
            '/^let cashRegisterChannel = null$/m',
            $source,
            'cashRegisterChannel must be a module-level let (FF-007 singleton)'
        );
        $this->assertMatchesRegularExpression(
            '/^let cashSessionChannel = null$/m',
            $source,
            'cashSessionChannel must be a module-level let (FF-007 singleton)'
        );
    }

    /** @test FF-007 */
    public function useCashRegister_singleton_guards_repeat_setup_calls(): void
    {
        $source = file_get_contents(self::PROJECT_ROOT . '/resources/js/composables/useCashRegister.js');
        $this->assertNotFalse($source);

        // A "setup" guard must exist inside setupWebSocketSubscriptions that
        // bails out when the current session is identical to the previous.
        $this->assertMatchesRegularExpression(
            '/setupWebSocketSubscriptions[\\s\\S]*?if \\([\\s\\S]*?currentSession\\.value\\?\\.id[\\s\\S]*?return\\s/',
            $source,
            'setupWebSocketSubscriptions must guard against re-subscribing for the same session (FF-007)'
        );
    }

    /** @test FF-013 */
    public function useNotifications_does_not_crash_when_localStorage_is_undefined(): void
    {
        // Simulate SSR / non-browser environment by deleting both
        // `window` and `localStorage` from globalThis BEFORE importing the
        // module. Pre-fix, the unguarded `loadFromStorage()` at
        // module top level threw a ReferenceError and prevented Vue
        // apps from being created during Vite SSR.

        $loader = <<<'JS'
import { pathToFileURL } from 'node:url';
delete globalThis.window;
delete globalThis.localStorage;
const modulePath = process.argv[2];
const url = pathToFileURL(modulePath).href;
const result = await (async () => {
  try {
    const mod = await import(url);
    const { notifications } = mod.useNotifications();
    return { ok: true, count: notifications.value.length };
  } catch (e) {
    return { ok: false, error: String((e && (e.message || e)) || 'unknown') };
  }
})();
process.stdout.write(JSON.stringify(result));
JS;

        $tmp = tempnam(sys_get_temp_dir(), 'use_notif_loader_');
        $loaderFile = $tmp . '.mjs';
        file_put_contents($loaderFile, $loader);
        @unlink($tmp);

        $modulePath = self::PROJECT_ROOT . '/resources/js/composables/useNotifications.js';
        $cmd = 'node "' . $loaderFile . '" "' . $modulePath . '" 2>&1';
        $output = shell_exec($cmd);
        @unlink($loaderFile);

        $result = null;
        if ($output !== null && $output !== '') {
            $jsonStart = strrpos($output, '{');
            if ($jsonStart !== false) {
                $result = json_decode(substr($output, $jsonStart), true);
            }
        }

        $this->assertNotNull(
            $result,
            'useNotifications module must evaluate without crashing when window is undefined (FF-013). Output: ' . substr((string) $output, 0, 500)
        );
        $this->assertTrue(
            $result['ok'] === true,
            'useNotifications top-level loadFromStorage must not throw when localStorage is unavailable (FF-013). Got: ' . json_encode($result)
        );
    }

    /** @test FF-013 */
    public function useNotifications_loadFromStorage_is_guarded(): void
    {
        $source = file_get_contents(self::PROJECT_ROOT . '/resources/js/composables/useNotifications.js');
        $this->assertNotFalse($source);

        // After the fix, loadFromStorage() must guard with typeof window
        // or wrap the top-level call in a typeof window check.
        $hasWindowGuardAtCallSite = (bool) preg_match(
            "/typeof window\\s*!==?\\s*['\\\"]undefined['\\\"]/",
            $source
        );
        $this->assertTrue(
            $hasWindowGuardAtCallSite,
            'useNotifications.js must guard loadFromStorage() with typeof window check (FF-013)'
        );

        // The bare top-level `loadFromStorage()` (line 33 in original) must
        // not exist unguarded.
        $hasUnguarded = (bool) preg_match(
            '/^loadFromStorage\(\)\s*$/m',
            $source
        );
        $this->assertFalse(
            $hasUnguarded,
            'useNotifications must not call loadFromStorage() unguarded at module top level (FF-013)'
        );
    }

    /** @test FF-009 */
    public function app_js_does_not_call_useEcho_before_auth(): void
    {
        $source = file_get_contents(self::PROJECT_ROOT . '/resources/js/app.js');
        $this->assertNotFalse($source);

        // FF-009: app.js used to call useEcho() unconditionally before the
        // auth token existed, sending an empty Bearer header to
        // /api/broadcasting/auth. After fix the call must be removed from
        // top-level init, OR wrapped in a useAuth().isAuthenticated guard.
        $hasEagerCall = (bool) preg_match(
            '/useEcho\\(\\)/',
            $source
        );
        $hasIsAuthenticatedGuard = (bool) preg_match(
            '/isAuthenticated\\s*\\.value/',
            $source
        );
        $hasAuthGateComment = str_contains($source, 'useEcho only after auth');

        if ($hasEagerCall) {
            // Allowed only if surrounded by an isAuthenticated guard.
            $this->assertTrue(
                $hasIsAuthenticatedGuard || $hasAuthGateComment,
                'If app.js still mentions useEcho() it must be guarded by isAuthenticated.value (FF-009)'
            );
        }
        // Always assert we don't assign window.Echo from a top-level call
        // when no auth has happened.
        $this->assertStringNotContainsString(
            "if (typeof window !== 'undefined') {\n  const { echo } = useEcho();",
            $source,
            'app.js must not initialise Echo at top level before auth (FF-009)'
        );
    }

    /** @test FF-005 */
    public function bootstrap_js_no_longer_imports_axios(): void
    {
        $source = file_get_contents(self::PROJECT_ROOT . '/resources/js/bootstrap.js');
        $this->assertNotFalse($source);

        $this->assertStringNotContainsString(
            "import axios from 'axios'",
            $source,
            'bootstrap.js must not import axios (FF-005)'
        );
        $this->assertStringNotContainsString(
            'window.axios = axios',
            $source,
            'bootstrap.js must not assign window.axios (FF-005)'
        );
    }

    /** @test FF-016 */
    public function router_auth_uses_useAuth_for_authentication_check(): void
    {
        $source = file_get_contents(self::PROJECT_ROOT . '/resources/js/router/auth.js');
        $this->assertNotFalse($source);

        // FF-016: router/auth.js used to read localStorage directly while
        // useAuth already exposes isAuthenticated as a single source of
        // truth. After fix the file must import useAuth and read
        // isAuthenticated.value instead of raw localStorage.

        $this->assertStringContainsString(
            "from '@/composables/useAuth'",
            $source,
            'router/auth.js must import useAuth from @/composables/useAuth (FF-016)'
        );

        // Must not read localStorage directly for auth check (raw tokens).
        $this->assertStringNotContainsString(
            "localStorage.getItem('auth_token')",
            $source,
            'router/auth.js must not read localStorage.getItem(auth_token) directly (FF-016)'
        );
        $this->assertStringNotContainsString(
            "localStorage.getItem('user')",
            $source,
            'router/auth.js must not read localStorage.getItem(user) directly (FF-016)'
        );

        // Must reference isAuthenticated at least once.
        $this->assertStringContainsString(
            'isAuthenticated',
            $source,
            'router/auth.js must reference isAuthenticated from useAuth (FF-016)'
        );
    }
}
