<?php

namespace App\Services;

use App\Models\TreatmentPlan;
use App\Models\TreatmentPlanVersion;
use Illuminate\Support\Facades\DB;

/**
 * #288 — plan versioning.
 *
 * A draft version is refreshed while the plan is still being prepared. Once a
 * version is accepted, any further change creates a new draft and leaves the
 * accepted snapshot untouched; the approved quotation points at the accepted
 * version, never at the moving head.
 */
class TreatmentPlanVersionService
{
    public function initialize(TreatmentPlan $plan, ?int $userId): TreatmentPlanVersion
    {
        $plan->loadMissing('items');

        $version = TreatmentPlanVersion::create([
            'treatment_plan_id' => $plan->id,
            'version_number' => 1,
            'status' => TreatmentPlanVersion::STATUS_DRAFT,
            'snapshot' => $this->snapshot($plan),
            'created_by' => $userId,
        ]);

        $plan->forceFill(['current_version_id' => $version->id])->save();

        return $version;
    }

    /**
     * Record the current definition. A draft head is refreshed; after an
     * accepted version, the change opens a new draft.
     */
    public function record(TreatmentPlan $plan, ?string $reason = null, ?int $userId = null): TreatmentPlanVersion
    {
        return DB::transaction(function () use ($plan, $reason, $userId) {
            $plan->refresh()->load(['items', 'currentVersion', 'activeVersion']);
            $current = $plan->currentVersion;

            if ($current === null) {
                return $this->initialize($plan, $userId);
            }

            if ($current->status === TreatmentPlanVersion::STATUS_DRAFT) {
                $snapshot = $this->snapshot($plan);

                if ($current->snapshot == $snapshot) {
                    return $current;
                }

                $current->update([
                    'snapshot' => $snapshot,
                    'reason' => $reason,
                ]);

                return $current->refresh();
            }

            $snapshot = $this->snapshot($plan);

            if ($current->snapshot == $snapshot) {
                return $current;
            }

            $next = (int) TreatmentPlanVersion::where('treatment_plan_id', $plan->id)
                ->max('version_number') + 1;

            $version = TreatmentPlanVersion::create([
                'treatment_plan_id' => $plan->id,
                'version_number' => $next,
                'status' => TreatmentPlanVersion::STATUS_DRAFT,
                'snapshot' => $snapshot,
                'reason' => $reason,
                'created_by' => $userId,
            ]);

            $plan->forceFill(['current_version_id' => $version->id])->save();

            return $version;
        });
    }

    public function accept(TreatmentPlan $plan, ?int $userId = null): TreatmentPlanVersion
    {
        return DB::transaction(function () use ($plan, $userId) {
            $plan->refresh()->load(['items', 'currentVersion', 'activeVersion']);

            $current = $plan->currentVersion ?? $this->initialize($plan, $userId);
            $current->refresh();

            if ($current->status === TreatmentPlanVersion::STATUS_ACCEPTED
                && (int) $plan->active_version_id === (int) $current->id) {
                return $current;
            }

            $previous = $plan->activeVersion;

            if ($previous && $previous->id !== $current->id
                && $previous->status === TreatmentPlanVersion::STATUS_ACCEPTED) {
                $previous->update(['status' => TreatmentPlanVersion::STATUS_SUPERSEDED]);
            }

            $current->update([
                'status' => TreatmentPlanVersion::STATUS_ACCEPTED,
                'accepted_at' => now(),
                'accepted_by' => $userId,
            ]);

            $plan->forceFill([
                'active_version_id' => $current->id,
                'current_version_id' => $current->id,
            ])->save();

            return $current->refresh();
        });
    }

    /**
     * The version a signed quotation binds to. If the plan never had an
     * accepted version, the current definition is accepted now, so the quote
     * freezes exactly what the patient signed.
     */
    public function acceptedVersion(TreatmentPlan $plan, ?int $userId = null): TreatmentPlanVersion
    {
        $plan->loadMissing('activeVersion');

        if ($plan->activeVersion !== null) {
            return $plan->activeVersion;
        }

        return $this->accept($plan, $userId);
    }

    /**
     * @return array<string, mixed>
     */
    public function snapshot(TreatmentPlan $plan): array
    {
        $plan->loadMissing(['items.alternatives']);

        return [
            'plan' => [
                'id' => $plan->id,
                'plan_number' => $plan->plan_number,
                'title' => $plan->title,
                'description' => $plan->description,
                'status' => $plan->status,
                'total_cost' => (float) $plan->total_cost,
                'discount_amount' => (float) $plan->discount_amount,
                'final_cost' => (float) $plan->final_cost,
                'estimated_duration_weeks' => $plan->estimated_duration_weeks,
                'start_date' => $plan->start_date?->toDateString(),
                'end_date' => $plan->end_date?->toDateString(),
                'notes' => $plan->notes,
                'patient_notes' => $plan->patient_notes,
                'phases' => $plan->phases,
                'requires_anesthesia' => (bool) $plan->requires_anesthesia,
                'is_urgent' => (bool) $plan->is_urgent,
            ],
            'items' => $plan->items->map(fn ($item) => [
                'id' => $item->id,
                'procedure_catalog_id' => $item->procedure_catalog_id,
                'dental_piece_id' => $item->dental_piece_id,
                'tooth_surface_id' => $item->tooth_surface_id,
                'site_code' => $item->site_code,
                'procedure_name' => $item->procedure_name,
                'procedure_description' => $item->procedure_description,
                'specialty' => $item->specialty,
                'quantity' => (int) $item->quantity,
                'unit_cost' => (float) $item->unit_cost,
                'total_cost' => (float) $item->total_cost,
                'estimated_duration_minutes' => $item->estimated_duration_minutes,
                'phase_number' => $item->phase_number,
                'sequence' => $item->sequence,
                'priority' => $item->priority,
                'materials_required' => $item->requiredMaterialsList(),
                'requires_anesthesia' => (bool) $item->requires_anesthesia,
                'is_optional' => (bool) $item->is_optional,
                'rejected_at' => $item->rejected_at?->toIso8601String(),
                'rejection_reason' => $item->rejection_reason,
                'notes' => $item->notes,
                'alternatives' => $item->alternatives->map(fn ($alternative) => [
                    'id' => $alternative->id,
                    'option_code' => $alternative->option_code,
                    'procedure_catalog_id' => $alternative->procedure_catalog_id,
                    'procedure_name' => $alternative->procedure_name,
                    'dental_piece_id' => $alternative->dental_piece_id,
                    'status' => $alternative->status,
                    'quantity' => (int) $alternative->quantity,
                    'unit_cost' => (float) $alternative->unit_cost,
                    'total_cost' => (float) $alternative->total_cost,
                    'rejected_at' => $alternative->rejected_at?->toIso8601String(),
                    'rejection_reason' => $alternative->rejection_reason,
                ])->values()->all(),
            ])->values()->all(),
        ];
    }
}
