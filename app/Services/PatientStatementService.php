<?php

namespace App\Services;

use App\Models\Patient;
use App\Models\PatientCharge;
use App\Models\PatientChargeAllocation;
use App\Models\Transaction;
use App\Support\MoneyVocabulary;
use Illuminate\Support\Carbon;

/**
 * #287 (T3, S3/S9) — the patient statement.
 *
 * The statement is the patient ledger walked in order and reconciled against
 * itself: the charges the acts and the counter wrote, the payments with their
 * discount, the refunds that raise the debt, and the write-offs and adjustments
 * that move it without touching the drawer (#287, D3/D4). The balance follows
 * the single rule of the issue:
 *
 *     cargos − descuentos − pagos + devoluciones − condonaciones + ajustes
 *
 * Only completed transactions count: a voided row never happened, and the
 * legacy `discount`/`income`/`expense` types stay out because the vocabulary
 * already moved past them (#287, D8). `patient_charges` is read by `charged_at`
 * and `transactions` by `created_at`, both inclusive; the opening balance is the
 * same rule applied to everything before the period, so the closing balance of
 * one period is the opening of the next.
 *
 * The ledger belongs to the patient, not to a sede: the ficha is shared between
 * branches (ADR-0010).
 *
 * #321 (S1-S4) — the statement also publishes the imputation as the view it is:
 * how much of each charge a completed payment covered (`charge_balances`) and
 * how much collected money no charge claimed (`unallocated_payments`). The
 * totals of `summary` and the two balances do not change because of it: the
 * allocation never moves money, it only says which charge the money paid.
 */
class PatientStatementService
{
    /** A charge is the only ledger row that is not a transaction. */
    private const CHARGE = 'charge';

    /**
     * The transaction types the statement reads. `discount` is included even
     * though it is no longer writable: a historical row of that type is a
     * discount the patient received, so the statement reads it as such instead
     * of hiding it and overstating the debt (#287, F-1).
     *
     * @var array<int, string>
     */
    private const TRANSACTION_TYPES = [
        MoneyVocabulary::PAYMENT,
        MoneyVocabulary::REFUND,
        MoneyVocabulary::WRITE_OFF,
        MoneyVocabulary::ADJUSTMENT,
        MoneyVocabulary::DISCOUNT,
    ];

    private const SUMMARY_KEYS = [
        'charges',
        'discounts',
        'payments',
        'refunds',
        'write_offs',
        'adjustments',
        'collected',
        'forgiven',
        'balance',
    ];

    /**
     * The statement of a period, defaulting to the current month through today.
     *
     * @return array<string, mixed>
     */
    public function forPatient(Patient $patient, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        $from = $dateFrom !== null
            ? Carbon::parse($dateFrom)->startOfDay()
            : now()->startOfMonth()->startOfDay();

        $to = $dateTo !== null
            ? Carbon::parse($dateTo)->endOfDay()
            : now()->endOfDay();

        $opening = $this->balance($patient, $from);

        $entries = $this->entries($patient, $from, $to);
        $summary = $this->summary($entries, $opening);
        $allocation = $this->allocationView($patient, $to);

        return [
            'patient' => [
                'id' => $patient->id,
                'first_name' => $patient->first_name,
                'last_name' => $patient->last_name,
                'document_number' => $patient->document_number,
            ],
            'period' => [
                'date_from' => $from->toDateString(),
                'date_to' => $to->toDateString(),
            ],
            'opening_balance' => $opening,
            'closing_balance' => $summary['balance'],
            'summary' => $summary,
            'charge_balances' => $allocation['rows'],
            'unallocated_payments' => $this->unallocatedPayments($patient, $to, $allocation['covered']),
            'entries' => array_map(static fn (array $item): array => $item['entry'], $entries),
        ];
    }

    /**
     * What the patient owes today: the whole ledger, with no period.
     */
    public function outstandingBalance(Patient $patient): float
    {
        return $this->balance($patient, null);
    }

