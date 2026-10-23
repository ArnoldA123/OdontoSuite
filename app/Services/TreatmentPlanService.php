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
use Illuminate\Validation\ValidationException;

class TreatmentPlanService
{
    public function __construct(
        private readonly AiAnalysisCitationService $citations,
        private readonly AccountingLockService $accountingLocks,
        private readonly TreatmentPlanSessionService $sessions,
        private readonly TreatmentPlanVersionService $versions,
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

            $this->versions->initialize($plan->refresh()->load('items'), Auth::id());

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
            $items = null;

            if (array_key_exists('items', $data) && is_array($data['items'])) {
                $items = $data['items'];
                unset($data['items']);
            }

            $plan->update($data);

            if ($citedAnalysisIds !== null) {
                $this->citations->sync($plan, $citedAnalysisIds, Auth::user());
            }

            if ($items !== null) {
                // F4 (#284): lines are reconciled, never deleted and recreated.
                // The act that consumed a line must keep pointing at it.
                $this->reconcileItems($plan, $items);
            }

            $this->calculateTotals($id);

            $this->versions->record($plan, 'Plan actualizado', Auth::id());

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

        $item = $this->persistItem($planId, $itemData);

        $this->versions->record(TreatmentPlan::findOrFail($planId), 'Ítem agregado', Auth::id());

        return $item;
    }

    /**
     * The line itself: shared by the public mutation door and by the creation
     * paths (new plan, duplicate, item replacement), which are not mutations of
     * a historical row and therefore do not re-open the lock guard.
     */
    private function persistItem(int $planId, array $itemData): TreatmentPlanItem
    {
        $itemData = $this->prepareItemPayload($itemData, null, $planId);
        $itemData['treatment_plan_id'] = $planId;

        $item = TreatmentPlanItem::create($itemData);

        $this->calculateTotals($planId);
        $this->touchPlanActivity($planId);

        return $item;
    }

    /**
     * @param array<int, array<string, mixed>> $items
     */
    private function reconcileItems(TreatmentPlan $plan, array $items): void
    {
        $existing = $plan->items()->get()->keyBy('id');
        $kept = [];

        foreach ($items as $itemData) {
            $itemId = isset($itemData['id']) ? (int) $itemData['id'] : null;

            if ($itemId !== null) {
                $item = $existing->get($itemId);

                if (! $item) {
                    throw ValidationException::withMessages([
                        'items' => ["El ítem {$itemId} no pertenece a este plan."],
                    ]);
                }

                $item->fill($this->prepareItemPayload($itemData, $item, $plan->id));
                $item->save();
                $kept[] = $item->id;

                continue;
            }

            $created = $this->persistItem($plan->id, $itemData);
            $kept[] = $created->id;
        }

        foreach ($existing as $item) {
            if (in_array($item->id, $kept, true)) {
                continue;
            }

            $this->sessions->assertRemovable($item);
            $item->delete();
        }

        $this->calculateTotals($plan->id);
        $this->touchPlanActivity($plan->id);
    }

