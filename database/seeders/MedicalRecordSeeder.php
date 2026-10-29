<?php

namespace Database\Seeders;

use App\Models\ClinicalEvolution;
use App\Models\MedicalRecord;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Auditoría UI U11 (#374) — la muestra clínica que el instrumento necesita.
 *
 * `tests/e2e/audit/u11` mide la superficie `/medical-records/:id` de la capa 2
 * y de la capa 3. Sobre una base sembrada a secas no había nada que medir:
 * ningún seeder creaba una historia clínica, así que el instrumento reportaba
 * `no-sample-record`, la fila `render/medical-records-id-odontologo.json`
 * quedaba sin evidencia y el gate cerraba en rojo sin que existiera una
 * regresión. Esta muestra mínima —cabecera con su profesional y una evolución
 * SOAP— le da al manifiesto la fila que su recorrido espera.
 *
 * FK chain (debe correr después de RoleBasedUsersSeeder y PatientSeeder):
 *   patient_id → patients
 *   created_by → users (un profesional clínico)
 *
 * Idempotente: cada fila se busca por su clave natural antes de crearse, así
 * un `migrate --seed` repetido no duplica la muestra.
 */
class MedicalRecordSeeder extends Seeder
{
    /**
     * Cantidad de historias de muestra y su numeración correlativa.
     */
    private const SAMPLE_SIZE = 2;

    private const CLINICAL_ROLES = ['odontologo', 'implantologo'];

    public function run(): void
    {
        $patients = Patient::orderBy('id')->limit(self::SAMPLE_SIZE)->get();
        $professional = User::whereIn('role', self::CLINICAL_ROLES)->orderBy('id')->first();

        if ($patients->count() < self::SAMPLE_SIZE || ! $professional) {
            $this->command?->info(
                'Sin pacientes o sin profesional clínico: ejecute antes RoleBasedUsersSeeder y PatientSeeder.'
            );

            return;
        }

        foreach ($patients->values() as $index => $patient) {
            $record = MedicalRecord::firstOrCreate(
                ['record_number' => sprintf('HC-DEMO-%03d', $index + 1)],
                [
                    'patient_id' => $patient->id,
                    'created_by' => $professional->id,
                    'first_visit_date' => now()->subDays(30 - $index)->toDateString(),
                    'chief_complaint' => 'Control odontológico de rutina.',
                    'medical_history' => 'Sin antecedentes sistémicos de relevancia.',
                    'dental_history' => 'Profilaxis anual; sin restauraciones previas.',
                    'allergies' => 'Ninguna conocida.',
                    'clinical_examination' => 'Mucosa oral sana; dentición completa sin lesiones cavitadas.',
                    'diagnosis' => 'Periodonto sano, sin patología pulpar activa.',
                    'is_active' => true,
                ]
            );

            ClinicalEvolution::firstOrCreate(
                [
                    'medical_record_id' => $record->id,
                    'evolution_date' => $record->first_visit_date->toDateString(),
                ],
                [
                    'patient_id' => $patient->id,
                    'created_by' => $professional->id,
                    'specialty' => 'general',
                    'subjective' => 'Acude a control semestral sin molestias.',
                    'objective' => 'Índice de placa bajo; sin sangrado al sondaje.',
                    'assessment' => 'Salud bucal estable.',
                    'plan' => 'Refuerzo de higiene oral y control en seis meses.',
                ]
            );
        }

        $this->command?->info('Historias clínicas de muestra: '.MedicalRecord::count());
    }
}
