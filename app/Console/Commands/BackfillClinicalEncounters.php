<?php

namespace App\Console\Commands;

use App\Models\Appointment;
use App\Models\ClinicalAttachment;
use App\Models\ClinicalEncounter;
use App\Models\ClinicalEvolution;
use App\Models\MedicalRecord;
use App\Models\OdontogramRecord;
use App\Models\ProcedureMaterial;
use App\Models\Quotation;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * F2 (#282) — the historical half of the encounter model.
 *
 * Appointments completed before the encounter existed carry their clinical
 * documentation on the appointment itself. This command gives each of them one
 * closed encounter and then links the clinical rows that still have no
 * `clinical_encounter_id`, so `ready-to-bill` and the medical records keep
 * reading exactly what they read before.
 *
 * Idempotent by construction: it only creates a closed encounter for a
 * completed appointment that has none, and it only fills columns that are
 * still NULL. A second run therefore reports zero creations and zero links.
 *
 * The values it cannot know are derived, never invented: `opened_at` and
 * `closed_at` come from the appointment's own columns, the mode from
 * `consultation_mode`, and the medical record is linked only when the patient
 * already has an active one.
 */
class BackfillClinicalEncounters extends Command
{
    /**
     * @var string
     */
    protected $signature = 'encounters:backfill {--dry-run : Report what would change without writing anything}';

    /**
     * @var string
     */
    protected $description = 'Crea la atención cerrada de las citas completadas históricas y enlaza sus filas clínicas';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $created = 0;
        $linked = 0;

        $this->completed()
            ->with(['clinicalEncounters' => fn ($query) => $query->orderBy('id')])
            ->chunkById(100, function ($appointments) use (&$created, &$linked, $dryRun) {
                foreach ($appointments as $appointment) {
                    $encounter = $appointment->clinicalEncounters
                        ->first(fn (ClinicalEncounter $candidate) => $candidate->isClosed());

                    if (! $encounter) {
                        if (! $dryRun) {
                            $encounter = $this->record($appointment);
                        }

                        $created++;
                    }

                    $linked += $this->linkClinicalRows($appointment, $encounter, $dryRun);
                }
            });

        $this->info("Atenciones creadas: {$created}");
        $this->info("Filas clínicas enlazadas: {$linked}");

        if ($dryRun) {
            $this->warn('dry-run: no se escribió nada.');
        }

        return self::SUCCESS;
    }

    /**
     * Attach every orphan clinical row of the appointment to its encounter. The
     * update is raw on purpose: a backfill must not move `updated_at` of
     * clinical documentation that nobody edited.
     */
    private function linkClinicalRows(Appointment $appointment, ?ClinicalEncounter $encounter, bool $dryRun): int
    {
        $linked = 0;

        foreach ($this->clinicalTables() as $model) {
            $query = $model::query()
                ->where('appointment_id', $appointment->id)
                ->whereNull('clinical_encounter_id');

            if ($dryRun || ! $encounter) {
                $linked += $query->count();

                continue;
            }

            $linked += $query->toBase()->update([
                'clinical_encounter_id' => $encounter->id,
            ]);
        }

        return $linked;
    }

    private function record(Appointment $appointment): ClinicalEncounter
    {
        $medicalRecord = MedicalRecord::query()
            ->where('patient_id', $appointment->patient_id)
            ->where('is_active', true)
            ->orderBy('id')
            ->first();

        return ClinicalEncounter::create([
            'patient_id' => $appointment->patient_id,
            'appointment_id' => $appointment->id,
            'user_id' => $appointment->user_id,
            'medical_record_id' => $medicalRecord?->id,
            'treatment_plan_id' => $appointment->treatment_plan_id,
            'mode' => $appointment->consultation_mode ?? ClinicalEncounter::MODE_EXECUTION,
            'status' => ClinicalEncounter::STATUS_CLOSED,
            'opened_at' => $appointment->checked_in_at ?? $appointment->scheduled_at,
            'closed_at' => $appointment->completed_at ?? $appointment->ends_at ?? $appointment->updated_at,
            'closed_by' => $appointment->updated_by ?? $appointment->created_by,
            'final_amount' => $appointment->final_amount,
        ]);
    }

    private function completed(): Builder
    {
        return Appointment::query()->where('status', 'completed');
    }

    /**
     * @return array<int, class-string<Model>>
     */
    private function clinicalTables(): array
    {
        return [
            ClinicalEvolution::class,
            OdontogramRecord::class,
            ProcedureMaterial::class,
            ClinicalAttachment::class,
            Quotation::class,
        ];
    }
}
