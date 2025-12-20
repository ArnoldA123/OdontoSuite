<?php

namespace App\Services;

use App\Models\TreatmentPlan;
use App\Models\TreatmentPlanItem;
use App\Events\TreatmentPlanCreated;
use App\Events\TreatmentPlanUpdated;
use App\Events\TreatmentPlanDeleted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class TreatmentPlanService
{
    /**
     * Crear un nuevo plan de tratamiento
     */
    public function createPlan(array $data): TreatmentPlan
    {
        return DB::transaction(function () use ($data) {
            // Generar número de plan único
            $data['plan_number'] = $this->generatePlanNumber();
            $data['created_by'] = Auth::id();

            $plan = TreatmentPlan::create($data);

            // Si hay items, agregarlos
            if (isset($data['items']) && is_array($data['items'])) {
                foreach ($data['items'] as $itemData) {
                    $this->addItem($plan->id, $itemData);
                }
            }

            // Recalcular totales
            $this->calculateTotals($plan->id);

            $plan->refresh();
            $plan->load(['items', 'patient', 'createdBy']);

            // Emitir evento de WebSocket
            event(new TreatmentPlanCreated($plan));

            return $plan;
        });
    }

    /**
     * Actualizar un plan de tratamiento
     */
    public function updatePlan(int $id, array $data): TreatmentPlan
    {
        return DB::transaction(function () use ($id, $data) {
            $plan = TreatmentPlan::findOrFail($id);
            $plan->update($data);

            // Recalcular totales si se modificaron items
            if (isset($data['items'])) {
                $this->calculateTotals($id);
            }

            $plan->refresh();
            $plan->load(['items', 'patient', 'createdBy']);

            // Emitir evento de WebSocket
            event(new TreatmentPlanUpdated($plan));

            return $plan;
        });
    }

    /**
     * Agregar un item al plan
     */
    public function addItem(int $planId, array $itemData): TreatmentPlanItem
    {
        $itemData['treatment_plan_id'] = $planId;
        $item = TreatmentPlanItem::create($itemData);

        // Recalcular totales
        $this->calculateTotals($planId);

        return $item;
    }

    /**
     * Eliminar un item del plan
     */
    public function removeItem(int $itemId): bool
    {
        $item = TreatmentPlanItem::findOrFail($itemId);
        $planId = $item->treatment_plan_id;

        $item->delete();

        // Recalcular totales
        $this->calculateTotals($planId);

        return true;
    }

    /**
     * Cambiar el estado del plan
     */
    public function changeStatus(int $id, string $status): TreatmentPlan
    {
        $plan = TreatmentPlan::findOrFail($id);

        // Validar transición de estado
        $this->validateStatusTransition($plan->status, $status);

        $plan->update(['status' => $status]);
        $plan->refresh();
        $plan->load(['items', 'patient', 'createdBy']);

        // Emitir evento de WebSocket
        event(new TreatmentPlanUpdated($plan));

        return $plan;
    }

    /**
     * Duplicar un plan de tratamiento
     */
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

            $newPlan = TreatmentPlan::create($newPlanData);

            // Duplicar items
            foreach ($originalPlan->items as $item) {
                $itemData = $item->toArray();
                unset($itemData['id'], $itemData['treatment_plan_id'], $itemData['created_at'], $itemData['updated_at']);
                $itemData['treatment_plan_id'] = $newPlan->id;

                TreatmentPlanItem::create($itemData);
            }

            $this->calculateTotals($newPlan->id);

            return $newPlan->load(['items', 'patient', 'createdBy']);
        });
    }

    /**
     * Recalcular totales del plan
     */
    public function calculateTotals(int $planId): TreatmentPlan
    {
        $plan = TreatmentPlan::findOrFail($planId);

        $items = $plan->items;
        $subtotal = $items->sum(function ($item) {
            return $item->quantity * $item->unit_price;
        });

        $discountAmount = $plan->discount_amount ?? 0;
        $finalCost = $subtotal - $discountAmount;

        $plan->update([
            'total_cost' => $subtotal,
            'final_cost' => $finalCost
        ]);

        return $plan;
    }

    /**
     * Generar número de plan único
     */
    private function generatePlanNumber(): string
    {
        do {
            $number = 'TP-' . date('Y') . '-' . strtoupper(Str::random(6));
        } while (TreatmentPlan::where('plan_number', $number)->exists());

        return $number;
    }

    /**
     * Validar transición de estado
     */
    private function validateStatusTransition(string $currentStatus, string $newStatus): void
    {
        $validTransitions = [
            'draft' => ['proposed', 'cancelled'],
            'proposed' => ['approved', 'cancelled'],
            'approved' => ['in_progress', 'cancelled'],
            'in_progress' => ['completed', 'cancelled'],
            'completed' => [],
            'cancelled' => []
        ];

        if (!in_array($newStatus, $validTransitions[$currentStatus] ?? [])) {
            throw new \InvalidArgumentException(
                "No se puede cambiar el estado de '{$currentStatus}' a '{$newStatus}'"
            );
        }
    }

    /**
     * Obtener planes con filtros
     */
    public function getPlans(array $filters = [])
    {
        $query = TreatmentPlan::with(['patient', 'createdBy', 'items']);

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
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }

        if (isset($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        return $query->orderBy('created_at', 'desc')->paginate(15);
    }
}
