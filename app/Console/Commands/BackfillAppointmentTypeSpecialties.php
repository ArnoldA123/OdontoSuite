<?php

namespace App\Console\Commands;

use App\Models\AppointmentType;
use App\Models\Specialty;
use App\Support\AppointmentTypeSpecialties;
use Illuminate\Console\Command;

/**
 * #523 — the deploy half of the appointment-type specialty.
 *
 * `appointment_types.specialty_id` was added nullable and without a backfill:
 * guessing the specialty of an existing row from its name is a business
 * decision, not a migration. The seeder only assigns it to the rows it creates,
 * so a base that was already populated keeps every type in "Sin especialidad".
 *
 * This command applies the same explicit map the seeder uses, only to rows that
 * still have no specialty. A human decision is never overwritten, and the
 * second run reports zero changes.
 */
class BackfillAppointmentTypeSpecialties extends Command
{
    /**
     * @var string
     */
    protected $signature = 'appointment-types:backfill {--dry-run : Report what would change without writing anything}';

    /**
     * @var string
     */
    protected $description = 'Asigna la especialidad de los tipos de cita sembrados que aún no la tienen';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $specialties = Specialty::pluck('id', 'code');

        $scanned = 0;
        $assigned = 0;
        $unmatched = 0;

        AppointmentType::query()
            ->whereNull('specialty_id')
            ->chunkById(100, function ($types) use ($specialties, $dryRun, &$scanned, &$assigned, &$unmatched) {
                foreach ($types as $type) {
                    $scanned++;

                    $code = AppointmentTypeSpecialties::codeFor($type->name);
                    $specialtyId = $code !== null ? ($specialties[$code] ?? null) : null;

                    if ($specialtyId === null) {
                        $unmatched++;

                        continue;
                    }

                    if (! $dryRun) {
                        $type->specialty_id = $specialtyId;
                        $type->save();
                    }

                    $assigned++;
                }
            });

        $this->info("Tipos de cita revisados: {$scanned}");
        $this->info("Especialidades asignadas: {$assigned}");
        $this->info("Tipos sin correspondencia: {$unmatched}");

        if ($dryRun) {
            $this->warn('dry-run: no se escribió nada.');
        }

        return self::SUCCESS;
    }
}
