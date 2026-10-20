<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Single definition of the clinical relationship used to scope patient data.
 *
 * The author's rule for the AI surface (issue #214) is: an administrator sees
 * everything; a clinical user reaches a patient when she is the professional of
 * one of that patient's appointments or the author of one of its clinical
 * evolutions. The analysis rows add their own two exceptions (requester and
 * reviewer) on top of this set, which is why the predicate lives here and not
 * inside a single model.
 */
final class ClinicalAccess
{
    /**
     * Patient ids the user reaches by clinical relationship, as a subquery.
     */
    public static function patientIdsFor(User $user): Builder
    {
        return DB::table('appointments')
            ->select('patient_id')
            ->where('user_id', $user->id)
            ->union(
                DB::table('clinical_evolutions')
                    ->select('patient_id')
                    ->where('created_by', $user->id)
                    ->whereNull('deleted_at')
            );
    }

    public static function canReachPatient(User $user, int $patientId): bool
    {
        if ($user->role === 'administrador') {
            return true;
        }

        $hasAppointment = DB::table('appointments')
            ->where('user_id', $user->id)
            ->where('patient_id', $patientId)
            ->exists();

        if ($hasAppointment) {
            return true;
        }

        return DB::table('clinical_evolutions')
            ->where('created_by', $user->id)
            ->where('patient_id', $patientId)
            ->whereNull('deleted_at')
            ->exists();
    }
}
