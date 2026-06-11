<?php

namespace Tests\Unit\Middleware;

use Tests\TestCase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Sprint 4 (M-6) + I-4: tests para RequireActiveCashSession.
 */
class RequireActiveCashSessionTest extends TestCase
{
    public function test_middleware_class_exists(): void
    {
        $this->assertTrue(class_exists(\App\Http\Middleware\RequireActiveCashSession::class));
    }

    public function test_middleware_has_handle_method(): void
    {
        $this->assertTrue(
            method_exists(\App\Http\Middleware\RequireActiveCashSession::class, 'handle')
        );
    }

    /**
     * Verifica que el middleware esta aliasado como 'cash.session'.
     * Si alguien lo renombra, este test falla.
     */
    public function test_middleware_is_aliased_as_cash_session(): void
    {
        $bootstrap = file_get_contents(base_path('bootstrap/app.php'));
        $this->assertStringContainsString(
            "'cash.session'",
            $bootstrap,
            "cash.session debe estar aliasado en bootstrap/app.php"
        );
    }

    /**
     * El middleware NO debe aplicar a GET (deja pasar lecturas).
     */
    public function test_middleware_bypasses_get_requests(): void
    {
        $middleware = new \App\Http\Middleware\RequireActiveCashSession();
        $request = Request::create('/api/transactions', 'GET');

        // Sin auth, el middleware no puede verificar la sesion. Pero como
        // es GET, debe bypasear inmediatamente sin tocar la BD.
        $response = $middleware->handle($request, fn($r) => response('ok'));
        $this->assertEquals('ok', $response->getContent());
    }
}
