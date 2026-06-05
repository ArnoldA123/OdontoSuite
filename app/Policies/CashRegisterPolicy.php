<?php

namespace App\Policies;

use App\Models\User;
use App\Models\CashRegisterSession;
use Illuminate\Auth\Access\HandlesAuthorization;

class CashRegisterPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any cash register sessions.
     */
    public function viewAny(User $user): bool
    {
        return in_array($user->role, ['administrador', 'finanzas']);
    }

    /**
     * Determine whether the user can view the cash register session.
     */
    public function view(User $user, CashRegisterSession $cashRegisterSession): bool
    {
        return in_array($user->role, ['administrador', 'finanzas', 'recepcionista']) &&
               ($user->role === 'administrador' || $cashRegisterSession->user_id === $user->id);
    }

    /**
     * Determine whether the user can create cash register sessions.
     */
    public function create(User $user): bool
    {
        return in_array($user->role, ['administrador', 'finanzas']);
    }

    /**
     * Determine whether the user can open a cash register session.
     */
    public function open(User $user): bool
    {
        return in_array($user->role, ['administrador', 'finanzas']);
    }

    /**
     * Determine whether the user can close a cash register session.
     */
    public function close(User $user, CashRegisterSession $cashRegisterSession): bool
    {
        return in_array($user->role, ['administrador', 'finanzas']);
    }

    /**
     * Determine whether the user can update the cash register session.
     */
    public function update(User $user, CashRegisterSession $cashRegisterSession): bool
    {
        return in_array($user->role, ['administrador', 'finanzas']) &&
               ($user->role === 'administrador' || $cashRegisterSession->user_id === $user->id);
    }

    /**
     * Determine whether the user can delete the cash register session.
     */
    public function delete(User $user, CashRegisterSession $cashRegisterSession): bool
    {
        return $user->role === 'administrador';
    }

    /**
     * Determine whether the user can apply discounts.
     */
    public function applyDiscount(User $user): bool
    {
        return in_array($user->role, ['administrador', 'finanzas']);
    }

    /**
     * Determine whether the user can apply large discounts (>10%).
     */
    public function applyLargeDiscount(User $user): bool
    {
        return $user->role === 'administrador';
    }

    /**
     * Determine whether the user can view cash reports.
     */
    public function viewReports(User $user): bool
    {
        return in_array($user->role, ['administrador', 'finanzas']);
    }

    /**
     * Determine whether the user can export cash data.
     */
    public function exportData(User $user): bool
    {
        return in_array($user->role, ['administrador', 'finanzas']);
    }
}

