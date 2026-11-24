<?php

namespace App\Policies;

use App\Authorization\Permissions;
use App\Models\Appointment;
use App\Models\User;

/**
 * The agenda's single rule (citas-privacidad-tiempo-real, T1).
 *
 * The route already answers the capability question (`manage.appointment` for
 * reading and operating the agenda, `create.appointment` for booking a new
 * row); this policy answers the ownership one, which the route never asked. A
 * clinical role used to read and operate every appointment of the group
 * because the old rule only excluded finance, so a professional saw another
 * professional's patients. The decision is strict privacy:
 *
 *  - `administrador` and `recepcionista` keep the whole agenda of their sede
 *    (the sede dimension is `ScopesToBranch`, not this class).
 *  - every other appointment role reads and operates only the appointments
 *    where it is the professional.
 *
 * `seesWholeAgenda()` is the only place the wide list is written. The index
 * scope, the doors and the tests all derive from it, so a new reader role is
 * added in one line. The grants themselves are not repeated here: `Permissions`
 * stays the single source of the capability, and `create` asks for the booking
 * door instead of the agenda one — the professional operates their own rows but
 * never books for others.
 */
class AppointmentPolicy
{
    /**
     * Roles that read and operate every appointment of their sede.
     *
     * @var list<string>
     */
    private const WIDE_AGENDA = ['administrador', 'recepcionista'];

    /**
     * Whether the user's agenda is the whole sede instead of their own rows.
     */
    public static function seesWholeAgenda(?User $user): bool
    {
        return $user instanceof User
            && in_array($user->role, self::WIDE_AGENDA, true);
    }

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return Permissions::roleHas($user->role, Permissions::MANAGE_APPOINTMENT);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Appointment $appointment): bool
    {
        return $this->viewAny($user) && $this->owns($user, $appointment);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return Permissions::roleHas($user->role, Permissions::CREATE_APPOINTMENT);
    }

    /**
     * Determine whether the user can update the model.
     *
     * Editing follows the same rule as reading: reception and administration
     * edit any appointment of their sede, a clinician only their own.
     */
    public function update(User $user, Appointment $appointment): bool
    {
        return $this->view($user, $appointment);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Appointment $appointment): bool
    {
        return self::seesWholeAgenda($user)
            && Permissions::roleHas($user->role, Permissions::MANAGE_APPOINTMENT);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Appointment $appointment): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Appointment $appointment): bool
    {
        return false;
    }

    /**
     * The ownership half of the rule: reception and administration answer for
     * every row of their sede, a clinician only for their own rows.
     */
    private function owns(User $user, Appointment $appointment): bool
    {
        return self::seesWholeAgenda($user)
            || (int) $appointment->user_id === (int) $user->id;
    }
}
