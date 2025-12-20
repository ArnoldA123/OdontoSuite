<?php

namespace App\Policies;

use App\Models\Appointment;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class AppointmentPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        // Todos los roles excepto finanzas pueden ver citas
        return !in_array($user->role, ['finanzas']);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Appointment $appointment): bool
    {
        // Todos los roles excepto finanzas pueden ver citas
        return !in_array($user->role, ['finanzas']);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        // Admin, recepcionista y clínicos pueden crear citas
        return in_array($user->role, [
            'administrador',
            'recepcionista',
            'odontologo',
            'implantologo',
            'tecnico_dental',
            'asistente'
        ]);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Appointment $appointment): bool
    {
        // Admin y recepcionista pueden editar cualquier cita
        if (in_array($user->role, ['administrador', 'recepcionista'])) {
            return true;
        }

        // Clínicos solo pueden editar sus propias citas
        if (in_array($user->role, ['odontologo', 'implantologo', 'tecnico_dental', 'asistente'])) {
            return $appointment->user_id === $user->id;
        }

        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Appointment $appointment): bool
    {
        // Solo admin y recepcionista pueden eliminar citas
        return in_array($user->role, ['administrador', 'recepcionista']);
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
}
