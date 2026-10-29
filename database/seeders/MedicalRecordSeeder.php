<?php

namespace Database\Seeders;

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
 * quedaba sin evidencia y su gate cerraba en rojo sin que existiera una
 * regresión de producto.
 *
 * La muestra es la cabecera de la historia, no narrativa clínica: el seeder no
 * escribe evoluciones. La base sembrada se asume sin evoluciones —
 * `AiAnalysisCitationTest`, `MedicalRecordEvolutionContentTest` y otras seis
 * aserciones de cero absoluto dependen de ello— y la superficie de detalle
 * pinta su estado vacío cuando no las hay, que es el comportamiento correcto.
 *
 * FK chain (debe correr después de RoleBasedUsersSeeder y PatientSeeder):
 *   patient_id → patients
 *   created_by → users (un profesional clínico)
 *
 * Idempotente: cada fila se busca por su `record_number` antes de crearse, así
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
            MedicalRecord::firstOrCreate(
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
        }

        $this->command?->info('Historias clínicas de muestra: '.MedicalRecord::count());
    }
}
