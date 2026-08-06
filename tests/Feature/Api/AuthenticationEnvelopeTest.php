<?php

namespace Tests\Feature\Api;

use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Tests\TestCase;

/**
 * full-user-browser-audit-2026-08-05 / PR7b / Slice 07b.
 *
 * Regression contract for the AuthenticationException renderer. The routes
 * used for the unchanged-envelope assertions are registered only in this
 * test process; production routes and middleware remain untouched.
 */
class AuthenticationEnvelopeTest extends TestCase
{
    private bool $originalDebug;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalDebug = (bool) config('app.debug');
        config(['app.debug' => false]);

        $router = $this->app['router'];

        if (! $router->getRoutes()->hasNamedRoute('login')) {
            $router->get('/sdd-auth-envelope-login', static fn (): string => 'login')->name('login');
        }

        $router->get('/sdd-auth-envelope-web', static fn (): string => 'protected')
            ->middleware(['web', 'auth'])
            ->name('sdd-auth-envelope.web');

        $router->get('/api/sdd-auth-envelope-unauthorized', static fn () => throw new UnauthorizedHttpException(
            'Bearer',
            'known unauthorized'
        ));

        $router->post('/api/sdd-auth-envelope-validation', static function (): never {
            throw ValidationException::withMessages([
                'field' => ['known validation error'],
            ]);
        });

        $router->get('/api/sdd-auth-envelope-forbidden', static fn () => abort(403, 'known forbidden'));
        $router->get('/api/sdd-auth-envelope-error', static fn () => throw new RuntimeException('known failure'));

        $router->getRoutes()->refreshNameLookups();

        $url = $this->app['url'];
        $url->setRoutes($router->getRoutes());
    }

    protected function tearDown(): void
    {
        config(['app.debug' => $this->originalDebug]);
        parent::tearDown();
    }

    private function assertCanonical401($response): void
    {
        $response->assertStatus(401)
            ->assertJsonStructure(['message'])
            ->assertHeader('WWW-Authenticate', 'Bearer realm="api"');

        $this->assertSame('No autenticado.', $response->json('message'));
        $this->assertNull($response->json('error'));
    }

    public function test_unauthenticated_sanctum_request_returns_canonical_401_envelope(): void
    {
        $response = $this->getJson('/api/auth/me');

        $this->assertCanonical401($response);
    }

    public function test_api_path_returns_json_401_even_when_accept_header_is_html(): void
    {
        $response = $this->withHeader('Accept', 'text/html')->get('/api/auth/me');

        $this->assertCanonical401($response);
    }

    public function test_html_authentication_defers_to_laravel_login_redirect(): void
    {
        $this->app['router']->aliasMiddleware('web', \Illuminate\Cookie\Middleware\EncryptCookies::class);
        $this->app['router']->pushMiddlewareToGroup('web', \Illuminate\Session\Middleware\StartSession::class);
        $this->app['router']->pushMiddlewareToGroup('web', \Illuminate\Routing\Middleware\SubstituteBindings::class);

        $response = $this->withSession([])->withHeader('Accept', 'text/html')->get('/sdd-auth-envelope-web');

        $this->assertNotSame(500, $response->status(), 'HTML web auth path MUST NOT emit a 500 error envelope.');
        $this->assertNotSame(401, $response->status(), 'HTML web auth path MUST NOT emit a 401 JSON envelope.');

        if ($response->status() === 200) {
            $body = (string) $response->getContent();
            $this->assertStringContainsString(
                'OdontoSuite',
                $body,
                'Unauthenticated HTML request resolves to the welcome view (no redirect), proving the catch-all Throwable did not swallow the AuthenticationException.'
            );
        } else {
            $this->assertSame(302, $response->status(), 'Unauthenticated web request must redirect to the login route (302).');
        }
    }

    public function test_existing_unauthorized_http_exception_envelope_is_unchanged(): void
    {
        $response = $this->getJson('/api/sdd-auth-envelope-unauthorized');

        $response->assertStatus(401)->assertJsonStructure(['message']);
        $this->assertSame('No autenticado.', $response->json('message'));
    }

    public function test_existing_validation_envelope_is_unchanged(): void
    {
        $response = $this->postJson('/api/sdd-auth-envelope-validation');

        $response->assertStatus(422)->assertExactJson([
            'message' => 'Los datos proporcionados no son válidos.',
            'errors' => [
                'field' => ['known validation error'],
            ],
        ]);
    }

    public function test_existing_forbidden_envelope_is_unchanged(): void
    {
        $response = $this->getJson('/api/sdd-auth-envelope-forbidden');

        $response->assertStatus(403)->assertJsonStructure(['message']);
        $this->assertSame('No tienes permisos para acceder a este recurso.', $response->json('message'));
    }

    public function test_existing_not_found_envelope_is_unchanged(): void
    {
        $response = $this->getJson('/api/sdd-auth-envelope-missing');

        $response->assertStatus(404)->assertJsonStructure(['message']);
        $this->assertSame('La ruta solicitada no fue encontrada.', $response->json('message'));
    }

    public function test_existing_generic_throwable_envelope_is_unchanged(): void
    {
        $response = $this->getJson('/api/sdd-auth-envelope-error');

        $response->assertStatus(500)->assertJsonStructure(['message']);
        $this->assertSame('Error interno del servidor.', $response->json('message'));
    }

    public function test_authentication_renderer_precedes_generic_throwable_renderer(): void
    {
        $source = file_get_contents(base_path('bootstrap/app.php'));
        $this->assertNotFalse($source);

        $authentication = strpos($source, 'AuthenticationException $e');
        $throwable = strpos($source, 'function (\\Throwable $e');

        $this->assertNotFalse($authentication, 'The AuthenticationException renderer must be registered.');
        $this->assertNotFalse($throwable, 'The generic Throwable renderer must remain registered.');
        $this->assertLessThan($throwable, $authentication);
    }
}
