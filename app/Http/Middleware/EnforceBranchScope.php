<?php

namespace App\Http\Middleware;

use App\Authorization\BranchAccess;
use App\Models\Branch;
use App\Models\Concerns\ScopesToBranch;
use App\Models\User;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The sede gate that answers 403 (#283 S2/S6).
 *
 * Controllers of this API catch `\Exception` and answer 500, so an authorization
 * failure raised inside the controller body would surface as a server error. The
 * check therefore runs here, in the `api` group after `SubstituteBindings`, and
 * only throws: a restricted user naming a foreign sede in the body, or binding a
 * record of another sede on a write, is rejected before the controller is
 * reached.
 *
 * The model guard (ScopesToBranch) stays as the backstop for any write path that
 * does not pass through the HTTP layer, and route-model binding answers 404 for
 * reads of another sede.
 */
class EnforceBranchScope
{
    /**
     * Writes that change stored state; reads are scoped by binding and query.
     *
     * @var list<string>
     */
    private const WRITE_METHODS = ['POST', 'PUT', 'PATCH', 'DELETE'];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User || ! BranchAccess::isRestricted($user)) {
            return $next($request);
        }

        // A sede named in the payload is authorization, not a filter: asking to
        // read or write another sede is a 403 in every method.
        if ($request->filled('branch_id')) {
            BranchAccess::assertBranch($user, (int) $request->input('branch_id'));
        }

        if (! in_array($request->method(), self::WRITE_METHODS, true)) {
            return $next($request);
        }

        foreach ($request->route()?->parameters() ?? [] as $parameter) {
            // A sede addressed by the route (tarifario, bloqueo contable) is a
            // write target of its own.
            if ($parameter instanceof Branch) {
                BranchAccess::assertBranch($user, $parameter->id);

                continue;
            }

            if (! $parameter instanceof Model) {
                continue;
            }

            if (! in_array(ScopesToBranch::class, class_uses_recursive($parameter), true)) {
                continue;
            }

            $branchId = $parameter->getAttribute('branch_id');

            if ($branchId !== null) {
                BranchAccess::assertBranch($user, (int) $branchId);
            }
        }

        return $next($request);
    }
}
