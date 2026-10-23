<?php

namespace App\Services;

use App\Models\PatientCharge;
use App\Models\PatientChargeAllocation;
use App\Models\Transaction;
use App\Support\MoneyVocabulary;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * #321 (S1-S4) — the two sum rules of the imputation.
 *
 * An allocation says how much of a payment settled a charge of the same
 * patient. It is a view over money that already exists, so the only things it
 * can get wrong are its sums:
 *
 *   - a payment never allocates more than its own amount, and
 *   - a charge never receives more than its own amount.
 *
 * Both are checked against locked rows before writing: the charges are locked
 * in id order (two payments imputing the same charge cannot both pass the cap)
 * and the payment is locked after them (two calls imputing the same payment
 * cannot either). Locking in a stable order keeps concurrent calls from
 * deadlocking each other.
 */
class PatientChargeAllocationService
{
    /**
     * Register the allocations of a completed payment to charges of the same
     * patient. Returns the rows it wrote, in the order they were requested.
     *
     * @param  array<int|string, array{patient_charge_id: int|string, amount: int|float|string}> $allocations
     * @return list<PatientChargeAllocation>
     */
    public function allocate(Transaction $payment, array $allocations): array
    {
        $this->assertImputable($payment);

        $requested = $this->normalize($allocations);

        if ($requested === []) {
            return [];
        }

        return DB::transaction(function () use ($payment, $requested): array {
            $charges = PatientCharge::query()
                ->whereIn('id', array_keys($requested))
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            foreach ($requested as $chargeId => $allocation) {
                $charge = $charges->get($chargeId);

                if ($charge === null) {
                    throw ValidationException::withMessages([
                        "allocations.{$allocation['index']}.patient_charge_id" => ['El cargo seleccionado no existe.'],
                    ]);
                }

                if ((int) $charge->patient_id !== (int) $payment->patient_id) {
                    throw ValidationException::withMessages([
                        "allocations.{$allocation['index']}.patient_charge_id" => ['El cargo pertenece a otro paciente.'],
                    ]);
                }
            }

            // Locked after the charges, never before: the lock order is the
            // same in every call.
            $lockedPayment = Transaction::query()->whereKey($payment->id)->lockForUpdate()->first();
            $this->assertImputable($lockedPayment ?? $payment);

            $alreadyAllocated = round((float) PatientChargeAllocation::query()
                ->where('transaction_id', $payment->id)
                ->sum('amount'), 2);

            // One row per (charge, payment): a repeated pair would violate the
            // unique index and surface as a 500, so it is refused as the field
            // error it is.
            $alreadyImputed = PatientChargeAllocation::query()
                ->where('transaction_id', $payment->id)
                ->whereIn('patient_charge_id', array_keys($requested))
                ->pluck('patient_charge_id')
                ->map(static fn ($chargeId): int => (int) $chargeId)
                ->all();

            foreach ($requested as $chargeId => $allocation) {
                if (in_array($chargeId, $alreadyImputed, true)) {
                    throw ValidationException::withMessages([
                        "allocations.{$allocation['index']}.patient_charge_id" => ['El pago ya tiene una imputación a ese cargo.'],
                    ]);
                }
            }

            $available = round((float) $lockedPayment->amount - $alreadyAllocated, 2);
            $requestedTotal = round(array_sum(array_column($requested, 'amount')), 2);

            if ($requestedTotal > $available + 0.001) {
                throw ValidationException::withMessages([
                    'allocations' => [sprintf(
                        'La suma de las imputaciones (%s) supera el importe disponible del pago (%s).',
                        number_format($requestedTotal, 2, '.', ''),
                        number_format($available, 2, '.', ''),
                    )],
                ]);
            }

            // Only a completed payment covers a charge, so only its rows
            // reserve the cap: a voided payment that once covered a charge
            // must not keep it half-covered forever.
            $allocatedPerCharge = PatientChargeAllocation::query()
                ->whereIn('patient_charge_id', array_keys($requested))
                ->whereHas('transaction', fn ($query) => $query->where('status', 'completed'))
                ->groupBy('patient_charge_id')
                ->selectRaw('patient_charge_id, SUM(amount) as allocated')
                ->pluck('allocated', 'patient_charge_id');

            foreach ($requested as $chargeId => $allocation) {
                $charge = $charges->get($chargeId);
                $pending = round((float) $charge->amount - (float) ($allocatedPerCharge[$chargeId] ?? 0), 2);

                if ($allocation['amount'] > $pending + 0.001) {
                    throw ValidationException::withMessages([
                        "allocations.{$allocation['index']}.amount" => [sprintf(
                            'La imputación supera el saldo pendiente del cargo (%s).',
                            number_format($pending, 2, '.', ''),
                        )],
                    ]);
                }
            }

            $created = [];

            foreach ($requested as $chargeId => $allocation) {
                $created[] = PatientChargeAllocation::create([
                    'patient_charge_id' => $chargeId,
                    'transaction_id' => $payment->id,
                    'amount' => $allocation['amount'],
                    'created_by' => Auth::id(),
                ]);
            }

            return $created;
        });
    }

    /**
     * A payment and only a payment covers a charge, and only while it is
     * completed: a voided row never happened.
     */
    private function assertImputable(?Transaction $transaction): void
    {
        if (! $transaction instanceof Transaction || $transaction->type !== MoneyVocabulary::PAYMENT) {
            throw ValidationException::withMessages([
                'transaction' => ['Sólo un pago puede imputarse a los cargos del paciente.'],
            ]);
        }

        if ($transaction->status !== 'completed') {
            throw ValidationException::withMessages([
                'transaction' => ['Sólo un pago completado puede imputarse a los cargos del paciente.'],
            ]);
        }
    }

    /**
     * One entry per charge, in the order it was requested. A repeated charge is
     * summed into its first entry, so the two sum rules see the real demand.
     *
     * @param  array<int|string, array{patient_charge_id: int|string, amount: int|float|string}> $allocations
     * @return array<int, array{amount: float, index: int}>
     */
    private function normalize(array $allocations): array
    {
        $normalized = [];

        foreach (array_values($allocations) as $index => $allocation) {
            $chargeId = (int) ($allocation['patient_charge_id'] ?? 0);
            $amount = round((float) ($allocation['amount'] ?? 0), 2);

            if ($amount <= 0) {
                throw ValidationException::withMessages([
                    "allocations.{$index}.amount" => ['La imputación debe ser mayor que cero.'],
                ]);
            }

            if (! isset($normalized[$chargeId])) {
                $normalized[$chargeId] = ['amount' => 0.0, 'index' => $index];
            }

            $normalized[$chargeId]['amount'] = round($normalized[$chargeId]['amount'] + $amount, 2);
        }

        return $normalized;
    }
}