    /**
     * The status of a plan line is derived from the act that consumed it (F4).
     *
     * @param  array<string, mixed> $itemData
     * @return array<string, mixed>
     */
    private function prepareItemPayload(array $itemData, ?TreatmentPlanItem $item = null, ?int $planId = null): array
    {
        unset($itemData['id'], $itemData['treatment_plan_id']);

        if (array_key_exists('status', $itemData)) {
            $status = $itemData['status'];
            unset($itemData['status']);

            $unchanged = $item !== null && $status === $item->status;
            $allowedOnCreate = $item === null && in_array($status, ['pending', 'proposed'], true);

            if (! $unchanged && ! $allowedOnCreate) {
                throw ValidationException::withMessages([
                    'items' => ['El estado del ítem se deriva de la atención que lo consume; no se escribe desde el plan.'],
                ]);
            }

            if ($existingStatus = $item?->status) {
                $itemData['status'] = $existingStatus;
            }
        }

        if (! $item) {
            if (empty($itemData['procedure_name'])) {
                throw ValidationException::withMessages([
                    'items' => ['El nombre del procedimiento es obligatorio para un ítem nuevo.'],
                ]);
            }

            if (! isset($itemData['quantity'], $itemData['unit_cost'])) {
                throw ValidationException::withMessages([
                    'items' => ['Un ítem nuevo necesita cantidad y precio unitario.'],
                ]);
            }

            $itemData['status'] ??= 'pending';
        }

        $priority = $itemData['priority'] ?? null;
        $isOptional = array_key_exists('is_optional', $itemData)
            ? (bool) $itemData['is_optional']
            : null;

        if ($priority !== null && ! in_array($priority, ['urgent', 'elective', 'optional'], true)) {
            throw ValidationException::withMessages([
                'items.priority' => ['La prioridad debe ser urgente, electiva u opcional.'],
            ]);
        }

        if ($priority === null && $isOptional === true) {
            $priority = 'optional';
        }

        if ($priority === 'optional') {
            $isOptional = true;
        }

        if ($priority === null && $isOptional === false && $item?->priority === 'optional') {
            $priority = 'elective';
        }

        $itemData['priority'] = $priority ?? $item?->priority ?? 'elective';
        $itemData['is_optional'] = $isOptional ?? $item?->is_optional ?? false;

        if (! isset($itemData['sequence']) || $itemData['sequence'] === null) {
            $itemData['sequence'] = $item?->sequence
                ?? $this->nextSequence($planId, (int) ($itemData['phase_number'] ?? $item?->phase_number ?? 1));
        }

        $itemData['phase_number'] = $itemData['phase_number'] ?? $item?->phase_number ?? 1;

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
            $quantity = $itemData['quantity'] ?? $item?->quantity ?? 0;
            $unitCost = $itemData['unit_cost'] ?? $item?->unit_cost ?? 0;

            $itemData['total_cost'] = round((float) $quantity * (float) $unitCost, 2);
        }

        return $itemData;
    }

    private function nextSequence(?int $planId, int $phaseNumber): int
    {
        if ($planId === null) {
            return 1;
        }

        return (int) TreatmentPlanItem::where('treatment_plan_id', $planId)
            ->where('phase_number', $phaseNumber)
            ->max('sequence') + 1;
    }

    public function removeItem(int $itemId, ?string $lockOverrideReason = null): bool
    {
        $item = TreatmentPlanItem::findOrFail($itemId);
        $planId = $item->treatment_plan_id;

        $this->guardPlan($planId, 'remove_item', $lockOverrideReason);

        $this->sessions->assertRemovable($item);

        $item->delete();

        $this->calculateTotals($planId);
        $this->touchPlanActivity($planId);

        $this->versions->record(TreatmentPlan::findOrFail($planId), 'Ítem eliminado', Auth::id());

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
        $plan = TreatmentPlan::with('items')->findOrFail($id);

        $this->validateStatusTransition($plan->status, $status);
        $this->assertStatusContent($plan, $status);

        $plan->update([
            'status' => $status,
            'last_activity_at' => now(),
        ]);

        if ($status === 'approved') {
            $this->versions->accept($plan, Auth::id());
        }

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
            unset(
                $newPlanData['id'],
                $newPlanData['created_at'],
                $newPlanData['updated_at'],
                $newPlanData['current_version_id'],
                $newPlanData['active_version_id'],
            );

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
                $itemData['status'] = $item->status === 'proposed' ? 'proposed' : 'pending';
                // total_cost se recalcula en persistItem si hace falta
                unset($itemData['total_cost']);

                $this->persistItem($newPlan->id, $itemData);
            }

            $this->calculateTotals($newPlan->id);

            $this->versions->initialize($newPlan->refresh()->load('items'), Auth::id());

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
            throw ValidationException::withMessages([
                'status' => ["No se puede cambiar el estado de '{$currentStatus}' a '{$newStatus}'."],
            ]);
        }
    }

    /**
     * #288 — the state machine validates content, not only the transition. A
     * plan cannot be approved empty or completed with lines still pending.
     */
    private function assertStatusContent(TreatmentPlan $plan, string $status): void
    {
        if ($status === 'approved') {
            $live = $plan->items->where('status', '!=', 'cancelled');

            if ($live->isEmpty()) {
                throw ValidationException::withMessages([
                    'status' => ['No se puede aprobar un plan sin procedimientos vigentes.'],
                ]);
            }
        }

        if ($status === 'completed') {
            $live = $plan->items->where('status', '!=', 'cancelled');
            $open = $live->where('status', '!=', 'completed');

            if ($live->isEmpty()) {
                throw ValidationException::withMessages([
                    'status' => ['No se puede completar un plan sin procedimientos vigentes.'],
                ]);
            }

            if ($open->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'status' => [
                        'No se puede completar el plan: '.$open->count().' procedimiento(s) siguen sin ejecutar.',
                    ],
                ]);
            }
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
