<?php

namespace App\Http\Middleware;

use App\Authorization\BranchAccess;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Group-scope gate (#283 S2, #290 S6).
 *
 * The catalog of sedes is not a sede responsibility: creating, renaming or
 * retiring one requires reach over the whole group, so a sede administrator is
 * rejected. It runs as middleware because controllers here catch `\Exception`
 * and would answer 500 for an authorization failure raised in the body.
 */
class RequireGlobalBranchScope
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User) {
            BranchAccess::assertGlobal($user);
        }

        return $next($request);
    }
}
