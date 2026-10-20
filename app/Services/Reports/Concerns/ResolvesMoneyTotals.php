<?php

namespace App\Services\Reports\Concerns;

use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * The single money rule of the Business Intelligence reports (#184, S3/S4).
 *
 * `payment` adds, `refund` subtracts, both must be completed and inside
 * [startOfDay(start_date), endOfDay(end_date)]. The refunded amount is always
 * reported next to the net figure, so `net + refunds` is the gross collected and
 * can never be negative.
 *
 * The SQL fragments are alias-free on purpose: each report aliases them to the
 * key its own contract exposes, and every column is prefixed with
 * `transactions.` so the joined queries stay unambiguous.
 */
trait ResolvesMoneyTotals
{
    /**
     * Transaction types that move money in or out of a period total.
     *
     * @return array<int, string>
     */
    protected function moneyTypes(): array
    {
        return ['payment', 'refund'];
    }

    /**
     * Net money: completed payments minus completed refunds.
     */
    protected function netTotalsSql(): string
    {
        return "COALESCE(SUM(CASE WHEN transactions.type = 'payment' THEN transactions.amount"
            ." WHEN transactions.type = 'refund' THEN -transactions.amount ELSE 0 END), 0)";
    }

    /**
     * Refunded money as a positive amount.
     */
    protected function refundTotalsSql(): string
    {
        return "COALESCE(SUM(CASE WHEN transactions.type = 'refund' THEN transactions.amount ELSE 0 END), 0)";
    }

    /**
     * Appointments that actually carry a payment.
     *
     * A refund-only appointment must never inflate the denominator of a cut.
     */
    protected function paidAppointmentsSql(): string
    {
        return "COUNT(DISTINCT CASE WHEN transactions.type = 'payment' THEN transactions.appointment_id END)";
    }

    /**
     * Money transactions of the period, before any attribution filter.
     */
    protected function moneyTransactions(Carbon $startDate, Carbon $endDate): Builder
    {
        return Transaction::query()
            ->whereIn('transactions.type', $this->moneyTypes())
            ->where('transactions.status', 'completed')
            ->whereBetween('transactions.created_at', [$startDate, $endDate]);
    }

    /**
     * Net and refunds of a money query.
     *
     * @return array{net: float, refunds: float}
     */
    protected function moneyTotals(Builder $query): array
    {
        $row = $query->clone()
            ->reorder()
            ->select(
                DB::raw($this->netTotalsSql().' as net'),
                DB::raw($this->refundTotalsSql().' as refunds')
            )
            ->first();

        return [
            'net' => round((float) ($row->net ?? 0), 2),
            'refunds' => round((float) ($row->refunds ?? 0), 2),
        ];
    }
}
