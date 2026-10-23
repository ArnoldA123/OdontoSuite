<?php

namespace App\Services;

use App\Models\ClinicalEncounter;
use App\Models\TreatmentPlan;
use App\Models\TreatmentPlanItem;
use App\Models\TreatmentPlanItemConsumption;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * F4 (#284) — the plan is executed by acts, not by editing status fields.
 *
 * A session registers one consumption per plan item in the act, and the item's
 * status is derived from the sum: `completed` when it covers `quantity`,
 * `in_progress` when it covers part of it, `pending` when there is no
 * consumption yet. The plan API never writes those statuses directly.
 */
class TreatmentPlanSessionService
{
    /**
     * @param  array<int, array<string, mixed>>         $lines
     * @return array<int, TreatmentPlanItemConsumption>
     */
    public function consume(ClinicalEncounter $encounter, TreatmentPlan $plan, array $lines, ?int $userId): array
    {
        return DB::transaction(function () use ($encounter, $plan, $lines, $userId) {
            $plan->loadMissing('items');

            $consumed = [];

            foreach ($lines as $line) {
                $itemId = (int) ($line['id'] ?? 0);

                if ($itemId <= 0) {
                    throw ValidationException::withMessages([
                        'treatment_plan.items' => ['Cada sesión documenta el ítem del plan que ejecutó; falta el id del ítem.'],
                    ]);
                }

                $item = $plan->items->firstWhere('id', $itemId);

                if (! $item) {
                    throw ValidationException::withMessages([
                        'treatment_plan.items' => ["El ítem {$itemId} no pertenece al plan {$plan->plan_number}."],
                    ]);
                }

                if ($item->status === 'cancelled') {
                    throw ValidationException::withMessages([
                        'treatment_plan.items' => ["El ítem {$item->procedure_name} está cancelado y no puede consumirse."],
                    ]);
                }

                $quantity = (float) ($line['consumed_quantity'] ?? $line['quantity'] ?? 1);

                if ($quantity <= 0) {
                    throw ValidationException::withMessages([
                        'treatment_plan.items' => ["La cantidad consumida de {$item->procedure_name} debe ser mayor que cero."],
                    ]);
                }

                $pending = $item->pendingQuantity();

                if ($pending <= 0) {
                    continue;
                }

                $consumed[] = TreatmentPlanItemConsumption::updateOrCreate(
                    [
                        'clinical_encounter_id' => $encounter->id,
                        'treatment_plan_item_id' => $item->id,
                    ],
                    [
                        'quantity' => min($quantity, $pending),
                        'consumed_by' => $userId,
                        'consumed_at' => now(),
                        'notes' => $line['notes'] ?? null,
                    ],
                );

                $this->syncStatus($item);
            }

            return $consumed;
        });
    }

    /**
     * Derive the item status from the consumptions already registered.
     */
    public function syncStatus(TreatmentPlanItem $item): string
    {
        if (in_array($item->status, ['cancelled', 'proposed'], true)) {
            return $item->status;
        }

        $executed = $item->executedQuantity();

        $status = $executed <= 0
            ? 'pending'
            : ($executed >= (float) $item->quantity ? 'completed' : 'in_progress');

        if ($status !== $item->status) {
            $item->status = $status;
            $item->save();
        }

        return $status;
    }

    /**
     * A line with history cannot be deleted: the act that executed it would be
     * left pointing at nothing.
     */
    public function assertRemovable(TreatmentPlanItem $item): void
    {
        if ($item->consumptions()->exists()) {
            throw ValidationException::withMessages([
                'items' => ["El ítem {$item->procedure_name} ya fue ejecutado por una atención y no se puede eliminar; cancélelo."],
            ]);
        }
    }
}
