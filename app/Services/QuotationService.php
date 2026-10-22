<?php

namespace App\Services;

use App\Events\QuotationApproved;
use App\Events\QuotationCreated;
use App\Events\QuotationRejected;
use App\Events\QuotationUpdated;
use App\Models\Quotation;
use App\Models\QuotationApproval;
use App\Models\QuotationItem;
use App\Models\TreatmentPlan;
use App\Support\QuotationTax;
use App\Support\QuotationText;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class QuotationService
{
    /**
     * F3 (#283, S4/S5): the accounting lock. The default keeps the service
     * constructible without the container.
     */
    public function __construct(
        private readonly AccountingLockService $accountingLocks = new AccountingLockService,
    ) {}

    /**
     * Statuses that still owe a patient decision: only these may move to
     * `approved` or `rejected`.
     */
    private const DECISION_PENDING = ['sent', 'viewed'];

    /**
     * Generar presupuesto desde un plan de tratamiento.
     */
    public function generateQuotation(int $treatmentPlanId, array $data = []): Quotation
    {
        return DB::transaction(function () use ($treatmentPlanId, $data) {
            $plan = TreatmentPlan::with(['patient', 'items'])->findOrFail($treatmentPlanId);

            $quotationData = [
                'treatment_plan_id' => $treatmentPlanId,
                'patient_id' => $plan->patient_id,
                'created_by' => Auth::id(),
                'quotation_number' => $this->generateQuotationNumber(),
                'quotation_date' => now()->toDateString(),
                'valid_until' => now()->addDays(30)->toDateString(),
                'subtotal' => $plan->total_cost,
                'discount_percentage' => $data['discount_percentage'] ?? 0,
                'discount_amount' => $data['discount_amount'] ?? 0,
                'tax_percentage' => $data['tax_percentage'] ?? QuotationTax::defaultPercentage(),
                'tax_amount' => 0,
                'total_amount' => $plan->final_cost,
                'status' => 'draft',
                'terms_conditions' => $data['terms_conditions'] ?? '',
                'notes' => $data['notes'] ?? '',
                'payment_terms' => $data['payment_terms'] ?? [],
            ];

            // Calcular descuentos e impuestos
            $this->calculateAmounts($quotationData);

            $quotation = Quotation::create($quotationData);

            // Crear items del presupuesto
            foreach ($plan->items as $item) {
                QuotationItem::create([
                    'quotation_id' => $quotation->id,
                    'treatment_plan_item_id' => $item->id,
                    'item_name' => $item->procedure_name,
                    'item_description' => $item->procedure_description,
                    'specialty' => $item->specialty,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_cost,
                    'total_price' => $item->quantity * $item->unit_cost,
                ]);
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
     */
    public function createQuotation(array $data): Quotation
    {
        return DB::transaction(function () use ($data) {
            $data['quotation_number'] = $this->generateQuotationNumber();
            $data['created_by'] = Auth::id();
            $data['quotation_date'] = $data['quotation_date'] ?? now()->toDateString();
            $data['valid_until'] = $data['valid_until'] ?? now()->addDays(30)->toDateString();
            $data['status'] = 'draft';

            // Calcular totales
            $this->calculateAmounts($data);

            $quotation = Quotation::create($data);

            // Crear items si se proporcionan
            if (isset($data['items']) && is_array($data['items'])) {
                foreach ($data['items'] as $itemData) {
                    QuotationItem::create([
                        'quotation_id' => $quotation->id,
                        'item_name' => $itemData['description'] ?? $itemData['item_name'] ?? '',
                        'item_description' => $itemData['item_description'] ?? $itemData['description'] ?? '',
                        'specialty' => $itemData['specialty'] ?? null,
                        'quantity' => $itemData['quantity'],
                        'unit_price' => $itemData['unit_price'],
                        'total_price' => $itemData['quantity'] * $itemData['unit_price'],
                    ]);
                }
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

            // Calcular totales si se modificaron. `subtotal` entra en la lista
            // porque es el importe bruto del que se descompone todo lo demás.
            if (isset($data['subtotal'])
                || isset($data['discount_percentage'])
                || isset($data['discount_amount'])
                || isset($data['tax_percentage'])) {
                $this->calculateAmounts($data, $quotation);
            }

            $quotation->update($data);

            // Actualizar items si se proporcionan
            if (isset($data['items']) && is_array($data['items'])) {
                // Eliminar items existentes
                $quotation->items()->delete();

                // Crear nuevos items
                foreach ($data['items'] as $itemData) {
                    QuotationItem::create([
                        'quotation_id' => $quotation->id,
                        'item_name' => $itemData['description'] ?? $itemData['item_name'] ?? '',
                        'item_description' => $itemData['item_description'] ?? $itemData['description'] ?? '',
                        'specialty' => $itemData['specialty'] ?? null,
                        'quantity' => $itemData['quantity'],
                        'unit_price' => $itemData['unit_price'],
                        'total_price' => $itemData['quantity'] * $itemData['unit_price'],
                    ]);
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
        $gross = isset($data['subtotal'])
            ? (float) $data['subtotal']
            : (float) ($existing->total_amount ?? 0);

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
