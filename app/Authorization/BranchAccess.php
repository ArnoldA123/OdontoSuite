<?php

namespace App\Authorization;

use App\Models\User;

/**
 * The branch (sede) dimension of authorization (#283 S2, #290 S6).
 *
 * A user is affiliated to one sede: `users.branch_id` is that affiliation, and
 * `null` means group scope (gerencia) — the decision recorded in
 * odd/tasks/f3-sucursales-283-290.md (L2). A restricted user operates only
 * inside their sede; naming a foreign sede while operating is a 403, not an
 * empty result set, because the sede is an authorization dimension and not only
 * a query filter.
 *
 * The patient file is deliberately outside this rule (S9): the patient, their
 * clinical history, evolutions, attachments, specialty records, encounters and
 * treatment plans are read from any sede. What this class scopes is the
 * operational surface — agenda, chairs, cash, staff.
 *
 * Every method is a pure function of the user, so it needs no container and no
 * database: the model guard, the route-model binding and the controllers all
 * call the same rule.
 */
final class BranchAccess
{
    /**
     * Sedes the user may operate, or null when the reach is the whole group.
     *
     * @return list<int>|null
     */
    public static function allowedBranchIds(?User $user): ?array
    {
        if (! self::isRestricted($user)) {
            return null;
        }

        return [(int) $user->branch_id];
    }

    /**
     * A user without an assigned sede has group scope.
     */
    public static function isRestricted(?User $user): bool
    {
        return $user !== null && $user->branch_id !== null;
    }

    /**
     * Whether the user may operate on a record that belongs to `$branchId`.
     *
     * A record with no sede is group data: it is outside the reach of a
     * restricted user.
     */
    public static function allows(?User $user, ?int $branchId): bool
    {
        if (! self::isRestricted($user)) {
            return true;
        }

        return $branchId !== null && (int) $user->branch_id === $branchId;
    }

    /**
     * Assert the user may operate on that sede, or fail with a 403.
     *
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public static function assertBranch(?User $user, ?int $branchId): void
    {
        if (self::allows($user, $branchId)) {
            return;
        }

        throw new \Illuminate\Auth\Access\AuthorizationException(
            'No puede operar sobre otra sede. Su acceso está limitado a la sede asignada.',
        );
    }

    /**
     * The branch filter a request must apply.
     *
     * A restricted user asking for a foreign sede is rejected; asking for
     * nothing is answered with their own sede, so the query can never widen to
     * the group by omission.
     *
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public static function effectiveFilter(?User $user, ?int $requestedBranchId): ?int
    {
        if (! self::isRestricted($user)) {
            return $requestedBranchId;
        }

        if ($requestedBranchId === null) {
            return (int) $user->branch_id;
        }

        self::assertBranch($user, $requestedBranchId);

        return $requestedBranchId;
    }

    /**
     * Group scope: the whole group, every sede.
     */
    public static function isGlobal(?User $user): bool
    {
        return $user !== null && ! self::isRestricted($user) && $user->hasPermission(Permissions::MANAGE_BRANCH);
    }

    /**
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public static function assertGlobal(?User $user): void
    {
        if (self::isGlobal($user)) {
            return;
        }

        throw new \Illuminate\Auth\Access\AuthorizationException(
            'La administración de sedes requiere alcance de grupo.',
        );
    }
}
