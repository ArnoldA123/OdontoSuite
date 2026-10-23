<?php

namespace App\Support;

use App\Models\ClinicalEncounter;
use App\Models\ClinicalEvolution;
use App\Models\EncounterDiagnosis;
use App\Models\OdontogramRecord;
use App\Models\ProcedureMaterial;

/**
 * F6 (#286) — the integrity of a signed clinical record.
 *
 * The simple electronic signature of the close is the professional's password
 * plus a SHA-256 of the content the act froze. The payload is built from the
 * rows the encounter owns, always in `id` order, so the hash is reproducible
 * years later: it is computed when the act is closed and recomputed on read to
 * answer whether the record still is what was signed.
 *
 * The treatment plan is deliberately outside the payload. A plan keeps living
 * after the act (later sessions add lines to it), so hashing it would make every
 * past signature fail for a reason the professional never signed. What the act
 * did is the evolution and its materials, and those are encounter rows.
 */
final class ClinicalRecordSignature
{
    /**
     * @return array<string, mixed>
     */
    public static function payload(ClinicalEncounter $encounter): array
    {
        $encounterId = (int) $encounter->getKey();

        return [
            'encounter' => [
                'id' => $encounterId,
                'patient_id' => (int) $encounter->patient_id,
                'appointment_id' => $encounter->appointment_id !== null ? (int) $encounter->appointment_id : null,
                'professional_id' => (int) $encounter->user_id,
                'mode' => $encounter->mode,
                'opened_at' => $encounter->opened_at?->toIso8601String(),
                'closed_at' => $encounter->closed_at?->toIso8601String(),
                'reason_for_consultation' => $encounter->reason_for_consultation,
                'final_amount' => $encounter->final_amount !== null
                    ? (string) $encounter->final_amount
                    : null,
            ],
            'evolutions' => ClinicalEvolution::query()
                ->where('clinical_encounter_id', $encounterId)
                ->orderBy('id')
                ->get()
                ->map(fn (ClinicalEvolution $evolution): array => [
                    'id' => (int) $evolution->id,
                    'evolution_date' => $evolution->evolution_date?->toDateString(),
                    'specialty' => $evolution->specialty,
                    'subjective' => $evolution->subjective,
                    'objective' => $evolution->objective,
                    'assessment' => $evolution->assessment,
                    'plan' => $evolution->plan,
                    'procedures_performed' => $evolution->procedures_performed,
                    'materials_used' => $evolution->materials_used,
                    'prescriptions' => $evolution->prescriptions,
                    'recommendations' => $evolution->recommendations,
                    'next_appointment_notes' => $evolution->next_appointment_notes,
                ])
                ->all(),
            'diagnoses' => EncounterDiagnosis::query()
                ->where('clinical_encounter_id', $encounterId)
                ->orderBy('id')
                ->get()
                ->map(fn (EncounterDiagnosis $diagnosis): array => [
                    'cie_code' => $diagnosis->cie_code,
                    'type' => $diagnosis->type,
                ])
                ->all(),
            'odontogram' => OdontogramRecord::query()
                ->where('clinical_encounter_id', $encounterId)
                ->orderBy('id')
                ->get()
                ->map(fn (OdontogramRecord $record): array => [
                    'dental_piece_id' => $record->dental_piece_id !== null ? (int) $record->dental_piece_id : null,
                    'tooth_surface_id' => $record->tooth_surface_id !== null ? (int) $record->tooth_surface_id : null,
                    'condition_code' => $record->condition_code,
                    'condition_name' => $record->condition_name,
                    'diagnosis' => $record->diagnosis,
                ])
                ->all(),
            'materials' => ProcedureMaterial::query()
                ->where('clinical_encounter_id', $encounterId)
                ->orderBy('id')
                ->get()
                ->map(fn (ProcedureMaterial $material): array => [
                    'product_id' => (int) $material->product_id,
                    'quantity_used' => (string) $material->quantity_used,
                    'unit_cost' => $material->unit_cost !== null ? (string) $material->unit_cost : null,
                    'total_cost' => $material->total_cost !== null ? (string) $material->total_cost : null,
                ])
                ->all(),
        ];
    }

    public static function hash(ClinicalEncounter $encounter): string
    {
        return hash(
            'sha256',
            (string) json_encode(
                self::payload($encounter),
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
            ),
        );
    }

    /**
     * Whether the stored hash still describes the record on disk. A record
     * without a signature is not verified: absence is reported as such.
     */
    public static function matches(ClinicalEncounter $encounter): bool
    {
        if ($encounter->signature_hash === null || $encounter->signed_at === null) {
            return false;
        }

        return hash_equals($encounter->signature_hash, self::hash($encounter));
    }
}
