<?php

namespace App\Support;

use App\Models\Appointment;

/**
 * #393 — what a closed attention owes, in one definition.
 *
 * The billable list read the debt from `final_amount` while the paying door read
 * `total_cost`/`paid_amount`, so a closure that had only projected the amount
 * produced a debt nobody could collect. `ready-to-bill`, the payment preview and
 * the paying door read the pair (amount, payments) from here.
 */
final class AppointmentBalance
{
    /**
     * The amount the patient owes for the attention.
     *
     * The closure projects `final_amount`; `total_cost` is the older column and
     * stays the answer for rows that were never closed by an act.
     */
    public static function total(Appointment $appointment): float
    {
        $finalAmount = (float) ($appointment->final_amount ?? 0);

        if ($finalAmount > 0) {
            return round($finalAmount, 2);
        }

        return round((float) ($appointment->total_cost ?? 0), 2);
    }

    /**
     * What has been collected for the attention.
     *
     * A partially loaded model must not answer from an incomplete relation, so
     * the fallback is a query; an eager-loaded relation is reused (filtered
     * again here, because the loader may bring more than the payments) to keep
     * the lists from running one query per row.
     */
    public static function paid(Appointment $appointment): float
    {
        if ($appointment->relationLoaded('transactions')) {
            $paid = $appointment->transactions
                ->where('type', MoneyVocabulary::PAYMENT)
                ->where('status', 'completed')
                ->sum('amount');
        } else {
            $paid = $appointment->transactions()
                ->where('type', MoneyVocabulary::PAYMENT)
                ->where('status', 'completed')
                ->sum('amount');
        }

        return round((float) $paid, 2);
    }

    public static function balance(Appointment $appointment): float
    {
        return max(0, round(self::total($appointment) - self::paid($appointment), 2));
    }

    /**
     * @return array{total: float, paid: float, balance: float}
     */
    public static function summary(Appointment $appointment): array
    {
        $total = self::total($appointment);
        $paid = self::paid($appointment);

        return [
            'total' => $total,
            'paid' => $paid,
            'balance' => max(0, round($total - $paid, 2)),
        ];
    }
}
