<?php

namespace App\Services;

use App\Models\Patient;
use App\Models\PatientCharge;
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
