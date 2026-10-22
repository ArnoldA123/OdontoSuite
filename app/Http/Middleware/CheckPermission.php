<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Permission gate for the `permission` middleware alias (#280).
 *
 * Routes declare permissions, never role lists:
 *   Route::middleware('permission:view.patient')->group(...)
 *   Route::middleware('permission:manage.medical_record,manage.evolution')
 *
 * Multiple permissions are an OR, matching the previous `role:` semantics.
 * The middleware assumes `auth:sanctum` ran first: without a user it answers
 * 401 instead of leaking a 403 to an anonymous caller.
 */
class CheckPermission
{
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(401, 'No autenticado');
        }

        if ($permissions === []) {
            abort(403, 'No tienes permisos para acceder a este recurso');
        }

        foreach ($permissions as $permission) {
            if ($user->hasPermission($permission)) {
                return $next($request);
            }
        }

        abort(403, 'No tienes permisos para acceder a este recurso');
    }
}
