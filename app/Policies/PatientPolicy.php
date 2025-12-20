<?php

namespace App\Policies;

use App\Models\Patient;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class PatientPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        // Todos los roles pueden ver pacientes
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Patient $patient): bool
    {
        // Todos los roles pueden ver pacientes
        return true;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        // Admin y recepcionista pueden crear pacientes
        return in_array($user->role, ['administrador', 'recepcionista']);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Patient $patient): bool
    {
        // Admin y recepcionista pueden editar cualquier paciente
        if (in_array($user->role, ['administrador', 'recepcionista'])) {
            return true;
        }

        // Clínicos pueden editar pacientes para agregar información médica
        if (in_array($user->role, ['odontologo', 'implantologo', 'tecnico_dental', 'asistente'])) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Patient $patient): bool
    {
        // Solo admin puede eliminar pacientes
        return $user->role === 'administrador';
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Patient $patient): bool
    {
        // Solo admin puede restaurar pacientes
        return $user->role === 'administrador';
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Patient $patient): bool
    {
        // Solo admin puede eliminar permanentemente
        return $user->role === 'administrador';
    }

    /**
     * Determine whether the user can export patient data.
     */
    public function export(User $user, Patient $patient): bool
    {
        // Admin, recepcionista y clínicos pueden exportar fichas de pacientes
        return in_array($user->role, [
            'administrador',
            'recepcionista',
            'odontologo',
            'implantologo',
            'tecnico_dental'
        ]);
    }
}
