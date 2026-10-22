<?php

namespace Tests\Unit\Authorization;

use App\Authorization\Permissions;
use App\Http\Middleware\CheckPermission;
use App\Models\User;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * #280 — the `permission` middleware is the only route-level gate.
 */
class CheckPermissionMiddlewareTest extends TestCase
{
    private function requestFor(?string $role): Request
    {
        $request = Request::create('/api/probe', 'POST');
        $request->setUserResolver(fn () => $role === null ? null : new User(['role' => $role]));

        return $request;
    }

    private function statusFor(?string $role, string ...$permissions): int
    {
        try {
            (new CheckPermission)->handle(
                $this->requestFor($role),
                fn () => response()->json(['ok' => true]),
                ...$permissions,
            );
        } catch (HttpException $exception) {
            return $exception->getStatusCode();
        }

        return 200;
    }

    #[Test]
    public function anonymous_requests_get_401(): void
    {
        $this->assertSame(401, $this->statusFor(null, Permissions::VIEW_PATIENT));
    }

    #[Test]
    public function reception_is_forbidden_from_closing_a_consultation(): void
    {
        $this->assertSame(403, $this->statusFor('recepcionista', Permissions::COMPLETE_APPOINTMENT));
    }

    #[Test]
    public function dentist_can_close_a_consultation(): void
    {
        $this->assertSame(200, $this->statusFor('odontologo', Permissions::COMPLETE_APPOINTMENT));
    }

    #[Test]
    public function multiple_permissions_are_an_or(): void
    {
        $this->assertSame(
            200,
            $this->statusFor('finanzas', Permissions::VOID_TRANSACTION, Permissions::MANAGE_CASH_MOVEMENT),
        );
    }

    #[Test]
    public function a_route_without_permissions_is_forbidden(): void
    {
        $this->assertSame(403, $this->statusFor('administrador'));
    }
}
