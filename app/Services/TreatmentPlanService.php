<?php

namespace App\Services;

use App\Events\TreatmentPlanCreated;
use App\Events\TreatmentPlanUpdated;
use App\Models\ProcedureCatalog;
use App\Models\TreatmentPlan;
use App\Models\TreatmentPlanItem;
use App\Support\ProcedureSiteRule;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class TreatmentPlanService
{
    public function __construct(
        private readonly AiAnalysisCitationService $citations,
        private readonly AccountingLockService $accountingLocks,
    ) {}

    public function createPlan(array $data): TreatmentPlan
    {
        return DB::transaction(function () use ($data) {
            // #296: a cited analysis must be reviewed and belong to the same
            // patient; the request is refused before the plan is written.
            $citedAnalysisIds = $data['cited_analysis_ids'] ?? [];
            unset($data['cited_analysis_ids']);

            $this->citations->assertCitable($citedAnalysisIds, (int) $data['patient_id'], Auth::user());

            $data['plan_number'] = $this->generatePlanNumber();
            $data['created_by'] = Auth::id();
            $data['last_activity_at'] = now();

            $plan = TreatmentPlan::create($data);

            if ($citedAnalysisIds !== []) {
                $this->citations->sync($plan, $citedAnalysisIds, Auth::user());
            }

            if (isset($data['items']) && is_array($data['items'])) {
                foreach ($data['items'] as $itemData) {
                    $this->persistItem($plan->id, $itemData);
                }
            }

            $this->calculateTotals($plan->id);

            $plan->refresh();
            $plan->load([
                'items',
                'patient',
                'createdBy',
                'citedAnalyses' => fn ($query) => $query->citationSummary(),
            ]);

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
        $lockOverrideReason = $data['lock_override_reason'] ?? null;
        unset($data['lock_override_reason']);

        $this->guardPlan($id, 'update', $lockOverrideReason);

        return DB::transaction(function () use ($id, $data) {
            $plan = TreatmentPlan::findOrFail($id);

            $citedAnalysisIds = $data['cited_analysis_ids'] ?? null;
            unset($data['cited_analysis_ids']);

            if ($citedAnalysisIds !== null) {
                $this->citations->assertCitable($citedAnalysisIds, (int) $plan->patient_id, Auth::user());
            }

            $data['last_activity_at'] = now();
            $plan->update($data);

            if ($citedAnalysisIds !== null) {
                $this->citations->sync($plan, $citedAnalysisIds, Auth::user());
            }

            if (isset($data['items']) && is_array($data['items'])) {
                // Reemplazar items completos (estrategia simple y consistente con el front)
                $plan->items()->delete();
                foreach ($data['items'] as $itemData) {
                    $this->persistItem($plan->id, $itemData);
                }
            }

            $this->calculateTotals($id);

            $plan->refresh();
            $plan->load([
                'items',
                'patient',
                'createdBy',
                'citedAnalyses' => fn ($query) => $query->citationSummary(),
            ]);

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
        $lockOverrideReason = $itemData['lock_override_reason'] ?? null;
        unset($itemData['lock_override_reason']);

        $this->guardPlan($planId, 'add_item', $lockOverrideReason);

        return $this->persistItem($planId, $itemData);
    }

    /**
     * The line itself: shared by the public mutation door and by the creation
     * paths (new plan, duplicate, item replacement), which are not mutations of
     * a historical row and therefore do not re-open the lock guard.
     */
    private function persistItem(int $planId, array $itemData): TreatmentPlanItem
    {
        $itemData['treatment_plan_id'] = $planId;

        // #293 S2: a line that names a catalog procedure carries the site the
        // procedure applies to. Free-text lines keep their payload untouched.
        if (! empty($itemData['procedure_catalog_id'])) {
            $procedure = ProcedureCatalog::find($itemData['procedure_catalog_id']);
            $site = ProcedureSiteRule::normalize([
                'dental_piece_id' => $itemData['dental_piece_id'] ?? null,
                'tooth_surface_id' => $itemData['tooth_surface_id'] ?? null,
                'site_code' => $itemData['site_code'] ?? null,
            ]);

            ProcedureSiteRule::assertFor($procedure, $site);

            $itemData = array_merge($itemData, $site);
        }

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

    public function removeItem(int $itemId, ?string $lockOverrideReason = null): bool
    {
        $item = TreatmentPlanItem::findOrFail($itemId);
        $planId = $item->treatment_plan_id;

        $this->guardPlan($planId, 'remove_item', $lockOverrideReason);

        $item->delete();

        $this->calculateTotals($planId);
        $this->touchPlanActivity($planId);

        return true;
    }

    /**
     * F3 (#283, S4/S5) — freeze a money mutation of an existing plan.
     *
     * Runs before the caller opens its transaction: the blocked attempt must
     * survive in `audit_logs`, and a rejected write must not roll it back.
     */
    private function guardPlan(int $planId, string $action, ?string $overrideReason = null): TreatmentPlan
    {
        $plan = TreatmentPlan::findOrFail($planId);

        $this->accountingLocks->guard(
            AccountingLockService::TREATMENT_PLANS,
            $plan->start_date ?? $plan->created_at,
            $plan->branch_id,
            $plan,
            $action,
            $overrideReason,
        );

        return $plan;
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
            $newPlanData['title'] = $originalPlan->title.' (Copia)';
            $newPlanData['status'] = 'draft';
            $newPlanData['created_by'] = Auth::id();
            $newPlanData['last_activity_at'] = now();

            $newPlan = TreatmentPlan::create($newPlanData);

            foreach ($originalPlan->items as $item) {
                $itemData = $item->toArray();
                unset($itemData['id'], $itemData['treatment_plan_id'], $itemData['created_at'], $itemData['updated_at']);
                $itemData['treatment_plan_id'] = $newPlan->id;
                // total_cost se recalcula en persistItem si hace falta
                unset($itemData['total_cost']);

                $this->persistItem($newPlan->id, $itemData);
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
            $number = 'TP-'.date('Y').'-'.strtoupper(Str::random(6));
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

    /**
     * Resumen exacto del módulo para la tira de KPIs.
     * No depende de la página visible ni de los filtros activos.
     *
     * @return array<string, mixed>
     */
    public function summary(): array
    {
        $statuses = ['draft', 'proposed', 'approved', 'in_progress', 'completed', 'cancelled'];

        $countsByStatus = TreatmentPlan::query()
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $byStatus = [];
        foreach ($statuses as $status) {
            $byStatus[$status] = (int) ($countsByStatus[$status] ?? 0);
        }

        $activeStatuses = ['draft', 'proposed', 'approved', 'in_progress'];

        return [
            'total' => TreatmentPlan::query()->count(),
            'by_status' => $byStatus,
            'active' => TreatmentPlan::query()->whereIn('status', $activeStatuses)->count(),
            'overdue' => TreatmentPlan::query()->overdue()->count(),
            'pending_approval' => $byStatus['proposed'],
            'pending_cost' => round(
                (float) TreatmentPlan::query()->whereIn('status', $activeStatuses)->sum('final_cost'),
                2
            ),
        ];
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

        if (! empty($filters['statuses'])) {
            $rawStatuses = is_array($filters['statuses'])
                ? $filters['statuses']
                : explode(',', (string) $filters['statuses']);

            $statuses = array_values(array_intersect(
                array_map('trim', $rawStatuses),
                ['draft', 'proposed', 'approved', 'in_progress', 'completed', 'cancelled']
            ));

            // A present-but-unknown list filters to nothing instead of silently
            // falling back to every plan: an applied filter must never widen.
            $query->whereIn('status', $statuses);
        }

        if (filter_var($filters['overdue'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            $query->overdue();
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
            $needle = '%'.$filters['patient_name'].'%';
            $query->whereHas('patient', function ($q) use ($needle) {
                $q->where('first_name', 'like', $needle)
                    ->orWhere('last_name', 'like', $needle)
                    ->orWhere('document_number', 'like', $needle);
            });
        }

        if (isset($filters['branch_id'])) {
            $query->whereHas('patient', fn ($q) => $q->where('branch_id', $filters['branch_id']));
        }

        return $query->orderByDesc('last_activity_at')
            ->orderByDesc('created_at')
            ->paginate(15);
    }
}
