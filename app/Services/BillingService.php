<?php

namespace App\Services;

use App\Events\QuotationCreated;
use App\Models\Appointment;
use App\Models\Quotation;
use App\Models\Transaction;
use App\Models\TreatmentPlan;
use App\Models\TreatmentPlanItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BillingService
{
    public const QUOTATION_STATUS_SENT = 'sent';

    /**
     * Citas completadas listas para facturar.
     * Una cita está "ready to bill" si:
     *   - status = completed
     *   - final_amount > 0
     *   - el monto pagado (suma de transactions tipo=payment status=completed) es menor al final_amount
     *
     * @return LengthAwarePaginator
     */
    public function readyToBill(array $filters = []): LengthAwarePaginator
    {
        $query = Appointment::query()
            ->with(['patient', 'user', 'appointmentType', 'treatmentPlan'])
            ->where('status', 'completed')
            ->whereNotNull('final_amount')
            ->where('final_amount', '>', 0)
            // Citas sin pago completo: lo que se haya pagado (sum de payments completed) < final_amount
            ->where(function (Builder $outer) {
                $outer->whereDoesntHave('transactions', function (Builder $q) {
                    $q->where('type', 'payment')->where('status', 'completed');
                })
                ->orWhereRaw('final_amount > (
                    SELECT COALESCE(SUM(amount), 0)
                    FROM transactions
                    WHERE transactions.appointment_id = appointments.id
                      AND transactions.type = "payment"
                      AND transactions.status = "completed"
                )');
            });

        if (!empty($filters['patient_id'])) {
            $query->where('patient_id', $filters['patient_id']);
        }
        if (!empty($filters['from'])) {
            $query->whereDate('completed_at', '>=', $filters['from']);
        }
        if (!empty($filters['to'])) {
            $query->whereDate('completed_at', '<=', $filters['to']);
        }

        $perPage = (int) ($filters['per_page'] ?? 20);
        $perPage = max(1, min($perPage, 100));

        return $query->orderByDesc('completed_at')->paginate($perPage);
    }

    /**
     * Preview del desglose económico de una cita cerrada.
     *
     * @return array<string, mixed>
     */
    public function paymentPreview(Appointment $appointment): array
    {
        $appointment->loadMissing(['treatmentPlan.items', 'transactions.paymentMethod', 'quotations']);

        $items = [];
        if ($appointment->treatmentPlan) {
            $items = $appointment->treatmentPlan->items
                ->where('status', 'completed')
                ->map(fn (TreatmentPlanItem $i) => [
                    'id' => $i->id,
                    'procedure_name' => $i->procedure_name,
                    'specialty' => $i->specialty,
                    'unit_cost' => (float) $i->unit_cost,
                    'quantity' => (int) $i->quantity,
                    'total_cost' => (float) $i->total_cost,
                ])
                ->values()
                ->all();
        }

        $payments = $appointment->transactions
            ->where('type', 'payment')
            ->where('status', 'completed')
            ->values()
            ->map(fn (Transaction $t) => [
                'id' => $t->id,
                'transaction_number' => $t->transaction_number,
                'amount' => (float) $t->amount,
                'payment_method' => $t->paymentMethod?->name,
                'processed_at' => $t->processed_at?->toIso8601String(),
            ])
            ->all();

        $subtotal = collect($items)->sum(fn ($i) => $i['total_cost']);
        $paid = collect($payments)->sum(fn ($p) => $p['amount']);
        $total = (float) ($appointment->final_amount ?? $subtotal);
        $balance = max(0, round($total - $paid, 2));

        $quotations = $appointment->quotations
            ->map(fn (Quotation $q) => [
                'id' => $q->id,
                'quotation_number' => $q->quotation_number,
                'status' => $q->status,
                'total_amount' => (float) $q->total_amount,
            ])
            ->values()
            ->all();

        return [
            'appointment' => [
                'id' => $appointment->id,
                'patient' => $appointment->patient?->only(['id', 'first_name', 'last_name', 'document_number']),
                'completed_at' => $appointment->completed_at?->toIso8601String(),
            ],
            'items' => $items,
            'subtotal' => round($subtotal, 2),
            'final_amount' => $total,
            'paid_amount' => round($paid, 2),
            'balance' => $balance,
            'is_fully_paid' => $balance <= 0.01,
            'payments' => $payments,
            'quotations' => $quotations,
        ];
    }

    /**
     * Genera una Quotation desde una cita cerrada.
     * Aplica reglas:
     *   - Si la cita ya tiene quotation en status sent/approved/viewed, devuelve la existente.
     *   - Si no, crea una nueva con status 'sent' basada en final_amount y los items del plan.
     */
    public function generateQuotationFromAppointment(Appointment $appointment): Quotation
    {
        return DB::transaction(function () use ($appointment) {
            $appointment->loadMissing('treatmentPlan.items');

            $existing = Quotation::where('appointment_id', $appointment->id)
                ->whereIn('status', ['draft', 'sent', 'viewed', 'approved'])
                ->first();

            if ($existing) {
                return $existing;
            }

            $plan = $appointment->treatmentPlan;
            $executedItems = $plan
                ? $plan->items->where('status', 'completed')
                : collect();

            $subtotal = $executedItems->isNotEmpty()
                ? (float) $executedItems->sum(fn ($i) => (float) $i->total_cost)
                : (float) ($appointment->final_amount ?? 0);

            $quotation = Quotation::create([
                'treatment_plan_id' => $appointment->treatment_plan_id,
                'appointment_id' => $appointment->id,
                'patient_id' => $appointment->patient_id,
                'created_by' => Auth::id(),
                'quotation_number' => $this->generateQuotationNumber(),
                'quotation_date' => now()->toDateString(),
                'valid_until' => now()->addDays(30)->toDateString(),
                'subtotal' => $subtotal,
                'discount_percentage' => 0,
                'discount_amount' => 0,
                'tax_percentage' => 0,
                'tax_amount' => 0,
                'total_amount' => $subtotal,
                'status' => self::QUOTATION_STATUS_SENT,
                'terms_conditions' => 'Presupuesto generado automáticamente al cierre de la cita.',
                'notes' => null,
                'payment_terms' => null,
            ]);

            foreach ($executedItems as $item) {
                \App\Models\QuotationItem::create([
                    'quotation_id' => $quotation->id,
                    'treatment_plan_item_id' => $item->id,
                    'item_name' => $item->procedure_name,
                    'item_description' => $item->procedure_description,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_cost,
                    'total_price' => $item->total_cost,
                    'specialty' => $item->specialty,
                    'notes' => null,
                ]);
            }

            event(new QuotationCreated($quotation->load(['items', 'patient', 'treatmentPlan'])));

            return $quotation;
        });
    }

    /**
     * Determina si la cita cerrada debe disparar la auto-generación de Quotation.
     * Reglas:
     *   - execution: siempre que tenga final_amount > 0
     *   - plan_session: si el clínico pasó generate_quotation=true (default true) o si hay items completed
     *   - consultation puro: NO (es solo evaluación)
     */
    public function shouldAutoGenerateQuotation(Appointment $appointment, array $payload): bool
    {
        if ((float) ($appointment->final_amount ?? 0) <= 0) {
            return false;
        }

        $mode = $payload['mode'] ?? null;
        if ($mode === \App\Services\ConsultationService::MODE_CONSULTATION) {
            return false;
        }
        if ($mode === \App\Services\ConsultationService::MODE_EXECUTION) {
            return true;
        }
        if ($mode === \App\Services\ConsultationService::MODE_PLAN_SESSION) {
            if (array_key_exists('generate_quotation', $payload) && $payload['generate_quotation'] === false) {
                return false;
            }
            return $appointment->treatmentPlan
                ? $appointment->treatmentPlan->items()->where('status', 'completed')->exists()
                : false;
        }
        return false;
    }

    private function generateQuotationNumber(): string
    {
        do {
            $number = 'PR-' . date('Y') . '-' . strtoupper(Str::random(6));
        } while (Quotation::where('quotation_number', $number)->exists());

        return $number;
    }
}
