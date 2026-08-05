<?php

namespace App\Policies;

use App\Models\CashMovement;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * Policy for the CashMovement resource.
 *
 * Slice 09 / FF-001 — RBAC defense-in-depth. The route-level middleware at
 * `routes/api.php:345` already enforces `role:administrador,finanzas` on the
 * `cash-movements` apiResource. This Policy class provides a single
 * authoritative source for programmatic `$user->can('create', CashMovement::class)`
 * checks (e.g. controller-level gates, future Lumen/Octane multi-tenant
 * filters, or any non-route caller).
 *
 * The allowed role set MUST stay aligned with the route middleware; the
 * `CashMovementPolicyTest::policy_is_aligned_with_backend_route_middleware`
 * test fails the build if either drifts.
 */
class CashMovementPolicy
{
    use HandlesAuthorization;

    /**
     * Roles that may create a cash movement.
     *
     * Kept as a public constant so route middleware, controller gates, and
     * the FE `usePermissions.createMovement` mapping can all read from the
     * same role set.
     */
    public const ALLOWED_ROLES = ['administrador', 'finanzas'];

    /**
     * Determine whether the user can create cash movements.
     *
     * Allowed roles: administrador, finanzas.
     */
    public function create(User $user): bool
    {
        return in_array($user->role, self::ALLOWED_ROLES, true);
    }

    /**
     * Determine whether the user can update a cash movement.
     * Mirrors the in-controller rule used by CashMovementController@update
     * (session owner OR administrador).
     */
    public function update(User $user, CashMovement $cashMovement): bool
    {
        return in_array($user->role, self::ALLOWED_ROLES, true)
            && ($user->role === 'administrador'
                || optional($cashMovement->cashRegisterSession)->user_id === $user->id);
    }

    /**
     * Determine whether the user can delete a cash movement.
     * Only administrador can delete (mirrors the controller's existing rule).
     */
    public function delete(User $user, CashMovement $cashMovement): bool
    {
        return $user->role === 'administrador';
    }
}