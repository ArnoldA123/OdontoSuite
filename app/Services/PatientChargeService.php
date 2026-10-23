<?php

namespace App\Services;

use App\Models\ClinicalEncounter;
use App\Models\Patient;
use App\Models\PatientCharge;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * #287 (T2, S3/S4) — the writer of the patient charge ledger.
 *
 * `chargeForEncounter()` is the single implementation that turns a closed act
 * into debt: the closure calls it inside its own transaction, and the backfill
 * command calls the same method for the acts documented before the ledger
 * existed. It is keyed by the encounter, so calling it again returns the row it
 * already wrote instead of duplicating the debt.
 *
 * `createManual()` is the counter's door for a charge no act produced. The
 * ledger is never edited nor deleted (#287, D1): a wrong charge is corrected
 * with a signed adjustment.
 */
class PatientChargeService
{
    /**
     * The description of an act that has no appointment comes from its mode,
     * because there is no appointment type to name it with.
     */
    private const MODE_LABELS = [
        ClinicalEncounter::MODE_CONSULTATION => 'Consulta',
        ClinicalEncounter::MODE_EXECUTION => 'Ejecución',
        ClinicalEncounter::MODE_PLAN_SESSION => 'Sesión de plan',
        ClinicalEncounter::MODE_URGENCY => 'Urgencia',
    ];

    /**
     * The charge the closure of the act produced. An act without amount owes
     * nothing, so it writes nothing.
     */
    public function chargeForEncounter(ClinicalEncounter $encounter): ?PatientCharge
    {
        $amount = $encounter->final_amount;

        if ($amount === null || (float) $amount <= 0) {
            return null;
        }

        return PatientCharge::firstOrCreate(
            ['clinical_encounter_id' => $encounter->id],
            [
                'patient_id' => $encounter->patient_id,
                'source' => PatientCharge::SOURCE_ENCOUNTER,
                'description' => $this->describe($encounter),
                'amount' => $amount,
                'charged_at' => $encounter->closed_at ?? now(),
                'created_by' => Auth::id(),
            ],
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    public function createManual(array $data): PatientCharge
    {
        $patient = Patient::query()
            ->whereKey($data['patient_id'] ?? null)
            ->first();

        if ($patient === null || ! $patient->is_active) {
            throw ValidationException::withMessages([
                'patient_id' => ['El paciente seleccionado no existe o está inactivo.'],
            ]);
        }

        $amount = (float) ($data['amount'] ?? 0);

        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'amount' => ['El importe del cargo debe ser mayor que cero.'],
            ]);
        }

        $description = trim((string) ($data['description'] ?? ''));

        if ($description === '') {
            throw ValidationException::withMessages([
                'description' => ['La descripción del cargo es requerida.'],
            ]);
        }

        $chargedAt = isset($data['charged_at'])
            ? Carbon::parse((string) $data['charged_at'])
            : now();

        if ($chargedAt->copy()->startOfDay()->isAfter(today())) {
            throw ValidationException::withMessages([
                'charged_at' => ['La fecha del cargo no puede ser futura.'],
            ]);
        }

        return PatientCharge::create([
            'patient_id' => $patient->id,
            'clinical_encounter_id' => null,
            'source' => PatientCharge::SOURCE_MANUAL,
            'description' => $description,
            'amount' => $amount,
            'charged_at' => $chargedAt,
            'created_by' => Auth::id(),
            'notes' => $data['notes'] ?? null,
        ]);
    }

    /**
     * A charge of an act is named after the act: the appointment type, or the
     * mode when the act never had an appointment. It is never empty.
     */
    private function describe(ClinicalEncounter $encounter): string
    {
        $name = $encounter->appointment?->appointmentType?->name;

        if (is_string($name) && trim($name) !== '') {
            return trim($name);
        }

        return self::MODE_LABELS[$encounter->mode] ?? 'Atención';
    }
}