    /**
     * The balance the period starts with: charges and ledger rows dated before
     * `$before`, or the whole ledger when no cut is given.
     */
    private function balance(Patient $patient, ?Carbon $before): float
    {
        $charges = PatientCharge::query()
            ->where('patient_id', $patient->id)
            ->when($before !== null, fn ($query) => $query->where('charged_at', '<', $before))
            ->sum('amount');

        $entries = Transaction::query()
            ->where('patient_id', $patient->id)
            ->where('status', 'completed')
            ->whereIn('type', self::TRANSACTION_TYPES)
            ->when($before !== null, fn ($query) => $query->where('created_at', '<', $before))
            ->get(['type', 'amount', 'discount_amount']);

        $balance = (float) $charges;

        foreach ($entries as $entry) {
            $balance += MoneyVocabulary::balanceEffect($entry->type, $entry->amount);

            // A payment's amount is already net: its discount is the second,
            // separate reduction of the charge it settles (#287, D3).
            if ($entry->type === MoneyVocabulary::PAYMENT) {
                $balance -= (float) $entry->discount_amount;
            }
        }

        return round($balance, 2);
    }

    /**
     * The rows of the period, in chronological order (date, then id). A payment
     * with a discount yields two lines, and the discount stays next to the
     * payment it belongs to.
     *
     * @return list<array{sort: array{0: Carbon, 1: int, 2: int}, entry: array<string, mixed>}>
     */
    private function entries(Patient $patient, Carbon $from, Carbon $to): array
    {
        $entries = [];

        $charges = PatientCharge::query()
            ->where('patient_id', $patient->id)
            ->whereBetween('charged_at', [$from, $to])
            ->orderBy('charged_at')
            ->orderBy('id')
            ->get();

        foreach ($charges as $charge) {
            $amount = round((float) $charge->amount, 2);

            $entries[] = [
                'sort' => [$charge->charged_at, (int) $charge->id, 0],
                'entry' => [
                    'kind' => self::CHARGE,
                    'date' => $charge->charged_at->toIso8601String(),
                    'reference' => 'CHG-'.str_pad((string) $charge->id, 6, '0', STR_PAD_LEFT),
                    'description' => $charge->description,
                    'amount' => $amount,
                    'effect' => $amount,
                    'balance' => 0.0,
                    'source_id' => $charge->id,
                ],
            ];
        }

        $transactions = Transaction::query()
            ->where('patient_id', $patient->id)
            ->where('status', 'completed')
            ->whereIn('type', self::TRANSACTION_TYPES)
            ->whereBetween('created_at', [$from, $to])
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        foreach ($transactions as $transaction) {
            $kind = $transaction->type;
            $amount = round((float) $transaction->amount, 2);

            $entries[] = [
                'sort' => [$transaction->created_at, (int) $transaction->id, 0],
                'entry' => [
                    'kind' => $kind,
                    'date' => $transaction->created_at->toIso8601String(),
                    'reference' => $transaction->transaction_number,
                    'description' => $this->describe($transaction),
                    'amount' => abs($amount),
                    'effect' => round(MoneyVocabulary::balanceEffect($kind, $amount), 2),
                    'balance' => 0.0,
                    'source_id' => $transaction->id,
                ],
            ];

            $discount = round((float) $transaction->discount_amount, 2);

            if ($kind === MoneyVocabulary::PAYMENT && $discount > 0) {
                $entries[] = [
                    'sort' => [$transaction->created_at, (int) $transaction->id, 1],
                    'entry' => [
                        'kind' => MoneyVocabulary::DISCOUNT,
                        'date' => $transaction->created_at->toIso8601String(),
                        'reference' => $transaction->transaction_number.'-D',
                        'description' => MoneyVocabulary::label(MoneyVocabulary::DISCOUNT),
                        'amount' => $discount,
                        'effect' => round(MoneyVocabulary::balanceEffect(MoneyVocabulary::DISCOUNT, $discount), 2),
                        'balance' => 0.0,
                        'source_id' => $transaction->id,
                    ],
                ];
            }
        }

        usort($entries, static function (array $left, array $right): int {
            return [$left['sort'][0]->timestamp, $left['sort'][1], $left['sort'][2]]
                <=> [$right['sort'][0]->timestamp, $right['sort'][1], $right['sort'][2]];
        });

        return $entries;
    }

