<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\CashMovement;
use App\Models\PaymentMethod;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * #322 (S5/S6) — the drawer rows written before ADR-0015, D5b.
 *
 * Until #287, `TransactionService` wrote a `CashMovement` for every payment and
 * every refund, whatever its method. Under the money vocabulary the drawer only
 * carries physical cash, so those legacy rows inflate the expected amount of
 * every session they belong to.
 *
 * The decision recorded by the user is to repair both open and closed sessions,
 * and "not rewriting the session" is honoured by never touching the session
 * columns: `expected_amount`, `closing_amount`, `difference_amount`, `status`
 * and `arqueo_data` keep the historical record, while the repair marks the
 * movement. Marking means stamping the evidence in `audit_logs` and soft
 * deleting the row, so it survives, and the live expected amount — which
 * `CashRegisterService::cashFlow()` recomputes from the remaining movements —
 * drops the electronic money on the next read.
 *
 * A movement whose method cannot be proven (no method, or a family outside the
 * closed list) is left alone: it is counted apart instead of being repaired on
 * a guess. Idempotent by construction, because the repaired rows no longer
 * appear in the scan, so a second run repairs zero. `--dry-run` reports the same
 * counts and writes nothing.
 */
class RepairElectronicCashMovements extends Command
{
    public const ACTION_REPAIRED = 'cash_movement_repaired';

    public const REASON = 'El método no mueve gaveta (ADR-0015, D5b).';

    /**
     * @var string
     */
    protected $signature = 'cash:repair-electronic-movements {--dry-run : Report what would change without writing anything}';

    /**
     * @var string
     */
    protected $description = 'Marca los movimientos de gaveta de métodos electrónicos sin reescribir las sesiones';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $scanned = 0;
        $repaired = 0;
        $keptCash = 0;
        $keptUnknownMethod = 0;

        $this->candidates()
            ->chunkById(100, function ($movements) use ($dryRun, &$scanned, &$repaired, &$keptCash, &$keptUnknownMethod) {
                foreach ($movements as $movement) {
                    $scanned++;

                    $type = $movement->transaction?->paymentMethod?->type;

                    if ($type === PaymentMethod::TYPE_CASH) {
                        $keptCash++;

                        continue;
                    }

                    if (! in_array($type, PaymentMethod::ELECTRONIC_TYPES, true)) {
                        $keptUnknownMethod++;

                        continue;
                    }

                    if (! $dryRun) {
                        $this->repair($movement, $type);
                    }

                    $repaired++;
                }
            });

        $this->info("Movimientos con transacción revisados: {$scanned}");
        $this->info($dryRun
            ? "Movimientos electrónicos que se repararían: {$repaired}"
            : "Movimientos electrónicos reparados: {$repaired}");
        $this->info("Movimientos de efectivo conservados: {$keptCash}");
        $this->info("Movimientos sin método comprobable: {$keptUnknownMethod}");
        $this->info('Idempotente: una segunda corrida repara 0 movimientos.');

        if ($dryRun) {
            $this->warn('dry-run: no se escribió nada.');
        }

        return self::SUCCESS;
    }

    /**
     * Every live movement that came from a transaction. The opening and closing
     * snapshots carry no transaction, so they are never candidates; the rows an
     * earlier run already repaired are soft deleted and drop out of the scan.
     */
    private function candidates(): Builder
    {
        return CashMovement::query()
            ->whereNotNull('transaction_id')
            ->with([
                'transaction' => fn ($query) => $query->withTrashed(),
                'transaction.paymentMethod' => fn ($query) => $query->withTrashed(),
            ]);
    }

    /**
     * Evidence first, then the soft delete, in one transaction: the row keeps
     * its amount, type, reference, session and transaction, and the audit trail
     * says why it no longer counts as a drawer flow.
     */
    private function repair(CashMovement $movement, string $paymentMethodType): void
    {
        DB::transaction(function () use ($movement, $paymentMethodType) {
            AuditLog::create([
                'user_id' => null,
                'action' => self::ACTION_REPAIRED,
                'auditable_type' => CashMovement::class,
                'auditable_id' => $movement->id,
                'metadata' => [
                    'command' => $this->getName(),
                    'reason' => self::REASON,
                    'cash_register_session_id' => $movement->cash_register_session_id,
                    'transaction_id' => $movement->transaction_id,
                    'payment_method_type' => $paymentMethodType,
                    'movement_type' => $movement->type,
                    'amount' => (float) $movement->amount,
                ],
            ]);

            $movement->delete();
        });
    }
}
