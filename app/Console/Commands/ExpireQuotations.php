<?php

namespace App\Console\Commands;

use App\Models\Quotation;
use Illuminate\Console\Command;

/**
 * Writer of the `expired` state (#201). The quotation status is authoritative,
 * so the sweep formalizes every pending quotation whose validity ended; the
 * date-derived badge in the UI still warns before the next run.
 */
class ExpireQuotations extends Command
{
    protected $signature = 'quotations:expire';

    protected $description = 'Marca como expirados los presupuestos enviados o vistos cuya vigencia terminó';

    public function handle(): int
    {
        $today = now()->toDateString();
        $expired = 0;

        Quotation::query()
            ->whereIn('status', ['sent', 'viewed'])
            ->whereDate('valid_until', '<', $today)
            ->chunkById(200, function ($quotations) use (&$expired) {
                foreach ($quotations as $quotation) {
                    $quotation->update(['status' => 'expired']);
                    $expired++;
                }
            });

        $this->info("Presupuestos expirados: {$expired}.");

        return self::SUCCESS;
    }
}