    /**
     * The summary is the reconciliation of the period: the totals of each book
     * and the balance the statement closes with. The running balance is stamped
     * here, on the same walk, so the entries and the summary cannot disagree.
     *
     * @param  list<array{sort: array{0: Carbon, 1: int, 2: int}, entry: array<string, mixed>}> $entries
     * @return array<string, float>
     */
    private function summary(array &$entries, float $opening): array
    {
        $summary = array_fill_keys(self::SUMMARY_KEYS, 0.0);
        $balance = $opening;

        foreach ($entries as $index => $item) {
            $entry = $item['entry'];
            $kind = $entry['kind'];
            $balance = round($balance + (float) $entry['effect'], 2);

            $entries[$index]['entry']['balance'] = $balance;

            $key = match ($kind) {
                self::CHARGE => 'charges',
                MoneyVocabulary::DISCOUNT => 'discounts',
                MoneyVocabulary::PAYMENT => 'payments',
                MoneyVocabulary::REFUND => 'refunds',
                MoneyVocabulary::WRITE_OFF => 'write_offs',
                MoneyVocabulary::ADJUSTMENT => 'adjustments',
            };

            // An adjustment is signed, so its magnitude alone would lose which
            // way the debt moved; the rest of the imports are magnitudes.
            $summary[$key] += $kind === MoneyVocabulary::ADJUSTMENT
                ? (float) $entry['effect']
                : (float) $entry['amount'];
        }

        $summary = array_map(static fn (float $value): float => round($value, 2), $summary);

        $summary['collected'] = round($summary['payments'] - $summary['refunds'], 2);
        $summary['forgiven'] = $summary['write_offs'];
        $summary['balance'] = $balance;

        return $summary;
    }

