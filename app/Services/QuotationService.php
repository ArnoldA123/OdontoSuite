<?php

namespace App\Services;

use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\QuotationApproval;
use App\Models\TreatmentPlan;
use App\Events\QuotationCreated;
use App\Events\QuotationUpdated;
use App\Events\QuotationApproved;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Barryvdh\DomPDF\Facade\Pdf;

class QuotationService
{
    /**
     * Generar presupuesto desde un plan de tratamiento
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
                'payment_terms' => $data['payment_terms'] ?? []
            ];

            // Calcular descuentos e impuestos
            $this->calculateAmounts($quotationData);

            $quotation = Quotation::create($quotationData);

            // Crear items del presupuesto
            foreach ($plan->items as $item) {
                QuotationItem::create([
                    'quotation_id' => $quotation->id,
                    'description' => $item->description,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'total_price' => $item->quantity * $item->unit_price
                ]);
            }

            $quotation->refresh();
            $quotation->load(['items', 'patient', 'treatmentPlan', 'createdBy']);

            // Emitir evento de WebSocket
            event(new QuotationCreated($quotation));

            return $quotation;
        });
    }

    /**
     * Crear presupuesto manual
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
                        'description' => $itemData['description'],
                        'quantity' => $itemData['quantity'],
                        'unit_price' => $itemData['unit_price'],
                        'total_price' => $itemData['quantity'] * $itemData['unit_price']
                    ]);
                }
            }

            $quotation->refresh();
            $quotation->load(['items', 'patient', 'treatmentPlan', 'createdBy']);

            // Emitir evento de WebSocket
            event(new QuotationCreated($quotation));

            return $quotation;
        });
    }

    /**
     * Actualizar presupuesto
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
                        'description' => $itemData['description'],
                        'quantity' => $itemData['quantity'],
                        'unit_price' => $itemData['unit_price'],
                        'total_price' => $itemData['quantity'] * $itemData['unit_price']
                    ]);
                }
            }

            $quotation->refresh();
            $quotation->load(['items', 'patient', 'treatmentPlan', 'createdBy']);

            // Emitir evento de WebSocket
            event(new QuotationUpdated($quotation));

            return $quotation;
        });
    }

    /**
     * Aprobar presupuesto
     */
    public function approveQuotation(int $id, array $approvalData): Quotation
    {
        return DB::transaction(function () use ($id, $approvalData) {
            $quotation = Quotation::findOrFail($id);

            $quotation->update(['status' => 'approved']);

            // Crear registro de aprobación
            QuotationApproval::create([
                'quotation_id' => $id,
                'approved_by' => Auth::id(),
                'approval_date' => now(),
                'approval_notes' => $approvalData['notes'] ?? '',
                'patient_signature' => $approvalData['patient_signature'] ?? null
            ]);

            $quotation->refresh();
            $quotation->load(['items', 'patient', 'treatmentPlan', 'createdBy', 'approvals']);

            // Emitir evento de WebSocket
            event(new QuotationApproved($quotation));
            event(new QuotationUpdated($quotation));

            return $quotation;
        });
    }

    /**
     * Rechazar presupuesto
     */
    public function rejectQuotation(int $id, string $reason): Quotation
    {
        $quotation = Quotation::findOrFail($id);

        $quotation->update([
            'status' => 'rejected',
            'notes' => $quotation->notes . "\n\nRechazado: " . $reason
        ]);

        return $quotation->load(['items', 'patient', 'treatmentPlan', 'createdBy']);
    }

    /**
     * Generar PDF del presupuesto
     */
    public function generatePDF(int $id): string
    {
        $quotation = Quotation::with(['items', 'patient', 'treatmentPlan', 'createdBy'])->findOrFail($id);

        $pdf = Pdf::loadView('reports.quotation', compact('quotation'));

        return $pdf->output();
    }

    /**
     * Calcular montos del presupuesto
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
     * Generar número de presupuesto único
     */
    private function generateQuotationNumber(): string
    {
        do {
            $number = 'PR-' . date('Y') . '-' . strtoupper(Str::random(6));
        } while (Quotation::where('quotation_number', $number)->exists());

        return $number;
    }

    /**
     * Obtener presupuestos con filtros
     */
    public function getQuotations(array $filters = [])
    {
        $query = Quotation::with(['patient', 'treatmentPlan', 'createdBy', 'items']);

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

        return $query->orderBy('quotation_date', 'desc')->paginate(15);
    }
}
