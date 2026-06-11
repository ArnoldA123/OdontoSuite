<?php

namespace App\Services;

use App\Models\TreatmentPlan;
use App\Models\TreatmentPlanItem;
use App\Events\TreatmentPlanCreated;
use App\Events\TreatmentPlanUpdated;
use App\Events\TreatmentPlanDeleted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class TreatmentPlanService
{
    public function createPlan(array $data): TreatmentPlan
    {
        return DB::transaction(function () use ($data) {
            $data['plan_number'] = $this->generatePlanNumber();
            $data['created_by'] = Auth::id();
            $data['last_activity_at'] = now();

            $plan = TreatmentPlan::create($data);

            if (isset($data['items']) && is_array($data['items'])) {
                foreach ($data['items'] as $itemData) {
                    $this->addItem($plan->id, $itemData);
                }
            }

            $this->calculateTotals($plan->id);

            $plan->refresh();
            $plan->load(['items', 'patient', 'createdBy']);

            try {

                event(new TreatmentPlanCreated($plan));

            } catch (\Throwable $e) {

                Log::warning('No se pudo emitir evento Event', ['error' => $e->getMessage()]);

            }
            return $plan;
        });
    }

    public function updatePlan(int $id, array $data): TreatmentPlan
    {
        return DB::transaction(function () use ($id, $data) {
            $plan = TreatmentPlan::findOrFail($id);
            $data['last_activity_at'] = now();
            $plan->update($data);

            if (isset($data['items']) && is_array($data['items'])) {
                // Reemplazar items completos (estrategia simple y consistente con el front)
                $plan->items()->delete();
                foreach ($data['items'] as $itemData) {
                    $this->addItem($plan->id, $itemData);
                }
            }

            $this->calculateTotals($id);

            $plan->refresh();
            $plan->load(['items', 'patient', 'createdBy']);

            try {

                event(new TreatmentPlanUpdated($plan));

            } catch (\Throwable $e) {

                Log::warning('No se pudo emitir evento Event', ['error' => $e->getMessage()]);

            }
            return $plan;
        });
    }

    public function addItem(int $planId, array $itemData): TreatmentPlanItem
    {
        $itemData['treatment_plan_id'] = $planId;

        // Calcular total_cost del item (quantity * unit_cost) si no fue provisto
        if (! isset($itemData['total_cost']) || $itemData['total_cost'] === null) {
            $itemData['total_cost'] = round(
                (float) ($itemData['quantity'] ?? 0) * (float) ($itemData['unit_cost'] ?? 0),
                2
            );
        }

        $item = TreatmentPlanItem::create($itemData);

        $this->calculateTotals($planId);
        $this->touchPlanActivity($planId);

        return $item;
    }

    public function removeItem(int $itemId): bool
    {
        $item = TreatmentPlanItem::findOrFail($itemId);
        $planId = $item->treatment_plan_id;

        $item->delete();

        $this->calculateTotals($planId);
        $this->touchPlanActivity($planId);

        return true;
    }

    public function changeStatus(int $id, string $status): TreatmentPlan
    {
        $plan = TreatmentPlan::findOrFail($id);

        $this->validateStatusTransition($plan->status, $status);

        $plan->update([
            'status' => $status,
            'last_activity_at' => now(),
        ]);
        $plan->refresh();
        $plan->load(['items', 'patient', 'createdBy']);

        try {

            event(new TreatmentPlanUpdated($plan));

        } catch (\Throwable $e) {

            Log::warning('No se pudo emitir evento Event', ['error' => $e->getMessage()]);

        }
        return $plan;
    }

    public function duplicate(int $id): TreatmentPlan
    {
        return DB::transaction(function () use ($id) {
            $originalPlan = TreatmentPlan::with('items')->findOrFail($id);

            $newPlanData = $originalPlan->toArray();
            unset($newPlanData['id'], $newPlanData['created_at'], $newPlanData['updated_at']);

            $newPlanData['plan_number'] = $this->generatePlanNumber();
            $newPlanData['title'] = $originalPlan->title . ' (Copia)';
            $newPlanData['status'] = 'draft';
            $newPlanData['created_by'] = Auth::id();
            $newPlanData['last_activity_at'] = now();

            $newPlan = TreatmentPlan::create($newPlanData);

            foreach ($originalPlan->items as $item) {
                $itemData = $item->toArray();
                unset($itemData['id'], $itemData['treatment_plan_id'], $itemData['created_at'], $itemData['updated_at']);
                $itemData['treatment_plan_id'] = $newPlan->id;
                // total_cost se recalcula en addItem si hace falta
                unset($itemData['total_cost']);

                $this->addItem($newPlan->id, $itemData);
            }

            $this->calculateTotals($newPlan->id);

            $newPlan->refresh();
            $newPlan->load(['items', 'patient', 'createdBy']);

            try {

                event(new TreatmentPlanCreated($newPlan));

            } catch (\Throwable $e) {

                Log::warning('No se pudo emitir evento Event', ['error' => $e->getMessage()]);

            }
            return $newPlan;
        });
    }

    public function calculateTotals(int $planId): TreatmentPlan
    {
        $plan = TreatmentPlan::findOrFail($planId);

        $items = $plan->items;
        $subtotal = $items->sum(function ($item) {
            return (float) $item->quantity * (float) $item->unit_cost;
        });

        $discountAmount = (float) ($plan->discount_amount ?? 0);
        $finalCost = $subtotal - $discountAmount;

        $plan->update([
            'total_cost' => round($subtotal, 2),
            'final_cost' => round($finalCost, 2),
        ]);

        return $plan;
    }

    private function touchPlanActivity(int $planId): void
    {
        TreatmentPlan::where('id', $planId)->update(['last_activity_at' => now()]);
    }

    private function generatePlanNumber(): string
    {
        do {
            $number = 'TP-' . date('Y') . '-' . strtoupper(Str::random(6));
        } while (TreatmentPlan::where('plan_number', $number)->exists());

        return $number;
    }

    private function validateStatusTransition(string $currentStatus, string $newStatus): void
    {
        $validTransitions = [
            'draft' => ['proposed', 'cancelled'],
            'proposed' => ['approved', 'cancelled'],
            'approved' => ['in_progress', 'cancelled'],
            'in_progress' => ['completed', 'cancelled'],
            'completed' => [],
            'cancelled' => [],
        ];

        if (! in_array($newStatus, $validTransitions[$currentStatus] ?? [])) {
            throw new \InvalidArgumentException(
                "No se puede cambiar el estado de '{$currentStatus}' a '{$newStatus}'"
            );
        }
    }

    public function getPlans(array $filters = [])
    {
        $query = TreatmentPlan::with(['patient', 'createdBy']);

        if (isset($filters['patient_id'])) {
            $query->where('patient_id', $filters['patient_id']);
        }

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['created_by'])) {
            $query->where('created_by', $filters['created_by']);
        }

        if (isset($filters['date_from'])) {
            $query->whereDate('start_date', '>=', $filters['date_from']);
        }

        if (isset($filters['date_to'])) {
            $query->whereDate('start_date', '<=', $filters['date_to']);
        }

        if (isset($filters['patient_name']) && $filters['patient_name'] !== '') {
            $needle = '%' . $filters['patient_name'] . '%';
            $query->whereHas('patient', function ($q) use ($needle) {
                $q->where('first_name', 'like', $needle)
                    ->orWhere('last_name', 'like', $needle)
                    ->orWhere('document_number', 'like', $needle);
            });
        }

        if (isset($filters['branch_id'])) {
            $query->whereHas('patient', fn($q) => $q->where('branch_id', $filters['branch_id']));
        }

        return $query->orderByDesc('last_activity_at')
            ->orderByDesc('created_at')
            ->paginate(15);
    }
}
