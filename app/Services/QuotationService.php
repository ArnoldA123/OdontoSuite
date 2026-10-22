<?php

namespace App\Services;

use App\Events\QuotationApproved;
use App\Events\QuotationCreated;
use App\Events\QuotationRejected;
use App\Events\QuotationUpdated;
use App\Models\ProcedureCatalog;
use App\Models\Quotation;
use App\Models\QuotationApproval;
use App\Models\QuotationItem;
use App\Models\TreatmentPlan;
use App\Support\ProcedureSiteRule;
use App\Support\QuotationTax;
use App\Support\QuotationText;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class QuotationService
{
    /**
     * F3 (#283, S4/S5): the accounting lock. #293 S4/S6: the tariff resolver is
     * the only reader of a price, so a line and the document that carries it can
     * never disagree. The default objects keep the service constructible without
     * the container.
     */
    public function __construct(
        private readonly AccountingLockService $accountingLocks = new AccountingLockService,
        private readonly ProcedurePriceResolver $prices = new ProcedurePriceResolver,
    ) {}

    /**
     * Statuses that still owe a patient decision: only these may move to
     * `approved` or `rejected`.
     */
    private const DECISION_PENDING = ['sent', 'viewed'];

    /**
     * Generar presupuesto desde un plan de tratamiento.
     *
     * #293 S4: the emission is the commercial act, so every line is frozen at
     * the tariff in force on `quotation_date` and the document's gross is the
     * sum of those lines — never the total the plan carried when it was written.
     */
    public function generateQuotation(int $treatmentPlanId, array $data = []): Quotation
    {
        return DB::transaction(function () use ($treatmentPlanId, $data) {
            $plan = TreatmentPlan::with(['patient', 'items.procedureCatalog'])->findOrFail($treatmentPlanId);

            $issueDate = $this->issueDate($data['quotation_date'] ?? null);
            $payerId = $data['payer_id'] ?? null;

            $lines = [];

            foreach ($plan->items as $item) {
                $site = $this->normalizedSite([
                    'dental_piece_id' => $item->dental_piece_id,
                    'tooth_surface_id' => $item->tooth_surface_id,
                    'site_code' => $item->site_code,
                ]);

                // #293 S2: a line written before the rule still has to name
                // the site of its procedure before it can become an estimate.
                ProcedureSiteRule::assertFor($item->procedureCatalog, $site);

                $frozen = $this->freezeLine(
                    ['quantity' => $item->quantity, 'unit_price' => $item->unit_cost],
                    $item->procedure_catalog_id,
                    $plan->branch_id,
                    $payerId,
                    $issueDate,
                );

                $lines[] = array_merge([
                    'treatment_plan_item_id' => $item->id,
                    'procedure_catalog_id' => $item->procedure_catalog_id,
                    'dental_piece_id' => $site['dental_piece_id'],
                    'tooth_surface_id' => $site['tooth_surface_id'],
                    'site_code' => $site['site_code'],
                    'item_name' => $item->procedure_name,
                    'item_description' => $item->procedure_description,
                    'specialty' => $item->specialty,
                    'quantity' => $item->quantity,
                ], $frozen);
            }

            $gross = $this->grossOf($lines);

            $quotationData = [
                'treatment_plan_id' => $treatmentPlanId,
                'patient_id' => $plan->patient_id,
                'payer_id' => $payerId,
                'created_by' => Auth::id(),
                'quotation_number' => $this->generateQuotationNumber(),
                'quotation_date' => $issueDate,
                'valid_until' => now()->addDays(30)->toDateString(),
                'subtotal' => $gross,
                'discount_percentage' => $data['discount_percentage'] ?? 0,
                'discount_amount' => $data['discount_amount'] ?? 0,
                'tax_percentage' => $data['tax_percentage'] ?? QuotationTax::defaultPercentage(),
                'tax_amount' => 0,
                'total_amount' => $gross,
                'status' => 'draft',
                'terms_conditions' => $data['terms_conditions'] ?? '',
                'notes' => $data['notes'] ?? '',
                'payment_terms' => $data['payment_terms'] ?? [],
            ];

            // Calcular descuentos e impuestos
            $this->calculateAmounts($quotationData);

            $quotation = Quotation::create($quotationData);

            foreach ($lines as $line) {
                QuotationItem::create($line + ['quotation_id' => $quotation->id]);
            }

            $quotation->refresh();
            $quotation->load(['items', 'patient', 'treatmentPlan', 'createdBy']);

            // Emitir evento de WebSocket
            try {
                event(new QuotationCreated($quotation));
            } catch (\Throwable $e) {
                Log::warning('No se pudo emitir evento Event', ['error' => $e->getMessage()]);
            }

            return $quotation;
        });
    }

    /**
     * Crear presupuesto manual.
     *
     * #293 S4: the lines are frozen before the document exists, because the
     * gross the IGV is decomposed from is their sum whenever lines are provided.
     */
    public function createQuotation(array $data): Quotation
    {
        return DB::transaction(function () use ($data) {
            $issueDate = $this->issueDate($data['quotation_date'] ?? null);
            $payerId = $data['payer_id'] ?? null;
            $branchId = $this->referenceBranch(
                isset($data['treatment_plan_id']) ? (int) $data['treatment_plan_id'] : null
            );

            $lines = [];

            if (isset($data['items']) && is_array($data['items'])) {
                foreach ($data['items'] as $itemData) {
                    $lines[] = $this->manualLine($itemData, $branchId, $payerId, $issueDate);
                }
            }

            $data['payer_id'] = $payerId;
            $data['quotation_number'] = $this->generateQuotationNumber();
            $data['created_by'] = Auth::id();
            $data['quotation_date'] = $issueDate;
            $data['valid_until'] = $data['valid_until'] ?? now()->addDays(30)->toDateString();
            $data['status'] = 'draft';

            // The server's number is the sum of the lines: a client cannot
            // declare a total the document does not describe.
            if ($lines !== []) {
                $data['subtotal'] = $this->grossOf($lines);
            }

            // Calcular totales
            $this->calculateAmounts($data);

            $quotation = Quotation::create($data);

            foreach ($lines as $line) {
                QuotationItem::create($line + ['quotation_id' => $quotation->id]);
            }

            $quotation->refresh();
            $quotation->load(['items', 'patient', 'treatmentPlan', 'createdBy']);

            // Emitir evento de WebSocket
            try {
                event(new QuotationCreated($quotation));
            } catch (\Throwable $e) {
                Log::warning('No se pudo emitir evento Event', ['error' => $e->getMessage()]);
            }

            return $quotation;
        });
    }

    /**
     * Statuses that still accept edits. An accepted, rejected or expired
     * quotation is a closed record: its amounts are immutable (#289, S2).
     */
    private const EDITABLE = ['draft', 'sent', 'viewed'];

    /**
     * Actualizar presupuesto.
     *
     * F3 (#283, S4/S5): editing a quotation dated inside the accounting lock is
     * refused; the guard runs before the transaction so the blocked attempt (or
     * the registered override) is not rolled back with the rejected write.
     *
     * #289, S2: the status guard runs before the lock, because an accepted
     * estimate is immutable whatever the period says.
     */
    public function updateQuotation(int $id, array $data): Quotation
    {
        $lockOverrideReason = $data['lock_override_reason'] ?? null;
        unset($data['lock_override_reason']);

        $quotation = Quotation::findOrFail($id);

        $this->assertEditable($quotation);

        $this->accountingLocks->guard(
            AccountingLockService::QUOTATIONS,
            $quotation->quotation_date ?? $quotation->created_at,
            $quotation->appointment?->branch_id ?? $quotation->patient?->branch_id,
            $quotation,
            'update',
            $lockOverrideReason,
        );

        return DB::transaction(function () use ($id, $data) {
            $quotation = Quotation::findOrFail($id);

            // #293 S4: an edit is priced on the delivery the estimate already
            // declared, not on today, so the delivered document keeps the
            // tariff in force on its own issue date.
            $issueDate = $quotation->quotation_date?->toDateString() ?? $this->issueDate(null);
            $payerId = $data['payer_id'] ?? $quotation->payer_id;
            $branchId = $this->referenceBranch($quotation->treatment_plan_id);

            $lines = null;

            if (isset($data['items']) && is_array($data['items'])) {
                $lines = [];

                foreach ($data['items'] as $itemData) {
                    $lines[] = $this->manualLine($itemData, $branchId, $payerId, $issueDate);
                }
            }

            // Calcular totales. #293 S4: a changed line changes the document,
            // so the gross is the frozen sum of the new lines even when the
            // payload sends no `subtotal`; a partial payload that touches no
            // line keeps the #289 rule (stored total + discount, applied once).
            if ($lines !== null) {
                $data['subtotal'] = $this->grossOf($lines);
                $this->calculateAmounts($data, $quotation);
            } elseif (isset($data['subtotal'])
                || isset($data['discount_percentage'])
                || isset($data['discount_amount'])
                || isset($data['tax_percentage'])) {
                $this->calculateAmounts($data, $quotation);
            }

            $data['payer_id'] = $payerId;

            $quotation->update($data);

            if ($lines !== null) {
                // Eliminar items existentes
                $quotation->items()->delete();

                // Crear nuevos items
                foreach ($lines as $line) {
                    QuotationItem::create($line + ['quotation_id' => $quotation->id]);
                }
            }

            $quotation->refresh();
            $quotation->load(['items', 'patient', 'treatmentPlan', 'createdBy']);

            // Emitir evento de WebSocket
            try {
                event(new QuotationUpdated($quotation));
            } catch (\Throwable $e) {
                Log::warning('No se pudo emitir evento Event', ['error' => $e->getMessage()]);
            }

            return $quotation;
        });
    }

    /**
     * Aprobar presupuesto.
     */
    public function approveQuotation(int $id, array $approvalData): Quotation
    {
        return DB::transaction(function () use ($id, $approvalData) {
            $quotation = Quotation::findOrFail($id);

            $this->assertDecisionPending($quotation, 'aprobar');

            $quotation->update(['status' => 'approved']);

            // One current approval per quotation: the transition guard above is
            // what prevents re-approvals from accumulating rows.
            QuotationApproval::updateOrCreate(
                ['quotation_id' => $id],
                [
                    'patient_id' => $quotation->patient_id,
                    'approval_status' => 'approved',
                    'approval_date' => now(),
                    'approval_method' => 'manual',
                    'patient_notes' => $approvalData['notes'] ?? null,
                    'signature_data' => $approvalData['patient_signature'] ?? null,
                ]
            );

            $quotation->refresh();
            $quotation->load(['items', 'patient', 'treatmentPlan', 'createdBy', 'approvals']);

            // Emitir evento de WebSocket
            try {
                event(new QuotationApproved($quotation));
            } catch (\Throwable $e) {
                Log::warning('No se pudo emitir evento Event', ['error' => $e->getMessage()]);
            }

            try {
                event(new QuotationUpdated($quotation));
            } catch (\Throwable $e) {
                Log::warning('No se pudo emitir evento Event', ['error' => $e->getMessage()]);
            }

            return $quotation;
        });
    }

    /**
     * Rechazar presupuesto.
     */
    public function rejectQuotation(int $id, string $reason): Quotation
    {
        return DB::transaction(function () use ($id, $reason) {
            $quotation = Quotation::findOrFail($id);

            $this->assertDecisionPending($quotation, 'rechazar');

            $quotation->update([
                'status' => 'rejected',
                'notes' => QuotationText::appendRejection($quotation->notes, $reason),
            ]);

            try {
                event(new QuotationRejected($quotation, $reason));
            } catch (\Throwable $e) {
                Log::warning('No se pudo emitir evento Event', ['error' => $e->getMessage()]);
            }

            return $quotation->load(['items', 'patient', 'treatmentPlan', 'createdBy']);
        });
    }

    /**
     * Generar PDF del presupuesto.
     */
    public function generatePDF(int $id): string
    {
        $quotation = Quotation::with(['items', 'patient', 'treatmentPlan', 'createdBy'])->findOrFail($id);

        $this->markViewed($quotation);

        $pdf = Pdf::loadView('reports.quotation', compact('quotation'));

        return $pdf->output();
    }

    /**
     * The first export is what exposes the quotation to the patient, so it is
     * the writer of the `viewed` state. Idempotent: only `sent` transitions.
     */
    private function markViewed(Quotation $quotation): void
    {
        if ($quotation->status !== 'sent') {
            return;
        }

        $quotation->update(['status' => 'viewed']);

        try {
            event(new QuotationUpdated($quotation));
        } catch (\Throwable $e) {
            Log::warning('No se pudo emitir evento Event', ['error' => $e->getMessage()]);
        }
    }

    private function assertDecisionPending(Quotation $quotation, string $action): void
    {
        if (in_array($quotation->status, self::DECISION_PENDING, true)) {
            return;
        }

        throw ValidationException::withMessages([
            'status' => sprintf(
                'Solo se puede %s un presupuesto enviado o visto. Estado actual: %s.',
                $action,
                $quotation->status
            ),
        ]);
    }

    /**
     * #289, S2: an accepted, rejected or expired quotation is a closed record.
     */
    private function assertEditable(Quotation $quotation): void
    {
        if (in_array($quotation->status, self::EDITABLE, true)) {
            return;
        }

        throw ValidationException::withMessages([
            'status' => sprintf(
                'Solo se puede modificar un presupuesto en borrador, enviado o visto. Estado actual: %s.',
                $quotation->status
            ),
        ]);
    }

    /**
     * Normalize the site columns a line carries into the shape the rule reads.
     *
     * @param  array<string, mixed>                                                     $itemData
     * @return array{dental_piece_id: ?int, tooth_surface_id: ?int, site_code: ?string}
     */
    private function normalizedSite(array $itemData): array
    {
        return ProcedureSiteRule::normalize([
            'dental_piece_id' => $itemData['dental_piece_id'] ?? null,
            'tooth_surface_id' => $itemData['tooth_surface_id'] ?? null,
            'site_code' => $itemData['site_code'] ?? null,
        ]);
    }

    /**
     * The row of a manual line, already frozen at the issue date.
     *
     * @param  array<string, mixed> $itemData
     * @return array<string, mixed>
     */
    private function manualLine(array $itemData, ?int $branchId, ?int $payerId, string $issueDate): array
    {
        $procedureId = isset($itemData['procedure_catalog_id']) && $itemData['procedure_catalog_id'] !== ''
            ? (int) $itemData['procedure_catalog_id']
            : null;
        $site = $this->normalizedSite($itemData);

        // #293 S2: a manual line that names a procedure carries the site the
        // procedure applies to, or it is not written.
        ProcedureSiteRule::assertFor(
            $procedureId !== null ? ProcedureCatalog::find($procedureId) : null,
            $site,
        );

        return array_merge([
            'procedure_catalog_id' => $procedureId,
            'dental_piece_id' => $site['dental_piece_id'],
            'tooth_surface_id' => $site['tooth_surface_id'],
            'site_code' => $site['site_code'],
            'item_name' => $itemData['description'] ?? $itemData['item_name'] ?? '',
            'item_description' => $itemData['item_description'] ?? $itemData['description'] ?? '',
            'specialty' => $itemData['specialty'] ?? null,
            'quantity' => $itemData['quantity'],
        ], $this->freezeLine($itemData, $procedureId, $branchId, $payerId, $issueDate));
    }

    /**
     * #293 S4/S6 — freeze one line at the tariff in force on the issue date.
     *
     * A line without a catalog procedure is free text: it keeps the price it
     * carries and records no evidence, because no tariff produced it. A line
     * with one ignores whatever price the client sent and stores what the
     * cascade resolved, together with the row and the window that applied.
     *
     * @param  array<string, mixed>                                                                                                                        $itemData
     * @return array{unit_price: float, total_price: float, price_source: ?string, price_row_id: ?int, price_valid_from: ?string, price_valid_to: ?string}
     */
    private function freezeLine(
        array $itemData,
        ?int $procedureCatalogId,
        ?int $branchId,
        ?int $payerId,
        string $issueDate,
    ): array {
        $quantity = (float) ($itemData['quantity'] ?? 0);
        $procedure = $procedureCatalogId !== null ? ProcedureCatalog::find($procedureCatalogId) : null;

        if ($procedure === null) {
            $unitPrice = round((float) ($itemData['unit_price'] ?? 0), 2);

            return [
                'unit_price' => $unitPrice,
                'total_price' => round($quantity * $unitPrice, 2),
                'price_source' => null,
                'price_row_id' => null,
                'price_valid_from' => null,
                'price_valid_to' => null,
            ];
        }

        $resolved = $this->prices->resolve(
            $procedure,
            $branchId,
            Carbon::parse($issueDate),
            $payerId,
        );

        return [
            'unit_price' => $resolved['price'],
            'total_price' => round($quantity * $resolved['price'], 2),
            'price_source' => $resolved['source'],
            'price_row_id' => $resolved['price_id'],
            'price_valid_from' => $resolved['valid_from'],
            'price_valid_to' => $resolved['valid_to'],
        ];
    }

    /**
     * The day the tariff is resolved on, as `Y-m-d`.
     */
    private function issueDate(mixed $value): string
    {
        return Carbon::parse($value ?? now())->toDateString();
    }

    /**
     * The sede a line is priced against: the plan's own, or the caller's when
     * the estimate does not come from a plan.
     */
    private function referenceBranch(?int $treatmentPlanId): ?int
    {
        if ($treatmentPlanId === null) {
            return $this->prices->currentBranchId();
        }

        $branchId = TreatmentPlan::query()->whereKey($treatmentPlanId)->value('branch_id');

        return $branchId !== null ? (int) $branchId : null;
    }

    /**
     * @param list<array<string, mixed>> $lines
     */
    private function grossOf(array $lines): float
    {
        return round(array_sum(array_column($lines, 'total_price')), 2);
    }

    /**
     * Calcular los montos del presupuesto.
     *
     * #289, S1: `total_amount` es el precio que paga el paciente y el IGV se
     * descompone de él (`App\Support\QuotationTax`). En una actualización solo
     * cambian los campos que llegan: el bruto y la tasa caen a lo que el
     * presupuesto ya tiene, así que un payload parcial ya no puede poner los
     * importes en cero.
     */
    private function calculateAmounts(array &$data, ?Quotation $existing = null): void
    {
        // The stored total is already net of the discount, so the gross it came
        // from is `total + discount`: a partial payload must not discount twice.
        $gross = isset($data['subtotal'])
            ? (float) $data['subtotal']
            : (float) ($existing->total_amount ?? 0) + (float) ($existing->discount_amount ?? 0);

        $discountPercentage = isset($data['discount_percentage'])
            ? (float) $data['discount_percentage']
            : (float) ($existing->discount_percentage ?? 0);

        $discountAmount = isset($data['discount_amount'])
            ? (float) $data['discount_amount']
            : (float) ($existing->discount_amount ?? 0);

        if ($discountPercentage > 0) {
            $discountAmount = round($gross * ($discountPercentage / 100), 2);
        }

        $taxPercentage = isset($data['tax_percentage'])
            ? (float) $data['tax_percentage']
            : ($existing !== null
                ? (float) $existing->tax_percentage
                : QuotationTax::defaultPercentage());

        $breakdown = QuotationTax::breakDown($gross, $discountAmount, $taxPercentage);

        $data['discount_percentage'] = $discountPercentage;
        $data['discount_amount'] = $discountAmount;
        $data['tax_percentage'] = $taxPercentage;
        $data['tax_amount'] = $breakdown['tax_amount'];
        $data['subtotal'] = $breakdown['subtotal'];
        $data['total_amount'] = $breakdown['total_amount'];
    }

    /**
     * Generar número de presupuesto único.
     */
    private function generateQuotationNumber(): string
    {
        do {
            $number = 'PR-'.date('Y').'-'.strtoupper(Str::random(6));
        } while (Quotation::where('quotation_number', $number)->exists());

        return $number;
    }

    /**
     * Obtener presupuestos con filtros.
     */
    public function getQuotations(array $filters = [])
    {
        $query = Quotation::with(['patient', 'treatmentPlan', 'createdBy', 'items']);

        if (! empty($filters['q'])) {
            $term = trim((string) $filters['q']);

            if ($term !== '') {
                $like = '%'.$term.'%';

                $query->where(function ($query) use ($like) {
                    $query->where('quotation_number', 'like', $like)
                        ->orWhereHas('patient', function ($patient) use ($like) {
                            $patient->where('first_name', 'like', $like)
                                ->orWhere('last_name', 'like', $like)
                                ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", [$like])
                                ->orWhere('document_number', 'like', $like);
                        });
                });
            }
        }

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
            $query->whereDate('quotation_date', '>=', $filters['date_from']);
        }

        if (isset($filters['date_to'])) {
            $query->whereDate('quotation_date', '<=', $filters['date_to']);
        }

        if (isset($filters['expired'])) {
            // The state is authoritative; `valid_until` only drives the
            // in-page expiry badge and the `quotations:expire` sweep.
            $query->where('status', filter_var($filters['expired'], FILTER_VALIDATE_BOOLEAN) ? '=' : '!=', 'expired');
        }

        if (isset($filters['branch_id'])) {
            $query->whereHas('patient', fn ($q) => $q->where('branch_id', $filters['branch_id']));
        }

        return $query->orderBy('quotation_date', 'desc')->paginate(15);
    }

    /**
     * Aggregated counters for the KPI strip. Every status of the DB enum is
     * present so the client never has to invent a zero.
     *
     * @return array<string, mixed>
     */
    public function summary(): array
    {
        $statuses = ['draft', 'sent', 'viewed', 'approved', 'rejected', 'expired'];

        $byStatus = [];
        foreach ($statuses as $status) {
            $byStatus[$status] = ['count' => 0, 'amount' => 0.0];
        }

        $rows = Quotation::query()
            ->selectRaw('status, COUNT(*) as total, COALESCE(SUM(total_amount), 0) as amount')
            ->groupBy('status')
            ->get();

        foreach ($rows as $row) {
            $byStatus[$row->status] = [
                'count' => (int) $row->total,
                'amount' => (float) $row->amount,
            ];
        }

        $today = now()->toDateString();
        $horizon = now()->addDays(7)->toDateString();

        $expiringSoon = Quotation::query()
            ->whereIn('status', ['sent', 'viewed'])
            ->whereBetween('valid_until', [$today, $horizon])
            ->count();

        $overdue = Quotation::query()
            ->whereIn('status', ['sent', 'viewed'])
            ->where('valid_until', '<', $today)
            ->count();

        $total = array_sum(array_column($byStatus, 'count'));

        return [
            'total' => $total,
            'by_status' => $byStatus,
            'pending' => $byStatus['sent']['count'] + $byStatus['viewed']['count'],
            'approved_total' => $byStatus['approved']['amount'],
            'expiring_soon' => $expiringSoon,
            'overdue' => $overdue,
        ];
    }
}
