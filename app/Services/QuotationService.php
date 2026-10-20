<?php

namespace App\Services;

use App\Events\QuotationApproved;
use App\Events\QuotationCreated;
use App\Events\QuotationUpdated;
use App\Models\Quotation;
use App\Models\QuotationApproval;
use App\Models\QuotationItem;
use App\Models\TreatmentPlan;
use App\Support\QuotationText;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class QuotationService
{
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
                'tax_percentage' => $data['tax_percentage'] ?? 0,
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
     * Actualizar presupuesto.
     */
    public function updateQuotation(int $id, array $data): Quotation
    {
        return DB::transaction(function () use ($id, $data) {
            $quotation = Quotation::findOrFail($id);

            // Calcular totales si se modificaron
            if (isset($data['discount_percentage']) || isset($data['discount_amount']) || isset($data['tax_percentage'])) {
                $this->calculateAmounts($data);
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

            $quotation->update(['status' => 'approved']);

            // Crear registro de aprobación
            QuotationApproval::create([
                'quotation_id' => $id,
                'patient_id' => $quotation->patient_id,
                'approval_status' => 'approved',
                'approval_date' => now(),
                'approval_method' => 'manual',
                'patient_notes' => $approvalData['notes'] ?? null,
                'signature_data' => $approvalData['patient_signature'] ?? null,
            ]);

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
        $quotation = Quotation::findOrFail($id);

        $quotation->update([
            'status' => 'rejected',
            'notes' => QuotationText::appendRejection($quotation->notes, $reason),
        ]);

        return $quotation->load(['items', 'patient', 'treatmentPlan', 'createdBy']);
    }

    /**
     * Generar PDF del presupuesto.
     */
    public function generatePDF(int $id): string
    {
        $quotation = Quotation::with(['items', 'patient', 'treatmentPlan', 'createdBy'])->findOrFail($id);

        $pdf = Pdf::loadView('reports.quotation', compact('quotation'));

        return $pdf->output();
    }

    /**
     * Calcular montos del presupuesto.
     */
    private function calculateAmounts(array &$data): void
    {
        $subtotal = $data['subtotal'] ?? 0;
        $discountPercentage = $data['discount_percentage'] ?? 0;
        $discountAmount = $data['discount_amount'] ?? 0;
        $taxPercentage = $data['tax_percentage'] ?? 0;

        // Calcular descuento
        if ($discountPercentage > 0) {
            $discountAmount = $subtotal * ($discountPercentage / 100);
        }

        $data['discount_amount'] = $discountAmount;

        // Calcular subtotal después de descuento
        $subtotalAfterDiscount = $subtotal - $discountAmount;

        // Calcular impuestos
        $taxAmount = $subtotalAfterDiscount * ($taxPercentage / 100);
        $data['tax_amount'] = $taxAmount;

        // Calcular total
        $data['total_amount'] = $subtotalAfterDiscount + $taxAmount;
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
            if ($filters['expired']) {
                $query->where('valid_until', '<', now()->toDateString());
            } else {
                $query->where('valid_until', '>=', now()->toDateString());
            }
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
