<?php

namespace App\Console\Commands;

use App\Models\ClinicalEncounter;
use App\Services\PatientChargeService;
use Illuminate\Console\Command;

/**
 * #287 (T2, S3/S4) — the historical half of the charge ledger.
 *
 * The acts closed before `patient_charges` existed never wrote their charge.
 * This command gives each of them the row it is missing through the same
 * writer the closure uses, so the amount, the date and the description are
 * derived exactly as they are today.
 *
 * Idempotent by construction: it only looks at closed acts with an amount and
 * skips the ones that already have a charge, so a second run reports zero
 * creations and the rows stay the same. `--dry-run` reports the same counts
 * without writing anything.
 */
class BackfillPatientCharges extends Command
{
    /**
     * @var string
     */
    protected $signature = 'charges:backfill {--dry-run : Report what would change without writing anything}';

    /**
     * @var string
     */
    protected $description = 'Crea el cargo faltante de cada atención cerrada con importe';

    public function handle(PatientChargeService $charges): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $scanned = 0;
        $created = 0;

        ClinicalEncounter::query()
            ->where('status', ClinicalEncounter::STATUS_CLOSED)
            ->whereNotNull('final_amount')
            ->where('final_amount', '>', 0)
            ->with(['charge', 'appointment.appointmentType'])
            ->chunkById(100, function ($encounters) use ($charges, $dryRun, &$scanned, &$created) {
                foreach ($encounters as $encounter) {
                    $scanned++;

                    if ($encounter->charge !== null) {
                        continue;
                    }

                    if ($dryRun) {
                        $created++;

                        continue;
                    }

                    if ($charges->chargeForEncounter($encounter) !== null) {
                        $created++;
                    }
                }
            });

        $this->info("Atenciones cerradas revisadas: {$scanned}");
        $this->info("Cargos creados: {$created}");

        if ($dryRun) {
            $this->warn('dry-run: no se escribió nada.');
        }

        return self::SUCCESS;
    }
}
