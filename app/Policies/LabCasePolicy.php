<?php

namespace App\Policies;

use App\Authorization\Permissions;
use App\Models\LabCase;
use App\Models\User;

/**
 * #449 — who may edit a prosthetic work order.
 *
 * The middleware already answers the role question (`manage.lab_case`); this
 * policy answers the ownership one, which the route never asked. A work order
 * belongs to the technician it is assigned to, so that technician is the one
 * who corrects it; the desks that prescribe and administer the workshop may
 * correct any case of their branch, because they answer for it.
 *
 * The role list is not repeated here: `Permissions` stays the single source of
 * the grant, and this policy only adds the ownership rule on top of it.
 */
class LabCasePolicy
{
    /**
     * Roles that answer for every case of their branch, not only their own.
     *
     * @var list<string>
     */
    private const OVERSEERS = ['administrador', 'odontologo', 'implantologo'];

    public function update(User $user, LabCase $case): bool
    {
        if (! Permissions::roleHas($user->role, Permissions::MANAGE_LAB_CASE)) {
            return false;
        }

        if (in_array($user->role, self::OVERSEERS, true)) {
            return true;
        }

        // The assigned technician, and nobody else outside the overseeing desks.
        return $case->technician_id !== null
            && (int) $case->technician_id === (int) $user->id;
    }
}