    /**
     * #321 (S1-S2) — the balance of each charge as of `$to`, and the money the
     * allocations covered.
     *
     * The view reads the charges up to the cut and the allocations of completed
     * payments up to it, so a charge of an earlier period still shows the
     * payments that covered it. A refund does not say which charge it returns,
     * so it releases the coverage of its own payment in the same proportion the
     * payment lost (`covered = allocation × (amount − refunded) / amount`); the
     * rounding residual stays on the last allocation of that payment so the sum
     * of the rows is the sum that was computed.
     *
     * @return array{rows: list<array<string, mixed>>, covered: float}
     */
    private function allocationView(Patient $patient, Carbon $to): array
    {
        $charges = PatientCharge::query()
            ->where('patient_id', $patient->id)
            ->where('charged_at', '<=', $to)
            ->orderBy('charged_at')
            ->orderBy('id')
            ->get();

        if ($charges->isEmpty()) {
            return ['rows' => [], 'covered' => 0.0];
        }

        $allocations = PatientChargeAllocation::query()
            ->whereIn('patient_charge_id', $charges->pluck('id'))
            ->whereHas('transaction', fn ($query) => $query
                ->where('status', 'completed')
                ->where('created_at', '<=', $to))
            ->orderBy('id')
            ->get(['id', 'patient_charge_id', 'transaction_id', 'amount']);

        $covered = [];

        if ($allocations->isNotEmpty()) {
            $paymentIds = $allocations->pluck('transaction_id')->unique()->values();

            $payments = Transaction::query()
                ->whereIn('id', $paymentIds)
                ->where('status', 'completed')
                ->where('created_at', '<=', $to)
                ->get(['id', 'amount'])
                ->keyBy('id');

            $refunded = Transaction::query()
                ->whereIn('parent_transaction_id', $paymentIds)
                ->where('type', MoneyVocabulary::REFUND)
                ->where('status', 'completed')
                ->where('created_at', '<=', $to)
                ->groupBy('parent_transaction_id')
                ->selectRaw('parent_transaction_id, SUM(amount) as total')
                ->pluck('total', 'parent_transaction_id');

            foreach ($allocations->groupBy('transaction_id') as $paymentId => $group) {
                $payment = $payments->get($paymentId);

                if ($payment === null) {
                    continue;
                }

                $amount = round((float) $payment->amount, 2);
                $ratio = $amount > 0
                    ? max(0.0, min(1.0, ($amount - (float) ($refunded[$paymentId] ?? 0)) / $amount))
                    : 0.0;

                // The coverage is distributed in whole cents: rounding each
                // share on its own can round four rows up while the total only
                // rounds one, which used to leave the last row negative. The
                // largest-remainder method keeps every share non-negative and
                // the sum exact.
                $coveredCents = (int) round((float) $group->sum('amount') * $ratio * 100);
                $rows = $group->values();
                $amountCents = $rows->map(static fn ($allocation): int => (int) round((float) $allocation->amount * 100));
                $totalCents = (int) $amountCents->sum();
                $shares = [];
                $remainders = [];
                $assignedCents = 0;

                foreach ($rows as $index => $allocation) {
                    $exact = $totalCents > 0
                        ? $coveredCents * $amountCents[$index] / $totalCents
                        : 0.0;
                    $share = (int) floor($exact);
                    $shares[$index] = $share;
                    $assignedCents += $share;
                    $remainders[$index] = $exact - $share;
                }

                arsort($remainders);

                foreach (array_slice(array_keys($remainders), 0, $coveredCents - $assignedCents) as $index) {
                    $shares[$index]++;
                }

                foreach ($rows as $index => $allocation) {
                    $chargeId = (int) $allocation->patient_charge_id;
                    $covered[$chargeId] = round(($covered[$chargeId] ?? 0.0) + $shares[$index] / 100, 2);
                }
            }
        }

        $rows = [];
        $coveredTotal = 0.0;

        foreach ($charges as $charge) {
            $amount = round((float) $charge->amount, 2);
            $allocated = round((float) ($covered[$charge->id] ?? 0.0), 2);
            $coveredTotal = round($coveredTotal + $allocated, 2);

            $rows[] = [
                'id' => $charge->id,
                'reference' => 'CHG-'.str_pad((string) $charge->id, 6, '0', STR_PAD_LEFT),
                'date' => $charge->charged_at->toIso8601String(),
                'description' => $charge->description,
                'amount' => $amount,
                'allocated' => $allocated,
                'balance' => round($amount - $allocated, 2),
            ];
        }

        return ['rows' => $rows, 'covered' => $coveredTotal];
    }

    /**
     * #321 (S1/S3) — the collected money no charge claimed. Refunds reduce what
     * is available to impute, and the charges covered by the allocations are
     * subtracted so the number is what is left to assign.
     */
    private function unallocatedPayments(Patient $patient, Carbon $to, float $covered): float
    {
        $collected = (float) Transaction::query()
            ->where('patient_id', $patient->id)
            ->where('type', MoneyVocabulary::PAYMENT)
            ->where('status', 'completed')
            ->where('created_at', '<=', $to)
            ->sum('amount');

        $refunded = (float) Transaction::query()
            ->where('patient_id', $patient->id)
            ->where('type', MoneyVocabulary::REFUND)
            ->where('status', 'completed')
            ->where('created_at', '<=', $to)
            ->sum('amount');

        return round($collected - $refunded - $covered, 2);
    }

    /**
     * A charge and a payment name themselves; a ledger row is named after its
     * kind, because it has no counter description of its own.
     */
    private function describe(Transaction $transaction): string
    {
        if (in_array($transaction->type, MoneyVocabulary::NON_CASH, true)) {
            return MoneyVocabulary::label($transaction->type);
        }

        $description = trim((string) $transaction->description);

        return $description !== ''
            ? $description
            : MoneyVocabulary::label($transaction->type);
    }
}
