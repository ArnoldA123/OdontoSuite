<?php

namespace App\Policies;

use App\Authorization\Permissions;
use App\Models\AiImageAnalysis;
use App\Models\User;
use App\Support\ClinicalAccess;

/**
 * Authorization for the AI analysis surface (issue #214).
 *
 * Reading, reviewing and annulling all share one predicate: the analysis is
 * reachable when the user is an administrator, asked for it, reviewed it, or
 * reaches the patient through the clinical relationship defined in
 * `ClinicalAccess`. Listing a patient's whole history (`byPatient`) and
 * creating new analyses require the patient relationship alone, because those
 * endpoints are not bound to a single analysis.
 */
class AiImageAnalysisPolicy
{
    /**
     * Who may list, read stats and read the pending queue. The catalog is the
     * only role matrix (#296, spec S2): repeating a role array here let the
     * policy and the routes disagree about the laboratory desktop.
     */
    public function viewAny(User $user): bool
    {
        return Permissions::roleHas($user->role, Permissions::VIEW_AI_ANALYSIS);
    }

    public function view(User $user, AiImageAnalysis $analysis): bool
    {
        return $this->reachable($user, $analysis);
    }

    public function update(User $user, AiImageAnalysis $analysis): bool
    {
        return $this->reachable($user, $analysis);
    }

    public function void(User $user, AiImageAnalysis $analysis): bool
    {
        return $this->reachable($user, $analysis);
    }

    /**
     * Gate ability for patient-scoped endpoints (`byPatient`, `analyze`,
     * `upload-and-analyze`): they list or create, so a requested analysis is
     * not enough to reach the patient's whole history.
     */
    public function accessPatient(User $user, int $patientId): bool
    {
        return ClinicalAccess::canReachPatient($user, $patientId);
    }

    private function reachable(User $user, AiImageAnalysis $analysis): bool
    {
        if ($user->role === 'administrador') {
            return true;
        }

        if ((int) $analysis->requested_by === (int) $user->id) {
            return true;
        }

        if ((int) $analysis->reviewed_by === (int) $user->id) {
            return true;
        }

        return ClinicalAccess::canReachPatient($user, (int) $analysis->patient_id);
    }
}
