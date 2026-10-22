<?php

namespace Tests\Unit\Authorization;

use App\Authorization\BranchAccess;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * #283 S2 / #290 S6 — the sede is an authorization dimension, not only a query
 * filter. These tests pin the whole rule without a database: a user without an
 * assigned sede has group scope, a user with one is restricted to it, and naming
 * a foreign sede while operating is a 403.
 */
class BranchAccessTest extends TestCase
{
    private function user(?int $branchId, string $role = 'recepcionista'): User
    {
        $user = new User;
        $user->role = $role;
        $user->branch_id = $branchId;

        return $user;
    }

    #[Test]
    public function a_user_without_an_assigned_sede_has_group_scope(): void
    {
        $user = $this->user(null, 'administrador');

        $this->assertFalse(BranchAccess::isRestricted($user));
        $this->assertNull(BranchAccess::allowedBranchIds($user));
        $this->assertTrue(BranchAccess::allows($user, 7));
        $this->assertTrue(BranchAccess::allows($user, null));
    }

    #[Test]
    public function a_user_with_an_assigned_sede_is_restricted_to_it(): void
    {
        $user = $this->user(3);

        $this->assertTrue(BranchAccess::isRestricted($user));
        $this->assertSame([3], BranchAccess::allowedBranchIds($user));
        $this->assertTrue(BranchAccess::allows($user, 3));
        $this->assertFalse(BranchAccess::allows($user, 4));
    }

    #[Test]
    public function a_record_without_a_sede_is_outside_the_reach_of_a_restricted_user(): void
    {
        $this->assertFalse(BranchAccess::allows($this->user(3), null));
    }

    #[Test]
    public function asserting_a_foreign_sede_throws_403(): void
    {
        $this->expectException(AuthorizationException::class);

        BranchAccess::assertBranch($this->user(3), 4);
    }

    #[Test]
    public function asserting_the_own_sede_or_group_scope_passes(): void
    {
        BranchAccess::assertBranch($this->user(3), 3);
        BranchAccess::assertBranch($this->user(null, 'administrador'), 4);

        $this->assertTrue(true);
    }

    #[Test]
    public function the_effective_filter_forces_the_own_sede_when_none_is_requested(): void
    {
        $this->assertSame(3, BranchAccess::effectiveFilter($this->user(3), null));
        $this->assertSame(4, BranchAccess::effectiveFilter($this->user(null), 4));
        $this->assertNull(BranchAccess::effectiveFilter($this->user(null), null));
    }

    #[Test]
    public function the_effective_filter_keeps_an_allowed_request(): void
    {
        $this->assertSame(3, BranchAccess::effectiveFilter($this->user(3), 3));
    }

    #[Test]
    public function the_effective_filter_rejects_a_foreign_request(): void
    {
        $this->expectException(AuthorizationException::class);

        BranchAccess::effectiveFilter($this->user(3), 4);
    }

    #[Test]
    public function global_scope_requires_an_unassigned_administrator(): void
    {
        BranchAccess::assertGlobal($this->user(null, 'administrador'));

        $this->assertTrue(BranchAccess::isGlobal($this->user(null, 'administrador')));
        $this->assertFalse(BranchAccess::isGlobal($this->user(3, 'administrador')));
        $this->assertFalse(BranchAccess::isGlobal($this->user(null, 'recepcionista')));
    }

    #[Test]
    public function a_sede_administrator_is_not_global_scope(): void
    {
        $this->expectException(AuthorizationException::class);

        BranchAccess::assertGlobal($this->user(3, 'administrador'));
    }
}
